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

if (!defined('STATUSNET')) {
    exit(1);
}

class SphinxSearch extends SearchEngine
{
    private $sphinx;
    private $connected;

    function __construct($target, $table)
    {
        $fp = @fsockopen(common_config('sphinx', 'server'), common_config('sphinx', 'port'));
        if (!$fp) {
            $this->connected = false;
            return;
        }
        fclose($fp);
        parent::__construct($target, $table);
        $this->sphinx = new SphinxClient;
        $this->sphinx->setServer(common_config('sphinx', 'server'), common_config('sphinx', 'port'));
        $this->connected = true;
    }

    function is_connected()
    {
        return $this->connected;
    }

    function limit($offset, $count, $rss = false)
    {
        //FIXME without LARGEST_POSSIBLE, the most recent results aren't returned
        //      this probably has a large impact on performance
        $LARGEST_POSSIBLE = 1e6;

        if ($rss) {
            $this->sphinx->setLimits($offset, $count, $count, $LARGEST_POSSIBLE);
        }
        else {
            // return at most 50 pages of results
            $this->sphinx->setLimits($offset, $count, 50 * ($count - 1), $LARGEST_POSSIBLE);
        }

        return $this->target->limit(0, $count);
    }

    function query($q)
    {
        $result = $this->sphinx->query($q, $this->remote_table());
        if ($result === false) {
            throw new ServerException($this->sphinx->getLastError());
        }
        if (!isset($result['matches'])) return false;
        $id_set = join(', ', array_keys($result['matches']));
        $this->target->whereAdd("id in ($id_set)");
        return true;
     }

    function set_sort_mode($mode)
    {
        switch ($mode) {
        case 'chron':
            $this->sphinx->SetSortMode(SPH_SORT_ATTR_DESC, 'created_ts');
            return $this->target->orderBy('id desc');
            break;
        case 'reverse_chron':
            $this->sphinx->SetSortMode(SPH_SORT_ATTR_ASC, 'created_ts');
            return $this->target->orderBy('id asc');
            break;
        case 'nickname_desc':
            if ($this->table != 'profile') {
                throw new Exception(
                    'nickname_desc sort mode can only be use when searching profile.'
                );
            } else {
                $this->sphinx->SetSortMode(SPH_SORT_ATTR_DESC, 'nickname');
                return $this->target->orderBy('id desc');
            }
            break;
        case 'nickname_asc':
            if ($this->table != 'profile') {
                throw new Exception(
                    'nickname_desc sort mode can only be use when searching profile.'
                );
            } else {
                $this->sphinx->SetSortMode(SPH_SORT_ATTR_ASC, 'nickname');
                return $this->target->orderBy('id asc');
            }
            break;
        default:
            $this->sphinx->SetSortMode(SPH_SORT_ATTR_DESC, 'created_ts');
            return $this->target->orderBy('id desc');
            break;
        }
    }

    function remote_table()
    {
        return $this->dbname() . '_' . $this->table;
    }

    function dbname()
    {
        // @fixme there should be a less dreadful way to do this.
        // DB objects won't give database back until they connect, it's confusing
        if (preg_match('!^.*?://.*?:.*?@.*?/(.*?)$!', common_config('db', 'database'), $matches)) {
            return $matches[1];
        }

        // TRANS: Server exception thrown when a database name cannot be identified.
        throw new ServerException(_m("Sphinx search could not identify database name."));
    }
}
