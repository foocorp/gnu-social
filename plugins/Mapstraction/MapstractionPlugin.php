<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to provide map visualization of location data
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to provide map visualization of location data
 *
 * This plugin uses the Mapstraction JavaScript library to
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @seeAlso  Location
 */

class MapstractionPlugin extends Plugin
{
    const VERSION = STATUSNET_VERSION;

    /** provider name, one of:
     'cloudmade', 'google', 'microsoft', 'openlayers', 'yahoo' */
    public $provider = 'openlayers';
    /** provider API key (or 'appid'), if required ('google' and 'yahoo' only) */
    public $apikey = null;

    /**
     * Hook for new URLs
     *
     * The way to register new actions from a plugin.
     *
     * @param Router $m reference to router
     *
     * @return boolean event handler return
     */

    function onRouterInitialized($m)
    {
        $m->connect(':nickname/all/map',
                    array('action' => 'allmap'),
                    array('nickname' => '['.NICKNAME_FMT.']{1,64}'));
        $m->connect(':nickname/map',
                    array('action' => 'usermap'),
                    array('nickname' => '['.NICKNAME_FMT.']{1,64}'));
        return true;
    }

    /**
     * Hook for autoloading classes
     *
     * This makes sure our classes get autoloaded from our directory
     *
     * @param string $cls name of class being used
     *
     * @return boolean event handler return
     */

    function onAutoload($cls)
    {
        switch ($cls)
        {
        case 'AllmapAction':
        case 'UsermapAction':
        case 'MapAction':
            include_once INSTALLDIR.'/plugins/Mapstraction/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Hook for adding extra JavaScript
     *
     * This makes sure our scripts get loaded for map-related pages
     *
     * @param Action $action Action object for the page
     *
     * @return boolean event handler return
     */

    function onEndShowScripts($action)
    {
        $actionName = $action->trimmed('action');

        if (!in_array($actionName,
                      array('showstream', 'all', 'usermap', 'allmap'))) {
            return true;
        }

        switch ($this->provider)
        {
        case 'cloudmade':
            $action->script('http://tile.cloudmade.com/wml/0.2/web-maps-lite.js');
            break;
        case 'google':
            $action->script(sprintf('http://maps.google.com/maps?file=api&v=2&sensor=false&key=%s',
                                    urlencode($this->apikey)));
            break;
        case 'microsoft':
            $action->script('http://dev.virtualearth.net/mapcontrol/mapcontrol.ashx?v=6');
            break;
        case 'openlayers':
            // XXX: is this not nice...?
            $action->script('http://openlayers.org/api/OpenLayers.js');
            break;
        case 'yahoo':
            $action->script(sprintf('http://api.maps.yahoo.com/ajaxymap?v=3.8&appid=%s',
                                    urlencode($this->apikey)));
            break;
        case 'geocommons': // don't support this yet
        default:
            return true;
        }

        $action->script(sprintf('%s?(%s)',
                                common_path('plugins/Mapstraction/js/mxn.js'),
                                $this->provider));

        $action->script(common_path('plugins/Mapstraction/usermap.js'));

        $action->inlineScript(sprintf('var _provider = "%s";', $this->provider));

        // usermap and allmap handle this themselves

        if (in_array($actionName,
                     array('showstream', 'all'))) {
            $action->inlineScript('$(document).ready(function() { '.
                                  ' var user = null; '.
                                  (($actionName == 'showstream') ? ' user = scrapeUser(); ' : '') .
                                  ' var notices = scrapeNotices(user); ' .
                                  ' showMapstraction($("#map_canvas"), notices); '.
                                  '});');
        }

        return true;
    }

    function onEndShowSections($action)
    {
        $actionName = $action->trimmed('action');
        // These are the ones that have maps on 'em
        if (!in_array($actionName,
                      array('showstream', 'all'))) {
            return true;
        }

        $action->elementStart('div', array('id' => 'entity_map',
                                         'class' => 'section'));

        $action->element('h2', null, _m('Map'));

        $action->element('div', array('id' => 'map_canvas',
                                    'class' => 'gray smallmap',
                                    'style' => "width: 100%; height: 240px"));

        $mapAct = ($actionName == 'showstream') ? 'usermap' : 'allmap';
        $mapUrl =  common_local_url($mapAct,
                                    array('nickname' => $action->trimmed('nickname')));

        $action->element('a', array('href' => $mapUrl),
                         _m("Full size"));

        $action->elementEnd('div');
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Mapstraction',
                            'version' => self::VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Mapstraction',
                            'rawdescription' =>
                            _m('Show maps of users\' and friends\' notices '.
                               'with <a href="http://www.mapstraction.com/">Mapstraction</a> '.
                               'JavaScript library.'));
        return true;
    }
}
