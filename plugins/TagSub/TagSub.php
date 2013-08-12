<?php
/**
 * Data class to store local tag subscriptions
 *
 * PHP version 5
 *
 * @category TagSubPlugin
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
 * For storing the tag subscriptions
 *
 * @category PollPlugin
 * @package  StatusNet
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class TagSub extends Managed_DataObject
{
    public $__table = 'tagsub'; // table name
    public $tag;         // text
    public $profile_id;  // int -> profile.id
    public $created;     // datetime

    /**
     * Get an instance by compound key
     *
     * This is a utility method to get a single instance with a given set of
     * key-value pairs. Usually used for the primary key for a compound key; thus
     * the name.
     *
     * @param array $kv array of key-value mappings
     *
     * @return TagSub object found, or null for no hits
     *
     */
    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('TagSub', $kv);
    }

    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        return array(
            'description' => 'TagSubPlugin tag subscription records',
            'fields' => array(
                'tag' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'hash tag associated with this subscription'),
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'profile ID of subscribing user'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
            ),
            'primary key' => array('tag', 'profile_id'),
            'foreign keys' => array(
                'tagsub_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
            ),
            'indexes' => array(
                'tagsub_created_idx' => array('created'),
                'tagsub_profile_id_tag_idx' => array('profile_id', 'tag'),
            ),
        );
    }

    /**
     * Start a tag subscription!
     *
     * @param profile $profile subscriber
     * @param string $tag subscribee
     * @return TagSub
     */
    static function start(Profile $profile, $tag)
    {
        $ts = new TagSub();
        $ts->tag = $tag;
        $ts->profile_id = $profile->id;
        $ts->created = common_sql_now();
        $ts->insert();
        self::blow('tagsub:by_profile:%d', $profile->id);
        return $ts;
    }

    /**
     * End a tag subscription!
     *
     * @param profile $profile subscriber
     * @param string $tag subscribee
     */
    static function cancel(Profile $profile, $tag)
    {
        $ts = TagSub::pkeyGet(array('tag' => $tag,
                                    'profile_id' => $profile->id));
        if ($ts) {
            $ts->delete();
            self::blow('tagsub:by_profile:%d', $profile->id);
        }
    }

    static function forProfile(Profile $profile)
    {
        $tags = array();

        $keypart = sprintf('tagsub:by_profile:%d', $profile->id);
        $tagstring = self::cacheGet($keypart);
        
        if ($tagstring !== false) { // cache hit
        	if (!empty($tagstring)) {
            	$tags = explode(',', $tagstring);
        	}
        } else {
            $tagsub             = new TagSub();
            $tagsub->profile_id = $profile->id;
            $tagsub->selectAdd();
            $tagsub->selectAdd('tag');

            if ($tagsub->find()) {
				$tags = $tagsub->fetchAll('tag');
            }

            self::cacheSet($keypart, implode(',', $tags));
        }

        return $tags;
    }
}
