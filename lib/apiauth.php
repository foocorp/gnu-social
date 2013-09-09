<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for API actions that require authentication
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
 * @category  API
 * @package   StatusNet
 * @author    Adrian Lang <mail@adrianlang.de>
 * @author    Brenda Wallace <shiny@cpan.org>
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Dan Moore <dan@moore.cx>
 * @author    Evan Prodromou <evan@status.net>
 * @author    mEDI <medi@milaro.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

/* External API usage documentation. Please update when you change how this method works. */

/*! @page authentication Authentication

    StatusNet supports HTTP Basic Authentication and OAuth for API calls.

    @warning Currently, users who have created accounts without setting a
    password via OpenID, Facebook Connect, etc., cannot use the API until
    they set a password with their account settings panel.

    @section HTTP Basic Auth



    @section OAuth

*/

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apioauth.php';

/**
 * Actions extending this class will require auth
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiAuthAction extends ApiAction
{
    var $auth_user_nickname = null;
    var $auth_user_password = null;

    /**
     * Take arguments for running, looks for an OAuth request,
     * and outputs basic auth header if needed
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */
    function prepare($args)
    {
        parent::prepare($args);

        // NOTE: $this->auth_user has to get set in prepare(), not handle(),
        // because subclasses do stuff with it in their prepares.

        $oauthReq = $this->getOAuthRequest();

        if (!$oauthReq) {
            if ($this->requiresAuth()) {
                $this->checkBasicAuthUser(true);
            } else {
                // Check to see if a basic auth user is there even
                // if one's not required
                $this->checkBasicAuthUser(false);
            }
        } else {
            $this->checkOAuthRequest($oauthReq);
        }

        // NOTE: Make sure we're scoped properly based on the auths!
        if (isset($this->auth_user) && !empty($this->auth_user)) {
            $this->scoped = $this->auth_user->getProfile();
        } else {
            $this->scoped = null;
        }

        // Reject API calls with the wrong access level

        if ($this->isReadOnly($args) == false) {
            if ($this->access != self::READ_WRITE) {
                // TRANS: Client error 401.
                $msg = _('API resource requires read-write access, ' .
                         'but you only have read access.');
                $this->clientError($msg, 401, $this->format);
                exit;
            }
        }

        return true;
    }

    /**
     * Determine whether the request is an OAuth request.
     * This is to avoid doign any unnecessary DB lookups.
     *
     * @return mixed the OAuthRequest or false
     */
    function getOAuthRequest()
    {
        ApiOauthAction::cleanRequest();

        $req  = OAuthRequest::from_request();

        $consumer    = $req->get_parameter('oauth_consumer_key');
        $accessToken = $req->get_parameter('oauth_token');

        // XXX: Is it good enough to assume it's not meant to be an
        // OAuth request if there is no consumer or token? --Z

        if (empty($consumer) || empty($accessToken)) {
            return false;
        }

        return $req;
    }

    /**
     * Verifies the OAuth request signature, sets the auth user
     * and access type (read-only or read-write)
     *
     * @param OAuthRequest $request the OAuth Request
     *
     * @return nothing
     */
    function checkOAuthRequest($request)
    {
        $datastore   = new ApiStatusNetOAuthDataStore();
        $server      = new OAuthServer($datastore);
        $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();

        $server->add_signature_method($hmac_method);

        try {
            $server->verify_request($request);

            $consumer     = $request->get_parameter('oauth_consumer_key');
            $access_token = $request->get_parameter('oauth_token');

            $app = Oauth_application::getByConsumerKey($consumer);

            if (empty($app)) {
                common_log(
                    LOG_WARNING,
                    'API OAuth - Couldn\'t find the OAuth app for consumer key: ' .
                    $consumer
                );
                // TRANS: OAuth exception thrown when no application is found for a given consumer key.
                throw new OAuthException(_('No application for that consumer key.'));
            }

            // set the source attr
            if ($app->name != 'anonymous') {
                $this->source = $app->name;
            }


            $appUser = Oauth_application_user::getKV('token', $access_token);

            if (!empty($appUser)) {
                // If access_type == 0 we have either a request token
                // or a bad / revoked access token

                if ($appUser->access_type != 0) {
                    // Set the access level for the api call
                    $this->access = ($appUser->access_type & Oauth_application::$writeAccess)
                      ? self::READ_WRITE : self::READ_ONLY;

                    // Set the auth user
                    if (Event::handle('StartSetApiUser', array(&$user))) {
                        $user = User::getKV('id', $appUser->profile_id);
                        if (!empty($user)) {
                            if (!$user->hasRight(Right::API)) {
                                // TRANS: Authorization exception thrown when a user without API access tries to access the API.
                                throw new AuthorizationException(_('Not allowed to use API.'));
                            }
                        }
                        $this->auth_user = $user;
                        // FIXME: setting the value returned by common_current_user()
                        // There should probably be a better method for this. common_set_user()
                        // does lots of session stuff.
                        global $_cur;
                        $_cur = $this->auth_user;
                        Event::handle('EndSetApiUser', array($user)); 
                    }

                    $msg = "API OAuth authentication for user '%s' (id: %d) on behalf of " .
                        "application '%s' (id: %d) with %s access.";

                    common_log(
                        LOG_INFO,
                        sprintf(
                            $msg,
                            $this->auth_user->nickname,
                            $this->auth_user->id,
                            $app->name,
                            $app->id,
                            ($this->access = self::READ_WRITE) ? 'read-write' : 'read-only'
                        )
                    );
                } else {
                    // TRANS: OAuth exception given when an incorrect access token was given for a user.
                    throw new OAuthException(_('Bad access token.'));
                }
            } else {
                // Also should not happen.
                // TRANS: OAuth exception given when no user was found for a given token (no token was found).
                throw new OAuthException(_('No user for that token.'));
            }

        } catch (OAuthException $e) {
            $this->logAuthFailure($e->getMessage());
            common_log(LOG_WARNING, 'API OAuthException - ' . $e->getMessage());
            $this->clientError($e->getMessage(), 401, $this->format);
            exit;
        }
    }

    /**
     * Does this API resource require authentication?
     *
     * @return boolean true
     */
    function requiresAuth()
    {
        return true;
    }

    /**
     * Check for a user specified via HTTP basic auth. If there isn't
     * one, try to get one by outputting the basic auth header.
     *
     * @return boolean true or false
     */
    function checkBasicAuthUser($required = true)
    {
        $this->basicAuthProcessHeader();

        $realm = common_config('api', 'realm');

        if (empty($realm)) {
            $realm = common_config('site', 'name') . ' API';
        }

        if (empty($this->auth_user_nickname) && $required) {
            header('WWW-Authenticate: Basic realm="' . $realm . '"');

            // show error if the user clicks 'cancel'
            // TRANS: Client error thrown when authentication fails becaus a user clicked "Cancel".
            $this->clientError(_('Could not authenticate you.'), 401, $this->format);
            exit;

        } else {

            $user = common_check_user($this->auth_user_nickname,
                                      $this->auth_user_password);

            if (Event::handle('StartSetApiUser', array(&$user))) {

                if (!empty($user)) {
                    if (!$user->hasRight(Right::API)) {
                        // TRANS: Authorization exception thrown when a user without API access tries to access the API.
                        throw new AuthorizationException(_('Not allowed to use API.'));
                    }
                    $this->auth_user = $user;
                }

                Event::handle('EndSetApiUser', array($user));
            }

            // By default, basic auth users have rw access
            $this->access = self::READ_WRITE;

            if (empty($this->auth_user) && ($required || isset($_SERVER['PHP_AUTH_USER']))) {
                $msg = sprintf(
                    "basic auth nickname = %s",
                    $this->auth_user_nickname
                );
                $this->logAuthFailure($msg);
                // TRANS: Client error thrown when authentication fails.
                $this->clientError(_('Could not authenticate you.'), 401, $this->format);
                exit;
            }
        }
    }

    /**
     * Read the HTTP headers and set the auth user.  Decodes HTTP_AUTHORIZATION
     * param to support basic auth when PHP is running in CGI mode.
     *
     * @return void
     */
    function basicAuthProcessHeader()
    {
        $authHeaders = array('AUTHORIZATION',
                             'HTTP_AUTHORIZATION',
                             'REDIRECT_HTTP_AUTHORIZATION'); // rewrite for CGI
        $authorization_header = null;
        foreach ($authHeaders as $header) {
            if (isset($_SERVER[$header])) {
                $authorization_header = $_SERVER[$header];
                break;
            }
        }

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $this->auth_user_nickname = $_SERVER['PHP_AUTH_USER'];
            $this->auth_user_password = $_SERVER['PHP_AUTH_PW'];
        } elseif (isset($authorization_header)
            && strstr(substr($authorization_header, 0, 5), 'Basic')) {

            // Decode the HTTP_AUTHORIZATION header on php-cgi server self
            // on fcgid server the header name is AUTHORIZATION
            $auth_hash = base64_decode(substr($authorization_header, 6));
            list($this->auth_user_nickname,
                 $this->auth_user_password) = explode(':', $auth_hash);

            // Set all to null on a empty basic auth request

            if (empty($this->auth_user_nickname)) {
                $this->auth_user_nickname = null;
                $this->auth_password = null;
            }
        }
    }

    /**
     * Log an API authentication failure. Collect the proxy and IP
     * and log them
     *
     * @param string $logMsg additional log message
     */
     function logAuthFailure($logMsg)
     {
        list($proxy, $ip) = common_client_ip();

        $msg = sprintf(
            'API auth failure (proxy = %1$s, ip = %2$s) - ',
            $proxy,
            $ip
        );

        common_log(LOG_WARNING, $msg . $logMsg);
     }
}
