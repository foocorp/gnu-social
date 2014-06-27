<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Superclass for system event items
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
 * @category  Activity
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
 * NoticeListItemAdapter for system activities
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class SystemListItem extends NoticeListItemAdapter
{
    /**
     * Show the activity
     *
     * @return void
     */
    function showNotice()
    {
        $out = $this->nli->out;
        $out->elementStart('div', 'entry-title');
        $this->showContent();
        $out->elementEnd('div');
    }

    function showContent()
    {
        $notice = $this->nli->notice;
        $out    = $this->nli->out;

        // FIXME: get the actual data on the leave

        $out->elementStart('div', 'system-activity');

        $out->raw($notice->rendered);

        $out->elementEnd('div');
    }

    function showNoticeOptions()
    {
        if (Event::handle('StartShowNoticeOptions', array($this))) {
            $user = common_current_user();
            if (!empty($user)) {
                $this->nli->out->elementStart('div', 'notice-options');
                if (Event::handle('StartShowNoticeOptionItems', array($this))) {
                    $this->showReplyLink();
                    Event::handle('EndShowNoticeOptionItems', array($this));
                }
                $this->nli->out->elementEnd('div');
            }
            Event::handle('EndShowNoticeOptions', array($this));
        }
    }
}
