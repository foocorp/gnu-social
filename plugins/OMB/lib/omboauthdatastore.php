<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2011 StatusNet, Inc.
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

require_once dirname(__FILE__) . '/../extlib/libomb/datastore.php';

// @todo FIXME: Class documentation missing.
class OMBOAuthDataStore extends OAuthDataStore
{
    // We keep a record of who's contacted us
    function lookup_consumer($consumer_key)
    {
        $con = Consumer::getKV('consumer_key', $consumer_key);
        if (!$con) {
            $con = new Consumer();
            $con->consumer_key = $consumer_key;
            $con->seed = common_good_rand(16);
            $con->created = DB_DataObject_Cast::dateTime();
            if (!$con->insert()) {
                return null;
            }
        }
        return new OAuthConsumer($con->consumer_key, '');
    }

    function lookup_token($consumer, $token_type, $token_key)
    {
        $t = new Token();
        if (!is_null($consumer)) {
            $t->consumer_key = $consumer->key;
        }
        $t->tok = $token_key;
        $t->type = ($token_type == 'access') ? 1 : 0;
        if ($t->find(true)) {
            return new OAuthToken($t->tok, $t->secret);
        } else {
            return null;
        }
    }

    // http://oauth.net/core/1.0/#nonce
    // "The Consumer SHALL then generate a Nonce value that is unique for
    // all requests with that timestamp."
    // XXX: It's not clear why the token is here
    function lookup_nonce($consumer, $token, $nonce, $timestamp)
    {
        $n = new Nonce();
        $n->consumer_key = $consumer->key;
        $n->ts = common_sql_date($timestamp);
        $n->nonce = $nonce;
        if ($n->find(true)) {
            return true;
        } else {
            $n->created = DB_DataObject_Cast::dateTime();
            $n->insert();
            return false;
        }
    }

    function new_request_token($consumer)
    {
        $t = new Token();
        $t->consumer_key = $consumer->key;
        $t->tok = common_good_rand(16);
        $t->secret = common_good_rand(16);
        $t->type = 0; // request
        $t->state = 0; // unauthorized
        $t->created = DB_DataObject_Cast::dateTime();
        if (!$t->insert()) {
            return null;
        } else {
            return new OAuthToken($t->tok, $t->secret);
        }
    }

    // defined in OAuthDataStore, but not implemented anywhere
    function fetch_request_token($consumer)
    {
        return $this->new_request_token($consumer);
    }

    function new_access_token($token, $consumer)
    {
        common_debug('new_access_token("'.$token->key.'","'.$consumer->key.'")', __FILE__);
        $rt = new Token();
        $rt->consumer_key = $consumer->key;
        $rt->tok = $token->key;
        $rt->type = 0; // request
        if ($rt->find(true) && $rt->state == 1) { // authorized
            common_debug('request token found.', __FILE__);
            $at = new Token();
            $at->consumer_key = $consumer->key;
            $at->tok = common_good_rand(16);
            $at->secret = common_good_rand(16);
            $at->type = 1; // access
            $at->created = DB_DataObject_Cast::dateTime();
            if (!$at->insert()) {
                $e = $at->_lastError;
                common_debug('access token "'.$at->tok.'" not inserted: "'.$e->message.'"', __FILE__);
                return null;
            } else {
                common_debug('access token "'.$at->tok.'" inserted', __FILE__);
                // burn the old one
                $orig_rt = clone($rt);
                $rt->state = 2; // used
                if (!$rt->update($orig_rt)) {
                    return null;
                }
                common_debug('request token "'.$rt->tok.'" updated', __FILE__);
                // Update subscription
                // XXX: mixing levels here
                $sub = Subscription::getKV('token', $rt->tok);
                if (!$sub) {
                    return null;
                }
                common_debug('subscription for request token found', __FILE__);
                $orig_sub = clone($sub);
                $sub->token = $at->tok;
                $sub->secret = $at->secret;
                if (!$sub->update($orig_sub)) {
                    return null;
                } else {
                    common_debug('subscription updated to use access token', __FILE__);
                    return new OAuthToken($at->tok, $at->secret);
                }
            }
        } else {
            return null;
        }
    }

    // defined in OAuthDataStore, but not implemented anywhere
    function fetch_access_token($consumer)
    {
        return $this->new_access_token($consumer);
    }

    /**
     * Revoke specified OAuth token
     *
     * Revokes the authorization token specified by $token_key.
     * Throws exceptions in case of error.
     *
     * @param string $token_key The token to be revoked
     *
     * @access public
     **/
    public function revoke_token($token_key) {
        $rt = new Token();
        $rt->tok = $token_key;
        $rt->type = 0;
        $rt->state = 0;
        if (!$rt->find(true)) {
            throw new Exception('Tried to revoke unknown token');
        }
        if (!$rt->delete()) {
            throw new Exception('Failed to delete revoked token');
        }
    }

