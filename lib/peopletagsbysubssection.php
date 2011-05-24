<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Peopletags with the most subscribers section
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 *
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Peopletags with the most subscribers section
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class PeopletagsBySubsSection extends PeopletagSection
{
    function getPeopletags()
    {
        $qry = 'SELECT profile_list.*, subscriber_count as value ' .
               'FROM profile_list WHERE profile_list.private = false ' .
               'ORDER BY value DESC ';

        $limit = PEOPLETAGS_PER_SECTION;
        $offset = 0;

        if (common_config('db','type') == 'pgsql') {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $qry .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $peopletag = Memcached_DataObject::cachedQuery('Profile_list',
                                                   $qry,
                                                   3600);
        return $peopletag;
    }

    function title()
    {
        // TRANS: Title for section contaning lists with the most subscribers.
        return _('Popular lists');
    }

    function divId()
    {
        return 'top_peopletags_by_subs';
    }
}
