<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Implements the JSON Account Management endpoint
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
 * Implements the JSON Account Management endpoint
 *
 * @category AccountManager
 * @package  StatusNet
 * @author   ECraig Andrews <candrews@integralblue.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AccountManagementControlDocumentAction extends Action
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

        header('Content-Type: application/json; charset=utf-8');

        $amcd = array();

        if(Event::handle('StartAccountManagementControlDocument', array(&$amcd))) {

            $amcd['version'] = 1;
            $amcd['sessionstatus'] = array(
                'method' => 'GET',
                'path' => common_local_url('AccountManagementSessionStatus')
            );
            $amcd['auth-methods'] = array(
                'username-password-form' => array(
                    'connect' => array(
                        'method' => 'POST',
                        'path' => common_local_url('login'),
                        'params' => array(
                            'username' => 'nickname',
                            'password' => 'password'
                        )
                    ),
                    'disconnect' => array(
                        'method' => 'GET',
                        'path' => common_local_url('logout')
                    )
                )
            );

            Event::handle('EndAccountManagementControlDocument', array(&$amcd));
        }
        
        print json_encode($amcd);

        return true;
    }

    function isReadOnly()
    {
        return true;
    }
}
