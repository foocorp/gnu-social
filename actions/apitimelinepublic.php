<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show the public timeline
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
 * Returns the most recent notices (default 20) posted by everybody
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

/* External API usage documentation. Please update when you change how this method works. */

/*! @page publictimeline statuses/public_timeline

    @section Description
    Returns the 20 most recent notices from users throughout the system who have
    uploaded their own avatars. Depending on configuration, it may or may not
    not include notices from automatic posting services.

    @par URL patterns
    @li /api/statuses/public_timeline.:format

    @par Formats (:format)
    xml, json, rss, atom

    @par HTTP Method(s)
    GET

    @par Requires Authentication
    No

    @param since_id (Optional) Returns only statuses with an ID greater
    than (that is, more recent than) the specified ID.
    @param max_id (Optional) Returns only statuses with an ID less than
    (that is, older than) or equal to the specified ID.
    @param count (Optional) Specifies the number of statuses to retrieve.
    @param page (Optional) Specifies the page of results to retrieve.

    @sa @ref apiroot

    @subsection usagenotes Usage notes
    @li The URL pattern is relative to the @ref apiroot.
    @li The XML response uses <a href="http://georss.org/Main_Page">GeoRSS</a>
    to encode the latitude and longitude (see example response below <georss:point>).

    @subsection exampleusage Example usage

    @verbatim
    curl http://identi.ca/api/statuses/friends_timeline/evan.xml?count=1&page=2
    @endverbatim

    @subsection exampleresponse Example response

    @verbatim
    <?xml version="1.0" encoding="UTF-8"?>
    <statuses type="array">
     <status>
      <text>@skwashd oh, commbank reenabled me super quick both times. but disconcerting when you don't expect it though</text>
      <truncated>false</truncated>
      <created_at>Sat Apr 17 00:49:12 +0000 2010</created_at>
      <in_reply_to_status_id>28838393</in_reply_to_status_id>
      <source>xmpp</source>
      <id>28838456</id>
      <in_reply_to_user_id>39303</in_reply_to_user_id>
      <in_reply_to_screen_name>skwashd</in_reply_to_screen_name>
      <geo></geo>
      <favorited>false</favorited>
      <user>
       <id>44517</id>
       <name>joshua may</name>
       <screen_name>notjosh</screen_name>
       <location></location>
       <description></description>
       <profile_image_url>http://avatar.identi.ca/44517-48-20090321004106.jpeg</profile_image_url>
       <url></url>
       <protected>false</protected>
       <followers_count>17</followers_count>
       <profile_background_color></profile_background_color>
       <profile_text_color></profile_text_color>
       <profile_link_color></profile_link_color>
       <profile_sidebar_fill_color></profile_sidebar_fill_color>
       <profile_sidebar_border_color></profile_sidebar_border_color>
       <friends_count>20</friends_count>
       <created_at>Sat Mar 21 00:40:25 +0000 2009</created_at>
       <favourites_count>0</favourites_count>
       <utc_offset>0</utc_offset>
       <time_zone>UTC</time_zone>
       <profile_background_image_url></profile_background_image_url>
       <profile_background_tile>false</profile_background_tile>
       <statuses_count>100</statuses_count>
       <following>false</following>
       <notifications>false</notifications>
    </user>
    </status>
    [....]
    </statuses>
@endverbatim
*/
class ApiTimelinePublicAction extends ApiPrivateAuthAction
{
    var $notices = null;

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
        $this->showTimeline();
    }

    function title()
    {
        // TRANS: Title for site timeline. %s is the GNU social sitename.
        return sprintf(_("%s public timeline"), common_config('site', 'name'));
    }

    /**
     * Show the timeline of notices
     *
     * @return void
     */
    function showTimeline()
    {
        $nonapi_action = substr($this->action, strlen('apitimeline'));  // Just so we don't need to set this explicitly

        $sitelogo   = (common_config('site', 'logo')) ? common_config('site', 'logo') : Theme::path('logo.png');
        $title      = $this->title();
        $taguribase = TagURI::base();
        $id         = "tag:$taguribase:" . ucfirst($nonapi_action) . 'Timeline';    // Public or Networkpublic probably
        $link       = common_local_url($nonapi_action);
        $self       = $this->getSelfUri();
        // TRANS: Subtitle for site timeline. %s is the GNU social sitename.
        $subtitle   = sprintf(_("%s updates from everyone!"), common_config('site', 'name'));

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
            $atom->addLink(common_local_url($nonapi_action));
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

        $stream = $this->getStream();

        $notice = $stream->getNotices(($this->page - 1) * $this->count,
                                      $this->count,
                                      $this->since_id,
                                      $this->max_id);

        $notices = $notice->fetchAll();

        NoticeList::prefill($notices);

        return $notices;
    }

    protected function getStream()
    {
        return new PublicNoticeStream($this->scoped);
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
                      strtotime($this->notices[0]->created),
                      strtotime($this->notices[$last]->created))
            )
            . '"';
        }

        return null;
    }
}
