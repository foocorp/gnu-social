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

$ini = parse_ini_file("oauth.ini");

// Check to make sure we have everything we need from the ini file
foreach(array('consumer_key', 'consumer_secret', 'apiroot', 'access_token_url') as $inikey) {
    if (empty($ini[$inikey])) {
        print "You forgot to specify a $inikey in your oauth.ini file.\n";
        exit(1);
    }
}

$consumer = new OAuthConsumer($ini['consumer_key'], $ini['consumer_secret']);

$endpoint = $ini['apiroot'] . $ini['access_token_url'];

$shortoptions = 't:s:v:';
$longoptions = array('oauth_token=', 'oauth_token_secret=', 'oauth_verifier=');

$helptext = <<<END_OF_ETOKENS_HELP
  fetch_token_creds.php [options]

  Exchange authorized OAuth temporary credentials for token credentials
  (an authorized request token for an access token)

    -t --oauth_token        authorized request token
    -s --oauth_token_secret authorized request token secret
    -v --oauth_verifier     authorized request token verifier


END_OF_ETOKENS_HELP;

require_once INSTALLDIR . '/scripts/commandline.inc';

$token = $secret = $verifier = null;

if (have_option('t', 'oauth_token')) {
    $token = get_option_value('t', 'oauth_token');
}

if (have_option('s', 'oauth_token_secret')) {
    $secret = get_option_value('s', 'oauth_token_secret');
}

if (have_option('v', 'oauth_verifier')) {
    $verifier = get_option_value('v', 'oauth_verifier');
}

if (empty($token)) {
    print "Please specify the request token (--help for help).\n";
    exit(1);
}

if (empty($secret)) {
    print "Please specify the request token secret (--help for help).\n";
    exit(1);
}

if (empty($verifier)) {
    print "Please specify the request token verifier (--help for help).\n";
    exit(1);
}

$rtok   = new OAuthToken($token, $secret);
$parsed = parse_url($endpoint);
parse_str($parsed['query'], $params);

$params['oauth_verifier'] = $verifier; // 1.0a

$hmac_method = new OAuthSignatureMethod_HMAC_SHA1();

try {

    $oauthReq = OAuthRequest::from_consumer_and_token(
        $consumer,
        $rtok,
        "POST",
        $endpoint,
        $params
    );

    $oauthReq->sign_request($hmac_method, $consumer, $rtok);

    $httpReq    = httpRequest($endpoint, $oauthReq->to_postdata());
    $body       = $httpReq->getBody();

} catch (Exception $e) {
    // oh noez
    print $e->getMessage();
    print "\nOAuth Request:\n";
    var_dump($oauthReq);
    exit(1);
}

$tokenStuff = array();
parse_str($body, $tokenStuff);

if (empty($tokenStuff['oauth_token']) || empty($tokenStuff['oauth_token_secret'])) {
    print "Error! HTTP response body: $body\n";
    exit(1);
}

print "Access Token\n";
print '   - oauth_token        = ' . $tokenStuff['oauth_token'] . "\n";
print '   - oauth_token_secret = ' . $tokenStuff['oauth_token_secret'] . "\n";

function httpRequest($endpoint, $poststr)
{
    $request = HTTPClient::start();

    $request->setConfig(
        array(
            'follow_redirects' => true,
	    'connect_timeout'  => 120,
	    'timeout'          => 120,
	    'ssl_verify_peer'  => false,
	    'ssl_verify_host'  => false
	)
    );

    parse_str($poststr, $postdata);
    return $request->post($endpoint, null, $postdata);
}

