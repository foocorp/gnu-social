<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Action for adding a new group message
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
 * Action for adding a new group message
 *
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class NewgroupmessageAction extends Action
{
    var $group;
    var $user;
    var $text;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        parent::prepare($argarray);

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Client exception thrown when trying to send a private group message while not logged in.
            throw new ClientException(_m('Must be logged in.'), 403);
        }

        if (!$this->user->hasRight(Right::NEWMESSAGE)) {
            // TRANS: Exception thrown when user %s is not allowed to send a private group message.
            throw new Exception(sprintf(_m('User %s is not allowed to send private messages.'),
                                        $this->user->nickname));
        }

        $nicknameArg = $this->trimmed('nickname');

        $nickname = common_canonical_nickname($nicknameArg);

        if ($nickname != $nicknameArg) {
            $url = common_local_url('newgroupmessage', array('nickname' => $nickname));
            common_redirect($url, 301);
            return false;
        }

        $localGroup = Local_group::getKV('nickname', $nickname);

        if (empty($localGroup)) {
            // TRANS: Client exception thrown when trying to send a private group message to a non-existing group.
            throw new ClientException(_m('No such group.'), 404);
        }

        $this->group = User_group::getKV('id', $localGroup->group_id);

        if (empty($this->group)) {
            // TRANS: Client exception thrown when trying to send a private group message to a non-existing group.
            throw new ClientException(_m('No such group.'), 404);
        }

        // This throws an exception on error
        Group_privacy_settings::ensurePost($this->user, $this->group);

        // If we're posted to, check session token and get text
        if ($this->isPost()) {
            $this->checkSessionToken();
            $this->text = $this->trimmed('content');
        }

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */
    function handle($argarray=null)
    {
        if ($this->isPost()) {
            $this->sendNewMessage();
        } else {
            $this->showPage();
        }
    }

    function showNoticeForm()
    {
        $form = new GroupMessageForm($this, $this->group);
        $form->show();
    }

    function sendNewMessage()
    {
        $gm = Group_message::send($this->user, $this->group, $this->text);

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Title after sending a private group message.
            $this->element('title', null, _m('Message sent'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->element('p',
                           array('id' => 'command_result'),
                           // TRANS: Succes text after sending a direct message to group %s.
                           sprintf(_m('Direct message to %s sent.'),
                                   $this->group->nickname));
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect($gm->url, 303);
        }
    }

    function title()
    {
        // TRANS: Title of form for new private group message.
        return sprintf(_m('New message to group %s'), $this->group->nickname);
    }
}
