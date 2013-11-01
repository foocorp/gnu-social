<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to check submitted notices with blogspam.net
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

define('BLOGSPAMNETPLUGIN_VERSION', '0.1');

/**
 * Plugin to check submitted notices with blogspam.net
 *
 * When new notices are saved, we check their text with blogspam.net (or
 * a compatible service).
 *
 * Blogspam.net is supposed to catch blog comment spam, and I found that
 * some of its tests (min/max size, bayesian match) gave a lot of false positives.
 * So, I've turned those tests off by default. This may not get as many
 * hits, but it's better than nothing.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Event
 */
class BlogspamNetPlugin extends Plugin
{
    var $baseUrl = 'http://test.blogspam.net:8888/';

    function __construct($url=null)
    {
        parent::__construct();
        if ($url) {
            $this->baseUrl = $url;
        }
    }

    function onStartNoticeSave($notice)
    {
        $args = $this->testArgs($notice);
        common_debug("Blogspamnet args = " . print_r($args, TRUE));
        $requestBody = xmlrpc_encode_request('testComment', array($args));

        $request = new HTTPClient($this->baseUrl, HTTPClient::METHOD_POST);
        $request->setHeader('Content-Type', 'text/xml');
        $request->setBody($requestBody);
        $httpResponse = $request->send();

        $response = xmlrpc_decode($httpResponse->getBody());
        if (xmlrpc_is_fault($response)) {
            throw new ServerException("$response[faultString] ($response[faultCode])", 500);
        } else {
            common_debug("Blogspamnet results = " . $response);
            if (preg_match('/^ERROR(:(.*))?$/', $response, $match)) {
                // TRANS: Server exception thrown when blogspam.net returns error status.
                // TRANS: %1$s is the base URL, %2$s is the error (unknown contents; no period).
                throw new ServerException(sprintf(_m('Error from %1$s: %2$s'), $this->baseUrl, $match[2]), 500);
            } else if (preg_match('/^SPAM(:(.*))?$/', $response, $match)) {
                // TRANS: Server exception thrown when blogspam.net returns spam status.
                // TRANS: Does not end with period because of unknown contents for %s (spam match).
                throw new ClientException(sprintf(_m('Spam checker results: %s'), $match[2]), 400);
            } else if (preg_match('/^OK$/', $response)) {
                // don't do anything
            } else {
                // TRANS: Server exception thrown when blogspam.net returns an unexpected status.
                // TRANS: %1$s is the base URL, %2$s is the response (unknown contents; no period).
                throw new ServerException(sprintf(_m('Unexpected response from %1$s: %2$s'), $this->baseUrl, $response), 500);
            }
        }
        return true;
    }

    function testArgs($notice)
    {
        $args = array();
        $args['comment'] = $notice->content;
        $args['ip'] = $this->getClientIP();

        if (isset($_SERVER) && array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            $args['agent'] = $_SERVER['HTTP_USER_AGENT'];
        }

        $profile = $notice->getProfile();

        if ($profile && $profile->homepage) {
            $args['link'] = $profile->homepage;
        }

        if ($profile && $profile->fullname) {
            $args['name'] = $profile->fullname;
        } else {
            $args['name'] = $profile->nickname;
        }

        $args['site'] = common_root_url();
        $args['version'] = $this->userAgent();

        $args['options'] = "max-size=" . common_config('site','textlimit') . ",min-size=0,min-words=0,exclude=bayasian";

        return $args;
    }

    function getClientIP()
    {
        if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
            // Note: order matters here; use proxy-forwarded stuff first
            foreach (array('HTTP_X_FORWARDED_FOR', 'CLIENT-IP', 'REMOTE_ADDR') as $k) {
                if (isset($_SERVER[$k])) {
                    return $_SERVER[$k];
                }
            }
        }
        return '127.0.0.1';
    }

    function version()
    {
        return BLOGSPAMNETPLUGIN_VERSION;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'BlogspamNet',
                            'version' => BLOGSPAMNETPLUGIN_VERSION,
                            'author' => 'Evan Prodromou, Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:BlogspamNet',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Plugin to check submitted notices with blogspam.net.'));
        return true;
    }
}
