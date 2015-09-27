<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 * @category Actions
 * @package  Actions
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 * @link     http://status.net
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class ShowprofiletagAction extends Action
{
    var $notice, $tagger, $peopletag, $userProfile;

    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);

        if (common_config('singleuser', 'enabled')) {
            $tagger_arg = User::singleUserNickname();
        } else {
            $tagger_arg = $this->arg('tagger');
        }
        $tag_arg = $this->arg('tag');
        $tagger = common_canonical_nickname($tagger_arg);
        $tag = common_canonical_tag($tag_arg);

        // Permanent redirect on non-canonical nickname

        if ($tagger_arg != $tagger || $tag_arg != $tag) {
            $args = array('tagger' => $nickname, 'tag' => $tag);
            if ($this->page != 1) {
                $args['page'] = $this->page;
            }
            common_redirect(common_local_url('showprofiletag', $args), 301);
        }

        if (!$tagger) {
            // TRANS: Client error displayed when a tagger is expected but not provided.
            $this->clientError(_('No tagger.'), 404);
        }

        $user = User::getKV('nickname', $tagger);

        if (!$user) {
            // TRANS: Client error displayed trying to perform an action related to a non-existing user.
            $this->clientError(_('No such user.'), 404);
        }

        $this->tagger = $user->getProfile();
        $this->peopletag = Profile_list::pkeyGet(array('tagger' => $user->id, 'tag' => $tag));

        $current = common_current_user();
        $can_see = !empty($this->peopletag) && (!$this->peopletag->private ||
                   ($this->peopletag->private && $this->peopletag->tagger === $current->id));

        if (!$can_see) {
            // TRANS: Client error displayed trying to reference a non-existing list.
            $this->clientError(_('No such list.'), 404);
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;
        $this->userProfile = Profile::current();

        $stream = new PeopletagNoticeStream($this->peopletag, $this->userProfile);

        $this->notice = $stream->getNotices(($this->page-1)*NOTICES_PER_PAGE,
                                            NOTICES_PER_PAGE + 1);

        if ($this->page > 1 && $this->notice->N == 0) {
            // TRANS: Client error when page not found (404).
            $this->clientError(_('No such page.'), 404);
        }

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if (!$this->peopletag) {
            // TRANS: Client error displayed trying to perform an action related to a non-existing user.
            $this->clientError(_('No such user.'));
        }

        $this->showPage();
    }

    function title()
    {
        if ($this->page > 1) {
            if($this->peopletag->private) {
                // TRANS: Title for private list timeline.
                // TRANS: %1$s is a list, %2$s is a page number.
                return sprintf(_('Private timeline for %1$s list by you, page %2$d'),
                                $this->peopletag->tag, $this->page);
            }

            $current = common_current_user();
            if (!empty($current) && $current->id == $this->peopletag->tagger) {
                // TRANS: Title for public list timeline where the viewer is the tagger.
                // TRANS: %1$s is a list, %2$s is a page number.
                return sprintf(_('Timeline for %1$s list by you, page %2$d'),
                                $this->peopletag->tag, $this->page);
            }

            // TRANS: Title for private list timeline.
            // TRANS: %1$s is a list, %2$s is the tagger's nickname, %3$d is a page number.
            return sprintf(_('Timeline for %1$s list by %2$s, page %3$d'),
                                $this->peopletag->tag,
                                $this->tagger->nickname,
                                $this->page
                          );
        } else {
            if($this->peopletag->private) {
                // TRANS: Title for private list timeline.
                // TRANS: %s is a list.
                return sprintf(_('Private timeline of %s list by you'),
                                $this->peopletag->tag);
            }

            $current = common_current_user();
            if (!empty($current) && $current->id == $this->peopletag->tagger) {
                // TRANS: Title for public list timeline where the viewer is the tagger.
                // TRANS: %s is a list.
                return sprintf(_('Timeline for %s list by you'),
                                $this->peopletag->tag);
            }

            // TRANS: Title for private list timeline.
            // TRANS: %1$s is a list, %2$s is the tagger's nickname.
            return sprintf(_('Timeline for %1$s list by %2$s'),
                                $this->peopletag->tag,
                                $this->tagger->nickname
                          );
        }
    }

    function getFeeds()
    {
        #XXX: make these actually work
        return array(new Feed(Feed::JSON,
                common_local_url(
                    'ApiTimelineList', array(
                        'user' => $this->tagger->id,
                        'id' => $this->peopletag->id,
                        'format' => 'as'
                    )
                ),
                // TRANS: Feed title.
                // TRANS: %s is tagger's nickname.
                sprintf(_('Feed for friends of %s (Activity Streams JSON)'), $this->tagger->nickname)),
                new Feed(Feed::RSS2,
                common_local_url(
                    'ApiTimelineList', array(
                        'user' => $this->tagger->id,
                        'id' => $this->peopletag->id,
                        'format' => 'rss'
                    )
                ),
                // TRANS: Feed title.
                // TRANS: %s is tagger's nickname.
                sprintf(_('Feed for friends of %s (RSS 2.0)'), $this->tagger->nickname)),
            new Feed(Feed::ATOM,
                common_local_url(
                    'ApiTimelineList', array(
                        'user' => $this->tagger->id,
                        'id' => $this->peopletag->id,
                        'format' => 'atom'
                    )
                ),
                // TRANS: Feed title.
                // TRANS: %1$s is a list, %2$s is tagger's nickname.
                sprintf(_('Feed for %1$s list by %2$s (Atom)'),
                            $this->peopletag->tag, $this->tagger->nickname
                       )
              )
        );
    }

    function showObjectNav()
    {
        $nav = new PeopletagGroupNav($this);
        $nav->show();
    }

    function showEmptyListMessage()
    {
        // TRANS: Empty list message for list timeline.
        // TRANS: %1$s is a list, %2$s is a tagger's nickname.
        $message = sprintf(_('This is the timeline for %1$s list by %2$s but no one has posted anything yet.'),
                           $this->peopletag->tag,
                           $this->tagger->nickname) . ' ';

        if (common_logged_in()) {
            $current_user = common_current_user();
            if ($this->tagger->id == $current_user->id) {
                // TRANS: Additional empty list message for list timeline for currently logged in user tagged tags.
                $message .= _('Try tagging more people.');
            }
        } else {
            // TRANS: Additional empty list message for list timeline.
            // TRANS: This message contains Markdown links in the form [description](link).
            $message .= _('Why not [register an account](%%%%action.register%%%%) and start following this timeline!');
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    function showContent()
    {
        $this->showPeopletag();
        $this->showNotices();
    }

    function showPeopletag()
    {
        $cur = common_current_user();
        $tag = new Peopletag($this->peopletag, $cur, $this);
        $tag->show();
    }

    function showNotices()
    {
        if (Event::handle('StartShowProfileTagContent', array($this))) {
            $nl = new PrimaryNoticeList($this->notice, $this, array('show_n'=>NOTICES_PER_PAGE));

            $cnt = $nl->show();

            if (0 == $cnt) {
                $this->showEmptyListMessage();
            }

            $this->pagination($this->page > 1,
                              $cnt > NOTICES_PER_PAGE,
                              $this->page,
                              'showprofiletag',
                              array('tag' => $this->peopletag->tag,
                                    'tagger' => $this->tagger->nickname)
            );

            Event::handle('EndShowProfileTagContent', array($this));
        }
    }

    function showSections()
    {
        $this->showTagged();
        if (!$this->peopletag->private) {
            $this->showSubscribers();
        }
        # $this->showStatistics();
    }

    function showPageTitle()
    {
        $this->element('h1', null, $this->title());
    }

    function showTagged()
    {
        $profile = $this->peopletag->getTagged(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_tagged',
                                         'class' => 'section'));
        if (Event::handle('StartShowTaggedProfilesMiniList', array($this))) {
            $title = '';

            // TRANS: Header on show list page.
            $this->element('h2', null, _('Listed'));

            $cnt = 0;

            if (!empty($profile)) {
                $pml = new ProfileMiniList($profile, $this);
                $cnt = $pml->show();
                if ($cnt == 0) {
                    // TRANS: Content of "Listed" page if there are no listed users.
                    $this->element('p', null, _('(None)'));
                }
            }

            if ($cnt > PROFILES_PER_MINILIST) {
                $this->elementStart('p');
                $this->element('a', array('href' => common_local_url('taggedprofiles',
                                                                     array('nickname' => $this->tagger->nickname,
                                                                           'profiletag' => $this->peopletag->tag)),
                                          'class' => 'more'),
                               // TRANS: Link for more "People in list x by a user"
                               // TRANS: if there are more than the mini list's maximum.
                               _('Show all'));
                $this->elementEnd('p');
            }

            Event::handle('EndShowTaggedProfilesMiniList', array($this));
        }
        $this->elementEnd('div');
    }

    function showSubscribers()
    {
        $profile = $this->peopletag->getSubscribers(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_subscribers',
                                         'class' => 'section'));
        if (Event::handle('StartShowProfileTagSubscribersMiniList', array($this))) {
            // TRANS: Header for tag subscribers.
            $this->element('h2', null, _('Subscribers'));

            $cnt = 0;

            if (!empty($profile)) {
                $pml = new ProfileMiniList($profile, $this);
                $cnt = $pml->show();
                if ($cnt == 0) {
                    // TRANS: Content of "People following tag x" if there are no subscribed users.
                    $this->element('p', null, _('(None)'));
                }
            }

            // FIXME: link to full list

            Event::handle('EndShowProfileTagSubscribersMiniList', array($this));
        }
        $this->elementEnd('div');
    }
}
