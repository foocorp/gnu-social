<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Profile block to show for a group
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
 * @category  Widget
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
 * Profile block to show for a group
 *
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class GroupProfileBlock extends ProfileBlock
{
    protected $group = null;

    function __construct($out, $group)
    {
        parent::__construct($out);
        $this->group = $group;
    }

    function avatar()
    {
        return ($this->group->homepage_logo) ?
            $this->group->homepage_logo : User_group::defaultLogo(AVATAR_PROFILE_SIZE);
    }

    function name()
    {
        return $this->group->getBestName();
    }

    function url()
    {
        return $this->group->homeUrl();
    }

    function location()
    {
        return $this->group->location;
    }

    function homepage()
    {
        return $this->group->homepage;
    }

    function description()
    {
        return $this->group->description;
    }

    function otherProfiles()
    {
        return array();
    }

    function showActions()
    {
        $cur = common_current_user();
        $this->out->elementStart('div', 'entity_actions');
        // TRANS: Group actions header (h2). Text hidden by default.
        $this->out->element('h2', null, _('Group actions'));
        $this->out->elementStart('ul');
        if (Event::handle('StartGroupActionsList', array($this, $this->group))) {
            $this->out->elementStart('li', 'entity_subscribe');
            if (Event::handle('StartGroupSubscribe', array($this, $this->group))) {
                if ($cur) {
                    $profile = $cur->getProfile();
                    if ($profile->isMember($this->group)) {
                        $lf = new LeaveForm($this->out, $this->group);
                        $lf->show();
                    } else if ($profile->isPendingMember($this->group)) {
                        $cf = new CancelGroupForm($this->out, $this->group);
                        $cf->show();
                    } else if (!Group_block::isBlocked($this->group, $profile)) {
                        $jf = new JoinForm($this->out, $this->group);
                        $jf->show();
                    }
                }
                Event::handle('EndGroupSubscribe', array($this, $this->group));
            }
            $this->out->elementEnd('li');
            if ($cur && $cur->isAdmin($this->group)) {
                $this->out->elementStart('li', 'entity_edit');
                $this->out->element('a', array('href' => common_local_url('editgroup',
                                                                          array('nickname' => $this->group->nickname)),
                                               // TRANS: Tooltip for menu item in the group navigation page. Only shown for group administrators.
                                               // TRANS: %s is the nickname of the group.
                                               'title' => sprintf(_m('TOOLTIP','Edit %s group properties'), $this->group->nickname)),
                                    // TRANS: Link text for link on user profile.
                                    _m('BUTTON','Edit'));
                $this->out->elementEnd('li');
                $this->out->elementStart('li', 'entity_edit');
                $this->out->element('a', array('href' => common_local_url('grouplogo',
                                                                          array('nickname' => $this->group->nickname)),
                                               // TRANS: Tooltip for menu item in the group navigation page. Only shown for group administrators.
                                               // TRANS: %s is the nickname of the group.
                                               'title' => sprintf(_m('TOOLTIP','Add or edit %s logo'), $this->group->nickname)),
                                    // TRANS: Link text for link on user profile.
                                    _m('MENU','Logo'));
                $this->out->elementEnd('li');
            }
            if ($cur && $cur->hasRight(Right::DELETEGROUP)) {
                $this->out->elementStart('li', 'entity_delete');
                $df = new DeleteGroupForm($this->out, $this->group);
                $df->show();
                $this->out->elementEnd('li');
            }

            Event::handle('EndGroupActionsList', array($this, $this->group));
        }
        $this->out->elementEnd('ul');
        $this->out->elementEnd('div');
    }

    function show()
    {
        $this->out->elementStart('div', 'profile_block group_profile_block section');
        if (Event::handle('StartShowGroupProfileBlock', array($this->out, $this->group))) {
            parent::show();
            Event::handle('EndShowGroupProfileBlock', array($this->out, $this->group));
        }
        $this->out->elementEnd('div');
    }

    function showName()
    {
        parent::showName();
        $this->showAliases();
    }

    function showAliases()
    {
        $aliases = $this->group->getAliases();

        if (!empty($aliases)) {
            $this->out->elementStart('ul', 'group_aliases');
            foreach ($aliases as $alias) {
                $this->out->element('li', 'group_alias', $alias);
            }
            $this->out->elementEnd('ul');
        }
    }
}
