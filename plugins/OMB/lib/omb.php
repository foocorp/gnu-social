<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2011, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

$dir = dirname(__FILE__);

require_once $dir . '/omboauthstore.php';
require_once $dir . '/../extlib/libomb/constants.php';
require_once $dir . '/../extlib/libomb/service_consumer.php';
require_once $dir . '/../extlib/libomb/notice.php';
require_once $dir . '/../extlib/libomb/profile.php';
require_once INSTALLDIR . '/extlib/Auth/Yadis/Yadis.php';

function omb_oauth_consumer()
{
    // Don't try to make this static. Leads to issues in
    // multi-site setups - Z
    return new OAuthConsumer(common_root_url(), '');
}

function omb_oauth_server()
{
    static $server = null;
    if (is_null($server)) {
        $server = new OAuthServer(omb_oauth_datastore());
        $server->add_signature_method(omb_hmac_sha1());
    }
    return $server;
}

function omb_oauth_datastore()
{
    static $store = null;
    if (is_null($store)) {
        $store = new OMBOAuthDataStore();
    }
    return $store;
}

function omb_hmac_sha1()
{
    static $hmac_method = null;
    if (is_null($hmac_method)) {
        $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
    }
    return $hmac_method;
}

function omb_broadcast_notice($notice)
{
    try {
        $omb_notice = notice_to_omb_notice($notice);
    } catch (Exception $e) {
        // @fixme we should clean up or highlight the problem item
        common_log(LOG_ERR, 'Invalid OMB outgoing notice for notice ' . $notice->id);
        common_log(LOG_ERR, 'Error status '.$e);
        return true;
    }

    /* Get remote users subscribed to this profile. */
    $rp = new Remote_profile();

    $rp->query('SELECT remote_profile.*, secret, token ' .
               'FROM subscription JOIN remote_profile ' .
               'ON subscription.subscriber = remote_profile.id ' .
               'WHERE subscription.subscribed = ' . $notice->profile_id . ' ');

    $posted = array();

    while ($rp->fetch()) {
        if (isset($posted[$rp->postnoticeurl])) {
            /* We already posted to this url. */
            continue;
        }
        common_debug('Posting to ' . $rp->postnoticeurl, __FILE__);

        /* Post notice. */
        $service = new StatusNet_OMB_Service_Consumer(
                     array(OMB_ENDPOINT_POSTNOTICE => $rp->postnoticeurl),
                                                      $rp->uri);
        try {
            $service->setToken($rp->token, $rp->secret);
            $service->postNotice($omb_notice);
        } catch (Exception $e) {
            common_log(LOG_ERR, 'Failed posting to ' . $rp->postnoticeurl);
            common_log(LOG_ERR, 'Error status '.$e);
            continue;
        }
        $posted[$rp->postnoticeurl] = true;

        common_debug('Finished to ' . $rp->postnoticeurl, __FILE__);
    }

    return true;
}

function omb_broadcast_profile($profile)
{
    $user = User::getKV('id', $profile->id);

    if (!$user) {
        return false;
    }

    $profile = $user->getProfile();

    $omb_profile = profile_to_omb_profile($user->uri, $profile, true);

    /* Get remote users subscribed to this profile. */
    $rp = new Remote_profile();

    $rp->query('SELECT remote_profile.*, secret, token ' .
               'FROM subscription JOIN remote_profile ' .
               'ON subscription.subscriber = remote_profile.id ' .
               'WHERE subscription.subscribed = ' . $profile->id . ' ');

    $posted = array();

    while ($rp->fetch()) {
        if (isset($posted[$rp->updateprofileurl])) {
            /* We already posted to this url. */
            continue;
        }
        common_debug('Posting to ' . $rp->updateprofileurl, __FILE__);

        /* Update profile. */
        $service = new StatusNet_OMB_Service_Consumer(
                     array(OMB_ENDPOINT_UPDATEPROFILE => $rp->updateprofileurl),
                                                      $rp->uri);
        try {
            $service->setToken($rp->token, $rp->secret);
            $service->updateProfile($omb_profile);
        } catch (Exception $e) {
            common_log(LOG_ERR, 'Failed posting to ' . $rp->updateprofileurl);
            common_log(LOG_ERR, 'Error status '.$e);
            continue;
        }
        $posted[$rp->updateprofileurl] = true;

        common_debug('Finished to ' . $rp->updateprofileurl, __FILE__);
    }

    return;
}

class StatusNet_OMB_Service_Consumer extends OMB_Service_Consumer {
    public function __construct($urls, $listener_uri=null)
    {
        $this->services       = $urls;
        $this->datastore      = omb_oauth_datastore();
        $this->oauth_consumer = omb_oauth_consumer();
        $this->fetcher        = Auth_Yadis_Yadis::getHTTPFetcher();
        $this->fetcher->timeout = intval(common_config('omb', 'timeout'));
        $this->listener_uri   = $listener_uri;
    }

}

function profile_to_omb_profile($uri, $profile, $force = false)
{
    $omb_profile = new OMB_Profile($uri);
    $omb_profile->setNickname($profile->nickname);
    $omb_profile->setLicenseURL(common_config('license', 'url'));
    if (!is_null($profile->fullname)) {
        $omb_profile->setFullname($profile->fullname);
    } elseif ($force) {
        $omb_profile->setFullname('');
    }
    if (!is_null($profile->homepage)) {
        $omb_profile->setHomepage($profile->homepage);
    } elseif ($force) {
        $omb_profile->setHomepage('');
    }
    if (!is_null($profile->bio)) {
        $omb_profile->setBio($profile->bio);
    } elseif ($force) {
        $omb_profile->setBio('');
    }
    if (!is_null($profile->location)) {
        $omb_profile->setLocation($profile->location);
    } elseif ($force) {
        $omb_profile->setLocation('');
    }
    if (!is_null($profile->profileurl)) {
        $omb_profile->setProfileURL($profile->profileurl);
    } elseif ($force) {
        $omb_profile->setProfileURL('');
    }

    $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
    if ($avatar) {
        $omb_profile->setAvatarURL($avatar->url);
    } elseif ($force) {
        $omb_profile->setAvatarURL('');
    }
    return $omb_profile;
}

function notice_to_omb_notice($notice)
{
    /* Create an OMB_Notice for $notice. */
    $user = User::getKV('id', $notice->profile_id);

    if (!$user) {
        return null;
    }

    $profile = $user->getProfile();

    $omb_notice = new OMB_Notice(profile_to_omb_profile($user->uri, $profile),
                                 $notice->uri,
                                 $notice->content);
    $omb_notice->setURL(common_local_url('shownotice', array('notice' =>
                                                                 $notice->id)));
    $omb_notice->setLicenseURL(common_config('license', 'url'));

    return $omb_notice;
}

