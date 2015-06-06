<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Plugin to throttle subscriptions by a user
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
 * @category  Throttle
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
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
 * Subscription throttle
 *
 * @category  Throttle
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class SubscriptionThrottlePlugin extends Plugin
{
    public $subLimits = array(86400 => 100,
                              3600 => 50);

    public $groupLimits = array(86400 => 50,
                                3600 => 25);

    /**
     * Filter subscriptions to see if they're coming too fast.
     *
     * @param Profile $profile  The profile subscribing
     * @param Profile $other    The profile being subscribed to
     *
     * @return boolean hook value
     */
    function onStartSubscribe(Profile $profile, $other)
    {
        foreach ($this->subLimits as $seconds => $limit) {
            $sub = $this->_getNthSub($profile, $limit);

            if (!empty($sub)) {
                $subtime = strtotime($sub->created);
                $now     = time();
                if ($now - $subtime < $seconds) {
                    // TRANS: Exception thrown when subscribing too quickly.
                    throw new Exception(_m('Too many subscriptions. Take a break and try again later.'));
                }
            }
        }

        return true;
    }

    /**
     * Filter group joins to see if they're coming too fast.
     *
     * @param Group   $group   The group being joined
     * @param Profile $profile The profile joining
     *
     * @return boolean hook value
     */
    function onStartJoinGroup($group, $profile)
    {
        foreach ($this->groupLimits as $seconds => $limit) {
            $mem = $this->_getNthMem($profile, $limit);
            if (!empty($mem)) {

                $jointime = strtotime($mem->created);
                $now      = time();
                if ($now - $jointime < $seconds) {
                    // TRANS: Exception thrown when joing groups too quickly.
                    throw new Exception(_m('Too many memberships. Take a break and try again later.'));
                }
            }
        }

        return true;
    }

    /**
     * Get the Nth most recent subscription for this profile
     *
     * @param Profile $profile profile to get subscriptions for
     * @param integer $n       How far to count back
     *
     * @return Subscription a subscription or null
     */
    private function _getNthSub(Profile $profile, $n)
    {
        $sub = new Subscription();

        $sub->subscriber = $profile->id;
        $sub->orderBy('created DESC');
        $sub->limit($n - 1, 1);

        if ($sub->find(true)) {
            return $sub;
        } else {
            return null;
        }
    }

    /**
     * Get the Nth most recent group membership for this profile
     *
     * @param Profile $profile The user to get memberships for
     * @param integer $n       How far to count back
     *
     * @return Group_member a membership or null
     */
    private function _getNthMem(Profile $profile, $n)
    {
        $mem = new Group_member();

        $mem->profile_id = $profile->id;
        $mem->orderBy('created DESC');
        $mem->limit($n - 1, 1);

        if ($mem->find(true)) {
            return $mem;
        } else {
            return null;
        }
    }

    /**
     * Return plugin version data for display
     *
     * @param array &$versions Array of version arrays
     *
     * @return boolean hook value
     */
    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'SubscriptionThrottle',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:SubscriptionThrottle',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Configurable limits for subscriptions and group memberships.'));
        return true;
    }
}
