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

require_once 'constants.php';
require_once 'helper.php';
require_once 'notice.php';
require_once 'remoteserviceexception.php';

/**
 * OMB service realization
 *
 * This class realizes a complete, simple OMB service.
 */
class OMB_Service_Provider
{
    protected $user; /* An OMB_Profile representing the user */
    protected $datastore; /* AN OMB_Datastore */

    protected $remote_user; /* An OMB_Profile representing the remote user
                               during the authorization process */

    protected $oauth_server; /* An OAuthServer; should only be accessed via
                                getOAuthServer. */

    /**
     * Initialize an OMB_Service_Provider object
     *
     * Constructs an OMB_Service_Provider instance that provides OMB services
     * referring to a particular user.
     *
     * @param OMB_Profile   $user         An OMB_Profile; mandatory for XRDS
     *                                    output, user auth handling and OMB
     *                                    action performing
     * @param OMB_Datastore $datastore    An OMB_Datastore; mandatory for
     *                                    everything but XRDS output
     * @param OAuthServer   $oauth_server An OAuthServer; used for token writing
     *                                    and OMB action handling; will use
     *                                    default value if not set
     *
     * @access public
     */
    public function __construct ($user = null, $datastore = null,
                                 $oauth_server = null)
    {
        $this->user         = $user;
        $this->datastore    = $datastore;
        $this->oauth_server = $oauth_server;
    }

    /**
     * Return the remote user during user authorization
     *
     * Returns an OMB_Profile representing the remote user during the user
     * authorization request.
     *
     * @return OMB_Profile The remote user
     */
    public function getRemoteUser()
    {
        return $this->remote_user;
    }

    /**
     * Write a XRDS document
     *
     * Writes a XRDS document specifying the OMB service. Optionally uses a
     * given object of a class implementing OMB_XRDS_Writer for output. Else
     * OMB_Plain_XRDS_Writer is used.
     *
     * @param OMB_XRDS_Mapper $xrds_mapper An object mapping actions to URLs
     * @param OMB_XRDS_Writer $xrds_writer Optional; The OMB_XRDS_Writer used to
     *                                     write the XRDS document
     *
     * @access public
     *
     * @return mixed Depends on the used OMB_XRDS_Writer; OMB_Plain_XRDS_Writer
     *               returns nothing.
     */
    public function writeXRDS($xrds_mapper, $xrds_writer = null)
    {
        if ($xrds_writer == null) {
                require_once 'plain_xrds_writer.php';
                $xrds_writer = new OMB_Plain_XRDS_Writer();
        }
        return $xrds_writer->writeXRDS($this->user, $xrds_mapper);
    }

    /**
     * Echo a request token
     *
     * Outputs an unauthorized request token for the query found in $_GET or
     * $_POST.
     *
     * @access public
     */
    public function writeRequestToken()
    {
        OMB_Helper::removeMagicQuotesFromRequest();
        echo $this->getOAuthServer()->fetch_request_token(
                                                  OAuthRequest::from_request());
    }

    /**
     * Handle an user authorization request.
     *
     * Parses an authorization request. This includes OAuth and OMB
     * verification.
     * Throws exceptions on failures. Returns an OMB_Profile object representing
     * the remote user.
     *
     * The OMB_Profile passed to the constructor of OMB_Service_Provider should
     * not represent the user specified in the authorization request, but the
     * one currently logged in to the service. This condition being satisfied,
     * handleUserAuth will check whether the listener specified in the request
     * is identical to the logged in user.
     *
     * @access public
     *
     * @return OMB_Profile The profile of the soon-to-be subscribed, i. e.
     *                     remote user
     */
    public function handleUserAuth()
    {
        OMB_Helper::removeMagicQuotesFromRequest();

        /* Verify the request token. */

        $this->token = $this->datastore->lookup_token(null, "request",
                                                      $_GET['oauth_token']);
        if (is_null($this->token)) {
            throw new OAuthException('The given request token has not been ' .
                                     'issued by this service.');
        }

        /* Verify the OMB part. */

        if ($_GET['omb_version'] !== OMB_VERSION) {
            throw OMB_RemoteServiceException::forRequest(OAUTH_ENDPOINT_AUTHORIZE,
                                                         'Wrong OMB version ' .
                                                         $_GET['omb_version']);
        }

        if ($_GET['omb_listener'] !== $this->user->getIdentifierURI()) {
            throw OMB_RemoteServiceException::forRequest(OAUTH_ENDPOINT_AUTHORIZE,
                                                         'Wrong OMB listener ' .
                                                         $_GET['omb_listener']);
        }

        foreach (array('omb_listenee', 'omb_listenee_profile',
                       'omb_listenee_nickname', 'omb_listenee_license') as $param) {
            if (!isset($_GET[$param]) || is_null($_GET[$param])) {
                throw OMB_RemoteServiceException::forRequest(
                                       OAUTH_ENDPOINT_AUTHORIZE,
                                       "Required parameter '$param' not found");
            }
        }

        /* Store given callback for later use. */
        if (isset($_GET['oauth_callback']) && $_GET['oauth_callback'] !== '') {
            $this->callback = $_GET['oauth_callback'];
            if (!OMB_Helper::validateURL($this->callback)) {
                throw OMB_RemoteServiceException::forRequest(
                                        OAUTH_ENDPOINT_AUTHORIZE,
                                        'Invalid callback URL specified');
            }
        }
        $this->remote_user = OMB_Profile::fromParameters($_GET, 'omb_listenee');

        return $this->remote_user;
    }

