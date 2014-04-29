<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 */

/**
 * @package SubMirrorPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

class SubMirror extends Managed_DataObject
{
    public $__table = 'submirror';

    public $subscriber;
    public $subscribed;

    public $style;

    public $created;
    public $modified;

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
        return array('subscriber' =>  DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'subscribed' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,

                     'style' => DB_DATAOBJECT_STR,

                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'modified' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    static function schemaDef()
    {
        // @fixme need a reverse key on (subscribed, subscriber) as well
        return array(new ColumnDef('subscriber', 'integer',
                                   null, false, 'PRI'),
                     new ColumnDef('subscribed', 'integer',
                                   null, false, 'PRI'),

                     new ColumnDef('style', 'varchar',
                                   16, true),

                     new ColumnDef('created', 'datetime',
                                   null, false),
                     new ColumnDef('modified', 'datetime',
                                   null, false));
    }

    /**
     * Temporary hack to set up the compound index, since we can't do
     * it yet through regular Schema interface. (Coming for 1.0...)
     *
     * @param Schema $schema
     * @return void
     */
    static function fixIndexes($schema)
    {
        try {
            $schema->createIndex('submirror', array('subscribed', 'subscriber'));
        } catch (Exception $e) {
            common_log(LOG_ERR, __METHOD__ . ': ' . $e->getMessage());
        }
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */

    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * Our caching system uses the same key definitions, but uses a different
     * method to get them.
     *
     * @return array key definitions
     */

    function keyTypes()
    {
        // @todo FIXME keys
        // need a sane key for reverse lookup too
        return array('subscriber' => 'K', 'subscribed' => 'K');
    }

    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * @param Profile $subscribed
     * @param Profile $subscribed
     * @return SubMirror
     * @throws ServerException
     */
    public static function saveMirror($subscriber, $subscribed, $style='repeat')
    {
        // @fixme make sure they're subscribed!
        $mirror = new SubMirror();

        $mirror->subscriber = $subscriber->id;
        $mirror->subscribed = $subscribed->id;
        $mirror->style = $style;

        $mirror->created = common_sql_now();
        $mirror->modified = common_sql_now();
        $mirror->insert();

        return $mirror;
    }

    /**
     * @param Notice $notice
     * @return mixed Notice on successful mirroring, boolean if not
     */
    public function mirrorNotice(Notice $notice)
    {
        $profile = Profile::getKV('id', $this->subscriber);
        if (!($profile instanceof Profile)) {
            common_log(LOG_ERR, "SubMirror plugin skipping auto-repeat of notice $notice->id for missing user $profile->id");
            return false;
        }

        if ($this->style == 'copy') {
            return $this->copyNotice($profile, $notice);
        } else { // default to repeat mode
            return $this->repeatNotice($profile, $notice);
        }
    }

    /**
     * Mirror a notice using StatusNet's repeat functionality.
     * This retains attribution within the site, and other nice things,
     * but currently ends up looking like 'RT @foobar bla bla' when
     * bridged out over OStatus or TwitterBridge.
     *
     * @param Notice $notice
     * @return mixed Notice on successful repeat, true if already repeated, false on failure
     */
    protected function repeatNotice(Profile $profile, Notice $notice)
    {
        if($profile->hasRepeated($notice)) {
            common_log(LOG_INFO, "SubMirror plugin skipping auto-repeat of notice $notice->id for user $profile->id; already repeated.");
            return true;
        } else {
            common_log(LOG_INFO, "SubMirror plugin auto-repeating notice $notice->id for $profile->id");
            return $notice->repeat($profile->id, 'mirror');
        }
    }

    /**
     * Mirror a notice by emitting a new notice with the same contents.
     * Kind of dirty, but if pulling an external data feed into an account
     * that may be what you want.
     *
     * @param Notice $notice
     * @return mixed Notice on successful repeat, true if already repeated, false on failure
     */
    protected function copyNotice($profile, $notice)
    {
        $options = array('is_local' => Notice::LOCAL_PUBLIC,
                         'url' => $notice->getUrl(), // pass through the foreign link...
                         'rendered' => $notice->rendered);

        $saved = Notice::saveNew($profile->id,
                                 $notice->content,
                                 'feed',
                                 $options);
        return $saved;
    }

    /**
     * Get the mirroring setting for a pair of profiles, if existing.
     *
     * @param Profile $subscriber
     * @param Profile $subscribed
     * @return mixed Profile or empty
     */
    public static function getMirror($subscriber, $subscribed)
    {
        return self::pkeyGet(array('subscriber' => $subscriber->id,
                                   'subscribed' => $subscribed->id));
    }
}
