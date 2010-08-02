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

require_once 'Validate.php';
require_once 'Auth/Yadis/Yadis.php';
require_once 'OAuth.php';
require_once 'constants.php';
require_once 'helper.php';
require_once 'omb_yadis_xrds.php';
require_once 'profile.php';
require_once 'remoteserviceexception.php';
require_once 'unsupportedserviceexception.php';

/**
 * OMB service representation
 *
 * This class represents a complete remote OMB service. It provides discovery
 * and execution of the service’s methods.
 */
class OMB_Service_Consumer
{
    protected $url; /* The service URL */
    protected $services; /* An array of strings mapping service URI to
                            service URL */

    protected $token; /* An OAuthToken */

    protected $listener_uri; /* The URI identifying the listener, i. e. the
                                remote user. */

    protected $listenee_uri; /* The URI identifying the listenee, i. e. the
                                local user during an auth request. */

    /**
     * According to OAuth Core 1.0, an user authorization request is no
     * full-blown OAuth request. nonce, timestamp, consumer_key and signature
     * are not needed in this step. See http://laconi.ca/trac/ticket/827 for
     * more informations.
     *
     * Since Laconica up to version 0.7.2 performs a full OAuth request check, a
     * correct request would fail.
     */
    public $performLegacyAuthRequest = true;

    /* Helper stuff we are going to need. */
    protected $fetcher;
    protected $oauth_consumer;
    protected $datastore;

    /**
     * Constructor for OMB_Service_Consumer
     *
     * Initializes an OMB_Service_Consumer object representing the OMB service
     * specified by $service_url. Performs a complete service discovery using
     * Yadis.
     * Throws OMB_UnsupportedServiceException if XRDS file does not specify a
     * complete OMB service.
     *
     * @param string        $service_url  The URL of the service
     * @param string        $consumer_url An URL representing the consumer
     * @param OMB_Datastore $datastore    An instance of a class implementing
     *                                    OMB_Datastore
     *
     * @access public
     */
    public function __construct ($service_url, $consumer_url, $datastore)
    {
        $this->url            = $service_url;
        $this->fetcher        = Auth_Yadis_Yadis::getHTTPFetcher();
        $this->datastore      = $datastore;
        $this->oauth_consumer = new OAuthConsumer($consumer_url, '');

        $xrds = OMB_Yadis_XRDS::fromYadisURL($service_url, $this->fetcher);

        /* Detect our services. This performs a validation as well, since
            getService und getXRD throw exceptions on failure. */
        $this->services = array();

        foreach (array(OAUTH_DISCOVERY => OMB_Helper::$OAUTH_SERVICES,
                       OMB_VERSION     => OMB_Helper::$OMB_SERVICES)
                 as    $service_root   => $targetservices) {
            $uris = $xrds->getService($service_root)->getURIs();
            $xrd  = $xrds->getXRD($uris[0]);
            foreach ($targetservices as $targetservice) {
                $yadis_service = $xrd->getService($targetservice);
                if ($targetservice == OAUTH_ENDPOINT_REQUEST) {
                        $localid            =
                                   $yadis_service->getElements('xrd:LocalID');
                        $this->listener_uri =
                                   $yadis_service->parser->content($localid[0]);
                }
                $uris                           = $yadis_service->getURIs();
                $this->services[$targetservice] = $uris[0];
            }
        }
    }

    /**
     * Get the handler URI for a service
     *
     * Returns the URI the remote web service has specified for the given
     * service.
     *
     * @param string $service The URI identifying the service
     *
     * @access public
     *
     * @return string The service handler URI
     */
    public function getServiceURI($service)
    {
        return $this->services[$service];
    }

    /**
     * Get the remote user’s URI
     *
     * Returns the URI of the remote user, i. e. the listener.
     *
     * @access public
     *
     * @return string The remote user’s URI
     */
    public function getRemoteUserURI()
    {
        return $this->listener_uri;
    }

    /**
     * Get the listenee’s URI
     *
     * Returns the URI of the user being subscribed to, i. e. the local user.
     *
     * @access public
     *
     * @return string The local user’s URI
     */
    public function getListeneeURI()
    {
        return $this->listenee_uri;
    }

