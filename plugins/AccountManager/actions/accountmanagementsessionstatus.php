<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Implements the session status Account Management endpoint
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
 * @category  AccountManager
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Implements the session status Account Management endpoint
 *
 * @category AccountManager
 * @package  StatusNet
 * @author   ECraig Andrews <candrews@integralblue.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AccountManagementSessionStatusAction extends Action
{
    /**
     * handle the action
     *
     * @param array $args unused.
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        $cur = common_current_user();
        if(empty($cur)) {
            print 'none';
        } else {
            //TODO it seems " should be escaped in the name and id, but the spec doesn't seem to indicate how to do that
            print 'active; name="' . $cur->nickname . '"; id="' . $cur->nickname . '"';
        }

        return true;
    }

    function isReadOnly()
    {
        return true;
    }
}
