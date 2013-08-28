<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin for Google Adsense
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
 * Plugin to add Google Adsense to StatusNet sites
 *
 * This plugin lets you add Adsense ad units to your StatusNet site.
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
 * To enable advertising, you must sign up with Google Adsense and
 * get a client ID.
 *
 *     https://www.google.com/adsense/
 *
 * You'll also need to create an Adsense for Content unit in one
 * of the four sizes described above. At the end of the process,
 * note the "google_ad_client" and "google_ad_slot" values in the
 * resultant Javascript.
 *
 * Add the plugin to config.php like so:
 *
 *     addPlugin('Adsense', array('client' => 'Your client ID',
 *                                'rectangle' => 'slot'));
 *
 * Here, your client ID is the value of google_ad_client and the
 * slot is the value of google_ad_slot. Note that if you create
 * a different size, you'll need to provide different arguments:
 * 'mediumRectangle', 'leaderboard', or 'wideSkyscraper'.
 *
 * If for some reason your ad server is different from the default,
 * use the 'adScript' parameter to set the full path to the ad script.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @seeAlso  UAPPlugin
 */
class AdsensePlugin extends UAPPlugin
{
    public $adScript = 'http://pagead2.googlesyndication.com/pagead/show_ads.js';
    public $client   = null;

    function initialize()
    {
        parent::initialize();

        // A little bit of chicanery so we avoid overwriting values that
        // are passed in with the constructor
        foreach (array('mediumRectangle', 'rectangle', 'leaderboard', 'wideSkyscraper', 'adScript', 'client') as $setting) {
            $value = common_config('adsense', strtolower($setting));
            if (!empty($value)) { // not found
                $this->$setting = $value;
            }
        }
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
        $this->showAdsenseCode($action, 300, 250, $this->mediumRectangle);
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
        $this->showAdsenseCode($action, 180, 150, $this->rectangle);
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
        $this->showAdsenseCode($action, 160, 600, $this->wideSkyscraper);
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
        $this->showAdsenseCode($action, 728, 90, $this->leaderboard);
    }

    /**
     * Output the bits of JavaScript code to show Adsense
     *
     * @param Action  $action Action being shown
     * @param integer $width  Width of the block
     * @param integer $height Height of the block
     * @param string  $slot   Slot identifier
     *
     * @return void
     */
    protected function showAdsenseCode($action, $width, $height, $slot)
    {
        $code  = 'google_ad_client = "'.$this->client.'"; ';
        $code .= 'google_ad_slot = "'.$slot.'"; ';
        $code .= 'google_ad_width = '.$width.'; ';
        $code .= 'google_ad_height = '.$height.'; ';

        $action->inlineScript($code);

        $action->script($this->adScript);
    }

    function onRouterInitialized($m)
    {
        $m->connect('panel/adsense',
                    array('action' => 'adsenseadminpanel'));

        return true;
    }

    function onEndAdminPanelNav($menu) {
        if (AdminPanelAction::canAdmin('adsense')) {
            // TRANS: Menu item title/tooltip
            $menu_title = _m('AdSense configuration');
            // TRANS: Menu item for site administration
            $menu->out->menuItem(common_local_url('adsenseadminpanel'), _m('MENU','AdSense'),
                                 $menu_title, $action_name == 'adsenseadminpanel', 'nav_adsense_admin_panel');
        }
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'BlankAdPlugin',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Adsense',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Plugin to add Google AdSense to StatusNet sites.'));
        return true;
    }
}
