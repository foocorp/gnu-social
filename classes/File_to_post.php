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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

/**
 * Table Definition for file_to_post
 */

class File_to_post extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'file_to_post';                    // table name
    public $file_id;                         // int(4)  primary_key not_null
    public $post_id;                         // int(4)  primary_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'file_id' => array('type' => 'int', 'not null' => true, 'description' => 'id of URL/file'),
                'post_id' => array('type' => 'int', 'not null' => true, 'description' => 'id of the notice it belongs to'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('file_id', 'post_id'),
            'foreign keys' => array(
                'file_to_post_file_id_fkey' => array('file', array('file_id' => 'id')),
                'file_to_post_post_id_fkey' => array('notice', array('post_id' => 'id')),
            ),
            'indexes' => array(
                'post_id_idx' => array('post_id'),
            ),
        );
    }

    function processNew($file_id, $notice_id) {
        static $seen = array();
        if (empty($seen[$notice_id]) || !in_array($file_id, $seen[$notice_id])) {

            $f2p = File_to_post::pkeyGet(array('post_id' => $notice_id,
                                               'file_id' => $file_id));
            if (empty($f2p)) {
                $f2p = new File_to_post;
                $f2p->file_id = $file_id;
                $f2p->post_id = $notice_id;
                $f2p->insert();
                
                $f = File::staticGet($file_id);

                if (!empty($f)) {
                    $f->blowCache();
                }
            }

            if (empty($seen[$notice_id])) {
                $seen[$notice_id] = array($file_id);
            } else {
                $seen[$notice_id][] = $file_id;
            }
        }
    }

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('File_to_post', $kv);
    }

    function delete()
    {
        $f = File::staticGet('id', $this->file_id);
        if (!empty($f)) {
            $f->blowCache();
        }
        return parent::delete();
    }
}
