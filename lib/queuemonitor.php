<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Monitoring output helper for IoMaster and IoManager/QueueManager
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
 * @category  QueueManager
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class QueueMonitor
{
    protected $monSocket = null;

    /**
     * Increment monitoring statistics for a given counter, if configured.
     * Only explicitly listed thread/site/queue owners will be incremented.
     *
     * @param string $key counter name
     * @param array $owners list of owner keys like 'queue:xmpp' or 'site:stat01'
     */
    public function stats($key, $owners=array())
    {
        $this->ping(array('counter' => $key,
                          'owners' => $owners));
    }

    /**
     * Send thread state update to the monitoring server, if configured.
     *
     * @param string $thread ID (eg 'generic.1')
     * @param string $state ('init', 'queue', 'shutdown' etc)
     * @param string $substate (optional, eg queue name 'omb' 'sms' etc)
     */
    public function logState($threadId, $state, $substate='')
    {
        $this->ping(array('thread_id' => $threadId,
                          'state' => $state,
                          'substate' => $substate,
                          'ts' => microtime(true)));
    }

    /**
     * General call to the monitoring server
     */
    protected function ping($data)
    {
        $target = common_config('queue', 'monitor');
        if (empty($target)) {
            return;
        }

        $data = $this->prepMonitorData($data);

        if (substr($target, 0, 4) == 'udp:') {
            $this->pingUdp($target, $data);
        } else if (substr($target, 0, 5) == 'http:') {
            $this->pingHttp($target, $data);
        } else {
            common_log(LOG_ERR, __METHOD__ . ' unknown monitor target type ' . $target);
        }
    }

    protected function pingUdp($target, $data)
    {
        if (!$this->monSocket) {
            $this->monSocket = stream_socket_client($target, $errno, $errstr);
        }
        if ($this->monSocket) {
            $post = http_build_query($data, '', '&');
            stream_socket_sendto($this->monSocket, $post);
        } else {
            common_log(LOG_ERR, __METHOD__ . " UDP logging fail: $errstr");
        }
    }

    protected function pingHttp($target, $data)
    {
        $client = new HTTPClient();
        try {
            $result = $client->post($target, array(), $data);
        
            if (!$result->isOk()) {
                common_log(LOG_ERR, __METHOD__ . ' HTTP ' . $result->getStatus() . ': ' . $result->getBody());
            }
        } catch (NoHttpResponseException $e) {
            common_log(LOG_ERR, __METHOD__ . ':'.$e->getMessage());
        }
    }

    protected function prepMonitorData($data)
    {
        #asort($data);
        #$macdata = http_build_query($data, '', '&');
        #$key = 'This is a nice old key';
        #$data['hmac'] = hash_hmac('sha256', $macdata, $key);
        return $data;
    }

}
