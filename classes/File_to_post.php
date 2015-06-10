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

if (!defined('GNUSOCIAL')) { exit(1); }

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
                'file_id_idx' => array('file_id'),
                'post_id_idx' => array('post_id'),
            ),
        );
    }

    static function processNew(File $file, Notice $notice) {
        static $seen = array();

        $file_id = $file->getID();
        $notice_id = $notice->getID();
        if (!array_key_exists($notice_id, $seen)) {
            $seen[$notice_id] = array();
        }

        if (empty($seen[$notice_id]) || !in_array($file_id, $seen[$notice_id])) {
            try {
                $f2p = File_to_post::getByPK(array('post_id' => $notice_id,
                                                   'file_id' => $file_id));
            } catch (NoResultException $e) {
                $f2p = new File_to_post;
                $f2p->file_id = $file_id;
                $f2p->post_id = $notice_id;
                $f2p->insert();
                
                $file->blowCache();
            }

            $seen[$notice_id][] = $file_id;
        }
    }

    static function getNoticeIDsByFile(File $file)
    {
        $f2p = new File_to_post();

        $f2p->selectAdd();
        $f2p->selectAdd('post_id');

        $f2p->file_id = $file->getID();

        $ids = array();

        if (!$f2p->find()) {
            throw new NoResultException($f2p);
        }

        return $f2p->fetchAll('post_id');
    }

    function delete($useWhere=false)
    {
        try {
            $f = File::getByID($this->file_id);
            $f->blowCache();
        } catch (NoResultException $e) {
            // ...alright, that's weird, but no File to delete anyway.
        }

        return parent::delete($useWhere);
    }
}
