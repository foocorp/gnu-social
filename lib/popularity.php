<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Wrapper for fetching lists of popular notices.
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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Wrapper for fetching notices ranked according to popularity,
 * broken out so it can be called from multiple actions with
 * less code duplication.
 */
class Popularity
{
    public $limit = NOTICES_PER_PAGE;
    public $offset = 0;
    public $tag = false;
    public $expiry = 600;

    /**
     * Run a cached query to fetch notices, whee!
     *
     * @return Notice
     */
    function getNotices()
    {
        // @fixme there should be a common func for this
        if (common_config('db', 'type') == 'pgsql') {
            if (!empty($this->tag)) {
                $tag = pg_escape_string($this->tag);
            }
        } else {
            if (!empty($this->tag)) {
                 $tag = mysql_escape_string($this->tag);
            }
        }
        $weightexpr = common_sql_weight('fave.modified', common_config('popular', 'dropoff'));
        $cutoff = sprintf("fave.modified > '%s'",
                          common_sql_date(time() - common_config('popular', 'cutoff')));
        $qry = "SELECT notice.*, $weightexpr as weight ";
        if(isset($tag)) {
            $qry .= 'FROM notice_tag, notice JOIN fave ON notice.id = fave.notice_id ' .
                    "WHERE $cutoff and notice.id = notice_tag.notice_id and '$tag' = notice_tag.tag";
        } else {
            $qry .= 'FROM notice JOIN fave ON notice.id = fave.notice_id ' .
                    "WHERE $cutoff";
        }
        $qry .= ' GROUP BY notice.id,notice.profile_id,notice.content,notice.uri,' .
                'notice.rendered,notice.url,notice.created,notice.modified,' .
                'notice.reply_to,notice.is_local,notice.source,notice.conversation, ' .
                'notice.lat,notice.lon,location_id,location_ns,notice.repeat_of';
        $qry .= ' HAVING \'silenced\' NOT IN (SELECT role FROM profile_role WHERE profile_id=notice.profile_id)';
        $qry .= ' ORDER BY weight DESC';

        $offset = $this->offset;
        $limit  = $this->limit + 1;

        $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $notice = Memcached_DataObject::cachedQuery('Notice',
                                                    $qry,
                                                    1200);
        return $notice;
    }
}