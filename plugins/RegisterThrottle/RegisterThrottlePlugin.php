<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Throttle registration by IP address
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
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Throttle registration by IP address
 *
 * We a) record IP address of registrants and b) throttle registrations.
 *
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class RegisterThrottlePlugin extends Plugin
{
    /**
     * Array of time spans in seconds to limits.
     *
     * Default is 3 registrations per hour, 5 per day, 10 per week.
     */
    public $regLimits = array(604800 => 10, // per week
                              86400 => 5, // per day
                              3600 => 3); // per hour

    /**
     * Disallow registration if a silenced user has registered from
     * this IP address.
     */
    public $silenced = true;

    /**
     * Whether we're enabled; prevents recursion.
     */
    static private $enabled = true;

    /**
     * Database schema setup
     *
     * We store user registrations in a table registration_ip.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing user-submitted flags on profiles
        $schema->ensureTable('registration_ip', Registration_ip::schemaDef());
        return true;
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'Registration_ip':
            include_once $dir . '/'.$cls.'.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Called when someone tries to register.
     *
     * We check the IP here to determine if it goes over any of our
     * configured limits.
     *
     * @param Action $action Action that is being executed
     *
     * @return boolean hook value
     */
    function onStartRegistrationTry($action)
    {
        $ipaddress = $this->_getIpAddress();

        if (empty($ipaddress)) {
            // TRANS: Server exception thrown when no IP address can be found for a registation attempt.
            throw new ServerException(_m('Cannot find IP address.'));
        }

        foreach ($this->regLimits as $seconds => $limit) {

            $this->debug("Checking $seconds ($limit)");

            $reg = $this->_getNthReg($ipaddress, $limit);

            if (!empty($reg)) {
                $this->debug("Got a {$limit}th registration.");
                $regtime = strtotime($reg->created);
                $now     = time();
                $this->debug("Comparing {$regtime} to {$now}");
                if ($now - $regtime < $seconds) {
                    // TRANS: Exception thrown when too many user have registered from one IP address within a given time frame.
                    throw new Exception(_m('Too many registrations. Take a break and try again later.'));
                }
            }
        }

        // Check for silenced users

        if ($this->silenced) {
            $ids = Registration_ip::usersByIP($ipaddress);
            foreach ($ids as $id) {
                $profile = Profile::getKV('id', $id);
                if ($profile && $profile->isSilenced()) {
                    // TRANS: Exception thrown when attempting to register from an IP address from which silenced users have registered.
                    throw new Exception(_m('A banned user has registered from this address.'));
                }
            }
        }

        return true;
    }

    /**
     * Called after someone registers, by any means.
     *
     * We record the successful registration and IP address.
     *
     * @param Profile $profile new user's profile
     * @param User $user new user
     *
     * @return boolean hook value
     */
    function onEndUserRegister($profile, $user)
    {
        $ipaddress = $this->_getIpAddress();

        if (empty($ipaddress)) {
            // User registration can happen from command-line scripts etc.
            return true;
        }

        $reg = new Registration_ip();

        $reg->user_id   = $user->id;
        $reg->ipaddress = $ipaddress;

        $result = $reg->insert();

        if (!$result) {
            common_log_db_error($reg, 'INSERT', __FILE__);
            // @todo throw an exception?
        }

        return true;
    }

    /**
     * Check the version of the plugin.
     *
     * @param array &$versions Version array.
     *
     * @return boolean hook value
     */
    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'RegisterThrottle',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:RegisterThrottle',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Throttles excessive registration from a single IP address.'));
        return true;
    }

    /**
     * Gets the current IP address.
     *
     * @return string IP address or null if not found.
     */
    private function _getIpAddress()
    {
        $keys = array('HTTP_X_FORWARDED_FOR',
                      'HTTP_X_CLIENT',
                      'CLIENT-IP',
                      'REMOTE_ADDR');

        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                return $_SERVER[$k];
            }
        }

        return null;
    }

    /**
     * Gets the Nth registration with the given IP address.
     *
     * @param string  $ipaddress Address to key on
     * @param integer $n         Nth address
     *
     * @return Registration_ip nth registration or null if not found.
     */
    private function _getNthReg($ipaddress, $n)
    {
        $reg = new Registration_ip();

        $reg->ipaddress = $ipaddress;

        $reg->orderBy('created DESC');
        $reg->limit($n - 1, 1);

        if ($reg->find(true)) {
            return $reg;
        } else {
            return null;
        }
    }

    /**
     * When silencing a user, silence all other users registered from that IP
     * address.
     *
     * @param Profile $profile Person getting a new role
     * @param string  $role    Role being assigned like 'moderator' or 'silenced'
     *
     * @return boolean hook value
     */
    function onEndGrantRole($profile, $role)
    {
        if (!self::$enabled) {
            return true;
        }

        if ($role != Profile_role::SILENCED) {
            return true;
        }

        if (!$this->silenced) {
            return true;
        }

        $ri = Registration_ip::getKV('user_id', $profile->id);

        if (empty($ri)) {
            return true;
        }

        $ids = Registration_ip::usersByIP($ri->ipaddress);

        foreach ($ids as $id) {
            if ($id == $profile->id) {
                continue;
            }

            $other = Profile::getKV('id', $id);

            if (empty($other)) {
                continue;
            }

            if ($other->isSilenced()) {
                continue;
            }

            $old = self::$enabled;
            self::$enabled = false;
            $other->silence();
            self::$enabled = $old;
        }
    }
}
