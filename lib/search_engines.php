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
        switch ($mode) {
        case 'chron':
            return $this->target->orderBy('created DESC');
            break;
        case 'reverse_chron':
            return $this->target->orderBy('created ASC');
            break;
        case 'nickname_desc':
            if ($this->table != 'profile') {
                throw new Exception(
                    'nickname_desc sort mode can only be use when searching profile.'
                );
            } else {
                return $this->target->orderBy(sprintf('%1$s.nickname DESC', $this->table));
            }
            break;
        case 'nickname_asc':
            if ($this->table != 'profile') {
                throw new Exception(
                    'nickname_desc sort mode can only be use when searching profile.'
                );
            } else {
                return $this->target->orderBy(sprintf('%1$s.nickname ASC', $this->table));
            }
            break;
        default:
            return $this->target->orderBy('created DESC');
            break;
        }
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
            $qry = sprintf('(%2$s.nickname LIKE "%%%1$s%%" OR '.
                            ' %2$s.fullname LIKE "%%%1$s%%" OR '.
                            ' %2$s.location LIKE "%%%1$s%%" OR '.
                            ' %2$s.bio      LIKE "%%%1$s%%" OR '.
                            ' %2$s.homepage LIKE "%%%1$s%%")',
                           $this->target->escape($q, true),
                           $this->table);
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

