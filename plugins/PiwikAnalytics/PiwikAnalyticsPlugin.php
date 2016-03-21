<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to use Piwik Analytics
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
 * @author    Evan Prodromou <evan@status.net>
 * @author    Tobias Diekershoff <tobias.diekershoff@gmx.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to use Piwik Analytics (based on the Analytics plugin by Evan)
 *
 * This plugin will spoot out the correct JavaScript spell to invoke
 * Piwik Analytics on a page.
 *
 * To use this plugin add the following to your config.php
 *
 *  addPlugin('PiwikAnalytics', array('piwikroot' => 'example.com/piwik/',
 *                                    'piwikId' => 'id'));
 *
 * Replace 'example.com/piwik/' with the URL to your Piwik installation and
 * make sure you don't forget the final /.
 * Replace 'id' with the ID your statusnet installation has in your Piwik
 * analytics setup - for example '8'.
 *
 */
class PiwikAnalyticsPlugin extends Plugin
{
    /** the base of your Piwik installation */
    public $piwikroot = null;
    /** the Piwik Id of your statusnet installation */
    public $piwikId   = null;

    /**
     * constructor
     *
     * @param string $root Piwik root URL
     * @param string $id   Piwik ID of this app
     */
    function __construct($root=null, $id=null)
    {
        $this->piwikroot = $root;
        $this->piwikId   = $id;
        parent::__construct();
    }

    /**
     * Called when all scripts have been shown
     *
     * @param Action $action Current action
     *
     * @return boolean ignored
     */
    function onEndShowScripts($action)
    {
        // Slight modification to the default code.
        // Loading the piwik.js file from a <script> created in a document.write
        // meant that the browser had no way to preload it, ensuring that its
        // loading will be synchronous, blocking further page rendering.
        //
        // User-agents understand protocol-relative links, so instead of the
        // URL produced in JS we can just give a universal one. Since it's
        // sitting there in the DOM ready to go, the browser can preload the
        // file for us and we're less likely to have to wait for it.
        $piwikUrl = '//' . $this->piwikroot . 'piwik.js';
        $piwikCode = <<<ENDOFPIWIK
try {
var pkBaseURL = (("https:" == document.location.protocol) ? "https://{$this->piwikroot}" : "http://{$this->piwikroot}");
    var piwikTracker = Piwik.getTracker(pkBaseURL + "piwik.php", {$this->piwikId});
    piwikTracker.trackPageView();
    piwikTracker.enableLinkTracking();
} catch( err ) {}
ENDOFPIWIK;

        // Don't use $action->script() here; it'll try to preface the URL.
        $action->element('script', array('type' => 'text/javascript', 'src' => $piwikUrl), ' ');
        $action->inlineScript($piwikCode);
        return true;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'PiwikAnalytics',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Tobias Diekershoff, Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Piwik',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Use <a href="http://piwik.org/">Piwik</a> Open Source web analytics software.'));
        return true;
    }
}
