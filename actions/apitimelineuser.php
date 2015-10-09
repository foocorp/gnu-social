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

if (!defined('GNUSOCIAL')) { exit(1); }

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

    var $next_id = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $this->target = $this->getTargetProfile($this->arg('id'));

        if (!($this->target instanceof Profile)) {
            // TRANS: Client error displayed requesting most recent notices for a non-existing user.
            $this->clientError(_('No such user.'), 404);
        }

        if (!$this->target->isLocal()) {
            $this->serverError(_('Remote user timelines are not available here yet.'), 501);
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
        $atom = new AtomUserNoticeFeed($this->target->getUser(), $this->scoped);

        $link = common_local_url(
                                 'showstream',
                                 array('nickname' => $this->target->getNickname())
                                 );

        $self = $this->getSelfUri();

        // FriendFeed's SUP protocol
        // Also added RSS and Atom feeds

        $suplink = common_local_url('sup', null, null, $this->target->getID());
        header('X-SUP-ID: ' . $suplink);


        // paging links
        $nextUrl = !empty($this->next_id)
                    ? common_local_url('ApiTimelineUser',
                                    array('format' => $this->format,
                                          'id' => $this->target->getID()),
                                    array('max_id' => $this->next_id))
                    : null;

        $prevExtra = array();
        if (!empty($this->notices)) {
            assert($this->notices[0] instanceof Notice);
            $prevExtra['since_id'] = $this->notices[0]->id;
        }

        $prevUrl = common_local_url('ApiTimelineUser',
                                    array('format' => $this->format,
                                          'id' => $this->target->getID()),
                                    $prevExtra);
        $firstUrl = common_local_url('ApiTimelineUser',
                                    array('format' => $this->format,
                                          'id' => $this->target->getID()));

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
                $atom->addLink($nextUrl,
                               array('rel' => 'next',
                                     'type' => 'application/atom+xml'));
            }

            if (($this->page > 1 || !empty($this->max_id)) && !empty($this->notices)) {
                $atom->addLink($prevUrl,
                               array('rel' => 'prev',
                                     'type' => 'application/atom+xml'));
            }

            if ($this->page > 1 || !empty($this->since_id) || !empty($this->max_id)) {
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
            $doc = new ActivityStreamJSONDocument($this->scoped);
            $doc->setTitle($atom->title);
            $doc->addLink($link, 'alternate', 'text/html');
            $doc->addItemsFromNotices($this->notices);

            if (!empty($this->next_id)) {
                $doc->addLink($nextUrl,
                               array('rel' => 'next',
                                     'type' => ActivityStreamJSONDocument::CONTENT_TYPE));
            }

            if (($this->page > 1 || !empty($this->max_id)) && !empty($this->notices)) {
                $doc->addLink($prevUrl,
                               array('rel' => 'prev',
                                     'type' => ActivityStreamJSONDocument::CONTENT_TYPE));
            }

            if ($this->page > 1 || !empty($this->since_id) || !empty($this->max_id)) {
                $doc->addLink($firstUrl,
                               array('rel' => 'first',
                                     'type' => ActivityStreamJSONDocument::CONTENT_TYPE));
            }

            $this->raw($doc->asString());
            break;
        default:
            // TRANS: Client error displayed when coming across a non-supported API method.
            $this->clientError(_('API method not found.'), 404);
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
                                       common_user_cache_hash($this->scoped),
                                       common_language(),
                                       $this->target->getID(),
                                       strtotime($this->notices[0]->created),
                                       strtotime($this->notices[$last]->created))
                                 )
              . '"';
        }

        return null;
    }

    function handlePost()
    {
        if (!$this->scoped instanceof Profile ||
                !$this->target->sameAs($this->scoped)) {
            // TRANS: Client error displayed trying to add a notice to another user's timeline.
            $this->clientError(_('Only the user can add to their own timeline.'), 403);
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

        common_debug('AtomPub: Ignoring right now, but this POST was made to collection: '.$activity->id);

        // Reset activity data so we can handle it in the same functions as with OStatus
        // because we don't let clients set their own UUIDs... Not sure what AtomPub thinks
        // about that though.
        $activity->id = null;
        $activity->actor = null;    // not used anyway, we use $this->target
        $activity->objects[0]->id = null;

        $stored = null;
        if (Event::handle('StartAtomPubNewActivity', array($activity, $this->target, &$stored))) {
            // TRANS: Client error displayed when not using the POST verb. Do not translate POST.
            throw new ClientException(_('Could not handle this Atom Activity.'));
        }
        if (!$stored instanceof Notice) {
            throw new ServerException('Server did not create a Notice object from handled AtomPub activity.');
        }
        Event::handle('EndAtomPubNewActivity', array($activity, $this->target, $stored));

        header('HTTP/1.1 201 Created');
        header("Location: " . common_local_url('ApiStatusesShow', array('id' => $stored->getID(),
                                                                        'format' => 'atom')));
        $this->showSingleAtomStatus($stored);
    }
}
