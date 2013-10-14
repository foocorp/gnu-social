<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * WebFinger functions
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
 * @package   GNUsocial
 * @author    Mikael Nordfeldth
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class WebFinger
{
    const PROFILEPAGE = 'http://webfinger.net/rel/profile-page';

    /*
     * Reconstructs a WebFinger ID from data we know about the profile.
     *
     * @param  Profile  $profile    The profile we want a WebFinger ID for
     * 
     * @return string   $acct       acct:user@example.com URI
     */
    public static function reconstruct(Profile $profile)
    {
        $acct = null;

        if (Event::handle('StartWebFingerReconstruction', array($profile, &$acct))) {
            // TODO: getUri may not always give us the correct host on remote users?
            $host = parse_url($profile->getUri(), PHP_URL_HOST);
            if (empty($profile->nickname) || empty($host)) {
                throw new WebFingerReconstructionException($profile);
            }
            $acct = sprintf('acct:%s@%s', $profile->nickname, $host);

            Event::handle('EndWebFingerReconstruction', array($profile, &$acct));
        }

        return $acct;
    }

    /*
     * Gets all URI aliases for a Profile
     *
     * @param  Profile  $profile    The profile we want aliases for
     * 
     * @return array    $aliases    All the Profile's alternative URLs
     */
    public static function getAliases(Profile $profile)
    {
        $aliases = array();
        $aliases[] = $profile->getUri();
        try {
            $aliases[] = $profile->getUrl();
        } catch (InvalidUrlException $e) {
            common_debug('Profile id='.$profile->id.' has invalid profileurl: ' .
                            var_export($profile->profileurl, true));
        }
        return $aliases;
    }

    /*
     * Gets all identities for a Profile, includes WebFinger acct: if
     * available, as well as alias URLs.
     *
     * @param  Profile  $profile    The profile we want aliases for
     * 
     * @return array    $uris       WebFinger acct: URI and alias URLs
     */
    public static function getIdentities(Profile $profile)
    {
        $uris = array();
        try {
            $uris[] = self::reconstruct($profile);
        } catch (WebFingerReconstructionException $e) {
            common_debug('WebFinger reconstruction for Profile failed, ' .
                            ' (id='.$profile->id.')');
        }
        $uris = array_merge($uris, self::getAliases($profile));

        return $uris;
    }
}
