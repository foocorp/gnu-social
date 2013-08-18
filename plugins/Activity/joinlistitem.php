<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * List item for when you join a group
 *
 * PHP version 5
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
 *
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * NoticeListItemAdapter for join activities
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class JoinListItem extends SystemListItem
{
    function showContent()
    {
        $notice = $this->nli->notice;
        $out    = $this->nli->out;

        $mem = Group_member::getKV('uri', $notice->uri);

        if (!empty($mem)) {
            $out->elementStart('div', 'join-activity');
            $profile = $mem->getMember();
            $group = $mem->getGroup();

            // TRANS: Text for "joined list" item in activity plugin.
            // TRANS: %1$s is a profile URL, %2$s is a profile name,
            // TRANS: %3$s is a group home URL, %4$s is a group name.
            $out->raw(sprintf(_m('<a href="%1$s">%2$s</a> joined the group <a href="%3$s">%4$s</a>.'),
                                $profile->profileurl,
                                $profile->getBestName(),
                                $group->homeUrl(),
                                $group->getBestName()));

            $out->elementEnd('div');
        } else {
            parent::showContent();
        }
    }
}
