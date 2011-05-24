<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Adapter to show bookmarks in a nicer way
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
 * @category  Bookmark
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
 * An adapter to show bookmarks in a nicer way
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class BookmarkListItem extends NoticeListItemAdapter
{
    function showNotice()
    {
        $this->nli->out->elementStart('div', 'entry-title');
        $this->nli->showAuthor();
        $this->showContent();
        $this->nli->out->elementEnd('div');
    }

    function showContent()
    {
        $notice = $this->nli->notice;
        $out    = $this->nli->out;

        $out->elementStart('p', array('class' => 'entry-content'));

        $nb = Bookmark::getByNotice($notice);

        $profile = $notice->getProfile();

        $atts = $notice->attachments();

        if (count($atts) < 1) {
            // Something wrong; let default code deal with it.
            // TRANS: Exception thrown when a bookmark has no attachments.
            // TRANS: %1$s is a bookmark ID, %2$s is a notice ID (number).
            throw new Exception(sprintf(_m('Bookmark %1$s (notice %2$d) has no attachments.'),
                                        $nb->id,
                                        $notice->id));
        }

        $att = $atts[0];

        $out->elementStart('h3');
        $out->element('a',
                      array('href' => $att->url,
                            'class' => 'bookmark-title'),
                      $nb->title);
        $out->elementEnd('h3');

        // Replies look like "for:" tags

        $replies = $notice->getReplies();
        $tags = $notice->getTags();

        if (!empty($replies) || !empty($tags)) {

            $out->elementStart('ul', array('class' => 'bookmark-tags'));

            foreach ($replies as $reply) {
                $other = Profile::staticGet('id', $reply);
                if (!empty($other)) {
                    $out->elementStart('li');
                    $out->element('a', array('rel' => 'tag',
                                             'href' => $other->profileurl,
                                             'title' => $other->getBestName()),
                                  sprintf('for:%s', $other->nickname));
                    $out->elementEnd('li');
                    $out->text(' ');
                }
            }

            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $out->elementStart('li');
                    $out->element('a',
                                  array('rel' => 'tag',
                                        'href' => Notice_tag::url($tag)),
                                  $tag);
                    $out->elementEnd('li');
                    $out->text(' ');
                }
            }

            $out->elementEnd('ul');
        }

        if (!empty($nb->description)) {
            $out->element('p',
                          array('class' => 'bookmark-description'),
                          $nb->description);
        }

        $out->elementEnd('p');
    }
}
