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

class FavoritedSliceAction extends FavoritedAction
{
    private $includeUsers = array(), $excludeUsers = array();

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     * @todo move queries from showContent() to here
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->slice = $this->arg('slice', 'default');
        $data = array();
        if (Event::handle('SlicedFavoritesGetSettings', array($this->slice, &$data))) {
            // TRANS: Client exception.
            throw new ClientException(_m('Unknown favorites slice.'));
        }
        if (isset($data['include'])) {
            $this->includeUsers = $data['include'];
        }
        if (isset($data['exclude'])) {
            $this->excludeUsers = $data['exclude'];
        }

        return true;
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
        $slice = $this->sliceWhereClause();
        if (!$slice) {
            return parent::showContent();
        }

        $weightexpr = common_sql_weight('fave.modified', common_config('popular', 'dropoff'));
        $cutoff = sprintf("fave.modified > '%s'",
                          common_sql_date(time() - common_config('popular', 'cutoff')));

        $qry = 'SELECT notice.*, '.
          $weightexpr . ' as weight ' .
          'FROM notice JOIN fave ON notice.id = fave.notice_id ' .
          "WHERE $cutoff AND $slice " .
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
            $this->showEmptyList();
        }

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'favorited');
    }

    private function sliceWhereClause()
    {
        $include = $this->nicknamesToIds($this->includeUsers);
        $exclude = $this->nicknamesToIds($this->excludeUsers);

        if (count($include) == 1) {
            return "profile_id = " . intval($include[0]);
        } else if (count($include) > 1) {
            return "profile_id IN (" . implode(',', $include) . ")";
        } else if (count($exclude) == 1) {
            return "profile_id != " . intval($exclude[0]);
        } else if (count($exclude) > 1) {
            return "profile_id NOT IN (" . implode(',', $exclude) . ")";
        } else {
            return false;
        }
    }

    /**
     *
     * @param array $nicks array of user nicknames
     * @return array of profile/user IDs
     */
    private function nicknamesToIds($nicks)
    {
        $ids = array();
        foreach ($nicks as $nick) {
            // not the most efficient way for a big list!
            $user = User::getKV('nickname', $nick);
            if ($user) {
                $ids[] = intval($user->id);
            }
        }
        return $ids;
    }
}
