<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin that uses the email address as a username, and checks the password as normal
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class EmailAuthenticationPlugin extends Plugin
{
    // $nickname for this plugin is the user's email address
    function onStartCheckPassword($nickname, $password, &$authenticatedUser)
    {
        if (!strpos($nickname, '@')) {
            return true;
        }

        $user = User::getKV('email', $nickname);
        if ($user instanceof User && $user->email === $nickname) {
            if (common_check_user($user->nickname, $password)) {
                $authenticatedUser = $user;
                return false;
            }
        }

        return true;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Email Authentication',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/EmailAuthentication',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The Email Authentication plugin allows users to login using their email address.'));
        return true;
    }
}
