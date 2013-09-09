<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * OpenMicroBlogging plugin - add OpenMicroBloggin 0.1 support to
 * StatusNet.
 *
 * Note: the OpenMicroBlogging protocol has been deprecated in favor of OStatus.
 * This plugin is provided for backwards compatibility and experimentation.
 *
 * Please see the README and the OStatus plugin.
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
 * @category  Sample
 * @package   StatusNet
 * @author    Zach Copley
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib/');

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * OMB plugin main class
 *
 * @category  Integration
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class OMBPlugin extends Plugin
{

    /**
     * Map URLs to actions
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onRouterInitialized($m)
    {
        $bare = array(
            'requesttoken',
            'accesstoken',
            'userauthorization',
            'postnotice',
            'updateprofile',
            'finishremotesubscribe'
        );

        foreach ($bare as $action) {
            $m->connect(
                'index.php?action=' . $action, array('action' => $action)
            );
        }

        // exceptional

        $m->connect('main/remote', array('action' => 'remotesubscribe'));
        $m->connect(
            'main/remote?nickname=:nickname',
            array('action' => 'remotesubscribe'),
            array('nickname' => '[A-Za-z0-9_-]+')
        );

        $m->connect(
            'xrds',
            array('action' => 'xrds', 'nickname' => $nickname)
        );

        return true;
    }

    /**
     * Put saved notices into the queue for OMB distribution
     *
     * @param Notice $notice     the notice to broadcast
     * @param array  $transports queuehandler's list of transports
     * @return boolean true if queing was successful
     */
    function onStartEnqueueNotice($notice, &$transports)
    {
        if ($notice->isLocal()) {
            if ($notice->inScope(null)) {
                array_unshift($transports, 'omb');
                common_log(
                    LOG_INFO, "Notice {$notice->id} queued for OMB processing"
                );
            } else {
                // Note: We don't do privacy-controlled OMB updates.
                common_log(
                    LOG_NOTICE,
                    "Not queueing notice {$notice->id} for OMB because of "
                    . "privacy; scope = {$notice->scope}",
                    __FILE__
                );
            }
        } else {
            common_log(
                LOG_NOTICE,
                "Not queueing notice {$notice->id} for OMB because it's not "
                . "local.",
                __FILE__
            );
        }

        return true;
    }

    /**
     * Set up queue handlers for outgoing OMB pushes
     *
     * @param QueueManager $qm
     * @return boolean hook return
     */
    function onEndInitializeQueueManager(QueueManager $qm)
    {
        // Prepare outgoing distributions after notice save.
        $qm->connect('omb', 'OmbQueueHandler');
        $qm->connect('profile', 'ProfileQueueHandler');

        return true;
    }

    /**
     * Return OMB remote profile, if any
     *
     * @param Profile $profile
     * @param string  $uri
     * @return boolen false if there's a remote profile
     */
    function onStartGetProfileUri($profile, &$uri)
    {
        $remote = Remote_profile::getKV('id', $this->id);
        if (!empty($remote)) {
            $uri = $remote->uri;
            return false;
        }
        return true;
    }

    /**
     * We can't initiate subscriptions for a remote OMB profile; don't show
     * subscribe button
     *
     * @param type $action
     */
    function onStartShowProfileListSubscribeButton($action)
    {
        $remote = Remote_profile::getKV('id', $action->profile->id);
        if (empty($remote)) {
            false;
        }
        return true;
    }

    /**
     * Check for illegal subscription attempts
     *
     * @param Profile $profile  subscriber
     * @param Profile $other    subscribee
     * @return hook return value
     */
    function onStartSubscribe(Profile $profile, Profile $other)
    {
        // OMB 0.1 doesn't have a mechanism for local-server-
        // originated subscription.

        $omb01 = Remote_profile::getKV('id', $other->id);

        if (!empty($omb01)) {
            throw new ClientException(
                // TRANS: Client error displayed trying to subscribe to an OMB 0.1 remote profile.
                _m('You cannot subscribe to an OMB 0.1 '
                    . 'remote profile with this action.'
                )
            );
            return false;
        }
    }

    /**
     * Throw an error if someone tries to tag a remote profile
     *
     * @param Profile $tagger_profile   profile of the tagger
     * @param Profile $tagged_profile   profile of the taggee
     * @param string $tag
     *
     * @return true
     */
    function onStartTagProfile($tagger_profile, $tagged_profile, $tag)
    {
        // OMB 0.1 doesn't have a mechanism for local-server-
        // originated tag.

        $omb01 = Remote_profile::getKV('id', $tagged_profile->id);

        if (!empty($omb01)) {
            $this->clientError(
                // TRANS: Client error displayed when trying to add an OMB 0.1 remote profile to a list.
                _m('You cannot list an OMB 0.1 '
                   .'remote profile with this action.')
            );
        }
        return false;
    }

    /**
     * Check to make sure we're not tryng to untag an OMB profile
     *
     * // XXX: Should this ever happen?
     *
     * @param Profile_tag $ptag the profile tag
     */
    function onUntagProfile($ptag)
    {
        // OMB 0.1 doesn't have a mechanism for local-server-
        // originated tag.

        $omb01 = Remote_profile::getKV('id', $ptag->tagged);

        if (!empty($omb01)) {
            $this->clientError(
                // TRANS: Client error displayed when trying to (un)list an OMB 0.1 remote profile.
                _m('You cannot (un)list an OMB 0.1 '
                    . 'remote profile with this action.')
            );
            return false;
        }
    }

    /**
     * Remove old OMB subscription tokens
     *
     * @param Profile $profile  subscriber
     * @param Profile $other    subscribee
     * @return hook return value
     */
    function onEndUnsubscribe(Profile $profile, Profile $other)
    {
        $sub = Subscription::pkeyGet(
            array('subscriber' => $profile->id, 'subscribed' => $other->id)
        );

        if (!empty($sub->token)) {

            $token = new Token();

            $token->tok    = $sub->token;

            if ($token->find(true)) {

                $result = $token->delete();

                if (!$result) {
                    common_log_db_error($token, 'DELETE', __FILE__);
                    throw new Exception(
                        // TRANS: Exception thrown when the OMB token for a subscription could not deleted on the server.
                        _m('Could not delete subscription OMB token.')
                    );
                }
            } else {
                common_log(
                    LOG_ERR,
                    "Couldn't find credentials with token {$token->tok}",
                    __FILE__
                );
            }
        }

        return true;
    }

    /**
     * Search for an OMB remote profile by URI
     *
     * @param string  $uri      remote profile URI
     * @param Profile $profile  the profile to set
     * @return boolean hook value
     */
    function onStartGetProfileFromURI($uri, &$profile)
    {
        $remote_profile = Remote_profile::getKV('uri', $uri);
        if (!empty($remote_profile)) {
            $profile = Profile::getKV('id', $remote_profile->profile_id);
            return false;
        }

        return true;
    }

    /**
     * Return OMB remote profiles as well as regular profiles
     * in helper
     *
     * @param type $profile
     * @param type $uri
     */
    function onStartCommonProfileURI($profile, &$uri)
    {
        $remote = Remote_profile::getKV($profile->id);
        if ($remote) {
            $uri = $remote->uri;
            return false;
        }
        return true;
    }

    /**
     * Broadcast a profile over OMB
     *
     * @param Profile $profile to broadcast
     * @return false
     */
    function onBroadcastProfile($profile) {
        $qm = QueueManager::get();
        $qm->enqueue($profile, "profile");
        return true;
    }

    function onStartProfileRemoteSubscribe($out, $profile)
    {
        $out->elementStart('li', 'entity_subscribe');
        $url = common_local_url('remotesubscribe',
                                array('nickname' => $this->profile->nickname));
        $out->element('a', array('href' => $url,
                                  'class' => 'entity_remote_subscribe'),
                       // TRANS: Link text for link that will subscribe to a remote profile.
                       _m('BUTTON','Subscribe'));
        $out->elementEnd('li');

        return false;
    }

    /**
     * Plugin version info
     *
     * @param array $versions
     * @return boolean hook value
     */
    function onPluginVersion(&$versions)
    {
        $versions[] = array(
            'name'           => 'OpenMicroBlogging',
            'version'        => STATUSNET_VERSION,
            'author'         => 'Zach Copley',
            'homepage'       => 'http://status.net/wiki/Plugin:Sample',
            // TRANS: Plugin description.
            'rawdescription' => _m('A sample plugin to show basics of development for new hackers.')
        );

        return true;
    }
}
