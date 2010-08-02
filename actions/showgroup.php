<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Group main page
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
 * @category  Group
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/noticelist.php';
require_once INSTALLDIR.'/lib/feedlist.php';

define('MEMBERS_PER_SECTION', 27);

/**
 * Group main page
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ShowgroupAction extends GroupDesignAction
{

    /** page we're viewing. */
    var $page = null;

    /**
     * Is this page read-only?
     *
     * @return boolean true
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
        if (!empty($this->group->fullname)) {
            $base = $this->group->fullname . ' (' . $this->group->nickname . ')';
        } else {
            $base = $this->group->nickname;
        }

        if ($this->page == 1) {
            return sprintf(_('%s group'), $base);
        } else {
            return sprintf(_('%1$s group, page %2$d'),
                           $base,
                           $this->page);
        }
    }

    /**
     * Prepare the action
     *
     * Reads and validates arguments and instantiates the attributes.
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */

    function prepare($args)
    {
        parent::prepare($args);

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        $nickname_arg = $this->arg('nickname');
        $nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->page != 1) {
                $args['page'] = $this->page;
            }
            common_redirect(common_local_url('showgroup', $args), 301);
            return false;
        }

        if (!$nickname) {
            $this->clientError(_('No nickname.'), 404);
            return false;
        }

        $local = Local_group::staticGet('nickname', $nickname);

        if (!$local) {
            $alias = Group_alias::staticGet('alias', $nickname);
            if ($alias) {
                $args = array('id' => $alias->group_id);
                if ($this->page != 1) {
                    $args['page'] = $this->page;
                }
                common_redirect(common_local_url('groupbyid', $args), 301);
                return false;
            } else {
                common_log(LOG_NOTICE, "Couldn't find local group for nickname '$nickname'");
                $this->clientError(_('No such group.'), 404);
                return false;
            }
        }

        $this->group = User_group::staticGet('id', $local->group_id);

        if (!$this->group) {
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        common_set_returnto($this->selfUrl());

        return true;
    }

    /**
     * Handle the request
     *
     * Shows a profile for the group, some controls, and a list of
     * group notices.
     *
     * @return void
     */

    function handle($args)
    {
        $this->showPage();
    }

    /**
     * Local menu
     *
     * @return void
     */

    function showLocalNav()
    {
        $nav = new GroupNav($this, $this->group);
        $nav->show();
    }

    /**
     * Show the page content
     *
     * Shows a group profile and a list of group notices
     */

    function showContent()
    {
        $this->showGroupProfile();
        $this->showGroupNotices();
    }

    /**
     * Show the group notices
     *
     * @return void
     */

    function showGroupNotices()
    {
        $notice = $this->group->getNotices(($this->page-1)*NOTICES_PER_PAGE,
                                           NOTICES_PER_PAGE + 1);

        $nl = new NoticeList($notice, $this);
        $cnt = $nl->show();

        $this->pagination($this->page > 1,
                          $cnt > NOTICES_PER_PAGE,
                          $this->page,
                          'showgroup',
                          array('nickname' => $this->group->nickname));
    }

    /**
     * Show the group profile
     *
     * Information about the group
     *
     * @return void
     */

    function showGroupProfile()
    {
        $this->elementStart('div', array('id' => 'i',
                                         'class' => 'entity_profile vcard author'));

        $this->element('h2', null, _('Group profile'));

        $this->elementStart('dl', 'entity_depiction');
        $this->element('dt', null, _('Avatar'));
        $this->elementStart('dd');

        $logo = ($this->group->homepage_logo) ?
          $this->group->homepage_logo : User_group::defaultLogo(AVATAR_PROFILE_SIZE);

        $this->element('img', array('src' => $logo,
                                    'class' => 'photo avatar',
                                    'width' => AVATAR_PROFILE_SIZE,
                                    'height' => AVATAR_PROFILE_SIZE,
                                    'alt' => $this->group->nickname));
        $this->elementEnd('dd');
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_nickname');
        $this->element('dt', null, _('Nickname'));
        $this->elementStart('dd');
        $hasFN = ($this->group->fullname) ? 'nickname url uid' : 'fn org nickname url uid';
        $this->element('a', array('href' => $this->group->homeUrl(),
                                  'rel' => 'me', 'class' => $hasFN),
                            $this->group->nickname);
        $this->elementEnd('dd');
        $this->elementEnd('dl');

        if ($this->group->fullname) {
            $this->elementStart('dl', 'entity_fn');
            $this->element('dt', null, _('Full name'));
            $this->elementStart('dd');
            $this->element('span', 'fn org', $this->group->fullname);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }

        if ($this->group->location) {
            $this->elementStart('dl', 'entity_location');
            $this->element('dt', null, _('Location'));
            $this->element('dd', 'label', $this->group->location);
            $this->elementEnd('dl');
        }

        if ($this->group->homepage) {
            $this->elementStart('dl', 'entity_url');
            $this->element('dt', null, _('URL'));
            $this->elementStart('dd');
            $this->element('a', array('href' => $this->group->homepage,
                                      'rel' => 'me', 'class' => 'url'),
                           $this->group->homepage);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }

        if ($this->group->description) {
            $this->elementStart('dl', 'entity_note');
            $this->element('dt', null, _('Note'));
            $this->element('dd', 'note', $this->group->description);
            $this->elementEnd('dl');
        }

        if (common_config('group', 'maxaliases') > 0) {
            $aliases = $this->group->getAliases();

            if (!empty($aliases)) {
                $this->elementStart('dl', 'entity_aliases');
                $this->element('dt', null, _('Aliases'));
                $this->element('dd', 'aliases', implode(' ', $aliases));
                $this->elementEnd('dl');
            }
        }

        $this->elementEnd('div');

        $this->elementStart('div', 'entity_actions');
        $this->element('h2', null, _('Group actions'));
        $this->elementStart('ul');
        $this->elementStart('li', 'entity_subscribe');
        if (Event::handle('StartGroupSubscribe', array($this, $this->group))) {
            $cur = common_current_user();
            if ($cur) {
                if ($cur->isMember($this->group)) {
                    $lf = new LeaveForm($this, $this->group);
                    $lf->show();
                } else if (!Group_block::isBlocked($this->group, $cur->getProfile())) {
                    $jf = new JoinForm($this, $this->group);
                    $jf->show();
                }
            }
            Event::handle('EndGroupSubscribe', array($this, $this->group));
        }
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('div');
    }

    /**
     * Get a list of the feeds for this page
     *
     * @return void
     */

    function getFeeds()
    {
        $url =
          common_local_url('grouprss',
                           array('nickname' => $this->group->nickname));

        return array(new Feed(Feed::RSS1,
                              common_local_url('grouprss',
                                               array('nickname' => $this->group->nickname)),
                              sprintf(_('Notice feed for %s group (RSS 1.0)'),
                                      $this->group->nickname)),
                     new Feed(Feed::RSS2,
                              common_local_url('ApiTimelineGroup',
                                               array('format' => 'rss',
                                                     'id' => $this->group->id)),
                              sprintf(_('Notice feed for %s group (RSS 2.0)'),
                                      $this->group->nickname)),
                     new Feed(Feed::ATOM,
                              common_local_url('ApiTimelineGroup',
                                               array('format' => 'atom',
                                                     'id' => $this->group->id)),
                              sprintf(_('Notice feed for %s group (Atom)'),
                                      $this->group->nickname)),
                     new Feed(Feed::FOAF,
                              common_local_url('foafgroup',
                                               array('nickname' => $this->group->nickname)),
                              sprintf(_('FOAF for %s group'),
                                       $this->group->nickname)));
    }

    /**
     * Fill in the sidebar.
     *
     * @return void
     */

    function showSections()
    {
        $this->showMembers();
        $this->showStatistics();
        $this->showAdmins();
        $cloud = new GroupTagCloudSection($this, $this->group);
        $cloud->show();
    }

    /**
     * Show mini-list of members
     *
     * @return void
     */

    function showMembers()
    {
        $member = $this->group->getMembers(0, MEMBERS_PER_SECTION);

        if (!$member) {
            return;
        }

        $this->elementStart('div', array('id' => 'entity_members',
                                         'class' => 'section'));

        if (Event::handle('StartShowGroupMembersMiniList', array($this))) {

            $this->element('h2', null, _('Members'));

            $gmml = new GroupMembersMiniList($member, $this);
            $cnt = $gmml->show();
            if ($cnt == 0) {
                $this->element('p', null, _('(None)'));
            }

            if ($cnt > MEMBERS_PER_SECTION) {
                $this->element('a', array('href' => common_local_url('groupmembers',
                                                                     array('nickname' => $this->group->nickname))),
                               _('All members'));
            }

            Event::handle('EndShowGroupMembersMiniList', array($this));
        }

        $this->elementEnd('div');
    }

    /**
     * Show list of admins
     *
     * @return void
     */

    function showAdmins()
    {
        $adminSection = new GroupAdminSection($this, $this->group);
        $adminSection->show();
    }

    /**
     * Show some statistics
     *
     * @return void
     */

    function showStatistics()
    {
        $this->elementStart('div', array('id' => 'entity_statistics',
                                         'class' => 'section'));

        $this->element('h2', null, _('Statistics'));

        $this->elementStart('dl', 'entity_created');
        $this->element('dt', null, _('Created'));
        $this->element('dd', null, date('j M Y',
                                                 strtotime($this->group->created)));
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_members');
        $this->element('dt', null, _('Members'));
        $this->element('dd', null, $this->group->getMemberCount());
        $this->elementEnd('dl');

        $this->elementEnd('div');
    }

    function showAnonymousMessage()
    {
        if (!(common_config('site','closed') || common_config('site','inviteonly'))) {
            $m = sprintf(_('**%s** is a user group on %%%%site.name%%%%, a [micro-blogging](http://en.wikipedia.org/wiki/Micro-blogging) service ' .
                'based on the Free Software [StatusNet](http://status.net/) tool. Its members share ' .
                'short messages about their life and interests. '.
                '[Join now](%%%%action.register%%%%) to become part of this group and many more! ([Read more](%%%%doc.help%%%%))'),
                     $this->group->nickname);
        } else {
            $m = sprintf(_('**%s** is a user group on %%%%site.name%%%%, a [micro-blogging](http://en.wikipedia.org/wiki/Micro-blogging) service ' .
                'based on the Free Software [StatusNet](http://status.net/) tool. Its members share ' .
                'short messages about their life and interests. '),
                     $this->group->nickname);
        }
        $this->elementStart('div', array('id' => 'anon_notice'));
        $this->raw(common_markup_to_html($m));
        $this->elementEnd('div');
    }
}

class GroupAdminSection extends ProfileSection
{
    var $group;

    function __construct($out, $group)
    {
        parent::__construct($out);
        $this->group = $group;
    }

    function getProfiles()
    {
        return $this->group->getAdmins();
    }

    function title()
    {
        return _('Admins');
    }

    function divId()
    {
        return 'group_admins';
    }

    function moreUrl()
    {
        return null;
    }
}

class GroupMembersMiniList extends ProfileMiniList
{
    function newListItem($profile)
    {
        return new GroupMembersMiniListItem($profile, $this->action);
    }
}

class GroupMembersMiniListItem extends ProfileMiniListItem
{
    function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'members')) {
            $aAttrs['rel'] .= ' nofollow';
        }

        return $aAttrs;
    }
}

