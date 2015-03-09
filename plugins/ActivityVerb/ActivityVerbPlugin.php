<?php
/**
 * GNU social - a federating social network
 *
 * Plugin that handles activity verb interact (like 'favorite' etc.)
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
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2014 Free Software Foundation http://fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class ActivityVerbPlugin extends Plugin
{
    public function onRouterInitialized(URLMapper $m)
    {
        $m->connect('notice/:id/:verb',
                    array('action' => 'activityverb'),
                    array('id'     => '[0-9]+',
                          'verb'   => '[a-z]+'));
        $m->connect('activity/:id/:verb',
                    array('action' => 'activityverb'),
                    array('id'     => '[0-9]+',
                          'verb'   => '[a-z]+'));
    }

    public function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Activity Verb',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'https://www.gnu.org/software/social/',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Adds more standardized verb handling for activities.'));
        return true;
    }
}
