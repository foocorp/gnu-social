<?php
/**
 * This file is part of libomb
 *
 * PHP version 5
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
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
 * @package OMB
 * @author  Adrian Lang <mail@adrianlang.de>
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL 3.0
 * @version 0.1a-20090828
 * @link    http://adrianlang.de/libomb
 */

/**
 * Exception stating that the remote service had a failure
 *
 * This exception is raised when a remote service failed to return a valid
 * response to a request or send a valid request.
 */
class OMB_RemoteServiceException extends Exception
{
    /**
     * Create exception from Yadis response
     *
     * Creates an exception from a passed yadis result.
     *
     * @param string                  $request_uri The target URI for the failed
     *                                             request
     * @param Auth_Yadis_HTTPResponse $result      The result of the failed
     *                                             request
     *
     * @return OMB_RemoteServiceException A new exception
     */
    public static function fromYadis($request_uri, $result)
    {
        if ($result->status == 200) {
            $err = 'Got wrong response ' . $result->body;
        } else {
            $err = 'Got error code ' . $result->status . ' with response ' .
                   $result->body;
        }
        return OMB_RemoteServiceException::forRequest($request_uri, $err);
    }

    /**
     * Create exception for a call to a resource
     *
     * Creates an exception for a given error message and target URI.
     *
     * @param string $action_uri The target URI for the failed request
     * @param string $failure    An error message
     *
     * @return OMB_RemoteServiceException A new exception
     */
    public static function forRequest($action_uri, $failure)
    {
        return new OMB_RemoteServiceException("Handler for $action_uri: $failure");
    }
}
?>
