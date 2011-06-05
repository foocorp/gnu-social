<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to create pretty Spotify URLs
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
 * @author    Nick Holliday <n.g.holliday@gmail.com>
 * @copyright Nick Holliday
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 *
 * @see      Event
 */
if (!defined('STATUSNET')) {
    exit(1);
}
define('SPOTIFYPLUGIN_VERSION', '0.1');

/**
 * Plugin to create pretty Spotify URLs
 *
 * The Spotify API is called before the notice is saved to gather artist and track information.
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Nick Holliday <n.g.holliday@gmail.com>
 * @copyright Nick Holliday
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 *
 * @see       Event
 */
class SpotifyPlugin extends Plugin
{
    function __construct()
    {
        parent::__construct();
    }

    function onStartNoticeSave($notice)
    {
        $notice->rendered = preg_replace_callback('/spotify:[a-z]{5,6}:[a-z0-9]{22}/i',
                                                  "renderSpotifyURILink",
                                                  $notice->rendered);

        $notice->rendered = preg_replace_callback('/<a href="http:\/\/open.spotify.com\/[a-z]{5,6}\/[a-z0-9]{22}" title="http:\/\/open.spotify.com\/[a-z]{5,6}\/[a-z0-9]{22}" rel="external">http:\/\/open.spotify.com\/[a-z]{5,6}\/[a-z0-9]{22}<\/a>/i',
                                                  "renderSpotifyHTTPLink",
                                                  $notice->rendered);

        return true;
    }

    function userAgent()
    {
        return 'SpotifyPlugin/'.SPOTIFYPLUGIN_VERSION .
               ' StatusNet/' . STATUSNET_VERSION;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Spotify',
                            'version' => SPOTIFYPLUGIN_VERSION,
                            'author' => 'Nick Holliday',
                            'homepage' => 'http://status.net/wiki/Plugin:Spotify',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Create pretty <a href="http://www.spotify.com">Spotify</a> URLs.'));

        return true;
    }
}

// @todo FIXME: This probably should not be global functions.
function doSpotifyLookup($uri, $isArtist)
{
    $request = HTTPClient::start();
    $response = $request->get('http://ws.spotify.com/lookup/1/?uri=' . $uri);
    if ($response->isOk()) {
        $xml = simplexml_load_string($response->getBody());

        if($isArtist)
            return $xml->name;
        else
            return $xml->artist->name . ' - ' . $xml->name;
    }
}

function renderSpotifyURILink($match)
{
    $isArtist = false;
    if(preg_match('/artist/', $match[0]) > 0) $isArtist = true;

    $name = doSpotifyLookup($match[0], $isArtist);
    return "<a href=\"{$match[0]}\">" . $name . "</a>";
}

function renderSpotifyHTTPLink($match)
{
    $match[0] = preg_replace('/<a href="http:\/\/open.spotify.com\/[a-z]{5,6}\/[a-z0-9]{22}" title="http:\/\/open.spotify.com\/[a-z]{5,6}\/[a-z0-9]{22}" rel="external">http:\/\/open.spotify.com\//i', 'spotify:', $match[0]);
    $match[0] = preg_replace('/<\/a>/', '', $match[0]);
    $match[0] = preg_replace('/\//', ':', $match[0]);

    $isArtist = false;
    if(preg_match('/artist/', $match[0]) > 0) $isArtist = true;

    $name = doSpotifyLookup($match[0], $isArtist);
    return "<a href=\"{$match[0]}\">" . $name . "</a>";
}
