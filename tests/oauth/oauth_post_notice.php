#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 **/

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

require_once INSTALLDIR . '/extlib/OAuth.php';

$shortoptions = 't:s:u:';
$longoptions = array('oauth_token=', 'oauth_token_secret=', 'update=');

$helptext = <<<END_OF_VERIFY_HELP
    oauth_post_notice.php [options]
    Update your status via OAuth

    -t --oauth_token        access token
    -s --oauth_token_secret access token secret
    -u --update             status update


END_OF_VERIFY_HELP;

$token        = null;
$token_secret = null;
$update       = null;

require_once INSTALLDIR . '/scripts/commandline.inc';

if (have_option('t', 'oauth_token')) {
    $token = get_option_value('t', 'oauth_token');
}

if (have_option('s', 'oauth_token_secret')) {
    $token_secret = get_option_value('s', 'oauth_token_secret');
}

if (have_option('u', 'update')) {
    $update = get_option_value('u', 'update');
}

if (empty($token)) {
    print "Please specify an access token.\n";
    exit(1);
}

if (empty($token_secret)) {
    print "Please specify an access token secret.\n";
    exit(1);
}

if (empty($update)) {
    print "You forgot to update your status!\n";
    exit(1);
}

$ini      = parse_ini_file("oauth.ini");
$consumer = new OAuthConsumer($ini['consumer_key'], $ini['consumer_secret']);
$endpoint = $ini['apiroot'] . '/statuses/update.xml';

$atok = new OAuthToken($token, $token_secret);

$parsed = parse_url($endpoint);
parse_str($parsed['query'], $params);

$params['status'] = $update;

$hmac_method = new OAuthSignatureMethod_HMAC_SHA1();

try {

    $oauthReq = OAuthRequest::from_consumer_and_token(
        $consumer,
        $atok,
        'POST',
        $endpoint,
        $params
    );

    $oauthReq->sign_request($hmac_method, $consumer, $atok);

    $httpReq = httpRequest($endpoint, $oauthReq->to_postdata());

    print $httpReq->getBody();

} catch (Exception $e) {
    print "Error! . $e->getMessage() . 'HTTP reponse body: " . $httpReq->getBody();
    exit(1);
}

function httpRequest($endpoint, $poststr)
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

    // Turn signed request query string back into an array
    parse_str($poststr, $postdata);
    return $request->post($endpoint, null, $postdata);
}

