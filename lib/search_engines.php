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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class SearchEngine
{
    protected $target;
    protected $table;

    function __construct($target, $table)
    {
        $this->target = $target;
        $this->table = $table;
    }

    function query($q)
    {
    }

    function limit($offset, $count, $rss = false)
    {
        return $this->target->limit($offset, $count);
    }

    function set_sort_mode($mode)
    {
        if ('chron' === $mode)
            return $this->target->orderBy('created desc');
    }
}

class MySQLSearch extends SearchEngine
{
    function query($q)
    {
        if ('profile' === $this->table) {
            $this->target->whereAdd('MATCH(nickname, fullname, location, bio, homepage) ' .
                                    'AGAINST (\''.$this->target->escape($q).'\' IN BOOLEAN MODE)');
            if (strtolower($q) != $q) {
                $this->target->whereAdd('MATCH(nickname, fullname, location, bio, homepage) ' .
                                        'AGAINST (\''.$this->target->escape(strtolower($q)).'\' IN BOOLEAN MODE)', 'OR');
            }
            return true;
        } else if ('notice' === $this->table) {

            // Don't show imported notices
            $this->target->whereAdd('notice.is_local != ' . Notice::GATEWAY);

            if (strtolower($q) != $q) {
                $this->target->whereAdd("( MATCH(content) AGAINST ('" . $this->target->escape($q) .
                    "' IN BOOLEAN MODE)) OR ( MATCH(content) " .
                    "AGAINST ('"  . $this->target->escape(strtolower($q)) .
                    "' IN BOOLEAN MODE))");
            } else {
                $this->target->whereAdd('MATCH(content) ' .
                                         'AGAINST (\''.$this->target->escape($q).'\' IN BOOLEAN MODE)');
            }

            return true;
        } else {
            throw new ServerException('Unknown table: ' . $this->table);
        }
    }
}

class MySQLLikeSearch extends SearchEngine
{
    function query($q)
    {
        if ('profile' === $this->table) {
            $qry = sprintf('(nickname LIKE "%%%1$s%%" OR '.
                           ' fullname LIKE "%%%1$s%%" OR '.
                           ' location LIKE "%%%1$s%%" OR '.
                           ' bio      LIKE "%%%1$s%%" OR '.
                           ' homepage LIKE "%%%1$s%%")', $this->target->escape($q, true));
        } else if ('notice' === $this->table) {
            $qry = sprintf('content LIKE "%%%1$s%%"', $this->target->escape($q, true));
        } else {
            throw new ServerException('Unknown table: ' . $this->table);
        }

        $this->target->whereAdd($qry);

        return true;
    }
}

class PGSearch extends SearchEngine
{
    function query($q)
    {
        if ('profile' === $this->table) {
            return $this->target->whereAdd('textsearch @@ plainto_tsquery(\''.$this->target->escape($q).'\')');
        } else if ('notice' === $this->table) {

            // XXX: We need to filter out gateway notices (notice.is_local = -2) --Zach

            return $this->target->whereAdd('to_tsvector(\'english\', content) @@ plainto_tsquery(\''.$this->target->escape($q).'\')');
        } else {
            throw new ServerException('Unknown table: ' . $this->table);
        }
    }
}