    /**
     * Continue the OAuth dance after user authorization
     *
     * Performs the appropriate actions after user answered the authorization
     * request.
     *
     * @param bool $accepted Whether the user granted authorization
     *
     * @access public
     *
     * @return array A two-component array with the values:
     *                 - callback The callback URL or null if none given
     *                 - token    The authorized request token or null if not
     *                            authorized.
     */
    public function continueUserAuth($accepted)
    {
        $callback = $this->callback;
        if (!$accepted) {
            $this->datastore->revoke_token($this->token->key);
            $this->token = null;

        } else {
            $this->datastore->authorize_token($this->token->key);
            $this->datastore->saveProfile($this->remote_user);
            $this->datastore->saveSubscription($this->user->getIdentifierURI(),
                                         $this->remote_user->getIdentifierURI(),
                                         $this->token);

            if (!is_null($this->callback)) {
                /* Callback wants to get some informations as well. */
                $params = $this->user->asParameters('omb_listener', false);

                $params['oauth_token'] = $this->token->key;
                $params['omb_version'] = OMB_VERSION;

                $callback .= (parse_url($this->callback, PHP_URL_QUERY) ? '&' : '?');
                foreach ($params as $k => $v) {
                    $callback .= OAuthUtil::urlencode_rfc3986($k) . '=' .
                                 OAuthUtil::urlencode_rfc3986($v) . '&';
                }
            }
        }
        return array($callback, $this->token);
    }

    /**
     * Echo an access token
     *
     * Outputs an access token for the query found in $_POST. OMB 0.1 specifies
     * that the access token request has to be a POST even if OAuth allows GET
     * as well.
     *
     * @access public
     */
    public function writeAccessToken()
    {
        OMB_Helper::removeMagicQuotesFromRequest();
        echo $this->getOAuthServer()->fetch_access_token(
                                            OAuthRequest::from_request('POST'));
    }

    /**
     * Handle an updateprofile request
     *
     * Handles an updateprofile request posted to this service. Updates the
     * profile through the OMB_Datastore.
     *
     * @access public
     *
     * @return OMB_Profile The updated profile
     */
    public function handleUpdateProfile()
    {
        list($req, $profile) = $this->handleOMBRequest(OMB_ENDPOINT_UPDATEPROFILE);
        $profile->updateFromParameters($req->get_parameters(), 'omb_listenee');
        $this->datastore->saveProfile($profile);
        $this->finishOMBRequest();
        return $profile;
    }

    /**
     * Handle a postnotice request
     *
     * Handles a postnotice request posted to this service. Saves the notice
     * through the OMB_Datastore.
     *
     * @access public
     *
     * @return OMB_Notice The received notice
     */
    public function handlePostNotice()
    {
        list($req, $profile) = $this->handleOMBRequest(OMB_ENDPOINT_POSTNOTICE);

        $notice = OMB_Notice::fromParameters($profile, $req->get_parameters());
        $this->datastore->saveNotice($notice);
        $this->finishOMBRequest();

        return $notice;
    }

