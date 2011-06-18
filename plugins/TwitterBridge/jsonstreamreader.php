<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class OAuthData
{
    public $consumer_key, $consumer_secret, $token, $token_secret;
}

/**
 *
 */
abstract class JsonStreamReader
{
    const CRLF = "\r\n";

    public $id;
    protected $socket = null;
    protected $state = 'init'; // 'init', 'connecting', 'waiting', 'headers', 'active'

    public function __construct()
    {
        $this->id = get_class($this) . '.' . substr(md5(mt_rand()), 0, 8);
    }

    /**
     * Starts asynchronous connect operation...
     *
     * @fixme Can we do the open-socket fully async to? (need write select infrastructure)
     *
     * @param string $url
     */
    public function connect($url)
    {
        common_log(LOG_DEBUG, "$this->id opening connection to $url");

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme == 'http') {
            $rawScheme = 'tcp';
        } else if ($scheme == 'https') {
            $rawScheme = 'ssl';
        } else {
            // TRANS: Server exception thrown when an invalid URL scheme is detected.
            throw new ServerException(_m('Invalid URL scheme for HTTP stream reader.'));
        }

        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        if (!$port) {
            if ($scheme == 'https') {
                $port = 443;
            } else {
                $port = 80;
            }
        }

        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query) {
            $path .= '?' . $query;
        }

        $errno = $errstr = null;
        $timeout = 5;
        //$flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $flags = STREAM_CLIENT_CONNECT;
        // @fixme add SSL params
        $this->socket = stream_socket_client("$rawScheme://$host:$port", $errno, $errstr, $timeout, $flags);

        $this->send($this->httpOpen($host, $path));

        stream_set_blocking($this->socket, false);
        $this->state = 'waiting';
    }

    /**
     * Send some fun data off to the server.
     *
     * @param string $buffer
     */
    function send($buffer)
    {
        fwrite($this->socket, $buffer);
    }

    /**
     * Read next packet of data from the socket.
     *
     * @return string
     */
    function read()
    {
        $buffer = fread($this->socket, 65536);
        return $buffer;
    }

    /**
     * Build HTTP request headers.
     *
     * @param string $host
     * @param string $path
     * @return string
     */
    protected function httpOpen($host, $path)
    {
        $lines = array(
            "GET $path HTTP/1.1",
            "Host: $host",
            "User-Agent: StatusNet/" . STATUSNET_VERSION . " (TwitterBridgePlugin)",
            "Connection: close",
            "",
            ""
        );
        return implode(self::CRLF, $lines);
    }

    /**
     * Close the current connection, if open.
     */
    public function close()
    {
        if ($this->isConnected()) {
            common_log(LOG_DEBUG, "$this->id closing connection.");
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Are we currently connected?
     *
     * @return boolean
     */
    public function isConnected()
    {
        return $this->socket !== null;
    }

    /**
     * Send any sockets we're listening on to the IO manager
     * to wait for input.
     *
     * @return array of resources
     */
    public function getSockets()
    {
        if ($this->isConnected()) {
            return array($this->socket);
        }
        return array();
    }

    /**
     * Take a chunk of input over the horn and go go go! :D
     *
     * @param string $buffer
     */
    public function handleInput($socket)
    {
        if ($this->socket !== $socket) {
            // TRANS: Exception thrown when input from an inexpected socket is encountered.
            throw new Exception(_m('Got input from unexpected socket!'));
        }

        try {
            $buffer = $this->read();
            $lines = explode(self::CRLF, $buffer);
            foreach ($lines as $line) {
                $this->handleLine($line);
            }
        } catch (Exception $e) {
            common_log(LOG_ERR, "$this->id aborting connection due to error: " . $e->getMessage());
            fclose($this->socket);
            throw $e;
        }
    }

    protected function handleLine($line)
    {
        switch ($this->state)
        {
            case 'waiting':
                $this->handleLineWaiting($line);
                break;
            case 'headers':
                $this->handleLineHeaders($line);
                break;
            case 'active':
                $this->handleLineActive($line);
                break;
            default:
                // TRANS: Exception thrown when an invalid state is encountered in handleLine.
                // TRANS: %s is the invalid state.
                throw new Exception(sprintf(_m('Invalid state in handleLine: %s.'),$this->state));
        }
    }

    /**
     *
     * @param <type> $line
     */
    protected function handleLineWaiting($line)
    {
        $bits = explode(' ', $line, 3);
        if (count($bits) != 3) {
            // TRANS: Exception thrown when an invalid response line is encountered.
            // TRANS: %s is the invalid line.
            throw new Exception(sprintf(_m('Invalid HTTP response line: %s.'),$line));
        }

        list($http, $status, $text) = $bits;
        if (substr($http, 0, 5) != 'HTTP/') {
            // TRANS: Exception thrown when an invalid response line part is encountered.
            // TRANS: %1$s is the chunk, %2$s is the line.
            throw new Exception(sprintf(_m('Invalid HTTP response line chunk "%1$s": %2$s.'),$http, $line));
        }
        if ($status != '200') {
            // TRANS: Exception thrown when an invalid response code is encountered.
            // TRANS: %1$s is the response code, %2$s is the line.
            throw new Exception(sprintf(_m('Bad HTTP response code %1$s: %2$s.'),$status,$line));
        }
        common_log(LOG_DEBUG, "$this->id $line");
        $this->state = 'headers';
    }

    protected function handleLineHeaders($line)
    {
        if ($line == '') {
            $this->state = 'active';
            common_log(LOG_DEBUG, "$this->id connection is active!");
        } else {
            common_log(LOG_DEBUG, "$this->id read HTTP header: $line");
            $this->responseHeaders[] = $line;
        }
    }

    protected function handleLineActive($line)
    {
        if ($line == "") {
            // Server sends empty lines as keepalive.
            return;
        }
        $data = json_decode($line);
        if ($data) {
            $this->handleJson($data);
        } else {
            common_log(LOG_ERR, "$this->id received bogus JSON data: " . var_export($line, true));
        }
    }

    abstract protected function handleJson(stdClass $data);
}
