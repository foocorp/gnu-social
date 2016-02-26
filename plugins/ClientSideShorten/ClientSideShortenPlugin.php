<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to enable client side url shortening in the status box
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

class ClientSideShortenPlugin extends Plugin
{
    function __construct()
    {
        parent::__construct();
    }

    function onEndShowScripts($action){
        if (common_logged_in()) {
            $user = common_current_user();
            $action->inlineScript('var maxNoticeLength = ' . User_urlshortener_prefs::maxNoticeLength($user));
            $action->inlineScript('var maxUrlLength = ' . User_urlshortener_prefs::maxUrlLength($user));
            $action->script($this->path('shorten.js'));
        }
    }

    function onRouterInitialized($m)
    {
        if (common_logged_in()) {
            $m->connect('plugins/ClientSideShorten/shorten', array('action'=>'shorten'));
        }
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Shorten',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/ClientSideShorten',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('ClientSideShorten causes the web interface\'s notice form to automatically shorten URLs as they entered, and before the notice is submitted.'));
        return true;
    }
}
