<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Personal tag cloud section
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
 * Personal tag cloud section
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class InboxTagCloudSection extends TagCloudSection
{
    var $user = null;

    function __construct($out=null, $user=null)
    {
        parent::__construct($out);
        $this->user = $user;
    }

    function title()
    {
        // TRANS: Title for inbox tag cloud section.
        return _m('TITLE', 'Trends');
    }

    function getTags()
    {
        $profile = Profile::current();

        $keypart = sprintf('Inbox:notice_tag:%d:%d', $this->user->id,
                $profile instanceof Profile ? $profile->id : 0);

        $tag = Memcached_DataObject::cacheGet($keypart);

        if ($tag === false) {

            $stream = new InboxNoticeStream($this->user, $profile);

            $ids = $stream->getNoticeIds(0, Inbox::MAX_NOTICES, null, null);

            if (empty($ids)) {
                $tag = array();
            } else {
                $weightexpr = common_sql_weight('notice_tag.created', common_config('tag', 'dropoff'));
                // @fixme should we use the cutoff too? Doesn't help with indexing per-user.

                $qry = 'SELECT notice_tag.tag, '.
                    $weightexpr . ' as weight ' .
                    'FROM notice_tag JOIN notice ' .
                    'ON notice_tag.notice_id = notice.id ' .
                    'WHERE notice.id in (' . implode(',', $ids) . ')'.
                    'GROUP BY notice_tag.tag ' .
                    'ORDER BY weight DESC ';

                $limit = TAGS_PER_SECTION;
                $offset = 0;

                if (common_config('db','type') == 'pgsql') {
                    $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
                } else {
                    $qry .= ' LIMIT ' . $offset . ', ' . $limit;
                }

                $t = new Notice_tag();

                $t->query($qry);

                $tag = array();

                while ($t->fetch()) {
                    $tag[] = clone($t);
                }
            }

            Memcached_DataObject::cacheSet($keypart, $tag, 3600);
        }

        return new ArrayWrapper($tag);
    }

    function showMore()
    {
    }
}