    /**
     * Authorize specified OAuth token
     *
     * Authorizes the authorization token specified by $token_key.
     * Throws exceptions in case of error.
     *
     * @param string $token_key The token to be authorized
     *
     * @access public
     **/
    public function authorize_token($token_key) {
        $rt = new Token();
        $rt->tok = $token_key;
        $rt->type = 0;
        $rt->state = 0;
        if (!$rt->find(true)) {
            throw new Exception('Tried to authorize unknown token');
        }
        $orig_rt = clone($rt);
        $rt->state = 1; # Authorized but not used
        if (!$rt->update($orig_rt)) {
            throw new Exception('Failed to authorize token');
        }
    }

    /**
     * Get profile by identifying URI
     *
     * Returns an OMB_Profile object representing the OMB profile identified by
     * $identifier_uri.
     * Returns null if there is no such OMB profile.
     * Throws exceptions in case of other error.
     *
     * @param string $identifier_uri The OMB identifier URI specifying the
     *                               requested profile
     *
     * @access public
     *
     * @return OMB_Profile The corresponding profile
     **/
    public function getProfile($identifier_uri) {
        /* getProfile is only used for remote profiles by libomb.
           @TODO: Make it work with local ones anyway. */
        $remote = Remote_profile::getKV('uri', $identifier_uri);
        if (!$remote) throw new Exception('No such remote profile');
        $profile = Profile::getKV('id', $remote->id);
        if (!$profile) throw new Exception('No profile for remote user');

        require_once dirname(__FILE__) . '/omb.php';
        return profile_to_omb_profile($identifier_uri, $profile);
    }

    /**
     * Save passed profile
     *
     * Stores the OMB profile $profile. Overwrites an existing entry.
     * Throws exceptions in case of error.
     *
     * @param OMB_Profile $profile   The OMB profile which should be saved
     *
     * @access public
     **/
    public function saveProfile($omb_profile) {
        if (common_profile_url($omb_profile->getNickname()) ==
                                                $omb_profile->getProfileURL()) {
            throw new Exception('Not implemented');
        } else {
            $remote = Remote_profile::getKV('uri', $omb_profile->getIdentifierURI());

            if ($remote) {
                $exists = true;
                $profile = Profile::getKV($remote->id);
                $orig_remote = clone($remote);
                $orig_profile = clone($profile);
                // XXX: compare current postNotice and updateProfile URLs to the ones
                // stored in the DB to avoid (possibly...) above attack
            } else {
                $exists = false;
                $remote = new Remote_profile();
                $remote->uri = $omb_profile->getIdentifierURI();
                $profile = new Profile();
            }

            $profile->nickname = $omb_profile->getNickname();
            $profile->profileurl = $omb_profile->getProfileURL();

            $fullname = $omb_profile->getFullname();
            $profile->fullname = is_null($fullname) ? '' : $fullname;
            $homepage = $omb_profile->getHomepage();
            $profile->homepage = is_null($homepage) ? '' : $homepage;
            $bio = $omb_profile->getBio();
            $profile->bio = is_null($bio) ? '' : $bio;
            $location = $omb_profile->getLocation();
            $profile->location = is_null($location) ? '' : $location;

            if ($exists) {
                $profile->update($orig_profile);
            } else {
                $profile->created = DB_DataObject_Cast::dateTime(); # current time
                $id = $profile->insert();
                if (!$id) {
                    // TRANS: Exception thrown when creating a new profile fails in OAuth store.
                    throw new Exception(_('Error inserting new profile.'));
                }
                $remote->id = $id;
            }

            $avatar_url = $omb_profile->getAvatarURL();
            if ($avatar_url) {
                if (!$this->add_avatar($profile, $avatar_url)) {
                    // TRANS: Exception thrown when creating a new avatar fails in OAuth store.
                    throw new Exception(_('Error inserting avatar.'));
                }
            } else {
                Avatar::deleteFromProfile($profile);
            }

            if ($exists) {
                if (!$remote->update($orig_remote)) {
                    // TRANS: Exception thrown when updating a remote profile fails in OAuth store.
                    throw new Exception(_('Error updating remote profile.'));
                }
            } else {
                $remote->created = DB_DataObject_Cast::dateTime(); # current time
                if (!$remote->insert()) {
                    // TRANS: Exception thrown when creating a remote profile fails in OAuth store.
                    throw new Exception(_('Error inserting remote profile.'));
                }
            }
        }
    }

    function add_avatar($profile, $url)
    {
        $temp_filename = tempnam(sys_get_temp_dir(), 'listener_avatar');
        try {
            copy($url, $temp_filename);
            $imagefile = new ImageFile($profile->id, $temp_filename);
            $filename = Avatar::filename($profile->id,
                                         image_type_to_extension($imagefile->type),
                                         null,
                                         common_timestamp());
            rename($temp_filename, Avatar::path($filename));
        } catch (Exception $e) {
            unlink($temp_filename);
            throw $e;
        }
        return $profile->setOriginal($filename);
    }

