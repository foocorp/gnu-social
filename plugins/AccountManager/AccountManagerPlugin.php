<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to implement the Account Manager specification
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class AccountManagerPlugin extends Plugin
{
    const AM_REL = 'acct-mgmt';

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        // Discovery actions
        $m->connect('main/amcd.json',
                    array('action' => 'AccountManagementControlDocument'));
        $m->connect('main/amsessionstatus',
                    array('action' => 'AccountManagementSessionStatus'));
        return true;
    }

    function onStartHostMetaLinks(&$links) {
        $links[] = new XML_XRD_Element_Link(AccountManagerPlugin::AM_REL,
                        common_local_url('AccountManagementControlDocument'));
    }

    function onStartShowHTML($action)
    {
        //Account management discovery link
        header('Link: <'.common_local_url('AccountManagementControlDocument').'>; rel="'. AccountManagerPlugin::AM_REL.'"; type="application/json"');

        //Account management login status
        $cur = common_current_user();
        if(empty($cur)) {
            header('X-Account-Management-Status: none');
        } else {
            //TODO it seems " should be escaped in the name and id, but the spec doesn't seem to indicate how to do that
            header('X-Account-Management-Status: active; name="' . $cur->nickname . '"; id="' . $cur->nickname . '"');
        }
    }

    function onLoginAction($action, &$login) {
        switch ($action) 
        {
         case 'AccountManagementControlDocument':
            $login = true;
            return false;
         default:
            return true;
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'AccountManager',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:AccountManager',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The Account Manager plugin implements the Account Manager specification.'));
        return true;
    }
}