    /**
     * Handle an OMB request
     *
     * Performs common OMB request handling.
     *
     * @param string $uri The URI defining the OMB endpoint being served
     *
     * @access protected
     *
     * @return array(OAuthRequest, OMB_Profile)
     */
    protected function handleOMBRequest($uri)
    {
        OMB_Helper::removeMagicQuotesFromRequest();
        $req      = OAuthRequest::from_request('POST');
        $listenee = $req->get_parameter('omb_listenee');

        try {
            list($consumer, $token) = $this->getOAuthServer()->verify_request($req);
        } catch (OAuthException $e) {
            header('HTTP/1.1 403 Forbidden');
            throw OMB_RemoteServiceException::forRequest($uri,
                                       'Revoked accesstoken for ' . $listenee);
        }

        $version = $req->get_parameter('omb_version');
        if ($version !== OMB_VERSION) {
            header('HTTP/1.1 400 Bad Request');
            throw OMB_RemoteServiceException::forRequest($uri,
                                               'Wrong OMB version ' . $version);
        }

        $profile = $this->datastore->getProfile($listenee);
        if (is_null($profile)) {
            header('HTTP/1.1 400 Bad Request');
            throw OMB_RemoteServiceException::forRequest($uri,
                                         'Unknown remote profile ' . $listenee);
        }

        $subscribers = $this->datastore->getSubscriptions($listenee);
        if (count($subscribers) === 0) {
            header('HTTP/1.1 403 Forbidden');
            throw OMB_RemoteServiceException::forRequest($uri,
                                              'No subscriber for ' . $listenee);
        }

        return array($req, $profile);
    }

    /**
     * Finishes an OMB request handling
     *
     * Performs common OMB request handling finishing.
     *
     * @access protected
     */
    protected function finishOMBRequest()
    {
        header('HTTP/1.1 200 OK');
        header('Content-type: text/plain');
        /* There should be no clutter but the version. */
        echo "omb_version=" . OMB_VERSION;
    }

    /**
     * Return an OAuthServer
     *
     * Checks whether the OAuthServer is null. If so, initializes it with a
     * default value. Returns the OAuth server.
     *
     * @access protected
     */
    protected function getOAuthServer()
    {
        if (is_null($this->oauth_server)) {
            $this->oauth_server = new OAuthServer($this->datastore);
            $this->oauth_server->add_signature_method(
                                          new OAuthSignatureMethod_HMAC_SHA1());
        }
        return $this->oauth_server;
    }

    /**
     * Publish a notice
     *
     * Posts an OMB notice. This includes storing the notice and posting it to
     * subscribed users.
     *
     * @param OMB_Notice $notice The new notice
     *
     * @access public
     *
     * @return array An array mapping subscriber URIs to the exception posting
     *               to them has raised; Empty array if no exception occured
     */
    public function postNotice($notice)
    {
        $uri = $this->user->getIdentifierURI();

        /* $notice is passed by reference and may change. */
        $this->datastore->saveNotice($notice);
        $subscribers = $this->datastore->getSubscriptions($uri);

        /* No one to post to. */
        if (is_null($subscribers)) {
            return array();
        }

        require_once 'service_consumer.php';

        $err = array();
        foreach ($subscribers as $subscriber) {
            try {
                $service = new OMB_Service_Consumer($subscriber['uri'], $uri,
                                                    $this->datastore);
                $service->setToken($subscriber['token'], $subscriber['secret']);
                $service->postNotice($notice);
            } catch (Exception $e) {
                $err[$subscriber['uri']] = $e;
                continue;
            }
        }
        return $err;
    }

    /**
     * Publish a profile update
     *
     * Posts the current profile as an OMB profile update. This includes
     * updating the stored profile and posting it to subscribed users.
     *
     * @access public
     *
     * @return array An array mapping subscriber URIs to the exception posting
     *               to them has raised; Empty array if no exception occured
     */
    public function updateProfile()
    {
        $uri = $this->user->getIdentifierURI();

        $this->datastore->saveProfile($this->user);
        $subscribers = $this->datastore->getSubscriptions($uri);

        /* No one to post to. */
        if (is_null($subscribers)) {
                return array();
        }

        require_once 'service_consumer.php';

        $err = array();
        foreach ($subscribers as $subscriber) {
            try {
                $service = new OMB_Service_Consumer($subscriber['uri'], $uri,
                                                    $this->datastore);
                $service->setToken($subscriber['token'], $subscriber['secret']);
                $service->updateProfile($this->user);
            } catch (Exception $e) {
                $err[$subscriber['uri']] = $e;
                continue;
            }
        }
        return $err;
    }
}
