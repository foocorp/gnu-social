<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Show a single group message
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
 * Show a single private group message
 *
 * @category  GroupPrivateMessage
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class ShowgroupmessageAction extends Action
{
    var $gm;
    var $group;
    var $sender;
    var $user;

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
            throw new ClientException(_('Only logged-in users can view private messages.'),
                                      403);
        }

        $id = $this->trimmed('id');

        $this->gm = Group_message::staticGet('id', $id);

        if (empty($this->gm)) {
            throw new ClientException(_('No such message'), 404);
        }

        $this->group = User_group::staticGet('id', $this->gm->to_group);

        if (empty($this->group)) {
            throw new ServerException(_('Group not found.'));
        }

        if (!$this->user->isMember($this->group)) {
            throw new ClientException(_('Cannot read message.'), 403);
        }

        $this->sender = Profile::staticGet('id', $this->gm->from_profile);

        if (empty($this->sender)) {
            throw new ServerException(_('No sender found.'));
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
        $this->showPage();
    }

    /**
     * Title of the page
     */

    function title()
    {
        return sprintf(_('Message from %1$s to group %2$s on %3$s'),
                       $this->sender->nickname,
                       $this->group->nickname,
                       common_exact_date($this->gm->created));
    }

    /**
     * Show the content area.
     */

    function showContent()
    {
        $this->elementStart('ul', 'notices messages');
        $gmli = new GroupMessageListItem($this, $this->gm);
        $gmli->show();
        $this->elementEnd('ul');
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
     * Return last modified, if applicable.
     *
     * MAY override
     *
     * @return string last modified http header
     */
    function lastModified()
    {
        return max(strtotime($this->group->modified),
                   strtotime($this->sender->modified),
                   strtotime($this->gm->modified));
    }

    /**
     * Return etag, if applicable.
     *
     * MAY override
     *
     * @return string etag http header
     */
    function etag()
    {
        $avatar = $this->sender->getAvatar(AVATAR_STREAM_SIZE);

        $avtime = ($avatar) ? strtotime($avatar->modified) : 0;

        return 'W/"' . implode(':', array($this->arg('action'),
                                          common_user_cache_hash(),
                                          common_language(),
                                          $this->gm->id,
                                          strtotime($this->sender->modified),
                                          strtotime($this->group->modified),
                                          $avtime)) . '"';
    }
}