    /**
     * Request a request token
     *
     * Performs a token request on the service. Returns an OAuthToken on success.
     * Throws an exception if the request fails.
     *
     * @access public
     *
     * @return OAuthToken An unauthorized request token
     */
    public function requestToken()
    {
        /* Set the token to null just in case the user called setToken. */
        $this->token = null;

        $result = $this->performAction(OAUTH_ENDPOINT_REQUEST,
                                       array('omb_listener' => $this->listener_uri));
        if ($result->status != 200) {
            throw OMB_RemoteServiceException::fromYadis(OAUTH_ENDPOINT_REQUEST,
                                                        $result);
        }
        parse_str($result->body, $return);
        if (!isset($return['oauth_token']) ||
            !isset($return['oauth_token_secret'])) {
            throw OMB_RemoteServiceException::fromYadis(OAUTH_ENDPOINT_REQUEST,
                                                        $result);
        }
        $this->setToken($return['oauth_token'], $return['oauth_token_secret']);
        return $this->token;
    }

    /**
     * Request authorization
     *
     * Returns an URL which equals to an authorization request. The end user
     * should be redirected to this location to perform authorization.
     * The $finish_url should be a local resource which invokes
     * OMB_Consumer::finishAuthorization on request.
     *
     * @param OMB_Profile $profile    An OMB_Profile object representing the
     *                                soon-to-be subscribed (i. e. local) user
     * @param string      $finish_url Target location after successful
     *                                authorization
     *
     * @access public
     *
     * @return string An URL representing an authorization request
     */
    public function requestAuthorization($profile, $finish_url)
    {
        if ($this->performLegacyAuthRequest) {
            $params                   = $profile->asParameters('omb_listenee',
                                                               false);
            $params['omb_listener']   = $this->listener_uri;
            $params['oauth_callback'] = $finish_url;

            $url = $this->prepareAction(OAUTH_ENDPOINT_AUTHORIZE, $params,
                                        'GET')->to_url();
        } else {
            $params = array('oauth_callback' => $finish_url,
                            'oauth_token'    => $this->token->key,
                            'omb_version'    => OMB_VERSION,
                            'omb_listener'   => $this->listener_uri);

            $params = array_merge($profile->asParameters('omb_listenee', false),
                                  $params);

            /* Build result URL. */
            $url = $this->services[OAUTH_ENDPOINT_AUTHORIZE] .
                   (strrpos($url, '?') === false ? '?' : '&');
            foreach ($params as $k => $v) {
                $url .= OAuthUtil::urlencode_rfc3986($k) . '=' .
                        OAuthUtil::urlencode_rfc3986($v) . '&';
            }
        }

        $this->listenee_uri = $profile->getIdentifierURI();

        return $url;
    }

    /**
     * Finish authorization
     *
     * Finish the subscription process by converting the received and authorized
     * request token into an access token. After that, the subscriber’s profile
     * and the subscription are stored in the database.
     * Expects an OAuthRequest in query parameters.
     * Throws exceptions on failure.
     *
     * @access public
     */
    public function finishAuthorization()
    {
        OMB_Helper::removeMagicQuotesFromRequest();
        $req = OAuthRequest::from_request();
        if ($req->get_parameter('oauth_token') != $this->token->key) {
            /* That’s not the token I wanted to get authorized. */
            throw new OAuthException('The authorized token does not equal ' .
                                     'the submitted token.');
        }

        if ($req->get_parameter('omb_version') != OMB_VERSION) {
            throw new OMB_RemoteServiceException('The remote service uses an ' .
                                                 'unsupported OMB version');
        }

        /* Construct the profile to validate it. */

        /* Fix OMB bug. Listener URI is not passed. */
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $params = $_POST;
        } else {
            $params = $_GET;
        }
        $params['omb_listener'] = $this->listener_uri;

        $listener = OMB_Profile::fromParameters($params, 'omb_listener');

        /* Ask the remote service to convert the authorized request token into
           an access token. */

        $result = $this->performAction(OAUTH_ENDPOINT_ACCESS, array());
        if ($result->status != 200) {
            throw new OAuthException('Could not get access token');
        }

        parse_str($result->body, $return);
        if (!isset($return['oauth_token']) ||
            !isset($return['oauth_token_secret'])) {
            throw new OAuthException('Could not get access token');
        }
        $this->setToken($return['oauth_token'], $return['oauth_token_secret']);

        /* Subscription is finished and valid. Now store the new subscriber and
           the subscription in the database. */

