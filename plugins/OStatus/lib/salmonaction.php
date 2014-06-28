<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 */

/**
 * @package OStatusPlugin
 * @author James Walker <james@status.net>
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class SalmonAction extends Action
{
    protected $needPost = true;

    protected $oprofile = null; // Ostatus_profile of the actor
    protected $actor    = null; // Profile object of the actor

    var $xml      = null;
    var $activity = null;
    var $target   = null;

    protected function prepare(array $args=array())
    {
        StatusNet::setApi(true); // Send smaller error pages

        parent::prepare($args);

        if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] != 'application/magic-envelope+xml') {
            // TRANS: Client error. Do not translate "application/magic-envelope+xml".
            $this->clientError(_m('Salmon requires "application/magic-envelope+xml".'));
        }

        try {
            $envxml = file_get_contents('php://input');
            $magic_env = new MagicEnvelope($envxml);   // parse incoming XML as a MagicEnvelope

            $entry = $magic_env->getPayload();  // Not cryptographically verified yet!
            $this->activity = new Activity($entry->documentElement);
            $profile = Profile::fromUri($this->activity->actor->id);
            assert($profile instanceof Profile);
        } catch (Exception $e) {
            common_debug('Salmon envelope parsing failed with: '.$e->getMessage());
            $this->clientError($e->getMessage());
        }

        // Cryptographic verification test
        if (!$magic_env->verify($profile)) {
            common_log(LOG_DEBUG, "Salmon signature verification failed.");
            // TRANS: Client error.
            $this->clientError(_m('Salmon signature verification failed.'));
        }

        $this->oprofile = $this->ensureProfile();
        $this->actor    = $this->oprofile->localProfile();

        return true;
    }

    /**
     * Check the posted activity type and break out to appropriate processing.
     */

    protected function handle()
    {
        parent::handle();

        common_log(LOG_DEBUG, "Got a " . $this->activity->verb);
        try {
            if (Event::handle('StartHandleSalmonTarget', array($this->activity, $this->target)) &&
                    Event::handle('StartHandleSalmon', array($this->activity))) {
                switch ($this->activity->verb) {
                case ActivityVerb::POST:
                    $this->handlePost();
                    break;
                case ActivityVerb::SHARE:
                    $this->handleShare();
                    break;
                case ActivityVerb::FAVORITE:
                    $this->handleFavorite();
                    break;
                case ActivityVerb::UNFAVORITE:
                    $this->handleUnfavorite();
                    break;
                case ActivityVerb::FOLLOW:
                case ActivityVerb::FRIEND:
                    $this->handleFollow();
                    break;
                case ActivityVerb::UNFOLLOW:
                    $this->handleUnfollow();
                    break;
                case ActivityVerb::JOIN:
                    $this->handleJoin();
                    break;
                case ActivityVerb::LEAVE:
                    $this->handleLeave();
                    break;
                case ActivityVerb::TAG:
                    $this->handleTag();
                    break;
                case ActivityVerb::UNTAG:
                    $this->handleUntag();
                    break;
                case ActivityVerb::UPDATE_PROFILE:
                    $this->handleUpdateProfile();
                    break;
                default:
                    // TRANS: Client exception.
                    throw new ClientException(_m('Unrecognized activity type.'));
                }
                Event::handle('EndHandleSalmon', array($this->activity));
                Event::handle('EndHandleSalmonTarget', array($this->activity, $this->target));
            }
        } catch (AlreadyFulfilledException $e) {
            // The action's results are already fulfilled. Maybe it was a
            // duplicate? Maybe someone's database is out of sync?
            // Let's just accept it and move on.
            common_log(LOG_INFO, 'Salmon slap carried an event which had already been fulfilled.');
        }
    }

    function handlePost()
    {
        // TRANS: Client exception.
        throw new ClientException(_m('This target does not understand posts.'));
    }

    function handleFollow()
    {
        // TRANS: Client exception.
        throw new ClientException(_m('This target does not understand follows.'));
    }

    function handleUnfollow()
    {
        // TRANS: Client exception.
        throw new ClientException(_m('This target does not understand unfollows.'));
    }

    function handleFavorite()
    {
        // TRANS: Client exception.
        throw new ClientException(_m('This target does not understand favorites.'));
    }

    function handleUnfavorite()
    {
        // TRANS: Client exception.
        throw new ClientException(_m('This target does not understand unfavorites.'));
    }

    function handleShare()
    {
        // TRANS: Client exception.
        throw new ClientException(_m('This target does not understand share events.'));
    }

    function handleJoin()
    {
        // TRANS: Client exception.
        throw new ClientException(_m('This target does not understand joins.'));
    }

    function handleLeave()
    {
        // TRANS: Client exception.
        throw new ClientException(_m('This target does not understand leave events.'));
    }

    function handleTag()
    {
        // TRANS: Client exception.
        throw new ClientException(_m('This target does not understand list events.'));
    }

    function handleUntag()
    {
        // TRANS: Client exception.
        throw new ClientException(_m('This target does not understand unlist events.'));
    }

    /**
     * Remote user sent us an update to their profile.
     * If we already know them, accept the updates.
     */
    function handleUpdateProfile()
    {
        $oprofile = Ostatus_profile::getActorProfile($this->activity);
        if ($oprofile instanceof Ostatus_profile) {
            common_log(LOG_INFO, "Got a profile-update ping from $oprofile->uri");
            $oprofile->updateFromActivityObject($this->activity->actor);
        } else {
            common_log(LOG_INFO, "Ignoring profile-update ping from unknown " . $this->activity->actor->id);
        }
    }

    /**
     * @return Ostatus_profile
     */
    function ensureProfile()
    {
        $actor = $this->activity->actor;
        if (empty($actor->id)) {
            common_log(LOG_ERR, "broken actor: " . var_export($actor, true));
            common_log(LOG_ERR, "activity with no actor: " . var_export($this->activity, true));
            // TRANS: Exception.
            throw new Exception(_m('Received a salmon slap from unidentified actor.'));
        }

        // ensureActivityObjectProfile throws exception on failure
        return Ostatus_profile::ensureActivityObjectProfile($actor);
    }

    function saveNotice()
    {
        $oprofile = $this->ensureProfile();
        return $oprofile->processPost($this->activity, 'salmon');
    }
}
