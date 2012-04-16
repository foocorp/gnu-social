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
 * @copyright 2008-2011 StatusNet, Inc.
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
class ProfileAction extends Action
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
            // TRANS: Client error displayed when calling a profile action without specifying a user.
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->profile = $this->user->getProfile();

        if (!$this->profile) {
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $user = common_current_user();

        if ($this->profile->hasRole(Profile_role::SILENCED) &&
            (empty($user) || !$user->hasRight(Right::SILENCEUSER))) {
            throw new ClientException(_('This profile has been silenced by site moderators'), 403);
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
        $this->showLists();
        $this->showStatistics();
    }

    /**
     * Convenience function for common pattern of links to subscription/groups sections.
     *
     * @param string $actionClass
     * @param string $title
     * @param string $cssClass
     */
    private function statsSectionLink($actionClass, $title, $cssClass='')
    {
        $this->element('a', array('href' => common_local_url($actionClass,
                                                             array('nickname' => $this->profile->nickname)),
                                  'class' => $cssClass),
                       $title);
    }

    function showSubscriptions()
    {
        $profile = $this->profile->getSubscriptions(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_subscriptions',
                                         'class' => 'section'));
        if (Event::handle('StartShowSubscriptionsMiniList', array($this))) {
            $this->elementStart('h2');
            // TRANS: H2 text for user subscription statistics.
            $this->statsSectionLink('subscriptions', _('Following'));
            $this->text(' ');
            $this->text($this->profile->subscriptionCount());
            $this->elementEnd('h2');

            $cnt = 0;

            if (!empty($profile)) {
                $pml = new ProfileMiniList($profile, $this);
                $cnt = $pml->show();
                if ($cnt == 0) {
                    // TRANS: Text for user subscription statistics if the user has no subscriptions.
                    $this->element('p', null, _('(None)'));
                }
            }

            Event::handle('EndShowSubscriptionsMiniList', array($this));
        }
        $this->elementEnd('div');
    }

    function showSubscribers()
    {
        $profile = $this->profile->getSubscribers(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_subscribers',
                                         'class' => 'section'));

        if (Event::handle('StartShowSubscribersMiniList', array($this))) {

            $this->elementStart('h2');
            // TRANS: H2 text for user subscriber statistics.
            $this->statsSectionLink('subscribers', _('Followers'));
            $this->text(' ');
            $this->text($this->profile->subscriberCount());
            $this->elementEnd('h2');

            $cnt = 0;

            if (!empty($profile)) {
                $sml = new SubscribersMiniList($profile, $this);
                $cnt = $sml->show();
                if ($cnt == 0) {
                    // TRANS: Text for user subscriber statistics if user has no subscribers.
                    $this->element('p', null, _('(None)'));
                }
            }

            Event::handle('EndShowSubscribersMiniList', array($this));
        }

        $this->elementEnd('div');
    }

    function showStatistics()
    {
        $notice_count = $this->profile->noticeCount();
        $age_days     = (time() - strtotime($this->profile->created)) / 86400;
        if ($age_days < 1) {
            // Rather than extrapolating out to a bajillion...
            $age_days = 1;
        }
        $daily_count = round($notice_count / $age_days);

        $this->elementStart('div', array('id' => 'entity_statistics',
                                         'class' => 'section'));

        // TRANS: H2 text for user statistics.
        $this->element('h2', null, _('Statistics'));

        $profile = $this->profile;
        $actionParams = array('nickname' => $profile->nickname);
        $stats = array(
            array(
                'id' => 'user-id',
                // TRANS: Label for user statistics.
                'label' => _('User ID'),
                'value' => $profile->id,
            ),
            array(
                'id' => 'member-since',
                // TRANS: Label for user statistics.
                'label' => _('Member since'),
                'value' => date('j M Y', strtotime($profile->created))
            ),
            array(
                'id' => 'notices',
                // TRANS: Label for user statistics.
                'label' => _('Notices'),
                'value' => $notice_count,
            ),
            array(
                'id' => 'daily_notices',
                // TRANS: Label for user statistics.
                // TRANS: Average count of posts made per day since account registration.
                'label' => _('Daily average'),
                'value' => $daily_count
            )
        );

        // Give plugins a chance to add stats entries
        Event::handle('ProfileStats', array($profile, &$stats));

        foreach ($stats as $row) {
            $this->showStatsRow($row);
        }
        $this->elementEnd('div');
    }

    private function showStatsRow($row)
    {
        $this->elementStart('dl', 'entity_' . $row['id']);
        $this->elementStart('dt');
        if (!empty($row['link'])) {
            $this->element('a', array('href' => $row['link']), $row['label']);
        } else {
            $this->text($row['label']);
        }
        $this->elementEnd('dt');
        $this->element('dd', null, $row['value']);
        $this->elementEnd('dl');
    }

    function showGroups()
    {
        $groups = $this->profile->getGroups(0, GROUPS_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_groups',
                                         'class' => 'section'));
        if (Event::handle('StartShowGroupsMiniList', array($this))) {
            $this->elementStart('h2');
            // TRANS: H2 text for user group membership statistics.
            $this->statsSectionLink('usergroups', _('Groups'));
            $this->text(' ');
            $this->text($this->profile->getGroups(0, null)->N);
            $this->elementEnd('h2');

            if ($groups) {
                $gml = new GroupMiniList($groups, $this->profile, $this);
                $cnt = $gml->show();
                if ($cnt == 0) {
                    // TRANS: Text for user user group membership statistics if user is not a member of any group.
                    $this->element('p', null, _('(None)'));
                }
            }

            Event::handle('EndShowGroupsMiniList', array($this));
        }
            $this->elementEnd('div');
    }

    function showLists()
    {
        $cur = common_current_user();

        $lists = $this->profile->getLists($cur);

        if ($lists->N > 0) {
            $this->elementStart('div', array('id' => 'entity_lists',
                                             'class' => 'section'));

            if (Event::handle('StartShowListsMiniList', array($this))) {

                $url = common_local_url('peopletagsbyuser',
                                        array('nickname' => $this->profile->nickname));

                $this->elementStart('h2');
                $this->element('a',
                               array('href' => $url),
                               // TRANS: H2 text for user list membership statistics.
                               _('Lists'));
                $this->text(' ');
                $this->text($lists->N);
                $this->elementEnd('h2');

                $this->elementStart('ul');


                $first = true;

                while ($lists->fetch()) {
                    if (!empty($lists->mainpage)) {
                        $url = $lists->mainpage;
                    } else {
                        $url = common_local_url('showprofiletag',
                                                array('tagger' => $this->profile->nickname,
                                                      'tag'    => $lists->tag));
                    }
                    if (!$first) {
                        $this->text(', ');
                    } else {
                        $first = false;
                    }

                    $this->element('a', array('href' => $url),
                                   $lists->tag);
                }

                $this->elementEnd('ul');

                Event::handle('EndShowListsMiniList', array($this));
            }
            $this->elementEnd('div');
        }
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
