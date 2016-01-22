<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin for OpenX ad server
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
 * Plugin for OpenX Ad Server
 *
 * This plugin supports the OpenX ad server, http://www.openx.org/
 *
 * We support the 4 ad sizes for the Universal Ad Platform (UAP):
 *
 *     Medium Rectangle
 *     (Small) Rectangle
 *     Leaderboard
 *     Wide Skyscraper
 *
 * They fit in different places on the default theme. Some themes
 * might interact quite poorly with this plugin.
 *
 * To enable advertising, you will need an OpenX server. You'll need
 * to set up a "zone" for your StatusNet site that identifies a
 * kind of ad you want to place (of the above 4 sizes).
 *
 * Add the plugin to config.php like so:
 *
 *     addPlugin('OpenX', array('adScript' => 'full path to script',
 *                              'rectangle' => 1));
 *
 * Here, the 'adScript' parameter is the full path to the OpenX
 * ad script, like 'http://example.com/www/delivery/ajs.php'. Note
 * that we don't do any magic to swap between HTTP and HTTPS, so
 * if you want HTTPS, say so.
 *
 * The 'rectangle' parameter is the zone ID for that ad space on
 * your site. If you've configured another size, try 'mediumRectangle',
 * 'leaderboard', or 'wideSkyscraper'.
 *
 * If for some reason your ad server is different from the default,
 * use the 'adScript' parameter to set the full path to the ad script.
 *
 * @category Ads
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @seeAlso  UAPPlugin
 */
class OpenXPlugin extends UAPPlugin
{
    public $adScript = null;

    function initialize()
    {
        parent::initialize();

        // A little bit of chicanery so we avoid overwriting values that
        // are passed in with the constructor
        foreach (array('mediumRectangle', 'rectangle', 'leaderboard', 'wideSkyscraper', 'adScript') as $setting) {
            $value = common_config('openx', $setting);
            if (!empty($value)) { // not found
                $this->$setting = $value;
            }
        }

        return true;
    }

    /**
     * Show a medium rectangle 'ad'
     *
     * @param Action $action Action being shown
     *
     * @return void
     */
    protected function showMediumRectangle($action)
    {
        $this->showAd($action, $this->mediumRectangle);
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
        $this->showAd($action, $this->rectangle);
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
        $this->showAd($action, $this->wideSkyscraper);
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
        $this->showAd($action, $this->leaderboard);
    }

    /**
     * Show an ad using OpenX
     *
     * @param Action  $action Action being shown
     * @param integer $zone   Zone to show
     *
     * @return void
     */
    protected function showAd($action, $zone)
    {
$scr = <<<ENDOFSCRIPT
var m3_u = '%s';
var m3_r = Math.floor(Math.random()*99999999999);
if (!document.MAX_used) document.MAX_used = ',';
document.write ("<scr"+"ipt type='text/javascript' src='"+m3_u);
document.write ("?zoneid=%d");
document.write ('&amp;cb=' + m3_r);
if (document.MAX_used != ',') document.write ("&amp;exclude=" + document.MAX_used);
document.write (document.charset ? '&amp;charset='+document.charset : (document.characterSet ? '&amp;charset='+document.characterSet : ''));
document.write ("&amp;loc=" + escape(window.location));
if (document.referrer) document.write ("&amp;referer=" + escape(document.referrer));
if (document.context) document.write ("&context=" + escape(document.context));
if (document.mmm_fo) document.write ("&amp;mmm_fo=1");
document.write ("'><\/scr"+"ipt>");
ENDOFSCRIPT;

        $action->inlineScript(sprintf($scr, $this->adScript, $zone));
        return true;
    }

    function onRouterInitialized($m)
    {
        $m->connect('panel/openx',
                    array('action' => 'openxadminpanel'));

        return true;
    }

    function onEndAdminPanelNav($menu) {
        if (AdminPanelAction::canAdmin('openx')) {
            // TRANS: Menu item title.
            $menu_title = _m('OpenX configuration.');
            // TRANS: Menu item for site administration
            $menu->out->menuItem(common_local_url('openxadminpanel'), _m('OpenX'),
                                 $menu_title, $action_name == 'openxadminpanel', 'nav_openx_admin_panel');
        }
        return true;
    }

    /**
     * Add our version information to output
     *
     * @param array &$versions Array of version-data arrays
     *
     * @return boolean hook value
     */
    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'OpenX',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/OpenX',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Plugin for <a href="http://www.openx.org/">OpenX Ad Server</a>.'));
        return true;
    }
}