        $this->datastore->saveProfile($listener);
        $this->datastore->saveSubscription($this->listener_uri,
                                           $this->listenee_uri,
                                           $this->token);
    }

    /**
     * Return the URI identifying the listener
     *
     * Returns the URI for the OMB user who tries to subscribe or already has
     * subscribed our user. This method is a workaround for a serious OMB flaw:
     * The Listener URI is not passed in the finishauthorization call.
     *
     * @access public
     *
     * @return string the listener’s URI
     */
    public function getListenerURI()
    {
        return $this->listener_uri;
    }

    /**
     * Inform the service about a profile update
     *
     * Sends an updated profile to the service.
     *
     * @param OMB_Profile $profile The profile that has changed
     *
     * @access public
     */
    public function updateProfile($profile)
    {
        $params = $profile->asParameters('omb_listenee', true);
        $this->performOMBAction(OMB_ENDPOINT_UPDATEPROFILE, $params,
                                $profile->getIdentifierURI());
    }

    /**
     * Inform the service about a new notice
     *
     * Sends a notice to the service.
     *
     * @param OMB_Notice $notice The notice
     *
     * @access public
     */
    public function postNotice($notice)
    {
        $params                 = $notice->asParameters();
        $params['omb_listenee'] = $notice->getAuthor()->getIdentifierURI();
        $this->performOMBAction(OMB_ENDPOINT_POSTNOTICE, $params,
                                $params['omb_listenee']);
    }

    /**
     * Set the token member variable
     *
     * Initializes the token based on given token and secret token.
     *
     * @param string $token  The token
     * @param string $secret The secret token
     *
     * @access public
     */
    public function setToken($token, $secret)
    {
        $this->token = new OAuthToken($token, $secret);
    }

    /**
     * Prepare an OAuthRequest object
     *
     * Creates an OAuthRequest object mapping the request specified by the
     * parameters.
     *
     * @param string $action_uri The URI specifying the target service
     * @param array  $params     Additional parameters for the service call
     * @param string $method     The HTTP method used to call the service
     *                           ('POST' or 'GET', usually)
     *
     * @access protected
     *
     * @return OAuthRequest the prepared request
     */
    protected function prepareAction($action_uri, $params, $method)
    {
        $url = $this->services[$action_uri];

        $url_params = array();
        parse_str(parse_url($url, PHP_URL_QUERY), $url_params);

        /* Add OMB version. */
        $url_params['omb_version'] = OMB_VERSION;

        /* Add user-defined parameters. */
        $url_params = array_merge($url_params, $params);

        $req = OAuthRequest::from_consumer_and_token($this->oauth_consumer,
                                                     $this->token, $method,
                                                     $url, $url_params);

        /* Sign the request. */
        $req->sign_request(new OAuthSignatureMethod_HMAC_SHA1(),
                           $this->oauth_consumer, $this->token);

        return $req;
    }

    /**
     * Perform a service call
     *
     * Creates an OAuthRequest object and execute the mapped call as POST
     * request.
     *
     * @param string $action_uri The URI specifying the target service
     * @param array  $params     Additional parameters for the service call
     *
     * @access protected
     *
     * @return Auth_Yadis_HTTPResponse The POST request response
     */
    protected function performAction($action_uri, $params)
    {
        $req = $this->prepareAction($action_uri, $params, 'POST');

        /* Return result page. */
        return $this->fetcher->post($req->get_normalized_http_url(),
                                    $req->to_postdata(), array());
    }

    /**
     * Perform an OMB action
     *
     * Executes an OMB action – as of OMB 0.1, it’s one of updateProfile and
     * postNotice.
     *
     * @param string $action_uri   The URI specifying the target service
     * @param array  $params       Additional parameters for the service call
     * @param string $listenee_uri The URI identifying the local user for whom
     *                             the action is performed
     *
     * @access protected
     */
    protected function performOMBAction($action_uri, $params, $listenee_uri)
    {
        $result = $this->performAction($action_uri, $params);
        if ($result->status == 403) {
            /* The remote user unsubscribed us. */
            $this->datastore->deleteSubscription($this->listener_uri,
                                                 $listenee_uri);
        } else if ($result->status != 200 ||
                   strpos($result->body, 'omb_version=' . OMB_VERSION) === false) {
            /* The server signaled an error or sent an incorrect response. */
            throw OMB_RemoteServiceException::fromYadis($action_uri, $result);
        }
    }
}
?>
