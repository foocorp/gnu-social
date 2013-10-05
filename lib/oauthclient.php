<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for doing OAuth calls as a consumer
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
 * @category  Action
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once 'OAuth.php';

/**
 * Exception wrapper for cURL errors
 *
 * @category Integration
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class OAuthClientException extends Exception
{
}

/**
 * Base class for doing OAuth calls as a consumer
 *
 * @category Integration
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class OAuthClient
{
    var $consumer;
    var $token;

    /**
     * Constructor
     *
     * Can be initialized with just consumer key and secret for requesting new
     * tokens or with additional request token or access token
     *
     * @param string $consumer_key       consumer key
     * @param string $consumer_secret    consumer secret
     * @param string $oauth_token        user's token
     * @param string $oauth_token_secret user's secret
     *
     * @return nothing
     */
    function __construct($consumer_key, $consumer_secret,
                         $oauth_token = null, $oauth_token_secret = null)
    {
        $this->sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
        $this->consumer    = new OAuthConsumer($consumer_key, $consumer_secret);
        $this->token       = null;

        if (isset($oauth_token) && isset($oauth_token_secret)) {
            $this->token = new OAuthToken($oauth_token, $oauth_token_secret);
        }
    }

    /**
     * Gets a request token from the given url
     *
     * @param string $url      OAuth endpoint for grabbing request tokens
     * @param string $callback authorized request token callback
     *
     * @return OAuthToken $token the request token
     */
    function getRequestToken($url, $callback = null)
    {
        $params = null;

        if (!is_null($callback)) {
            $params['oauth_callback'] = $callback;
        }

        $response = $this->oAuthGet($url, $params);

        $arr = array();
        parse_str($response, $arr);

        $token   = $arr['oauth_token'];
        $secret  = $arr['oauth_token_secret'];
        $confirm = $arr['oauth_callback_confirmed'];

        if (isset($token) && isset($secret)) {

            $token = new OAuthToken($token, $secret);

            if (isset($confirm)) {
                if ($confirm == 'true') {
                    return $token;
                } else {
                    throw new OAuthClientException(
                        'Callback was not confirmed by remote OAuth side.'
                    );
                }
            }
            return $token;
        } else {
            throw new OAuthClientException(
                'Could not get a request token from remote OAuth side.'
            );
        }
    }

    /**
     * Builds a link that can be redirected to in order to
     * authorize a request token.
     *
     * @param string     $url            endpoint for authorizing request tokens
     * @param OAuthToken $request_token  the request token to be authorized
     *
     * @return string $authorize_url the url to redirect to
     */
    function getAuthorizeLink($url, $request_token)
    {
        $authorize_url = $url . '?oauth_token=' .
            $request_token->key;

        return $authorize_url;
    }

    /**
     * Fetches an access token
     *
     * @param string $url      OAuth endpoint for exchanging authorized request tokens
     *                         for access tokens
     * @param string $verifier 1.0a verifier
     *
     * @return OAuthToken $token the access token
     */
    function getAccessToken($url, $verifier = null)
    {
        $params = array();

        if (!is_null($verifier)) {
            $params['oauth_verifier'] = $verifier;
        }

        $response = $this->oAuthPost($url, $params);

        $arr = array();
        parse_str($response, $arr);

        $token  = $arr['oauth_token'];
        $secret = $arr['oauth_token_secret'];

        if (isset($token) && isset($secret)) {
            $token = new OAuthToken($token, $secret);
            return $token;
        } else {
            throw new OAuthClientException(
                'Could not get a access token from remote OAuth side.'
            );
        }
    }

    /**
     * Use HTTP GET to make a signed OAuth requesta
     *
     * @param string $url    OAuth request token endpoint
     * @param array  $params additional parameters
     *
     * @return mixed the request
     */
    function oAuthGet($url, $params = null)
    {
        $request = OAuthRequest::from_consumer_and_token($this->consumer,
            $this->token, 'GET', $url, $params);
        $request->sign_request($this->sha1_method,
            $this->consumer, $this->token);

        return $this->httpRequest($request->to_url());
    }

    /**
     * Use HTTP POST to make a signed OAuth request
     *
     * @param string $url    OAuth endpoint
     * @param array  $params additional post parameters
     *
     * @return mixed the request
     */
    function oAuthPost($url, $params = null)
    {
        $request = OAuthRequest::from_consumer_and_token($this->consumer,
            $this->token, 'POST', $url, $params);
        $request->sign_request($this->sha1_method,
            $this->consumer, $this->token);

        return $this->httpRequest($request->get_normalized_http_url(),
            $request->to_postdata());
    }

    /**
     * Make a HTTP request.
     *
     * @param string $url    Where to make the
     * @param array  $params post parameters
     *
     * @return mixed the request
     */
    function httpRequest($url, $params = null)
    {
        $request = new HTTPClient($url);
        $request->setConfig(array(
            'connect_timeout' => 120,
            'timeout' => 120,
            'follow_redirects' => true,
            'ssl_verify_peer' => false,
            'ssl_verify_host' => false
        ));

        // Twitter was strict about accepting invalid "Expect" headers
        // between 2008ish and October 2012. Caused "417 Expectation failed"
        //$request->setHeader('Expect', '');

        if (isset($params)) {
            $request->setMethod(HTTP_Request2::METHOD_POST);
            $request->setBody($params);
        }

        try {
            $response = $request->send();
            $code = $response->getStatus();
            if ($code < 200 || $code >= 400) {
                throw new OAuthClientException($response->getBody(), $code);
            }
            return $response->getBody();
        } catch (Exception $e) {
            throw new OAuthClientException($e->getMessage(), $e->getCode());
        }
    }

}
