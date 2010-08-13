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
 * (a commercial service) and register your site's URL.
 *
 *     http://aboutecho.com/
 *
 * Once you've done that it's pretty straight forward to turn the
 * plugin on, just add:
 *
 *     addPlugin('Echo');
 *
 * to your config.php. The defaults should work OK with the default
 * theme, but there are a lot of options to customize the look and
 * feel of the comment widget. You can control both the CSS for the
 * div that contains the widget, as well as the CSS for the widget
 * itself via config parameters that can be passed into the plugin.
 * See below for a more complex example:
 *
 * // Custom stylesheet for Echo commenting widget
 * // See: http://wiki.js-kit.com/Skinning-Guide#UsingCSSnbsptocustomizefontsandcolors
 * $stylesheet = <<<ENDOFCSS
 * .js-CommentsArea { width: 400px; }
 * .jsk-HeaderWrapper { display: none; }
 * .jsk-ItemUserAvatar { display: none; }
 * .jsk-ItemBody { margin-left: -48px; }
 * .js-kit-avatars-wrapper { display: none; }
 * .js-kit-nonLoggedUserInfo { margin-left: -75px; }
 * .js-singleViaLinkWrapper { display: none; }
 * .js-CommentsSkin-echo div.jsk-ThreadWrapper { padding: 0px; }
 * .js-singleCommentAdminStar { display: none !important; }
 * .js-singleCommentName { margin-right: 1em; }
 * .js-kit-miniProfile { background-color:#FFFFFF; }
 * .jskit-MenuContainer { background-color:#FFFFFF; }
 * .jskit-MenuItemMO { background-color: #EDEDED; }
 * .jsk-CommentFormButton { display: none; }
 * .js-singleCommentReplyable { display: none; }
 * .jsk-CommentFormSurface { display: none; }
 * .js-kit-tab-follow { display: none; }
 * ENDOFCSS;
 *
 * addPlugin(
 *   'Echo',
 *    array
 *    (
 *        // div_css is the css for the div containing the comment widget
 *        'div_css' => 'width:675px; padding-top:10px; position:relative; float:left;',
 *        // stylesheet is the CSS for the comment widget itself
 *        'stylesheet' => $stylesheet
 *    )
 * );
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

            if (empty($this->div_css)) {
                // This CSS seems to work OK with the default theme
                $attrs['style'] = 'width:675px; padding-top:10px; position:relative; float:left;';
            } else {
                $attrs['style'] = $this->css;
            }

            $action->element('div', $attrs, null);
        }
    }

    function onEndShowStyles($action)
    {
        if (get_class($action) == 'ShownoticeAction' && !empty($this->stylesheet)) {
            $action->style($this->stylesheet);
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Echo',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Zach Copley',
                            'homepage' => 'http://status.net/wiki/Plugin:Echo',
                            'rawdescription' =>
                            _m('Use <a href="http://aboutecho.com/">Echo</a>'.
                               ' to add commenting to notice pages.'));
        return true;
    }
}
