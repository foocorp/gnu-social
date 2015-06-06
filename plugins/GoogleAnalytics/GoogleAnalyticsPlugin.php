<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to use Google Analytics
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
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to use Google Analytics
 *
 * This plugin will spoot out the correct JavaScript spell to invoke Google Analytics on a page.
 *
 * Note that Google Analytics is not compatible with the Franklin Street Statement; consider using
 * Piwik (http://www.piwik.org/) instead!
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Event
 */
class GoogleAnalyticsPlugin extends Plugin
{
    var $code;
    var $domain;

    const VERSION = '0.2';

    function __construct($code=null)
    {
        if (!empty($code)) {
            global $config;
            $config['googleanalytics']['code'] = $code;
        }

        parent::__construct();
    }

    function onEndShowScripts($action)
    {
        $code = common_config('googleanalytics', 'code');
        if (empty($code)) {
            $code = $this->code;
        }
        $domain = common_config('googleanalytics', 'domain');
        if (empty($domain)) {
            $domain = $this->domain;
        }

        $js = <<<ENDOFSCRIPT0

var _gaq = _gaq || [];
_gaq.push(['_setAccount', '{$code}']);
_gaq.push(['_trackPageview']);

ENDOFSCRIPT0;

if (!empty($domain)) {
        $js .= <<<ENDOFSCRIPT1

_gaq.push(['_setDomainName', '{$domain}']);
_gaq.push(['_setAllowHash', false]);

ENDOFSCRIPT1;
}

        $js .= <<<ENDOFSCRIPT2

(function() {
   var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
   ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
   var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();

ENDOFSCRIPT2;

       $action->inlineScript($js);
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'GoogleAnalytics',
                            'version' => self::VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:GoogleAnalytics',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Use <a href="http://www.google.com/analytics/">Google Analytics</a>'.
                               ' to track web access.'));
        return true;
    }
}
