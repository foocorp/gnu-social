<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List of popular notices
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
 * @category  Public
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class GroupFavoritedAction extends ShowgroupAction
{
    /**
     * Title of the page
     *
     * @return string page title, with page number
     */
    function title()
    {
        $base = $this->group->getFancyName();

        if ($this->page == 1) {
            // TRANS: %s is a group name.
            return sprintf(_m('Popular posts in %s group'), $base);
        } else {
            // TRANS: %1$s is a group name, %2$s is a group number.
            return sprintf(_m('Popular posts in %1$s group, page %2$d'),
                           $base,
                           $this->page);
        }
    }

    /**
     * Content area
     *
     * Shows the list of popular notices
     *
     * @return void
     */
    function showContent()
    {
        $groupId = intval($this->group->id);
        $weightexpr = common_sql_weight('fave.modified', common_config('popular', 'dropoff'));
        $cutoff = sprintf("fave.modified > '%s'",
                          common_sql_date(time() - common_config('popular', 'cutoff')));

        $qry = 'SELECT notice.*, '.
          $weightexpr . ' as weight ' .
          'FROM notice ' .
          "JOIN group_inbox ON notice.id = group_inbox.notice_id " .
          'JOIN fave ON notice.id = fave.notice_id ' .
          "WHERE $cutoff AND group_id = $groupId " .
          'GROUP BY id,profile_id,uri,content,rendered,url,created,notice.modified,reply_to,is_local,source,notice.conversation ' .
          'ORDER BY weight DESC';

        $offset = ($this->page - 1) * NOTICES_PER_PAGE;
        $limit  = NOTICES_PER_PAGE + 1;

        if (common_config('db', 'type') == 'pgsql') {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $qry .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $notice = Memcached_DataObject::cachedQuery('Notice',
                                                    $qry,
                                                    600);

        $nl = new NoticeList($notice, $this);

        $cnt = $nl->show();

        if ($cnt == 0) {
            //$this->showEmptyList();
        }

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'groupfavorited',
                          array('nickname' => $this->group->nickname));
    }
}
