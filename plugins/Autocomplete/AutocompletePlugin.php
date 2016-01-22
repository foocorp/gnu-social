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

    function onEndShowScripts($action){
        if (common_logged_in()) {
            $action->element('span', array('id' => 'autocomplete-api',
                                           'data-url' => common_local_url('autocomplete')));
            $action->script($this->path('js/autocomplete.go.js'));
        }
    }

    function onRouterInitialized($m)
    {
        $m->connect('main/autocomplete/suggest', array('action'=>'autocomplete'));
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Autocomplete',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/Autocomplete',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The autocomplete plugin adds autocompletion for @ replies.'));
        return true;
    }
}
