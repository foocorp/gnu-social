<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to add Disqus commenting to notice pages
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
 *
 * This plugin adds Disqus commenting to your notices. Enabling this
 * plugin will make each notice page display the Disqus widget, and
 * notice lists will display the number of commments each notice has.
 *
 * To use this plugin, you need to first register your site with Disqus
 * and get a Discus 'shortname' for it.
 *
 *    http://disqus.com
 *
 * To enable the plugin, put the following in you config.php:
 *
 * addPlugin(
 *     'Disqus', array(
 *         'shortname' => 'YOURSHORTNAME',
 *         'divStyle'  => 'width:675px; padding-top:10px; position:relative; float:left;'
 *     )
 * );
 *
 * If you only want to allow commenting on a specific user's notices or
 * a specific set of users' notices initialize the plugin with the "restricted"
 * parameter and grant the "richedit" role to those users. E.g.:
 *
 * addPlugin(
 *     'Disqus', array(
 *         'shortname'  => 'YOURSHORTNAME',
 *         'divStyle'   => 'width:675px; padding-top:10px; position:relative; float:left;',
 *         'restricted' => true
 *     )
 * );
 *
 * $ php userrole.php -s#### -nusername -rrichedit
 *
 *
 * NOTE: the 'divStyle' in an optional parameter that passes in some
 * inline CSS when creating the Disqus widget. It's a shortcut to make
 * the widget look OK with the default StatusNet theme. If you leave
 * it out you'll have to edit your theme CSS files to make the widget
 * look good.  You can also control the way the widget looks by
 * adding style rules to your theme.
 *
 * See: http://help.disqus.com/entries/100878-css-customization
 *
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Event
 */
class DisqusPlugin extends Plugin
{
    public $shortname; // Required 'shortname' for actually triggering Disqus
    public $divStyle;  // Optional CSS chunk for the main <div>

    // By default, Disqus commenting will be available to all users.
    // With restricted on, only users who have been granted the
    // "richedit" role get it.
    public $restricted = false;

    /**
     * Add a Disqus commenting section to the end of an individual
     * notice page's content block
     *
     * @param Action $action The current action
     */
    function onEndShowContentBlock($action)
    {
        if (get_class($action) == 'ShownoticeAction') {

            $profile = Profile::getKV('id', $action->notice->profile_id);

            if ($this->isAllowedRichEdit($profile)) {

                $attrs = array();
                $attrs['id'] = 'disqus_thread';

                if ($this->divStyle) {
                    $attrs['style'] = $this->divStyle;
                }

                $action->element('div', $attrs, null);

                $script = <<<ENDOFSCRIPT
    var disqus_identifier = %d;
      (function() {
       var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
       dsq.src = 'http://%s.disqus.com/embed.js';
       (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
      })();
ENDOFSCRIPT;

                $action->inlineScript(sprintf($script, $action->notice->id, $this->shortname));

                $attrs = array();

                $attrs['id'] = 'disqus_thread_footer';

                if ($this->divStyle) {
                    $attrs['style'] = $this->divStyle;
                }

                $action->elementStart('div', $attrs);
                $action->elementStart('noscript');

                // TRANS: User notification that JavaScript is required for Disqus comment display.
                // TRANS: This message contains Markdown links in the form [description](link).
                $noScriptMsg = sprintf(_m("Please enable JavaScript to view the [comments powered by Disqus](http://disqus.com/?ref_noscript=%s)."), $this->shortname);
                $output = common_markup_to_html($noScriptMsg);
                $action->raw($output);

                $action->elementEnd('noscript');

                $action->elementStart('a', array('href' => 'http://disqus.com', 'class' => 'dsq-brlink'));
                // TRANS: This message is followed by a Disqus logo. Alt text is "Disqus".
                $action->raw(_m('Comments powered by '));
                $action->element('span', array('class' => 'logo-disqus'), 'Disqus');
                $action->elementEnd('a');
                $action->elementEnd('div');
            }
        }
    }

    /**
     * Add Disqus comment count script to the end of the scripts section
     *
     * @param Action $action the current action
     *
     */
    function onEndShowScripts($action)
    {
        // fugly
        $script = <<<ENDOFSCRIPT
var disqus_shortname = '%s';
(function () {
  var s = document.createElement('script'); s.async = true;
  s.src = 'http://disqus.com/forums/%s/count.js';
  (document.getElementsByTagName('HEAD')[0] || document.getElementsByTagName('BODY')[0]).appendChild(s);
}());
ENDOFSCRIPT;
        $action->inlineScript(sprintf($script, $this->shortname, $this->shortname));

    }

    /**
     * Tack on a Disqus comments link to the notice options stanza
     * (the link displays the total number of comments for each notice)
     *
     * @param NoticeListItem $noticeListItem
     *
     */
    function onEndShowNoticeInfo($noticeListItem)
    {
        // Don't enable commenting for remote notices
        if (empty($noticeListItem->notice->is_local)) {
            return;
        }

        $profile = Profile::getKV('id', $noticeListItem->notice->profile_id);

        if ($this->isAllowedRichEdit($profile)) {
            $noticeUrl = $noticeListItem->notice->bestUrl();
            $noticeUrl .= '#disqus_thread';

            $noticeListItem->out->element(
                'a',
                array('href' => $noticeUrl, 'class' => 'disqus_count'),
                // TRANS: Plugin supplied feature for Disqus comments to notices.
                _m('Comments')
            );
        }
    }

    /**
     * Does the current user have permission to use the Disqus plugin?
     * Always true unless the plugin's "restricted" setting is on, in which
     * case it's limited to users with the "richedit" role.
     *
     * @fixme make that more sanely configurable :)
     *
     * @param Profile $profile the profile to check
     *
     * @return boolean
     */
    private function isAllowedRichEdit($profile)
    {
        if ($this->restricted) {
            $user = User::getKV($profile->id);
            return !empty($user) && $user->hasRole('richedit');
        } else {
            return true;
        }
    }

    /**
     * Plugin details
     *
     * @param &$versions Array of current plugins
     *
     * @return boolean true
     */
    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Disqus',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Zach Copley',
                            'homepage' => 'http://status.net/wiki/Plugin:Disqus',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Use <a href="http://disqus.com/">Disqus</a>'.
                               ' to add commenting to notice pages.'));
        return true;
    }
}
