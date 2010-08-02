<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Common parent of Personal and Profile actions
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
 * @category  Personal
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

require_once INSTALLDIR.'/lib/profileminilist.php';
require_once INSTALLDIR.'/lib/groupminilist.php';

/**
 * Profile action common superclass
 *
 * Abstracts out common code from profile and personal tabs
 *
 * @category Personal
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ProfileAction extends OwnerDesignAction
{
    var $page    = null;
    var $profile = null;
    var $tag     = null;

    function prepare($args)
    {
        parent::prepare($args);

        $nickname_arg = $this->arg('nickname');
        $nickname     = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->arg('page') && $this->arg('page') != 1) {
                $args['page'] = $this->arg['page'];
            }
            common_redirect(common_local_url($this->trimmed('action'), $args), 301);
            return false;
        }

        $this->user = User::staticGet('nickname', $nickname);

        if (!$this->user) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->profile = $this->user->getProfile();

        if (!$this->profile) {
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $this->tag = $this->trimmed('tag');
        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;
        common_set_returnto($this->selfUrl());
        return true;
    }

    function showSections()
    {
        $this->showSubscriptions();
        $this->showSubscribers();
        $this->showGroups();
        $this->showStatistics();
    }

    function showSubscriptions()
    {
        $profile = $this->user->getSubscriptions(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_subscriptions',
                                         'class' => 'section'));
        if (Event::handle('StartShowSubscriptionsMiniList', array($this))) {
            $this->element('h2', null, _('Subscriptions'));

            $cnt = 0;

            if (!empty($profile)) {
                $pml = new ProfileMiniList($profile, $this);
                $cnt = $pml->show();
                if ($cnt == 0) {
                    $this->element('p', null, _('(None)'));
                }
            }

            if ($cnt > PROFILES_PER_MINILIST) {
                $this->elementStart('p');
                $this->element('a', array('href' => common_local_url('subscriptions',
                                                                     array('nickname' => $this->profile->nickname)),
                                          'class' => 'more'),
                               _('All subscriptions'));
                $this->elementEnd('p');
            }

            Event::handle('EndShowSubscriptionsMiniList', array($this));
        }
        $this->elementEnd('div');
    }

    function showSubscribers()
    {
        $profile = $this->user->getSubscribers(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_subscribers',
                                         'class' => 'section'));

        if (Event::handle('StartShowSubscribersMiniList', array($this))) {

            $this->element('h2', null, _('Subscribers'));

            $cnt = 0;

            if (!empty($profile)) {
                $sml = new SubscribersMiniList($profile, $this);
                $cnt = $sml->show();
                if ($cnt == 0) {
                    $this->element('p', null, _('(None)'));
                }
            }

            if ($cnt > PROFILES_PER_MINILIST) {
                $this->elementStart('p');
                $this->element('a', array('href' => common_local_url('subscribers',
                                                                     array('nickname' => $this->profile->nickname)),
                                          'class' => 'more'),
                               _('All subscribers'));
                $this->elementEnd('p');
            }

            Event::handle('EndShowSubscribersMiniList', array($this));
        }

        $this->elementEnd('div');
    }

    function showStatistics()
    {
        $subs_count   = $this->profile->subscriptionCount();
        $subbed_count = $this->profile->subscriberCount();
        $notice_count = $this->profile->noticeCount();
        $group_count  = $this->user->getGroups()->N;
        $age_days     = (time() - strtotime($this->profile->created)) / 86400;
        if ($age_days < 1) {
            // Rather than extrapolating out to a bajillion...
            $age_days = 1;
        }
        $daily_count = round($notice_count / $age_days);

        $this->elementStart('div', array('id' => 'entity_statistics',
                                         'class' => 'section'));

        $this->element('h2', null, _('Statistics'));

        // Other stats...?
        $this->elementStart('dl', 'entity_user-id');
        $this->element('dt', null, _('User ID'));
        $this->element('dd', null, $this->profile->id);
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_member-since');
        $this->element('dt', null, _('Member since'));
        $this->element('dd', null, date('j M Y',
                                        strtotime($this->profile->created)));
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_subscriptions');
        $this->elementStart('dt');
        $this->element('a', array('href' => common_local_url('subscriptions',
                                                             array('nickname' => $this->profile->nickname))),
                       _('Subscriptions'));
        $this->elementEnd('dt');
        $this->element('dd', null, $subs_count);
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_subscribers');
        $this->elementStart('dt');
        $this->element('a', array('href' => common_local_url('subscribers',
                                                             array('nickname' => $this->profile->nickname))),
                       _('Subscribers'));
        $this->elementEnd('dt');
        $this->element('dd', 'subscribers', $subbed_count);
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_groups');
        $this->elementStart('dt');
        $this->element('a', array('href' => common_local_url('usergroups',
                                                             array('nickname' => $this->profile->nickname))),
                       _('Groups'));
        $this->elementEnd('dt');
        $this->element('dd', 'groups', $group_count);
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_notices');
        $this->element('dt', null, _('Notices'));
        $this->element('dd', null, $notice_count);
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_daily_notices');
        // TRANS: Average count of posts made per day since account registration
        $this->element('dt', null, _('Daily average'));
        $this->element('dd', null, $daily_count);
        $this->elementEnd('dl');

        $this->elementEnd('div');
    }

    function showGroups()
    {
        $groups = $this->user->getGroups(0, GROUPS_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_groups',
                                         'class' => 'section'));
        if (Event::handle('StartShowGroupsMiniList', array($this))) {
            $this->element('h2', null, _('Groups'));

            if ($groups) {
                $gml = new GroupMiniList($groups, $this->user, $this);
                $cnt = $gml->show();
                if ($cnt == 0) {
                    $this->element('p', null, _('(None)'));
                }
            }

            if ($cnt > GROUPS_PER_MINILIST) {
                $this->elementStart('p');
                $this->element('a', array('href' => common_local_url('usergroups',
                                                                     array('nickname' => $this->profile->nickname)),
                                          'class' => 'more'),
                               _('All groups'));
                $this->elementEnd('p');
            }

            Event::handle('EndShowGroupsMiniList', array($this));
        }
            $this->elementEnd('div');
    }
}

class SubscribersMiniList extends ProfileMiniList
{
    function newListItem($profile)
    {
        return new SubscribersMiniListItem($profile, $this->action);
    }
}

class SubscribersMiniListItem extends ProfileMiniListItem
{
    function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();
        if (common_config('nofollow', 'subscribers')) {
            $aAttrs['rel'] .= ' nofollow';
        }
        return $aAttrs;
    }
}

