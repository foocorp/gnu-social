<?php
/**
 * Data class for group direct messages
 *
 * PHP version 5
 *
 * @category Data
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

/**
 * Data class for group direct messages
 *
 * @category GroupPrivateMessage
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class Group_message extends Managed_DataObject
{
    public $__table = 'group_message'; // table name
    public $id;                        // char(36)  primary_key not_null
    public $uri;                       // varchar(255)
    public $from_profile;              // int
    public $to_group;                  // int
    public $content;
    public $rendered;
    public $url;
    public $created;

    /**
     * return table definition for DB_DataObject
     *
     * DB_DataObject needs to know something about the table to manipulate
     * instances. This method provides all the DB_DataObject needs to know.
     *
     * @return array array of column definitions
     */
    function table()
    {
        return array('id' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'uri' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'from_profile' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'to_group' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'content' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'rendered' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'url' => DB_DATAOBJECT_STR,
                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has, since it
     * won't appear in StatusNet's own keys list. In most cases, this will
     * simply reference your keyTypes() function.
     *
     * @return array list of key field names
     */
    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * @return array associative array of key definitions, field name to type:
     *         'K' for primary key: for compound keys, add an entry for each component;
     *         'U' for unique keys: compound keys are not well supported here.
     */
    function keyTypes()
    {
        return array('id' => 'K', 'uri' => 'U');
    }

    static function send($user, $group, $text)
    {
        if (!$user->hasRight(Right::NEWMESSAGE)) {
            // XXX: maybe break this out into a separate right
            // TRANS: Exception thrown when trying to send group private message without having the right to do that.
            // TRANS: %s is a user nickname.
            throw new Exception(sprintf(_m('User %s is not allowed to send private messages.'),
                                        $user->nickname));
        }

        Group_privacy_settings::ensurePost($user, $group);

        $text = $user->shortenLinks($text);

        // We use the same limits as for 'regular' private messages.

        if (Message::contentTooLong($text)) {
            // TRANS: Exception thrown when trying to send group private message that is too long.
            // TRANS: %d is the maximum meggage length.
            throw new Exception(sprintf(_m('That\'s too long. Maximum message size is %d character.',
                                           'That\'s too long. Maximum message size is %d characters.',
                                           Message::maxContent()),
                                        Message::maxContent()));
        }

        // Valid! Let's do this thing!

        $gm = new Group_message();

        $gm->id           = UUID::gen();
        $gm->uri          = common_local_url('showgroupmessage', array('id' => $gm->id));
        $gm->from_profile = $user->id;
        $gm->to_group     = $group->id;
        $gm->content      = $text; // XXX: is this cool?!
        $gm->rendered     = common_render_text($text);
        $gm->url          = $gm->uri;
        $gm->created      = common_sql_now();

        // This throws a conniption if there's a problem

        $gm->insert();

        $gm->distribute();

        return $gm;
    }

    function distribute()
    {
        $group = User_group::getKV('id', $this->to_group);

        $member = $group->getMembers();

        while ($member->fetch()) {
            Group_message_profile::send($this, $member);
        }
    }

    function getGroup()
    {
        $group = User_group::getKV('id', $this->to_group);
        if (empty($group)) {
            // TRANS: Exception thrown when trying to send group private message to a non-existing group.
            throw new ServerException(_m('No group for group message.'));
        }
        return $group;
    }

    function getSender()
    {
        $sender = Profile::getKV('id', $this->from_profile);
        if (empty($sender)) {
            // TRANS: Exception thrown when trying to send group private message without having a sender.
            throw new ServerException(_m('No sender for group message.'));
        }
        return $sender;
    }

    static function forGroup($group, $offset, $limit)
    {
        // XXX: cache
        $gm = new Group_message();

        $gm->to_group = $group->id;
        $gm->orderBy('created DESC');
        $gm->limit($offset, $limit);

        $gm->find();

        return $gm;
    }
}
