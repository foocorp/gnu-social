<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for sections showing lists of notices
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
 * @copyright 2009,2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Base class for sections showing lists of notices
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
class PopularNoticeSection extends NoticeSection
{
    protected $viewer;

    function __construct($out, $viewer)
    {
        parent::__construct($out);
        $this->viewer = $viewer;
    }

    function getNotices()
    {
        $stream = new PopularNoticeStream($this->viewer);
        return $stream->getNotices(0, NOTICES_PER_SECTION + 1);
    }

    function title()
    {
        // TRANS: Title for favourited notices section.
        return _('Popular notices');
    }

    function divId()
    {
        return 'popular_notices';
    }

    function moreUrl()
    {
        if (common_config('singleuser', 'enabled')) {
            $user = User::singleUser();
            common_local_url('showfavorites', array('nickname' =>
                                                    $user->nickname));
        } else {
            return common_local_url('favorited');
        }
    }
}
