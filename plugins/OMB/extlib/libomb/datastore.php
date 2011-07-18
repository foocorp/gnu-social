<?php
/**
 * This file is part of libomb
 *
 * PHP version 5
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
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
 * @package OMB
 * @author  Adrian Lang <mail@adrianlang.de>
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL 3.0
 * @version 0.1a-20090828
 * @link    http://adrianlang.de/libomb
 */

require_once 'OAuth.php';

/**
 * Data access interface
 *
 * This interface specifies data access methods libomb needs. It should be
 * implemented by libomb users. OMB_Datastore is libomb’s main interface to the
 * application’s data. Objects corresponding to this interface are used in
 * OMB_Service_Provider and OMB_Service_Consumer.
 *
 * Note that it’s implemented as a class since OAuthDataStore is as well a
 * class, though only declaring methods.
 *
 * OMB_Datastore extends OAuthDataStore with two OAuth-related methods for token
 * revoking and authorizing and all OMB-related methods.
 * Refer to OAuth.php for a complete specification of OAuth-related methods.
 *
 * It is the user’s duty to signal and handle errors. libomb does not check
 * return values nor handle exceptions. It is suggested to use exceptions.
 * Note that lookup_token and getProfile return null if the requested object
 * is not available. This is NOT an error and should not raise an exception.
 * Same applies for lookup_nonce which returns a boolean value. These methods
 * may nevertheless throw an exception, for example in case of a storage errors.
 *
 * Most of the parameters passed to these methods are unescaped and unverified
 * user input. Therefore they should be handled with extra care to avoid
 * security problems like SQL injections.
 */
class OMB_Datastore extends OAuthDataStore
{

    /*********
     * OAUTH *
     *********/

    /**
     * Revoke specified OAuth token
     *
     * Revokes the authorization token specified by $token_key.
     * Throws exceptions in case of error.
     *
     * @param string $token_key The key of the token to be revoked
     *
     * @access public
     */
    public function revoke_token($token_key)
    {
        throw new Exception();
    }

    /**
     * Authorize specified OAuth token
     *
     * Authorizes the authorization token specified by $token_key.
     * Throws exceptions in case of error.
     *
     * @param string $token_key The key of the token to be authorized
     *
     * @access public
     */
    public function authorize_token($token_key)
    {
        throw new Exception();
    }

    /*********
     *  OMB  *
     *********/

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
     */
    public function getProfile($identifier_uri)
    {
        throw new Exception();
    }

    /**
     * Save passed profile
     *
     * Stores the OMB profile $profile. Overwrites an existing entry.
     * Throws exceptions in case of error.
     *
     * @param OMB_Profile $profile The OMB profile which should be saved
     *
     * @access public
     */
    public function saveProfile($profile)
    {
        throw new Exception();
    }

    /**
     * Save passed notice
     *
     * Stores the OMB notice $notice. The datastore may change the passed
     * notice. This might by necessary for URIs depending on a database key.
     * Note that it is the user’s duty to present a mechanism for his
     * OMB_Datastore to appropriately change his OMB_Notice.
     * Throws exceptions in case of error.
     *
     * @param OMB_Notice &$notice The OMB notice which should be saved
     *
     * @access public
     */
    public function saveNotice(&$notice)
    {
        throw new Exception();
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
     */
    public function getSubscriptions($subscribed_user_uri)
    {
        throw new Exception();
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
     */
    public function deleteSubscription($subscriber_uri, $subscribed_user_uri)
    {
        throw new Exception();
    }

    /**
     * Save a subscription
     *
     * Saves the subscription from $subscriber_uri to $subscribed_user_uri.
     * Throws exceptions in case of error.
     *
     * @param string     $subscriber_uri      The OMB identifier URI specifying
     *                                            the subscribing profile
     *
     * @param string     $subscribed_user_uri The OMB identifier URI specifying
     *                                            the subscribed profile
     * @param OAuthToken $token               The access token
     *
     * @access public
     */
    public function saveSubscription($subscriber_uri, $subscribed_user_uri,
                                     $token)
    {
        throw new Exception();
    }
}
?>
