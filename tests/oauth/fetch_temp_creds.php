#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

require_once INSTALLDIR . '/scripts/commandline.inc';
require_once INSTALLDIR . '/extlib/OAuth.php';

$ini = parse_ini_file("oauth.ini");

// Check to make sure we have everything we need from the ini file
foreach(array('consumer_key', 'consumer_secret', 'apiroot', 'request_token_url') as $inikey) {
    if (empty($ini[$inikey])) {
        print "You forgot to specify a $inikey in your oauth.ini file.\n";
        exit(1);
    }
}

$consumer = new OAuthConsumer($ini['consumer_key'], $ini['consumer_secret']);
$endpoint = $ini['apiroot'] . $ini['request_token_url'];
$parsed   = parse_url($endpoint);
$params   = array();

parse_str($parsed['query'], $params);
$params['oauth_callback'] = 'oob'; // out-of-band

$hmac_method = new OAuthSignatureMethod_HMAC_SHA1();

try {
    $req = OAuthRequest::from_consumer_and_token(
        $consumer,
        null,
        "POST",
        $endpoint,
        $params
    );
    $req->sign_request($hmac_method, $consumer, NULL);
    $r = httpRequest($endpoint, $req->to_postdata());
} catch (Exception $e) {
    // oh noez
    print $e->getMessage();
    print "\nOAuth Request:\n";
    var_dump($req);
    exit(1);
}

$body       = $r->getBody();
$tokenStuff = array();

parse_str($body, $tokenStuff);

$tok       = $tokenStuff['oauth_token'];
$confirmed = $tokenStuff['oauth_callback_confirmed'];

if (empty($tokenStuff['oauth_token'])
    || empty($tokenStuff['oauth_token_secret'])
    || empty($confirmed)
    || $confirmed != 'true')
{
    print "Error! HTTP response body: $body\n";
    exit(1);
}

$authurl = $ini['apiroot'] . $ini['authorize_url'] . '?oauth_token=' . $tok;

print "Request Token\n";
print '   - oauth_token        = ' . $tokenStuff['oauth_token'] . "\n";
print '   - oauth_token_secret = ' . $tokenStuff['oauth_token_secret'] . "\n";
print "Authorize URL\n    $authurl\n\n";
print "Now paste the Authorize URL into your browser and authorize your temporary credentials.\n";

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
