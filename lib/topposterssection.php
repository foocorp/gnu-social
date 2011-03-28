<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for sections showing lists of people
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
 * Base class for sections
 *
 * These are the widgets that show interesting data about a person
 * group, or site.
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class TopPostersSection extends ProfileSection
{
    function getProfiles()
    {
        $qry = 'SELECT profile.*, count(*) as value ' .
          'FROM profile JOIN notice ON profile.id = notice.profile_id ' .
          (common_config('public', 'localonly') ? 'WHERE is_local = 1 ' : '') .
          'GROUP BY profile.id,nickname,fullname,profileurl,homepage,bio,location,profile.created,profile.modified,textsearch ' .
          'ORDER BY value DESC ';

        $limit = PROFILES_PER_SECTION;
        $offset = 0;

        if (common_config('db','type') == 'pgsql') {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $qry .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $profile = Memcached_DataObject::cachedQuery('Profile',
                                                     $qry,
                                                     6 * 3600);
        return $profile;
    }

    function title()
    {
        // TRANS: Title for top posters section.
        return _('Top posters');
    }

    function divId()
    {
        return 'top_posters';
    }
}
