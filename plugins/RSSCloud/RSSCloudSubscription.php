<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Table Definition for rsscloud_subscription
 */

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

class RSSCloudSubscription extends Memcached_DataObject {

    var $__table='rsscloud_subscription'; // table name
    var $subscribed;                      // int    primary key user id
    var $url;                             // string primary key
    var $failures;                        // int
    var $created;                         // datestamp()
    var $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    static function getKV($k,$v=NULL) { return DB_DataObject::staticGet('DataObjects_Grp',$k,$v); }

    function table()
    {

        $db = $this->getDatabaseConnection();
        $dbtype = $db->phptype;

        $cols = array('subscribed' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                      'url'        => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                      'failures'   => DB_DATAOBJECT_INT,
                      'created'    => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                      'modified'  => ($dbtype == 'mysql' || $dbtype == 'mysqli') ?
                      DB_DATAOBJECT_MYSQLTIMESTAMP + DB_DATAOBJECT_NOTNULL :
                      DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME
                      );

        return $cols;
    }

    function keys()
    {
        return array('subscribed' => 'N', 'url' => 'N');
    }

    static function getSubscription($subscribed, $url)
    {
        $sub = new RSSCloudSubscription();
        $sub->whereAdd("subscribed = $subscribed");
        $sub->whereAdd("url = '$url'");
        $sub->limit(1);

        if ($sub->find()) {
            $sub->fetch();
            return $sub;
        }

        return false;
    }
}
