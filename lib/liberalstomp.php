<?php

/**
 * Based on code from Stomp PHP library, working around bugs in the base class.
 *
 * Original code is copyright 2005-2006 The Apache Software Foundation
 * Modifications copyright 2009 StatusNet Inc by Brion Vibber <brion@status.net>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class LiberalStomp extends Stomp
{
    /**
     * We need to be able to get the socket so advanced daemons can
     * do a select() waiting for input both from the queue and from
     * other sources such as an XMPP connection.
     *
     * @return resource
     */
    function getSocket()
    {
        return $this->_socket;
    }

    /**
     * Return the host we're currently connected to.
     *
     * @return string
     */
    function getServer()
    {
        $idx = $this->_currentHost;
        if ($idx >= 0) {
            $host = $this->_hosts[$idx];
            return "$host[0]:$host[1]";
        } else {
            return '[unconnected]';
        }
    }

    /**
     * Make socket connection to the server
     * We also set the stream to non-blocking mode, since we'll be
     * select'ing to wait for updates. In blocking mode it seems
     * to get confused sometimes.
     *
     * @throws StompException
     */
    protected function _makeConnection ()
    {
        parent::_makeConnection();
        stream_set_blocking($this->_socket, 0);
    }

    /**
     * Version 1.0.0 of the Stomp library gets confused if messages
     * come in too fast over the connection. This version will read
     * out as many frames as are ready to be read from the socket.
     *
     * Modified from Stomp::readFrame()
     *
     * @return StompFrame False when no frame to read
     */
    public function readFrames ()
    {
        if (!$this->hasFrameToRead()) {
            return false;
        }
        
        $rb = 1024;
        $data = '';
        $end = false;
        $frames = array();

        do {
            // @fixme this sometimes hangs in blocking mode...
            // shouldn't we have been idle until we found there's more data?
            $read = fread($this->_socket, $rb);
            if ($read === false || ($read === '' && feof($this->_socket))) {
                // @fixme possibly attempt an auto reconnect as old code?
                throw new StompException("Error reading");
                //$this->_reconnect();
                // @fixme this will lose prior items
                //return $this->readFrames();
            }
            $data .= $read;
            if (strpos($data, "\x00") !== false) {
                // Frames are null-delimited, but some servers
                // may append an extra \n according to old bug reports.
                $data = str_replace("\x00\n", "\x00", $data);
                $chunks = explode("\x00", $data);

                $data = array_pop($chunks);
                $frames = array_merge($frames, $chunks);
                if ($data == '') {
                    // We're at the end of a frame; stop reading.
                    break;
                } else {
                    // In the middle of a frame; keep going.
                }
            }
            // @fixme find out why this len < 2 check was there
            //$len = strlen($data);
        } while (true);//$len < 2 || $end == false);

        return array_map(array($this, 'parseFrame'), $frames);
    }
    
    /**
     * Parse a raw Stomp frame into an object.
     * Extracted from Stomp::readFrame()
     *
     * @param string $data
     * @return StompFrame
     */
    function parseFrame($data)
    {
        list ($header, $body) = explode("\n\n", $data, 2);
        $header = explode("\n", $header);
        $headers = array();
        $command = null;
        foreach ($header as $v) {
            if (isset($command)) {
                list ($name, $value) = explode(':', $v, 2);
                $headers[$name] = $value;
            } else {
                $command = $v;
            }
        }
        $frame = new StompFrame($command, $headers, trim($body));
        if (isset($frame->headers['transformation']) && $frame->headers['transformation'] == 'jms-map-json') {
            require_once 'Stomp/Message/Map.php';
            return new StompMessageMap($frame);
        } else {
            return $frame;
        }
        return $frame;
    }

    /**
     * Write frame to server
     *
     * @param StompFrame $stompFrame
     */
    protected function _writeFrame (StompFrame $stompFrame)
    {
        if (!is_resource($this->_socket)) {
            require_once 'Stomp/Exception.php';
            throw new StompException('Socket connection hasn\'t been established');
        }

        $data = $stompFrame->__toString();

        // Make sure the socket's in a writable state; if not, wait a bit.
        stream_set_blocking($this->_socket, 1);

        $r = fwrite($this->_socket, $data, strlen($data));
        stream_set_blocking($this->_socket, 0);
        if ($r === false || $r == 0) {
            $this->_reconnect();
            $this->_writeFrame($stompFrame);
        }
    }
 }

