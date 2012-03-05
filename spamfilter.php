<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2012, StatusNet, Inc.
 *
 * Spam filter class
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
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Spam filter class
 *
 * Local proxy for remote filter
 *
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class SpamFilter {

    const HAM  = 'ham';
    const SPAM = 'spam';

    public $server;
    public $username;
    public $password;

    function __construct($server, $username, $password) {

        $this->server   = $server;
        $this->username = $username;
        $this->password = $password;
    }

    protected function toActivity($notice) {
        // FIXME: need this to autoload ActivityStreamsMediaLink
        $doc = new ActivityStreamJSONDocument();

        $activity = $notice->asActivity(null);

        return $activity;
    }

    public function test($notice) {

        $activity = $this->toActivity($notice);
        return $this->testActivity($activity);
    }
    
    public function testActivity($activity) {

        $client = new HTTPClient($this->server . "/is-this-spam");

        $client->setMethod('POST');
        $client->setAuth($this->username, $this->password);
        $client->setHeader('Content-Type', 'application/json');
        $client->setBody(json_encode($activity->asArray()));

        $response = $client->send();

        if (!$response->isOK()) {
            throw new Exception("Error " . $response->getStatus() . " checking spam score: " . $response->getBody());
        }

        $result = json_decode($response->getBody());

        return $result;
    }

    public function train($notice, $category) {

        $activity = $this->toActivity($notice);
        return $this->trainActivity($activity, $category);

    }

    public function trainActivity($activity, $category) {

        switch ($category) {
        case self::HAM:
            $endpoint = '/this-is-ham';
            break;
        case self::SPAM:
            $endpoint = '/this-is-spam';
            break;
        default:
            throw new Exception("Unknown category: " + $category);
        }

        $client = new HTTPClient($this->server . $endpoint);

        $client->setMethod('POST');
        $client->setAuth($this->username, $this->password);
        $client->setHeader('Content-Type', 'application/json');
        $client->setBody(json_encode($activity->asArray()));

        $response = $client->send();

        if (!$response->isOK()) {
            throw new Exception("Error " . $response->getStatus() . " checking spam score: " . $response->getBody());
        }

        // We don't do much with the results
        return true;
    }

    public function trainOnError($notice, $category) {

        $activity = $this->toActivity($notice);

        return $this->trainActivityOnError($activity, $category);
    }
    
    public function trainActivityOnError($activity, $category) {

        $result = $this->testActivity($activity);

        if (($category === self::SPAM && $result->isSpam) ||
            ($category === self::HAM && !$result->isSpam)) {
            return true;
        } else {
            return $this->trainActivity($activity, $category);
        }
    }
}
