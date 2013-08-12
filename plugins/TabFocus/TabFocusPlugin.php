<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to enable Twitter-like "tab-space" pattern for a user to submit a notice
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
 * @author    Paul Irish <paul.irish@isobar.net>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class TabFocusPlugin extends Plugin
{
    function __construct()
    {
        parent::__construct();
    }

    function onEndShowScripts($action)
    {
        $action->script($this->path('tabfocus.js'));
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'TabFocus',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews and Paul Irish',
                            'homepage' => 'http://status.net/wiki/Plugin:TabFocus',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('TabFocus changes the notice form behavior so that, while in the text area, pressing the tab key focuses the "Send" button, matching the behavior of Twitter.'));
        return true;
    }
}
