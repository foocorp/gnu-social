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
 * Basic client class for Yammer's OAuth/JSON API.
 *
 * @package YammerImportPlugin
 * @author Brion Vibber <brion@status.net>
 */
class SNYammerClient
{
    protected $apiBase = "https://www.yammer.com";
    protected $consumerKey, $consumerSecret;
    protected $token, $tokenSecret, $verifier;

    public function __construct($consumerKey, $consumerSecret, $token=null, $tokenSecret=null)
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->token = $token;
        $this->tokenSecret = $tokenSecret;
    }

    /**
     * Make an HTTP GET request with OAuth headers and return an HTTPResponse
     * with the returned body and codes.
     *
     * @param string $url
     * @return HTTPResponse
     *
     * @throws Exception on low-level network error
     */
    protected function httpGet($url)
    {
        $headers = array('Authorization: ' . $this->authHeader());

        $client = HTTPClient::start();
        return $client->get($url, $headers);
    }

    /**
     * Make an HTTP GET request with OAuth headers and return the response body
     * on success.
     *
     * @param string $url
     * @return string
     *
     * @throws Exception on low-level network or HTTP error
     */
    public function fetchUrl($url)
    {
        $response = $this->httpGet($url);
        if ($response->isOk()) {
            return $response->getBody();
        } else {
            // TRANS: Exeption thrown when an external Yammer system gives an error.
            // TRANS: %1$s is an HTTP error code, %2$s is the error message body.
            throw new Exception(sprintf(_m('Yammer API returned HTTP code %1$s: %2$s'),
                                $response->getStatus(),
                                $response->getBody()));
        }
    }

    /**
     * Make an HTTP hit with OAuth headers and return the response body on success.
     *
     * @param string $path URL chunk for the API method
     * @param array $params
     * @return string
     *
     * @throws Exception on low-level network or HTTP error
     */
    protected function fetchApi($path, $params=array())
    {
        $url = $this->apiBase . '/' . $path;
        if ($params) {
            $url .= '?' . http_build_query($params, null, '&');
        }
        return $this->fetchUrl($url);
    }

    /**
     * Hit the main Yammer API point and decode returned JSON data.
     *
     * @param string $method
     * @param array $params
     * @return array from JSON data
     *
     * @throws Exception for HTTP error or bad JSON return
     */
    public function api($method, $params=array())
    {
        $body = $this->fetchApi("api/v1/$method.json", $params);
        $data = json_decode($body, true);
        if ($data === null) {
            common_log(LOG_ERR, "Invalid JSON response from Yammer API: " . $body);
            // TRANS: Exeption thrown when an external Yammer system an invalid JSON response.
            throw new Exception(_m('Invalid JSON response from Yammer API.'));
        }
        return $data;
    }

    /**
     * Build an Authorization header value from the keys we have available.
     */
    protected function authHeader()
    {
        // token
        // token_secret
        $params = array('realm' => '',
                        'oauth_consumer_key' => $this->consumerKey,
                        'oauth_signature_method' => 'PLAINTEXT',
                        'oauth_timestamp' => time(),
                        'oauth_nonce' => time(),
                        'oauth_version' => '1.0');
        if ($this->token) {
            $params['oauth_token'] = $this->token;
        }
        if ($this->tokenSecret) {
            $params['oauth_signature'] = $this->consumerSecret . '&' . $this->tokenSecret;
        } else {
            $params['oauth_signature'] = $this->consumerSecret . '&';
        }
        if ($this->verifier) {
            $params['oauth_verifier'] = $this->verifier;
        }
        $parts = array_map(array($this, 'authHeaderChunk'), array_keys($params), array_values($params));
        return 'OAuth ' . implode(', ', $parts);
    }

    /**
     * Encode a key-value pair for use in an authentication header.
     *
     * @param string $key
     * @param string $val
     * @return string
     */
    protected function authHeaderChunk($key, $val)
    {
        return urlencode($key) . '="' . urlencode($val) . '"';
    }

    /**
     * Ask the Yammer server for a request token, which can be passed on
     * to authorizeUrl() for the user to start the authentication process.
     *
     * @return array of oauth return data; should contain nice things
     */
    public function requestToken()
    {
        if ($this->token || $this->tokenSecret) {
            // TRANS: Exeption thrown when a trust relationship has already been established.
            throw new Exception(_m('Requesting a token, but already set up with a token.'));
        }
        $data = $this->fetchApi('oauth/request_token');
        $arr = array();
        parse_str($data, $arr);
        return $arr;
    }

    /**
     * Get a final access token from the verifier/PIN code provided to
     * the user from Yammer's auth pages.
     *
     * @return array of oauth return data; should contain nice things
     */
    public function accessToken($verifier)
    {
        $this->verifier = $verifier;
        $data = $this->fetchApi('oauth/access_token');
        $this->verifier = null;
        $arr = array();
        parse_str($data, $arr);
        return $arr;
    }

    /**
     * Give the URL to send users to to authorize a new app setup.
     *
     * @param string $token as returned from accessToken()
     * @return string URL
     */
    public function authorizeUrl($token)
    {
        return $this->apiBase . '/oauth/authorize?oauth_token=' . urlencode($token);
    }

    /**
     * High-level API hit: fetch all messages in the network (up to 20 at a time).
     * Return data is the full JSON array returned, including meta and references
     * sections.
     *
     * The matching messages themselves will be in the 'messages' item within.
     *
     * @param array $options optional set of additional params for the request.
     * @return array
     *
     * @throws Exception on low-level or HTTP error
     */
    public function messages($params=array())
    {
        return $this->api('messages', $params);
    }

    /**
     * High-level API hit: fetch all users in the network (up to 50 at a time).
     * Return data is the full JSON array returned, listing user items.
     *
     * The matching messages themselves will be in the 'users' item within.
     *
     * @param array $options optional set of additional params for the request.
     * @return array of JSON-sourced user data arrays
     *
     * @throws Exception on low-level or HTTP error
     */
    public function users($params=array())
    {
        return $this->api('users', $params);
    }

    /**
     * High-level API hit: fetch all groups in the network (up to 20 at a time).
     * Return data is the full JSON array returned, listing user items.
     *
     * The matching messages themselves will be in the 'users' item within.
     *
     * @param array $options optional set of additional params for the request.
     * @return array of JSON-sourced user data arrays
     *
     * @throws Exception on low-level or HTTP error
     */
    public function groups($params=array())
    {
        return $this->api('groups', $params);
    }
}
