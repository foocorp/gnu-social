<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to add Echo/JS-Kit commenting to notice pages
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to use Echo (formerly JS-Kit)
 *
 * This plugin adds an Echo commenting widget to each notice page on
 * your site.  To get it to work, first you'll have to sign up for Echo
 * (a for-pay service) and register your site's URL.
 *
 *     http://aboutecho.com/
 *
 * Once you've done that it's pretty straight forward to turn the
 * plugin on; just add this to your config.php:
 *
 *     addPlugin(
 *        'Echo',
 *        array('div_style' => 'width:675px; padding-top:10px; position:relative; float:left;')
 *     );
 *
 * NOTE: the 'div_style' in an optional parameter that passes in some
 * inline CSS when creating the Echo widget. It's a shortcut to make
 * the widget look OK with the default StatusNet theme. If you leave
 * it out you'll have to edit your theme CSS files to make the widget
 * look good. You can also control the way the widget looks by
 * adding style rules to your theme.
 *
 * See: http://wiki.js-kit.com/Skinning-Guide#UsingCSSnbsptocustomizefontsandcolors
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Event
 */
class EchoPlugin extends Plugin
{
    // NOTE: The Echo documentation says that this script will change on
    // a per site basis, but I think that's incorrect. It always seems to
    // be the same.
    public $script = 'http://cdn.js-kit.com/scripts/comments.js';

    function onEndShowScripts($action)
    {
        if (get_class($action) == 'ShownoticeAction') {
            $action->script($this->script);
        }

        return true;
    }

    function onEndShowContentBlock($action)
    {
        if (get_class($action) == 'ShownoticeAction') {

            $attrs = array();
            $attrs['class'] = 'js-kit-comments';
            $attrs['permalink'] = $action->notice->uri;
            $attrs['uniq'] = $action->notice->id;

            // NOTE: there are some other attributes that could be useful
            // http://wiki.js-kit.com/Echo-Behavior

            if (!empty($this->div_style)) {
                $attrs['style'] = $this->div_style;
            }

            $action->element('div', $attrs, null);
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Echo',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Zach Copley',
                            'homepage' => 'http://status.net/wiki/Plugin:Echo',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Use <a href="http://aboutecho.com/">Echo</a>'.
                               ' to add commenting to notice pages.'));
        return true;
    }
}
