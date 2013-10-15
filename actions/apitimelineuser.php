<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a user's timeline
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
 * @category  API
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Jeffery To <jeffery.to@gmail.com>
 * @author    mac65 <mac65@mac65.com>
 * @author    Mike Cochrane <mikec@mikenz.geek.nz>
 * @author    Robin Millette <robin@millette.info>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Returns the most recent notices (default 20) posted by the authenticating
 * user. Another user's timeline can be requested via the id parameter. This
 * is the API equivalent of the user profile web page.
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   mac65 <mac65@mac65.com>
 * @author   Mike Cochrane <mikec@mikenz.geek.nz>
 * @author   Robin Millette <robin@millette.info>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiTimelineUserAction extends ApiBareAuthAction
{
    var $notices = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    protected function prepare($args)
    {
        parent::prepare($args);

        $this->target = $this->getTargetProfile($this->arg('id'));

        if (!($this->target instanceof Profile)) {
            // TRANS: Client error displayed requesting most recent notices for a non-existing user.
            $this->clientError(_('No such user.'), 404);
        }

        $this->notices = $this->getNotices();

        return true;
    }

    /**
     * Handle the request
     *
     * Just show the notices
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();

        if ($this->isPost()) {
            $this->handlePost();
        } else {
            $this->showTimeline();
        }
    }

    /**
     * Show the timeline of notices
     *
     * @return void
     */
    function showTimeline()
    {
        // We'll use the shared params from the Atom stub
        // for other feed types.
        $atom = new AtomUserNoticeFeed($this->target->getUser(), $this->auth_user);

        $link = common_local_url(
                                 'showstream',
                                 array('nickname' => $this->target->nickname)
                                 );

        $self = $this->getSelfUri();

        // FriendFeed's SUP protocol
        // Also added RSS and Atom feeds

        $suplink = common_local_url('sup', null, null, $this->target->id);
        header('X-SUP-ID: ' . $suplink);

        switch($this->format) {
        case 'xml':
            $this->showXmlTimeline($this->notices);
            break;
        case 'rss':
            $this->showRssTimeline(
                                   $this->notices,
                                   $atom->title,
                                   $link,
                                   $atom->subtitle,
                                   $suplink,
                                   $atom->logo,
                                   $self
                                   );
            break;
        case 'atom':
            header('Content-Type: application/atom+xml; charset=utf-8');

            $atom->setId($self);
            $atom->setSelfLink($self);

            // Add navigation links: next, prev, first
            // Note: we use IDs rather than pages for navigation; page boundaries
            // change too quickly!

            if (!empty($this->next_id)) {
                $nextUrl = common_local_url('ApiTimelineUser',
                                            array('format' => 'atom',
                                                  'id' => $this->target->id),
                                            array('max_id' => $this->next_id));

                $atom->addLink($nextUrl,
                               array('rel' => 'next',
                                     'type' => 'application/atom+xml'));
            }

            if (($this->page > 1 || !empty($this->max_id)) && !empty($this->notices)) {

                $lastNotice = $this->notices[0];
                $lastId     = $lastNotice->id;

                $prevUrl = common_local_url('ApiTimelineUser',
                                            array('format' => 'atom',
                                                  'id' => $this->target->id),
                                            array('since_id' => $lastId));

                $atom->addLink($prevUrl,
                               array('rel' => 'prev',
                                     'type' => 'application/atom+xml'));
            }

            if ($this->page > 1 || !empty($this->since_id) || !empty($this->max_id)) {

                $firstUrl = common_local_url('ApiTimelineUser',
                                            array('format' => 'atom',
                                                  'id' => $this->target->id));

                $atom->addLink($firstUrl,
                               array('rel' => 'first',
                                     'type' => 'application/atom+xml'));

            }

            $atom->addEntryFromNotices($this->notices);
            $this->raw($atom->getString());

            break;
        case 'json':
            $this->showJsonTimeline($this->notices);
            break;
        case 'as':
            header('Content-Type: ' . ActivityStreamJSONDocument::CONTENT_TYPE);
            $doc = new ActivityStreamJSONDocument($this->auth_user);
            $doc->setTitle($atom->title);
            $doc->addLink($link, 'alternate', 'text/html');
            $doc->addItemsFromNotices($this->notices);

            // XXX: Add paging extension?

            $this->raw($doc->asString());
            break;
        default:
            // TRANS: Client error displayed when coming across a non-supported API method.
            $this->clientError(_('API method not found.'), $code = 404);
        }
    }

    /**
     * Get notices
     *
     * @return array notices
     */
    function getNotices()
    {
        $notices = array();

        $notice = $this->target->getNotices(($this->page-1) * $this->count,
                                          $this->count + 1,
                                          $this->since_id,
                                          $this->max_id,
                                          $this->scoped);

        while ($notice->fetch()) {
            if (count($notices) < $this->count) {
                $notices[] = clone($notice);
            } else {
                $this->next_id = $notice->id;
                break;
            }
        }

        return $notices;
    }

    /**
     * We expose AtomPub here, so non-GET/HEAD reqs must be read/write.
     *
     * @param array $args other arguments
     *
     * @return boolean true
     */

    function isReadOnly($args)
    {
        return ($_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'HEAD');
    }

    /**
     * When was this feed last modified?
     *
     * @return string datestamp of the latest notice in the stream
     */
    function lastModified()
    {
        if (!empty($this->notices) && (count($this->notices) > 0)) {
            return strtotime($this->notices[0]->created);
        }

        return null;
    }

    /**
     * An entity tag for this stream
     *
     * Returns an Etag based on the action name, language, user ID, and
     * timestamps of the first and last notice in the timeline
     *
     * @return string etag
     */
    function etag()
    {
        if (!empty($this->notices) && (count($this->notices) > 0)) {
            $last = count($this->notices) - 1;

            return '"' . implode(
                                 ':',
                                 array($this->arg('action'),
                                       common_user_cache_hash($this->auth_user),
                                       common_language(),
                                       $this->target->id,
                                       strtotime($this->notices[0]->created),
                                       strtotime($this->notices[$last]->created))
                                 )
              . '"';
        }

        return null;
    }

    function handlePost()
    {
        if (empty($this->auth_user) ||
            $this->auth_user->id != $this->target->id) {
            // TRANS: Client error displayed trying to add a notice to another user's timeline.
            $this->clientError(_('Only the user can add to their own timeline.'));
        }

        // Only handle posts for Atom
        if ($this->format != 'atom') {
            // TRANS: Client error displayed when using another format than AtomPub.
            $this->clientError(_('Only accept AtomPub for Atom feeds.'));
        }

        $xml = trim(file_get_contents('php://input'));
        if (empty($xml)) {
            // TRANS: Client error displayed attempting to post an empty API notice.
            $this->clientError(_('Atom post must not be empty.'));
        }

        $old = error_reporting(error_reporting() & ~(E_WARNING | E_NOTICE));
        $dom = new DOMDocument();
        $ok = $dom->loadXML($xml);
        error_reporting($old);
        if (!$ok) {
            // TRANS: Client error displayed attempting to post an API that is not well-formed XML.
            $this->clientError(_('Atom post must be well-formed XML.'));
        }

        if ($dom->documentElement->namespaceURI != Activity::ATOM ||
            $dom->documentElement->localName != 'entry') {
            // TRANS: Client error displayed when not using an Atom entry.
            $this->clientError(_('Atom post must be an Atom entry.'));
        }

        $activity = new Activity($dom->documentElement);

        $saved = null;

        if (Event::handle('StartAtomPubNewActivity', array(&$activity, $this->target->getUser(), &$saved))) {
            if ($activity->verb != ActivityVerb::POST) {
                // TRANS: Client error displayed when not using the POST verb. Do not translate POST.
                $this->clientError(_('Can only handle POST activities.'));
            }

            $note = $activity->objects[0];

            if (!in_array($note->type, array(ActivityObject::NOTE,
                                             ActivityObject::BLOGENTRY,
                                             ActivityObject::STATUS))) {
                // TRANS: Client error displayed when using an unsupported activity object type.
                // TRANS: %s is the unsupported activity object type.
                $this->clientError(sprintf(_('Cannot handle activity object type "%s".'),
                                             $note->type));
            }

            $saved = $this->postNote($activity);

            Event::handle('EndAtomPubNewActivity', array($activity, $this->target->getUser(), $saved));
        }

        if (!empty($saved)) {
            header('HTTP/1.1 201 Created');
            header("Location: " . common_local_url('ApiStatusesShow', array('id' => $saved->id,
                                                                            'format' => 'atom')));
            $this->showSingleAtomStatus($saved);
        }
    }

    function postNote($activity)
    {
        $note = $activity->objects[0];

        // Use summary as fallback for content

        if (!empty($note->content)) {
            $sourceContent = $note->content;
        } else if (!empty($note->summary)) {
            $sourceContent = $note->summary;
        } else if (!empty($note->title)) {
            $sourceContent = $note->title;
        } else {
            // @fixme fetch from $sourceUrl?
            // TRANS: Client error displayed when posting a notice without content through the API.
            // TRANS: %d is the notice ID (number).
            $this->clientError(sprintf(_('No content for notice %d.'), $note->id));
        }

        // Get (safe!) HTML and text versions of the content

        $rendered = $this->purify($sourceContent);
        $content = html_entity_decode(strip_tags($rendered), ENT_QUOTES, 'UTF-8');

        $shortened = $this->auth_user->shortenLinks($content);

        $options = array('is_local' => Notice::LOCAL_PUBLIC,
                         'rendered' => $rendered,
                         'replies' => array(),
                         'groups' => array(),
                         'tags' => array(),
                         'urls' => array());

        // accept remote URI (not necessarily a good idea)

        common_debug("Note ID is {$note->id}");

        if (!empty($note->id)) {
            $notice = Notice::getKV('uri', trim($note->id));

            if (!empty($notice)) {
                // TRANS: Client error displayed when using another format than AtomPub.
                // TRANS: %s is the notice URI.
                $this->clientError(sprintf(_('Notice with URI "%s" already exists.'), $note->id));
            }
            common_log(LOG_NOTICE, "Saving client-supplied notice URI '$note->id'");
            $options['uri'] = $note->id;
        }

        // accept remote create time (also maybe not such a good idea)

        if (!empty($activity->time)) {
            common_log(LOG_NOTICE, "Saving client-supplied create time {$activity->time}");
            $options['created'] = common_sql_date($activity->time);
        }

        // Check for optional attributes...

        if (!empty($activity->context)) {

            foreach ($activity->context->attention as $uri) {

                $profile = Profile::fromURI($uri);

                if (!empty($profile)) {
                    $options['replies'][] = $uri;
                } else {
                    $group = User_group::getKV('uri', $uri);
                    if (!empty($group)) {
                        $options['groups'][] = $group->id;
                    } else {
                        // @fixme: hook for discovery here
                        common_log(LOG_WARNING, sprintf('AtomPub post with unknown attention URI %s', $uri));
                    }
                }
            }

            // Maintain direct reply associations
            // @fixme what about conversation ID?

            if (!empty($activity->context->replyToID)) {
                $orig = Notice::getKV('uri',
                                          $activity->context->replyToID);
                if (!empty($orig)) {
                    $options['reply_to'] = $orig->id;
                }
            }

            $location = $activity->context->location;

            if ($location) {
                $options['lat'] = $location->lat;
                $options['lon'] = $location->lon;
                if ($location->location_id) {
                    $options['location_ns'] = $location->location_ns;
                    $options['location_id'] = $location->location_id;
                }
            }
        }

        // Atom categories <-> hashtags

        foreach ($activity->categories as $cat) {
            if ($cat->term) {
                $term = common_canonical_tag($cat->term);
                if ($term) {
                    $options['tags'][] = $term;
                }
            }
        }

        // Atom enclosures -> attachment URLs
        foreach ($activity->enclosures as $href) {
            // @fixme save these locally or....?
            $options['urls'][] = $href;
        }

        $saved = Notice::saveNew($this->target->id,
                                 $content,
                                 'atompub', // TODO: deal with this
                                 $options);

        return $saved;
    }

    function purify($content)
    {
        require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';

        $config = array('safe' => 1,
                        'deny_attribute' => 'id,style,on*');
        return htmLawed($content, $config);
    }
}
