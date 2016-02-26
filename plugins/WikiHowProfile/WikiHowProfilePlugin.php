<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Plugin to pull WikiHow-style user avatars at OpenID setup time.
 * These are not currently exposed via OpenID.
 *
 * PHP version 5
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
 *
 * @category  Plugins
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Sample plugin main class
 *
 * Each plugin requires a main class to interact with the StatusNet system.
 *
 * @category  Plugins
 * @package   WikiHowProfilePlugin
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class WikiHowProfilePlugin extends Plugin
{
    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'WikiHow avatar fetcher',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/Sample',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Fetches avatar and other profile information for WikiHow users when setting up an account via OpenID.'));
        return true;
    }

    /**
     * Hook for OpenID user creation; we'll pull the avatar.
     *
     * @param User $user
     * @param string $canonical OpenID provider URL
     * @param array $sreg query data from provider
     */
    function onEndOpenIDCreateNewUser($user, $canonical, $sreg)
    {
        $this->updateProfile($user, $canonical);
        return true;
    }

    /**
     * Hook for OpenID profile updating; we'll pull the avatar.
     *
     * @param User $user
     * @param string $canonical OpenID provider URL (wiki profile page)
     * @param array $sreg query data from provider
     */
    function onEndOpenIDUpdateUser($user, $canonical, $sreg)
    {
        $this->updateProfile($user, $canonical);
        return true;
    }

    /**
     * @param User $user
     * @param string $canonical OpenID provider URL (wiki profile page)
     */
    private function updateProfile($user, $canonical)
    {
        $prefix = 'http://www.wikihow.com/User:';

        if (substr($canonical, 0, strlen($prefix)) == $prefix) {
            // Yes, it's a WikiHow user!
            $profile = $this->fetchProfile($canonical);

            if (!empty($profile['avatar'])) {
                $this->saveAvatar($user, $profile['avatar']);
            }
        }
    }

    /**
     * Given a user's WikiHow profile URL, find their avatar.
     *
     * @param string $profileUrl user page on the wiki
     *
     * @return array of data; possible members:
     *               'avatar' => full URL to avatar image
     *
     * @throws Exception on various low-level failures
     *
     * @todo pull location, web site, and about sections -- they aren't currently marked up cleanly.
     */
    private function fetchProfile($profileUrl)
    {
        $client = HTTPClient::start();
        $response = $client->get($profileUrl);
        if (!$response->isOk()) {
            // TRANS: Exception thrown when fetching a WikiHow profile page fails.
            throw new Exception(_m('WikiHow profile page fetch failed.'));
            // HTTP error response already logged.
            return false;
        }

        // Suppress warnings during HTML parsing; non-well-formed bits will
        // spew horrible warning everywhere even though it works fine.
        $old = error_reporting();
        error_reporting($old & ~E_WARNING);

        $dom = new DOMDocument();
        $ok = $dom->loadHTML($response->getBody());

        error_reporting($old);

        if (!$ok) {
            // TRANS: Exception thrown when parsing a WikiHow profile page fails.
            throw new Exception(_m('HTML parse failure during check for WikiHow avatar.'));
            return false;
        }

        $data = array();

        $avatar = $dom->getElementById('avatarULimg');
        if ($avatar) {
            $src = $avatar->getAttribute('src');

            $base = new Net_URL2($profileUrl);
            $absolute = $base->resolve($src);
            $avatarUrl = strval($absolute);

            common_log(LOG_DEBUG, "WikiHow avatar found for $profileUrl - $avatarUrl");
            $data['avatar'] = $avatarUrl;
        }

        return $data;
    }

    /**
     * Actually save the avatar we found locally.
     *
     * @param User $user
     * @param string $url to avatar URL
     * @todo merge wrapper funcs for this into common place for 1.0 core
     */
    private function saveAvatar($user, $url)
    {
        if (!common_valid_http_url($url)) {
            // TRANS: Server exception thrown when an avatar URL is invalid.
            // TRANS: %s is the invalid avatar URL.
            throw new ServerException(sprintf(_m('Invalid avatar URL %s.'), $url));
        }

        // @todo FIXME: This should be better encapsulated
        // ripped from OStatus via oauthstore.php (for old OMB client)
        $temp_filename = tempnam(sys_get_temp_dir(), 'listener_avatar');
        try {
            if (!copy($url, $temp_filename)) {
                // TRANS: Exception thrown when fetching an avatar from a URL fails.
                // TRANS: %s is a URL.
                throw new ServerException(sprintf(_m('Unable to fetch avatar from %s.'), $url));
            }

            $profile = $user->getProfile();
            $id = $profile->id;
            $imagefile = new ImageFile(null, $temp_filename);
            $filename = Avatar::filename($id,
                                         image_type_to_extension($imagefile->type),
                                         null,
                                         common_timestamp());
            rename($temp_filename, Avatar::path($filename));
        } catch (Exception $e) {
            unlink($temp_filename);
            throw $e;
        }
        $profile->setOriginal($filename);
    }
}
