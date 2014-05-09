<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * A site profile is a set of default settings for a particular style of
 * StatusNet site: public, private, community, etc.
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
 * @category  Installation
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) {
    exit(1);
}

/**
 * Helper class for getting the settings for a particular site profile
 */
class SiteProfile
{
    /**
     * Returns the config settings for a site profile by name
     *
     * @param  string $name name of a site profile
     * @return array  config settings
     */
    static public function getSettings($name)
    {
        $sprofileClass = ucfirst($name) . "Site";

        if (class_exists($sprofileClass)) {
            return call_user_func(array($sprofileClass, 'getSettings'));
        } else {
            common_log(
                LOG_ERR,
                "Unknown site profile '{$name}' specified in config file.",
                __FILE__
            );
            return array();
        }
    }
}

/**
 * Site profile settings contain the list of the default settings (and
 * possibly other information for a particular flavor of StatusNet
 * installation). These will overwrite base defaults in $config global.
 *
 * @category Installation
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
abstract class SiteProfileSettings
{
    static function getSettings()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    static function corePlugins() {
        return common_config('plugins', 'core');
    }
    static function defaultPlugins() {
        return common_config('plugins', 'default');
    }
}

/**
 * Settings for a 'public' site
 */
class PublicSite extends SiteProfileSettings
{
    /**
     * Get the settings for this site profile
     *
     * @return type array   an array of settings
     */
    static function getSettings() {
        global $config;
        return array(
            // We only want to change these values, not replace entire 'site' array
            'site' => array_merge(
                $config['site'], array(
                    'inviteonly' => false,
                    'private'    => false,
                    'closed'     => false
                )
            ),
            'plugins' => array(
                'core'    => self::corePlugins(),
                'default' => array_merge(self::defaultPlugins(), array(
                    'ExtendedProfile'         => array(),
                    'RegisterThrottle'        => array(),
                ))
            ),
            'discovery' => array('cors' => true) // Allow Cross-Origin Resource Sharing for service discovery (host-meta, XRD, etc.)
        );
    }
}

/**
 * Settings for a 'private' site
 *
 * // XXX Too business oriented?
 */
class PrivateSite extends SiteProfileSettings
{
    /**
     * Get the settings for this site profile
     *
     * @return type array  an array of settings
     */
    static function getSettings() {
        global $config;
        return array(
            // We only want to change these values, not replace entire 'site' array
            'site' => array_merge(
                $config['site'], array(
                    'inviteonly' => true,
                    'private'    => true,
                )
            ),
            'plugins' => array(
                'core'    => self::corePlugins(),
                'default' => array_merge(self::defaultPlugins(), array(
                    'ExtendedProfile'         => array(),
                    'EmailRegistration'       => array(),
                    'MobileProfile'           => array(),
                )),
                'disable-OStatus' => 1,
                'disable-WebFinger' => 1,
             ),
            'profile'       => array('delete' => 'true'),
            'license'       => array('type'   => 'private'),
            'attachments'   => array(
                // Only allow uploads of pictures and MS Office files
                'supported' => array(
                    'image/png',
                    'image/jpeg',
                    'image/gif',
                    'image/svg+xml',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.ms-office',
                    'application/vnd.ms-excel',
                    'application/vnd.ms-powerpoint',
                    'application/ogg'
                )
             ),
            'discovery' => array('cors'   => false) // Allow Cross-Origin Resource Sharing for service discovery (host-meta, XRD, etc.)
        );
    }
}

/**
 * Settings for a 'community' site
 */
class CommunitySite extends SiteProfileSettings
{
    /**
     * Get the settings for this site profile
     *
     * @return type array  an array of settings
     */
    static function getSettings() {
        global $config;
        return array(
            // We only want to change these values, not replace entire 'site' array
            'site' => array_merge(
                $config['site'], array(
                    'private'    => false,
                    'inviteonly' => true,
                    'closed'     => false
                )
            ),
            'plugins' => array(
                'core'    => self::corePlugins(),
                'default' => array_merge(self::defaultPlugins(), array(
                ))
            ),
            'discovery' => array('cors' => true) // Allow Cross-Origin Resource Sharing for service discovery (host-meta, XRD, etc.)
        );
    }

}

/**
 * Settings for a 'singleuser' site
 */
class SingleuserSite extends SiteProfileSettings
{
    /**
     * Get the settings for this site profile
     *
     * @return type array  an array of settings
     */
    static function getSettings() {
        global $config;
        return array(
            'singleuser' => array('enabled' => true),
            // We only want to change these values, not replace entire 'site' array
            'site' => array_merge(
                $config['site'], array(
                    'private'    => false,
                    'closed'     => true,
                )
            ),
            'plugins' => array(
                'core'    => self::corePlugins(),
                'default' => array_merge(self::defaultPlugins(), array(
                    'MobileProfile'           => array(),
                    'TwitterBridge'           => array(),
                    'FacebookBridge'          => array(),
                )),
                'disable-Directory' => 1,
            ),
            'discovery' => array('cors' => true) // Allow Cross-Origin Resource Sharing for service discovery (host-meta, XRD, etc.)
        );
    }

}
