<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show the home timeline
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
 * Returns the most recent notices (default 20) posted by the target user.
 * This is the equivalent of 'You and friends' page accessed via Web.
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
class ApiTimelineHomeAction extends ApiBareAuthAction
{
    var $notices  = null;

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

        $this->user = $this->getTargetUser($this->arg('id'));

        if (empty($this->user)) {
            // TRANS: Client error displayed when requesting most recent dents by user and friends for a non-existing user.
            $this->clientError(_('No such user.'), 404, $this->format);
            return;
        }

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
        $profile    = $this->user->getProfile();
        $avatar     = $profile->getAvatar(AVATAR_PROFILE_SIZE);
        $sitename   = common_config('site', 'name');
        // TRANS: Timeline title for user and friends. %s is a user nickname.
        $title      = sprintf(_("%s and friends"), $this->user->nickname);
        $taguribase = TagURI::base();
        $id         = "tag:$taguribase:HomeTimeline:" . $this->user->id;

        $subtitle   = sprintf(
            // TRANS: Message is used as a subtitle. %1$s is a user nickname, %2$s is a site name.
            _('Updates from %1$s and friends on %2$s!'),
            $this->user->nickname, $sitename
        );

        $link = common_local_url(
            'all',
            array('nickname' => $this->user->nickname)
        );

        $self = $this->getSelfUri();

        $logo = (!empty($avatar))
            ? $avatar->displayUrl()
            : Avatar::defaultImage(AVATAR_PROFILE_SIZE);

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
                $logo,
                $self
            );
            break;
        case 'atom':

            header('Content-Type: application/atom+xml; charset=utf-8');

            $atom = new AtomNoticeFeed($this->auth_user);

            $atom->setId($id);
            $atom->setTitle($title);
            $atom->setSubtitle($subtitle);
            $atom->setLogo($logo);
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

        $profile = null;

        if (isset($this->auth_user)) {
            $profile = $this->auth_user->getProfile();
        }

        $stream = new InboxNoticeStream($this->user, $profile);
        
        $notice = $stream->getNotices(($this->page-1) * $this->count,
                                      $this->count,
                                      $this->since_id,
                                      $this->max_id);

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
                      $this->user->id,
                      strtotime($this->notices[0]->created),
                      strtotime($this->notices[$last]->created))
            )
            . '"';
        }

        return null;
    }
}
