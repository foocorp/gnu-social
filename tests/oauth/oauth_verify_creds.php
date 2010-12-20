#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

require_once INSTALLDIR . '/extlib/OAuth.php';

$shortoptions = 't:s:';
$longoptions = array('oauth_token=', 'oauth_token_secret=');

$helptext = <<<END_OF_VERIFY_HELP
  oauth_verify_creds.php [options]
  Access /api/account/verify_credentials.xml with OAuth

    -t --oauth_token        access token
    -s --oauth_token_secret access token secret

END_OF_VERIFY_HELP;

$token        = null;
$token_secret = null;

require_once INSTALLDIR . '/scripts/commandline.inc';

if (have_option('t', 'oauth_token')) {
    $token = get_option_value('t', 'oauth_token');
}

if (have_option('s', 'oauth_token_secret')) {
    $token_secret = get_option_value('s', 'oauth_token_secret');
}

if (empty($token)) {
    print "Please specify an access token (--help for help).\n";
    exit(1);
}

if (empty($token_secret)) {
    print "Please specify an access token secret (--help for help).\n";
    exit(1);
}

$ini      = parse_ini_file("oauth.ini");
$consumer = new OAuthConsumer($ini['consumer_key'], $ini['consumer_secret']);
$endpoint = $ini['apiroot'] . '/account/verify_credentials.xml';

$atok   = new OAuthToken($token, $token_secret);
$parsed = parse_url($endpoint);

parse_str($parsed['query'], $params);

try {

    $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();

    $oauthReq = OAuthRequest::from_consumer_and_token(
        $consumer,
        $atok,
        "GET",
        $endpoint,
        $params
    );

    $oauthReq->sign_request($hmac_method, $consumer, $atok);

    $httpReq = httpRequest($oauthReq->to_url());

} catch (Exception $e) {
    print "Error! HTTP response body: " . $httpReq->getBody();
    exit(1);
}

print $httpReq->getBody();

function httpRequest($url)
{
    $request = HTTPClient::start();

    $request->setConfig(
        array(
            'follow_redirects' => true,
	    'connect_timeout' => 120,
	    'timeout' => 120,
	    'ssl_verify_peer' => false,
	    'ssl_verify_host' => false
        )
    );

    return $request->get($url);
}
