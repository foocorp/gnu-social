<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show the latest notices for a given tag
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
 * @copyright 2009-2010 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Returns the 20 most recent notices tagged by a given tag
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
class ApiTimelineTagAction extends ApiPrivateAuthAction
{
    var $notices = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        common_debug("apitimelinetag prepare()");

        $this->tag     = $this->arg('tag');
        $this->notices = $this->getNotices();

        return true;
    }

    /**
     * Handle the request
     *
     * Just show the notices
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $this->showTimeline();
    }

    /**
     * Show the timeline of notices
     *
     * @return void
     */
    function showTimeline()
    {
        $sitename   = common_config('site', 'name');
        $sitelogo   = (common_config('site', 'logo')) ? common_config('site', 'logo') : Theme::path('logo.png');
        // TRANS: Title for timeline with lastest notices with a given tag.
        // TRANS: %s is the tag.
        $title      = sprintf(_("Notices tagged with %s"), $this->tag);
        $subtitle   = sprintf(
            // TRANS: Subtitle for timeline with lastest notices with a given tag.
            // TRANS: %1$s is the tag, $2$s is the StatusNet sitename.
            _('Updates tagged with %1$s on %2$s!'),
            $this->tag,
            $sitename
        );
        $taguribase = TagURI::base();
        $id         = "tag:$taguribase:TagTimeline:".$this->tag;

        $link = common_local_url(
            'tag',
            array('tag' => $this->tag)
        );

        $self = $this->getSelfUri();

        switch($this->format) {
        case 'xml':
            $this->showXmlTimeline($this->notices);
            break;
        case 'rss':
            $this->showRssTimeline(
                $this->notices,
                $title,
                $link,
                $subtitle,
                null,
                $sitelogo,
                $self
            );
            break;
        case 'atom':
            header('Content-Type: application/atom+xml; charset=utf-8');

            $atom = new AtomNoticeFeed($this->auth_user);

            $atom->setId($id);
            $atom->setTitle($title);
            $atom->setSubtitle($subtitle);
            $atom->setLogo($sitelogo);
            $atom->setUpdated('now');

            $atom->addLink($link);
            $atom->setSelfLink($self);

            $atom->addEntryFromNotices($this->notices);
            $this->raw($atom->getString());

            break;
        case 'json':
            $this->showJsonTimeline($this->notices);
            break;
        case 'as':
            header('Content-Type: ' . ActivityStreamJSONDocument::CONTENT_TYPE);
            $doc = new ActivityStreamJSONDocument($this->auth_user);
            $doc->setTitle($title);
            $doc->addLink($link, 'alternate', 'text/html');
            $doc->addItemsFromNotices($this->notices);
            $this->raw($doc->asString());
            break;
        default:
            // TRANS: Client error displayed when coming across a non-supported API method.
            $this->clientError(_('API method not found.'), $code = 404);
            break;
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

        $notice = Notice_tag::getStream(
            $this->tag,
            ($this->page - 1) * $this->count,
            $this->count + 1,
            $this->since_id,
            $this->max_id
        );

        while ($notice->fetch()) {
            $notices[] = clone($notice);
        }

        return $notices;
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
     * Returns an Etag based on the action name, language, and
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
                      $this->tag,
                      strtotime($this->notices[0]->created),
                      strtotime($this->notices[$last]->created))
            )
            . '"';
        }

        return null;
    }
}
