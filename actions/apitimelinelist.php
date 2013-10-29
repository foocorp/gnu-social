<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a list's notices
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/atomlistnoticefeed.php';

/**
 * Returns the most recent notices (default 20) posted to the list specified by ID
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiTimelineListAction extends ApiPrivateAuthAction
{

    var $list   = null;
    var $notices = array();
    var $next_cursor = 0;
    var $prev_cursor = 0;
    var $cursor = -1;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $this->cursor = (int) $this->arg('cursor', -1);
        $this->list = $this->getTargetList($this->arg('user'), $this->arg('id'));

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

        if (empty($this->list)) {
            // TRANS: Client error displayed trying to perform an action related to a non-existing list.
            $this->clientError(_('List not found.'), 404);
        }

        $this->getNotices();
        $this->showTimeline();
    }

    /**
     * Show the timeline of notices
     *
     * @return void
     */
    function showTimeline()
    {
        // We'll pull common formatting out of this for other formats
        $atom = new AtomListNoticeFeed($this->list, $this->auth_user);

        $self = $this->getSelfUri();

        switch($this->format) {
        case 'xml':
            $this->initDocument('xml');
            $this->elementStart('statuses_list',
                    array('xmlns:statusnet' => 'http://status.net/schema/api/1/'));
            $this->elementStart('statuses', array('type' => 'array'));

            foreach ($this->notices as $n) {
                $twitter_status = $this->twitterStatusArray($n);
                $this->showTwitterXmlStatus($twitter_status);
            }

            $this->elementEnd('statuses');
            $this->element('next_cursor', null, $this->next_cursor);
            $this->element('previous_cursor', null, $this->prev_cursor);
            $this->elementEnd('statuses_list');
            $this->endDocument('xml');
            break;
        case 'rss':
            $this->showRssTimeline(
                $this->notices,
                $atom->title,
                $this->list->getUri(),
                $atom->subtitle,
                null,
                $atom->logo,
                $self
            );
            break;
        case 'atom':
            header('Content-Type: application/atom+xml; charset=utf-8');

            try {
                $atom->setId($self);
                $atom->setSelfLink($self);
                $atom->addEntryFromNotices($this->notices);
                $this->raw($atom->getString());
            } catch (Atom10FeedException $e) {
                // TRANS: Server error displayed whe trying to get a timeline fails.
                // TRANS: %s is the error message.
                $this->serverError(sprintf(_('Could not generate feed for list - %s'), $e->getMessage()));
            }

            break;
        case 'json':
            $this->initDocument('json');

            $statuses = array();
            foreach ($this->notices as $n) {
                $twitter_status = $this->twitterStatusArray($n);
                array_push($statuses, $twitter_status);
            }

            $statuses_list = array('statuses' => $statuses,
                                   'next_cursor' => $this->next_cusror,
                                   'next_cursor_str' => strval($this->next_cusror),
                                   'previous_cursor' => $this->prev_cusror,
                                   'previous_cursor_str' => strval($this->prev_cusror)
                                   );
            $this->showJsonObjects($statuses_list);

            $this->initDocument('json');
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
        $fn = array($this->list, 'getNotices');
        list($this->notices, $this->next_cursor, $this->prev_cursor) =
                Profile_list::getAtCursor($fn, array(), $this->cursor, 20);
        if (!$this->notices) {
            $this->notices = array();
        }
    }

    /**
     * Is this action read only?
     *
     * @param array $args other arguments
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
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
     * Returns an Etag based on the action name, language, list ID and
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
                      common_language(),
                      $this->list->id,
                      strtotime($this->notices[0]->created),
                      strtotime($this->notices[$last]->created))
            )
            . '"';
        }

        return null;
    }
}
