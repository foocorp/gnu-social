<?php
/**
 * Display a conversation in the browser
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

// XXX: not sure how to do paging yet,
// so set a 60-notice limit

require_once INSTALLDIR.'/lib/noticelist.php';

/**
 * Conversation tree in the browser
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class ConversationRepliesAction extends ConversationAction
{
    function handle($args)
    {
        if ($this->boolean('ajax')) {
            $this->showAjax();
        } else {
            parent::handle($args);
        }
    }

    /**
     * Show content.
     *
     * Display a hierarchical unordered list in the content area.
     * Uses ConversationTree to do most of the heavy lifting.
     *
     * @return void
     */
    function showContent()
    {
        $ct = new FullThreadedNoticeList($this->notices, $this, $this->userProfile);

        $cnt = $ct->show();
    }

    function showAjax()
    {
        $this->startHTML('text/xml;charset=utf-8');
        $this->elementStart('head');
        // TRANS: Title for conversation page.
        $this->element('title', null, _m('TITLE','Notice'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $this->showContent();
        $this->elementEnd('body');
        $this->endHTML();
    }
}
