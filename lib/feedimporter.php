<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Importer for feeds of activities
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
 * @category  Account
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
 * Importer for feeds of activities
 *
 * Takes an XML file representing a feed of activities and imports each
 * activity to the user in question.
 *
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class FeedImporter extends QueueHandler
{
    /**
     * Transport identifier
     *
     * @return string identifier for this queue handler
     */
    public function transport()
    {
        return 'feedimp';
    }

    function handle($data)
    {
        list($user, $xml, $trusted) = $data;

        try {
            $doc = DOMDocument::loadXML($xml);

            $feed = $doc->documentElement;

            if ($feed->namespaceURI != Activity::ATOM ||
                $feed->localName != 'feed') {
                // TRANS: Client exception thrown when an imported feed is not an Atom feed.
                throw new ClientException(_("Not an Atom feed."));
            }


            $author = ActivityUtils::getFeedAuthor($feed);

            if (empty($author)) {
                // TRANS: Client exception thrown when an imported feed does not have an author.
                throw new ClientException(_("No author in the feed."));
            }

            if (empty($user)) {
                if ($trusted) {
                    $user = $this->userFromAuthor($author);
                } else {
                    // TRANS: Client exception thrown when an imported feed does not have an author that
                    // TRANS: can be associated with a user.
                    throw new ClientException(_("Cannot import without a user."));
                }
            }

            $activities = $this->getActivities($feed);

            $qm = QueueManager::get();

            foreach ($activities as $activity) {
                $qm->enqueue(array($user, $author, $activity, $trusted), 'actimp');
            }
        } catch (ClientException $ce) {
            common_log(LOG_WARNING, $ce->getMessage());
            return true;
        } catch (ServerException $se) {
            common_log(LOG_ERR, $ce->getMessage());
            return false;
        } catch (Exception $e) {
            common_log(LOG_ERR, $ce->getMessage());
            return false;
        }
    }

    function getActivities($feed)
    {
        $entries = $feed->getElementsByTagNameNS(Activity::ATOM, 'entry');

        $activities = array();

        for ($i = 0; $i < $entries->length; $i++) {
            $activities[] = new Activity($entries->item($i));
        }

        usort($activities, array("FeedImporter", "activitySort"));

        return $activities;
    }

    /**
     * Sort activities oldest-first
     */
    static function activitySort($a, $b)
    {
        if ($a->time == $b->time) {
            return 0;
        } else if ($a->time < $b->time) {
            return -1;
        } else {
            return 1;
        }
    }

    function userFromAuthor($author)
    {
        $user = User::getKV('uri', $author->id);

        if (empty($user)) {
            $attrs =
                array('nickname' => Ostatus_profile::getActivityObjectNickname($author),
                      'uri' => $author->id);

            $user = User::register($attrs);
        }

        $profile = $user->getProfile();
        Ostatus_profile::updateProfile($profile, $author);

        // @todo FIXME: Update avatar
        return $user;
    }
}
