<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * ActivitySpam Plugin
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
 * Check new notices with activity spam service.
 * 
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class ActivitySpamPlugin extends Plugin
{
    public $server = null;

    public $username = null;
    public $password = null;

    /**
     * Initializer
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function initialize()
    {
        foreach (array('username', 'password', 'server') as $attr) {
            if (!$this->$attr) {
                $this->$attr = common_config('activityspam', $attr);
            }
        }

        return true;
    }

    /**
     * Database schema setup
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('spam_score', Spam_score::schemaDef());

        return true;
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'Spam_score':
            include_once $dir . '/'.$cls.'.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * This should probably be done in its own queue handler
     */

    function onEndNoticeSave($notice)
    {
        // FIXME: need this to autoload ActivityStreamsMediaLink
        $doc = new ActivityStreamJSONDocument();

        $activity = $notice->asActivity(null);

        $client = new HTTPClient($this->server . "/is-this-spam");

        $client->setMethod('POST');
        $client->setAuth($this->username, $this->password);
        $client->setHeader('Content-Type', 'application/json');
        $client->setBody(json_encode($activity->asArray()));

        $response = $client->send();

        if (!$response->isOK()) {
            $this->log(LOG_ERR, "Error " . $response->getStatus() . " checking spam score: " . $response->getBody());
            return true;
        }

        $result = json_decode($response->getBody());

        $score = new Spam_score();

        $score->notice_id = $notice->id;
        $score->score     = $result->probability;
        $score->created   = common_sql_now();

        $score->insert();

        $this->log(LOG_INFO, "Notice " . $notice->id . " has spam score " . $score->score);

        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'ActivitySpam',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:ActivitySpam',
                            'description' =>
                            _m('Test notices against the Activity Spam service.'));
        return true;
    }
}
