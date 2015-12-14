<?php
/*
 * This program is free software: you can redistribute it and/or modify
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
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PingbackAction extends Action
{
    protected function handle()
    {
        GNUsocial::setApi(true); // Minimize error messages to aid in debugging
        parent::handle();
        if ($this->isPost()) {
            return $this->handlePost();
        }

        return false;
    }

    function handlePost()
    {

        $server = xmlrpc_server_create();
        xmlrpc_server_register_method($server, 'pingback.ping', array($this, 'ping'));
        echo xmlrpc_server_call_method($server, file_get_contents('php://input'), null, array('encoding' => 'utf-8'));
        xmlrpc_server_destroy($server);
        return true;
    }

    function ping($method, $parameters) {
        list($source, $target) = $parameters;

        if(!$source) {
            return array(
                'faultCode' => 0x0010,
                'faultString' => '"source" is missing'
            );
        }

        if(!$target) {
            return array(
                'faultCode' => 0x0020,
                'faultString' => '"target" is missing'
            );
        }

        $response = linkback_get_source($source, $target);
        if(!$response) {
            return array(
                'faultCode' => 0x0011,
                'faultString' => 'Source does not link to target'
            );
        }

        $notice = linkback_get_target($target);
        if(!$notice) {
            return array(
                'faultCode' => 0x0021,
                'faultString' => 'Target not found'
            );
        }

        $url = linkback_save($source, $target, $response, $notice);
        if(!$url) {
            return array(
                'faultCode' => 0,
                'faultString' => 'An error occured while saving.'
            );
        }

        return array('Success');
    }
}
