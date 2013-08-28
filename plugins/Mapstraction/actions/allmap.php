<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a map of user's friends' notices
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
 * @category  Mapstraction
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Show a map of user's notices
 *
 * @category Mapstraction
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AllmapAction extends MapAction
{
    function prepare($args)
    {
        if (parent::prepare($args)) {
            $cur = common_current_user();
            $stream = new InboxNoticeStream($this->user, $cur->getProfile());
            $this->notice = $stream->getNotices(($this->page-1)*NOTICES_PER_PAGE,
                                                NOTICES_PER_PAGE + 1,
                                                null,
                                                null);
            return true;
        } else {
            return false;
        }
    }

    function title()
    {
        $base = $this->profile->getFancyName();

        if ($this->page == 1) {
            // TRANS: Page title.
            // TRANS: %s is a user nickname.
            return sprintf(_m("%s friends map"),
                           $base);
        } else {
            // @todo CHECKME: does this even happen? May not be needed.
            // TRANS: Page title.
            // TRANS: %1$s is a user nickname, %2$d is a page number.
            return sprintf(_m('%1$s friends map, page %2$d'),
                           $base,
                           $this->page);
        }
    }
}
