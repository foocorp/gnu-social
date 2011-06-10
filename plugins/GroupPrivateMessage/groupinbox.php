<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * List of private messages to this group
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
 * @category  GroupPrivateMessage
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
 * Show a list of private messages to this group
 *
 * @category  GroupPrivateMessage
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class GroupinboxAction extends GroupAction
{
    var $gm;

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

        $cur = common_current_user();

        if (empty($cur)) {
            // TRANS: Client exception thrown when trying to view group inbox while not logged in.
            throw new ClientException(_m('Only for logged-in users.'), 403);
        }

        $nicknameArg = $this->trimmed('nickname');

        $nickname = common_canonical_nickname($nicknameArg);

        if ($nickname != $nicknameArg) {
            $url = common_local_url('groupinbox', array('nickname' => $nickname));
            common_redirect($url);
            return false;
        }

        $localGroup = Local_group::staticGet('nickname', $nickname);

        if (empty($localGroup)) {
            // TRANS: Client exception thrown when trying to view group inbox for non-existing group.
            throw new ClientException(_m('No such group.'), 404);
        }

        $this->group = User_group::staticGet('id', $localGroup->group_id);

        if (empty($this->group)) {
            // TRANS: Client exception thrown when trying to view group inbox for non-existing group.
            throw new ClientException(_m('No such group.'), 404);
        }

        if (!$cur->isMember($this->group)) {
            // TRANS: Client exception thrown when trying to view group inbox while not a member.
            throw new ClientException(_m('Only for members.'), 403);
        }

        $this->page = $this->trimmed('page');

        if (!$this->page) {
            $this->page = 1;
        }

        $this->gm = Group_message::forGroup($this->group,
                                            ($this->page - 1) * MESSAGES_PER_PAGE,
                                            MESSAGES_PER_PAGE + 1);
        return true;
    }

    function showLocalNav()
    {
        $nav = new GroupNav($this, $this->group);
        $nav->show();
    }

    function showNoticeForm()
    {
        $form = new GroupMessageForm($this, $this->group);
        $form->show();
    }

    function showContent()
    {
        $gml = new GroupMessageList($this, $this->gm);
        $cnt = $gml->show();

        if ($cnt == 0) {
            // TRANS: Text of group inbox if no private messages were sent to it.
            $this->element('p', 'guide', _m('This group has not received any private messages.'));
        }
        $this->pagination($this->page > 1,
                          $cnt > MESSAGES_PER_PAGE,
                          $this->page,
                          'groupinbox',
                          array('nickname' => $this->group->nickname));
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
        $this->showPage();
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Title of the page
     *
     * @return string page title, with page number
     */
    function title()
    {
        $base = $this->group->getFancyName();

        if ($this->page == 1) {
            // TRANS: Title of inbox for group %s.
            return sprintf(_m('%s group inbox'), $base);
        } else {
            // TRANS: Page title for any but first group page.
            // TRANS: %1$s is a group name, $2$s is a page number.
            return sprintf(_m('%1$s group inbox, page %2$d'),
                           $base,
                           $this->page);
        }
    }

    /**
     * Show the page notice
     *
     * Shows instructions for the page
     *
     * @return void
     */
    function showPageNotice()
    {
        $instr  = $this->getInstructions();
        $output = common_markup_to_html($instr);

        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }

    /**
     * Instructions for using this page
     *
     * @return string localised instructions for using the page
     */
    function getInstructions()
    {
        // TRANS: Instructions for user inbox page.
        return _m('This is the group inbox, which lists all incoming private messages for this group.');
    }
}
