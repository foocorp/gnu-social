<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Feed of ActivityStreams 'favorite' actions
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
 * @category  AtomPub
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Feed of ActivityStreams 'favorite' actions
 *
 * @category  AtomPub
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class AtompubfavoritefeedAction extends ApiAuthAction
{
    private $_profile = null;
    private $_faves   = null;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        parent::prepare($argarray);

        $this->_profile = Profile::getKV('id', $this->trimmed('profile'));

        if (empty($this->_profile)) {
            // TRANS: Client exception thrown when requesting a favorite feed for a non-existing profile.
            throw new ClientException(_('No such profile.'), 404);
        }

        $offset = ($this->page-1) * $this->count;
        $limit  = $this->count + 1;

        $this->_faves = Fave::byProfile($this->_profile->id,
                                        $offset,
                                        $limit);

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */
    function handle($argarray=null)
    {
        parent::handle($argarray);

        switch ($_SERVER['REQUEST_METHOD']) {
        case 'HEAD':
        case 'GET':
            $this->showFeed();
            break;
        case 'POST':
            $this->addFavorite();
            break;
        default:
            // TRANS: Client exception thrown when using an unsupported HTTP method.
            throw new ClientException(_('HTTP method not supported.'), 405);
            return;
        }

        return;
    }

    /**
     * Show a feed of favorite activity streams objects
     *
     * @return void
     */
    function showFeed()
    {
        header('Content-Type: application/atom+xml; charset=utf-8');

        $url = common_local_url('AtomPubFavoriteFeed',
                                array('profile' => $this->_profile->id));

        $feed = new Atom10Feed(true);

        $feed->addNamespace('activity',
                            'http://activitystrea.ms/spec/1.0/');

        $feed->addNamespace('poco',
                            'http://portablecontacts.net/spec/1.0');

        $feed->addNamespace('media',
                            'http://purl.org/syndication/atommedia');

        $feed->id = $url;

        $feed->setUpdated('now');

        $feed->addAuthor($this->_profile->getBestName(),
                         $this->_profile->getUri());

        // TRANS: Title for Atom favorites feed.
        // TRANS: %s is a user nickname.
        $feed->setTitle(sprintf(_("%s favorites"),
                                $this->_profile->getBestName()));

        // TRANS: Subtitle for Atom favorites feed.
        // TRANS: %1$s is a user nickname, %2$s is the StatusNet sitename.
        $feed->setSubtitle(sprintf(_('Notices %1$s has favorited on %2$s'),
                                   $this->_profile->getBestName(),
                                   common_config('site', 'name')));

        $feed->addLink(common_local_url('showfavorites',
                                        array('nickname' =>
                                              $this->_profile->nickname)));

        $feed->addLink($url,
                       array('rel' => 'self',
                             'type' => 'application/atom+xml'));

        // If there's more...

        if ($this->page > 1) {
            $feed->addLink($url,
                           array('rel' => 'first',
                                 'type' => 'application/atom+xml'));

            $feed->addLink(common_local_url('AtomPubFavoriteFeed',
                                            array('profile' =>
                                                  $this->_profile->id),
                                            array('page' =>
                                                  $this->page - 1)),
                           array('rel' => 'prev',
                                 'type' => 'application/atom+xml'));
        }

        if ($this->_faves->N > $this->count) {

            $feed->addLink(common_local_url('AtomPubFavoriteFeed',
                                            array('profile' =>
                                                  $this->_profile->id),
                                            array('page' =>
                                                  $this->page + 1)),
                           array('rel' => 'next',
                                 'type' => 'application/atom+xml'));
        }

        $i = 0;

        while ($this->_faves->fetch()) {

            // We get one more than needed; skip that one

            $i++;

            if ($i > $this->count) {
                break;
            }

            $act = $this->_faves->asActivity();
            $feed->addEntryRaw($act->asString(false, false, false));
        }

        $this->raw($feed->getString());
    }

    /**
     * add a new favorite
     *
     * @return void
     */
    function addFavorite()
    {
        // XXX: Refactor this; all the same for atompub

        if (empty($this->auth_user) ||
            $this->auth_user->id != $this->_profile->id) {
            // TRANS: Client exception thrown when trying to set a favorite for another user.
            throw new ClientException(_("Cannot add someone else's".
                                        " subscription."), 403);
        }

        $xml = file_get_contents('php://input');

        $dom = DOMDocument::loadXML($xml);

        if ($dom->documentElement->namespaceURI != Activity::ATOM ||
            $dom->documentElement->localName != 'entry') {
            // TRANS: Client error displayed when not using an Atom entry.
            throw new ClientException(_('Atom post must be an Atom entry.'));
            return;
        }

        $activity = new Activity($dom->documentElement);

        $fave = null;

        if (Event::handle('StartAtomPubNewActivity', array(&$activity))) {

            if ($activity->verb != ActivityVerb::FAVORITE) {
                // TRANS: Client exception thrown when trying use an incorrect activity verb for the Atom pub method.
                throw new ClientException(_('Can only handle favorite activities.'));
                return;
            }

            $note = $activity->objects[0];

            if (!in_array($note->type, array(ActivityObject::NOTE,
                                             ActivityObject::BLOGENTRY,
                                             ActivityObject::STATUS))) {
                // TRANS: Client exception thrown when trying favorite an object that is not a notice.
                throw new ClientException(_('Can only fave notices.'));
                return;
            }

            $notice = Notice::getKV('uri', $note->id);

            if (empty($notice)) {
                // XXX: import from listed URL or something
                // TRANS: Client exception thrown when trying favorite a notice without content.
                throw new ClientException(_('Unknown notice.'));
            }

            $old = Fave::pkeyGet(array('user_id' => $this->auth_user->id,
                                       'notice_id' => $notice->id));

            if (!empty($old)) {
                // TRANS: Client exception thrown when trying favorite an already favorited notice.
                throw new ClientException(_('Already a favorite.'));
            }

            $profile = $this->auth_user->getProfile();

            $fave = Fave::addNew($profile, $notice);

            if ($fave instanceof Fave) {
                Fave::blowCacheForProfileId($this->_profile->id);
                $this->notify($fave, $notice, $this->auth_user);
            }

            Event::handle('EndAtomPubNewActivity', array($activity, $fave));
        }

        if (!empty($fave)) {
            $act = $fave->asActivity();

            header('Content-Type: application/atom+xml; charset=utf-8');
            header('Content-Location: ' . $act->selfLink);

            $this->startXML();
            $this->raw($act->asString(true, true, true));
            $this->endXML();
        }
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return last modified, if applicable.
     *
     * MAY override
     *
     * @return string last modified http header
     */
    function lastModified()
    {
        // For comparison with If-Last-Modified
        // If not applicable, return null
        return null;
    }

    /**
     * Return etag, if applicable.
     *
     * MAY override
     *
     * @return string etag http header
     */
    function etag()
    {
        return null;
    }

    /**
     * Does this require authentication?
     *
     * @return boolean true if delete, else false
     */
    function requiresAuth()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Notify the author of the favorite that the user likes their notice
     *
     * @param Favorite $fave   the favorite in question
     * @param Notice   $notice the notice that's been faved
     * @param User     $user   the user doing the favoriting
     *
     * @return void
     */
    function notify($fave, $notice, $user)
    {
        $other = User::getKV('id', $notice->profile_id);
        if ($other && $other->id != $user->id) {
            if ($other->email && $other->emailnotifyfav) {
                mail_notify_fave($other, $user, $notice);
            }
            // XXX: notify by IM
            // XXX: notify by SMS
        }
    }
}
