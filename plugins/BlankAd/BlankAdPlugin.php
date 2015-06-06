<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin for testing ad layout
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
 * @category  Ads
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin for testing ad layout
 *
 * This plugin uses the UAPPlugin framework to output ad content. However,
 * its ad content is just images with one red pixel stretched to the
 * right size. It's mostly useful for debugging theme layout.
 *
 * To use this plugin, set the parameter for the ad size you want to use
 * to true (or anything non-null). For example, to make a leaderboard:
 *
 *     addPlugin('BlankAd', array('leaderboard' => true));
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @seeAlso  Location
 */
class BlankAdPlugin extends UAPPlugin
{
    /**
     * Show a medium rectangle 'ad'
     *
     * @param Action $action Action being shown
     *
     * @return void
     */
    protected function showMediumRectangle($action)
    {
        $action->element('img',
                         array('width' => 300,
                               'height' => 250,
                               'src' => $this->path('redpixel.png')),
                         '');
    }

    /**
     * Show a rectangle 'ad'
     *
     * @param Action $action Action being shown
     *
     * @return void
     */
    protected function showRectangle($action)
    {
        $action->element('img',
                         array('width' => 180,
                               'height' => 150,
                               'src' => $this->path('redpixel.png')),
                         '');
    }

    /**
     * Show a wide skyscraper ad
     *
     * @param Action $action Action being shown
     *
     * @return void
     */
    protected function showWideSkyscraper($action)
    {
        $action->element('img',
                         array('width' => 160,
                               'height' => 600,
                               'src' => $this->path('redpixel.png')),
                         '');
    }

    /**
     * Show a leaderboard ad
     *
     * @param Action $action Action being shown
     *
     * @return void
     */
    protected function showLeaderboard($action)
    {
        $action->element('img',
                         array('width' => 728,
                               'height' => 90,
                               'src' => $this->path('redpixel.png')),
                         '');
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'BlankAd',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:BlankAdPlugin',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Plugin for testing ad layout.'));
        return true;
    }
}
