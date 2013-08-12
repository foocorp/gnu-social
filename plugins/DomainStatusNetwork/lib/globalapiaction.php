<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * An action that requires an API key
 * 
 * PHP version 5
 *
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
 *
 * @category  DomainStatusNetwork
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * An action that requires an API key
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class GlobalApiAction extends Action
{
    var $email;

    /**
     * Check for an API key, and throw an exception if it's not set
     *
     * @param array $args URL and POST params
     *
     * @return boolean continuation flag
     */

    function prepare($args)
    {
        StatusNet::setApi(true); // reduce exception reports to aid in debugging

        parent::prepare($args);

        if (!common_config('globalapi', 'enabled')) {
            throw new ClientException(_('Global API not enabled.'), 403);
        }

        $apikey = $this->trimmed('apikey');

        if (empty($apikey)) {
            throw new ClientException(_('No API key.'), 403);
        }

        $expected = common_config('globalapi', 'key');

        if ($expected != $apikey) {
            // FIXME: increment a counter by IP address to prevent brute-force
            // attacks on the key.
            throw new ClientException(_('Bad API key.'), 403);
        }

        $email = common_canonical_email($this->trimmed('email'));

        if (empty($email)) {
            throw new ClientException(_('No email address.'));
        }

        if (!Validate::email($email, common_config('email', 'check_domain'))) {
            throw new ClientException(_('Invalid email address.'));
        }

        $this->email = $email;

        return true;
    }

    function showError($message, $code=400)
    {
        $this->showOutput(array('error' => $message), $code);
    }

    function showSuccess($values=null, $code=200)
    {
        if (empty($values)) {
            $values = array();
        }
        $values['success'] = 1;
        $this->showOutput($values, $code);
    }

    function showOutput($values, $code)
    {
        if (array_key_exists($code, ClientErrorAction::$status)) {
            $status_string = ClientErrorAction::$status[$code];
        } else if (array_key_exists($code, ServerErrorAction::$status)) {
            $status_string = ServerErrorAction::$status[$code];
        } else {
            // bad code!
            $code = 500;
            $status_string = ServerErrorAction::$status[$code];
        }

        header('HTTP/1.1 '.$code.' '.$status_string);

        header('Content-Type: application/json; charset=utf-8');
        print(json_encode($values));
        print("\n");
    }
}
