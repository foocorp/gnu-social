<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to add additional awesomenss to StatusNet
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
 * @author    Jeroen De Dauw <jeroendedauw@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Fun sample plugin: tweaks input data and adds a 'Cornify' widget to sidebar.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Jeroen De Dauw <jeroendedauw@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class AwesomenessPlugin extends Plugin
{
    const VERSION = '0.0.42';

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array(
            'name' => 'Awesomeness',
            'version' => self::VERSION,
            'author' => 'Jeroen De Dauw',
            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/Awesomeness',
            // TRANS: Plugin description for a sample plugin.
            'rawdescription' => _m('The Awesomeness plugin adds additional awesomeness ' .
                'to a StatusNet installation.'
            )
        );
        return true;
    }

    /**
     * Add the conrnify button
     *
     * @param Action $action the current action
     *
     * @return void
     */
    function onEndShowSections(Action $action)
    {
        $action->elementStart('div', array('id' => 'cornify_section',
                                         'class' => 'section'));

        $action->raw(
        <<<EOT
                <a href="http://www.cornify.com" onclick="cornify_add();return false;">
                <img src="http://www.cornify.com/assets/cornify.gif" width="61" height="16" border="0" alt="Cornify" />
                </a>
        <script type="text/javascript">(function() {
        var js = document.createElement('script');
        js.type = 'text/javascript';
        js.async = true;
        js.src = 'http://www.cornify.com/js/cornify.js';
        (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(js);
})();</script>
EOT
        );

        $action->elementEnd('div');
    }

    /**
     * Hook for new-notice form processing to take our HTML goodies;
     * won't affect API posting etc.
     *
     * @param NewNoticeAction $action
     * @param User $user
     * @param string $content
     * @param array $options
     * @return boolean hook return
     */
    function onStartSaveNewNoticeWeb($action, $user, &$content, &$options)
    {
        $content = htmlspecialchars($content);
        $options['rendered'] = preg_replace("/(^|\s|-)((?:awesome|awesomeness)[\?!\.\,]?)(\s|$)/i", " <b>$2</b> ", $content);
    }
}
