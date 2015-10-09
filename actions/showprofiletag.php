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

class ShowprofiletagAction extends ShowstreamAction
{
    var $notice, $peopletag;

    protected function doStreamPreparation()
    {
        $tag = common_canonical_tag($this->arg('tag'));
        try {
            $this->peopletag = Profile_list::getByPK(array('tagger' => $this->target->getID(), 'tag' => $tag));
        } catch (NoResultException $e) {
            // TRANS: Client error displayed trying to reference a non-existing list.
            throw new ClientException('No such list.');
        }

        if ($this->peopletag->private && !$this->peopletag->getTagger()->sameAs($this->scoped)) {
            // TRANS: Client error displayed trying to reference a non-existing list.
            throw new AuthorizationException('You do not have permission to see this list.');
        }
    }

    public function getStream()
    {
        return new PeopletagNoticeStream($this->peopletag, $this->scoped);
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
                                $this->target->getNickname(),
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
                                $this->target->getNickname()
                          );
        }
    }

    function getFeeds()
    {
        #XXX: make these actually work
        return array(new Feed(Feed::JSON,
                common_local_url(
                    'ApiTimelineList', array(
                        'user' => $this->target->id,
                        'id' => $this->peopletag->id,
                        'format' => 'as'
                    )
                ),
                // TRANS: Feed title.
                // TRANS: %s is tagger's nickname.
                sprintf(_('Feed for friends of %s (Activity Streams JSON)'), $this->target->getNickname())),
                new Feed(Feed::RSS2,
                common_local_url(
                    'ApiTimelineList', array(
                        'user' => $this->target->id,
                        'id' => $this->peopletag->id,
                        'format' => 'rss'
                    )
                ),
                // TRANS: Feed title.
                // TRANS: %s is tagger's nickname.
                sprintf(_('Feed for friends of %s (RSS 2.0)'), $this->target->getNickname())),
            new Feed(Feed::ATOM,
                common_local_url(
                    'ApiTimelineList', array(
                        'user' => $this->target->id,
                        'id' => $this->peopletag->id,
                        'format' => 'atom'
                    )
                ),
                // TRANS: Feed title.
                // TRANS: %1$s is a list, %2$s is tagger's nickname.
                sprintf(_('Feed for %1$s list by %2$s (Atom)'),
                            $this->peopletag->tag, $this->target->getNickname()
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
                           $this->target->getNickname()) . ' ';

        if (common_logged_in()) {
            if ($this->target->sameAs($this->scoped)) {
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

    protected function showContent()
    {
        $this->showPeopletag();
        parent::showContent();
    }

    function showPeopletag()
    {
        $tag = new Peopletag($this->peopletag, $this->scoped, $this);
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
                                    'nickname' => $this->target->getNickname())
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
                                                                     array('nickname' => $this->target->getNickname(),
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
