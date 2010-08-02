<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to enable nickname completion in the enter status box
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
 * @copyright 2010 Free Software Foundation http://fsf.org
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class AutocompletePlugin extends Plugin
{
    function __construct()
    {
        parent::__construct();
    }

    function onAutoload($cls)
    {
        switch ($cls)
        {
         case 'AutocompleteAction':
            require_once(INSTALLDIR.'/plugins/Autocomplete/autocomplete.php');
            return false;
        }
    }

    function onEndShowScripts($action){
        if (common_logged_in()) {
            $action->script('plugins/Autocomplete/jquery-autocomplete/jquery.autocomplete.pack.js');
            $action->script('plugins/Autocomplete/Autocomplete.js');
        }
    }

    function onEndShowStatusNetStyles($action)
    {
        if (common_logged_in()) {
            $action->cssLink('plugins/Autocomplete/jquery-autocomplete/jquery.autocomplete.css');
        }
    }

    function onRouterInitialized($m)
    {
        if (common_logged_in()) {
            $m->connect('plugins/Autocomplete/autocomplete.json', array('action'=>'autocomplete'));
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Autocomplete',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:Autocomplete',
                            'rawdescription' =>
                            _m('The autocomplete plugin allows users to autocomplete screen names in @ replies. When an "@" is typed into the notice text area, an autocomplete box is displayed populated with the user\'s friend\' screen names.'));
        return true;
    }

}
?>
