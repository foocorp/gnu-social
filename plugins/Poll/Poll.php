<?php
/**
 * Data class to mark notices as bookmarks
 *
 * PHP version 5
 *
 * @category PollPlugin
 * @package  StatusNet
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * For storing the poll options and such
 *
 * @category PollPlugin
 * @package  StatusNet
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */

class Poll extends Managed_DataObject
{
    public $__table = 'poll'; // table name
    public $id;          // char(36) primary key not null -> UUID
    public $uri;
    public $profile_id;  // int -> profile.id
    public $question;    // text
    public $options;     // text; newline(?)-delimited
    public $created;     // datetime

    /**
     * Get an instance by key
     *
     * This is a utility method to get a single instance with a given key value.
     *
     * @param string $k Key to use to lookup (usually 'user_id' for this class)
     * @param mixed  $v Value to lookup
     *
     * @return User_greeting_count object found, or null for no hits
     *
     */
    function staticGet($k, $v=null)
    {
        return Memcached_DataObject::staticGet('Poll', $k, $v);
    }

    /**
     * Get an instance by compound key
     *
     * This is a utility method to get a single instance with a given set of
     * key-value pairs. Usually used for the primary key for a compound key; thus
     * the name.
     *
     * @param array $kv array of key-value mappings
     *
     * @return Bookmark object found, or null for no hits
     *
     */
    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Poll', $kv);
    }

    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        return array(
            'description' => 'Per-notice poll data for Poll plugin',
            'fields' => array(
                'id' => array('type' => 'char', 'length' => 36, 'not null' => true, 'description' => 'UUID'),
                'uri' => array('type' => 'varchar', 'length' => 255, 'not null' => true),
                'profile_id' => array('type' => 'int'),
                'question' => array('type' => 'text'),
                'options' => array('type' => 'text'),
                'created' => array('type' => 'datetime', 'not null' => true),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'poll_uri_key' => array('uri'),
            ),
        );
    }

    /**
     * Get a bookmark based on a notice
     *
     * @param Notice $notice Notice to check for
     *
     * @return Poll found poll or null
     */
    function getByNotice($notice)
    {
        return self::staticGet('uri', $notice->uri);
    }

    function getOptions()
    {
        return explode("\n", $this->options);
    }

    /**
     * Is this a valid selection index?
     *
     * @param numeric $selection (1-based)
     * @return boolean
     */
    function isValidSelection($selection)
    {
        if ($selection != intval($selection)) {
            return false;
        }
        if ($selection < 1 || $selection > count($this->getOptions())) {
            return false;
        }
        return true;
    }

    function getNotice()
    {
        return Notice::staticGet('uri', $this->uri);
    }

    function bestUrl()
    {
        return $this->getNotice()->bestUrl();
    }

    /**
     * Get the response of a particular user to this poll, if any.
     *
     * @param Profile $profile
     * @return Poll_response object or null
     */
    function getResponse(Profile $profile)
    {
    	$pr = Poll_response::pkeyGet(array('poll_id' => $this->id,
    									   'profile_id' => $profile->id));
    	return $pr;
    }

    function countResponses()
    {
        $pr = new Poll_response();
        $pr->poll_id = $this->id;
        $pr->groupBy('selection');
        $pr->selectAdd('count(profile_id) as votes');
        $pr->find();

        $raw = array();
        while ($pr->fetch()) {
            // Votes list 1-based
            // Array stores 0-based
            $raw[$pr->selection - 1] = $pr->votes;
        }

        $counts = array();
        foreach (array_keys($this->getOptions()) as $key) {
            if (isset($raw[$key])) {
                $counts[$key] = $raw[$key];
            } else {
                $counts[$key] = 0;
            }
        }
        return $counts;
    }

    /**
     * Save a new poll notice
     *
     * @param Profile $profile
     * @param string  $question
     * @param array   $opts (poll responses)
     *
     * @return Notice saved notice
     */
    static function saveNew($profile, $question, $opts, $options=null)
    {
        if (empty($options)) {
            $options = array();
        }

        $p = new Poll();

        $p->id          = UUID::gen();
        $p->profile_id  = $profile->id;
        $p->question    = $question;
        $p->options     = implode("\n", $opts);

        if (array_key_exists('created', $options)) {
            $p->created = $options['created'];
        } else {
            $p->created = common_sql_now();
        }

        if (array_key_exists('uri', $options)) {
            $p->uri = $options['uri'];
        } else {
            $p->uri = common_local_url('showpoll',
                                        array('id' => $p->id));
        }

        common_log(LOG_DEBUG, "Saving poll: $p->id $p->uri");
        $p->insert();

        // TRANS: Notice content creating a poll.
        // TRANS: %1$s is the poll question, %2$s is a link to the poll.
        $content  = sprintf(_m('Poll: %1$s %2$s'),
                            $question,
                            $p->uri);
        $link = '<a href="' . htmlspecialchars($p->uri) . '">' . htmlspecialchars($question) . '</a>';
        // TRANS: Rendered version of the notice content creating a poll.
        // TRANS: %s is a link to the poll with the question as link description.
        $rendered = sprintf(_m('Poll: %s'), $link);

        $tags    = array('poll');
        $replies = array();

        $options = array_merge(array('urls' => array(),
                                     'rendered' => $rendered,
                                     'tags' => $tags,
                                     'replies' => $replies,
                                     'object_type' => PollPlugin::POLL_OBJECT),
                               $options);

        if (!array_key_exists('uri', $options)) {
            $options['uri'] = $p->uri;
        }

        $saved = Notice::saveNew($profile->id,
                                 $content,
                                 array_key_exists('source', $options) ?
                                 $options['source'] : 'web',
                                 $options);

        return $saved;
    }
}
