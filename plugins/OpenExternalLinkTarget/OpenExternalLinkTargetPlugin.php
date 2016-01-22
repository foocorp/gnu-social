<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Opens links with rel=external on a new window or tab
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
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Opens links with rel=external on a new window or tab
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class OpenExternalLinkTargetPlugin extends Plugin
{
    function onEndShowScripts($action)
    {
        $action->inlineScript('$(document).on("click", "a[rel~=external]:not([class~=attachment])", function () { window.open(this.href); return false; });');

        return true;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'OpenExternalLinkTarget',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Sarven Capadisli',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/OpenExternalLinkTarget',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Opens external links (i.e. with rel=external) on a new window or tab.'));
        return true;
    }
}