    /**
     * Save passed notice
     *
     * Stores the OMB notice $notice. The datastore may change the passed notice.
     * This might by neccessary for URIs depending on a database key. Note that
     * it is the user’s duty to present a mechanism for his OMB_Datastore to
     * appropriately change his OMB_Notice.
     * Throws exceptions in case of error.
     *
     * @param OMB_Notice $notice The OMB notice which should be saved
     *
     * @access public
     **/
    public function saveNotice(&$omb_notice) {
        if (Notice::getKV('uri', $omb_notice->getIdentifierURI())) {
            // TRANS: Exception thrown when a notice is denied because it has been sent before.
            throw new Exception(_('Duplicate notice.'));
        }
        $author_uri = $omb_notice->getAuthor()->getIdentifierURI();
        common_log(LOG_DEBUG, $author_uri, __FILE__);
        $author = Remote_profile::getKV('uri', $author_uri);
        if (!$author) {
            $author = User::getKV('uri', $author_uri);
        }
        if (!$author) {
            throw new Exception('No such user.');
        }

        common_log(LOG_DEBUG, print_r($author, true), __FILE__);

        $notice = Notice::saveNew($author->id,
                                  $omb_notice->getContent(),
                                  'omb',
                                  array('is_local' => Notice::REMOTE,
                                        'uri' => $omb_notice->getIdentifierURI()));

    }

    /**
     * Get subscriptions of a given profile
     *
     * Returns an array containing subscription informations for the specified
     * profile. Every array entry should in turn be an array with keys
     *   'uri´: The identifier URI of the subscriber
     *   'token´: The subscribe token
     *   'secret´: The secret token
     * Throws exceptions in case of error.
     *
     * @param string $subscribed_user_uri The OMB identifier URI specifying the
     *                                    subscribed profile
     *
     * @access public
     *
     * @return mixed An array containing the subscriptions or 0 if no
     *               subscription has been found.
     **/
    public function getSubscriptions($subscribed_user_uri) {
        $sub = new Subscription();

        $user = $this->_getAnyProfile($subscribed_user_uri);

        $sub->subscribed = $user->id;

        if (!$sub->find(true)) {
            return array();
        }

        /* Since we do not use OMB_Service_Provider’s action methods, there
           is no need to actually return the subscriptions. */
        return 1;
    }

    private function _getAnyProfile($uri)
    {
        $user = Remote_profile::getKV('uri', $uri);
        if (!$user) {
            $user = User::getKV('uri', $uri);
        }
        if (!$user) {
            throw new Exception('No such user.');
        }
        return $user;
    }

    /**
     * Delete a subscription
     *
     * Deletes the subscription from $subscriber_uri to $subscribed_user_uri.
     * Throws exceptions in case of error.
     *
     * @param string $subscriber_uri      The OMB identifier URI specifying the
     *                                    subscribing profile
     *
     * @param string $subscribed_user_uri The OMB identifier URI specifying the
     *                                    subscribed profile
     *
     * @access public
     **/
    public function deleteSubscription($subscriber_uri, $subscribed_user_uri)
    {
        $sub = new Subscription();

        $subscribed = $this->_getAnyProfile($subscribed_user_uri);
        $subscriber = $this->_getAnyProfile($subscriber_uri);

        $sub->subscribed = $subscribed->id;
        $sub->subscriber = $subscriber->id;

        $sub->delete();
    }

    /**
     * Save a subscription
     *
     * Saves the subscription from $subscriber_uri to $subscribed_user_uri.
     * Throws exceptions in case of error.
     *
     * @param string     $subscriber_uri      The OMB identifier URI specifying
     *                                        the subscribing profile
     *
     * @param string     $subscribed_user_uri The OMB identifier URI specifying
     *                                        the subscribed profile
     * @param OAuthToken $token               The access token
     *
     * @access public
     **/
    public function saveSubscription($subscriber_uri, $subscribed_user_uri,
                                                                       $token)
    {
        $sub = new Subscription();

        $subscribed = $this->_getAnyProfile($subscribed_user_uri);
        $subscriber = $this->_getAnyProfile($subscriber_uri);

        if (!$subscriber->hasRight(Right::SUBSCRIBE)) {
            common_log(LOG_INFO, __METHOD__ . ": remote subscriber banned ($subscriber_uri subbing to $subscribed_user_uri)");
            // TRANS: Error message displayed to a banned user when they try to subscribe.
            return _('You have been banned from subscribing.');
        }

        $sub->subscribed = $subscribed->id;
        $sub->subscriber = $subscriber->id;

        $sub_exists = $sub->find(true);

        if ($sub_exists) {
            $orig_sub = clone($sub);
        } else {
            $sub->created = DB_DataObject_Cast::dateTime();
        }

        $sub->token  = $token->key;
        $sub->secret = $token->secret;

        if ($sub_exists) {
            $result = $sub->update($orig_sub);
        } else {
            $result = $sub->insert();
        }

        if (!$result) {
            common_log_db_error($sub, ($sub_exists) ? 'UPDATE' : 'INSERT', __FILE__);
            // TRANS: Exception thrown when creating a new subscription fails in OAuth store.
            throw new Exception(_('Could not insert new subscription.'));
            return;
        }

        /* Notify user, if necessary. */

        if ($subscribed instanceof User) {
            mail_subscribe_notify_profile($subscribed,
                                          Profile::getKV($subscriber->id));
        }
    }
}
