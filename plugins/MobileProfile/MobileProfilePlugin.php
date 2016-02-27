<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * XHTML Mobile Profile plugin that uses WAP 2.0 Plugin
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
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

define('PAGE_TYPE_PREFS_MOBILEPROFILE',
       'application/vnd.wap.xhtml+xml, application/xhtml+xml, text/html;q=0.9');

require_once INSTALLDIR.'/plugins/Mobile/WAP20Plugin.php';

/**
 * Superclass for plugin to output XHTML Mobile Profile
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class MobileProfilePlugin extends WAP20Plugin
{
    public $DTD            = null;
    public $serveMobile    = false;
    public $reallyMobile   = false;
    public $mobileFeatures = array();

    function __construct($DTD='http://www.wapforum.org/DTD/xhtml-mobile10.dtd')
    {
        $this->DTD = $DTD;

        parent::__construct();
    }

    public function onStartShowHTML(Action $action)
    {
        // TODO: A lot of this should probably graduate to WAP20Plugin

        $httpaccept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : null;

        $cp = common_accept_to_prefs($httpaccept);
        $sp = common_accept_to_prefs(PAGE_TYPE_PREFS_MOBILEPROFILE);

        $type = common_negotiate_type($cp, $sp);

        if (!$type) {
            // TRANS: Client exception thrown when requesting a not supported media type.
            throw new ClientException(_m('This page is not available in a '.
                                        'media type you accept.'), 406);
        }

        // If they are on the mobile site, serve them MP
        if ((common_config('site', 'mobileserver').'/'.common_config('site', 'path').'/'
                == $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'])) {
            $this->serveMobile = true;
        } elseif (isset($_COOKIE['MobileOverride'])) {
            // Cookie override is controlled by link at bottom.
            $this->serveMobile = (bool)$_COOKIE['MobileOverride'];
        } elseif (strstr('application/vnd.wap.xhtml+xml', $type) !== false) {
            // If they like the WAP 2.0 mimetype, serve them MP
            $this->serveMobile = true;
        } elseif (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            // If they are a mobile device that supports WAP 2.0,
            // serve them MP

            // XXX: Browser sniffing sucks

            // I really don't like going through this every page,
            // perhaps use $_SESSION or cookies

            // May be better to group the devices in terms of
            // low,mid,high-end

            // Or, detect the mobile devices based on their support for
            // MP 1.0, 1.1, or 1.2 may be ideal. Possible?

            $this->mobiledevices = array(
                'alcatel',
                'android',
                'audiovox',
                'au-mic,',
                'avantgo',
                'blackberry',
                'blazer',
                'cldc-',
                'danger',
                'epoc',
                'ericsson',
                'ericy',
                'iphone',
                'ipaq',
                'ipod',
                'j2me',
                'lg',
                'maemo',
                'midp-',
                'mobile',
                'mot',
                'netfront',
                'nitro',
                'nokia',
                'opera mini',
                'palm',
                'palmsource',
                'panasonic',
                'philips',
                'pocketpc',
                'portalmmm',
                'rover',
                'samsung',
                'sanyo',
                'series60',
                'sharp',
                'sie-',
                'smartphone',
                'sony',
                'symbian',
                'up.browser',
                'up.link',
                'up.link',
                'vodafone',
                'wap1',
                'wap2',
                'webos',
                'windows ce'
            );

            $blacklist = array(
                'ipad', // Larger screen handles the full theme fairly well.
            );

            $httpuseragent = strtolower($_SERVER['HTTP_USER_AGENT']);

            foreach ($blacklist as $md) {
                if (strstr($httpuseragent, $md) !== false) {
                    $this->serveMobile = false;
                    return true;
                }
            }

            foreach ($this->mobiledevices as $md) {
                if (strstr($httpuseragent, $md) !== false) {
                    $this->setMobileFeatures($httpuseragent);

                    $this->serveMobile = true;
                    $this->reallyMobile = true;
                    break;
                }
            }
        }

        if (!$this->serveMobile) {
            return true;
        }

        // If they are okay with MP, and the site has a mobile server,
        // redirect there
        if (common_config('site', 'mobileserver') !== false &&
                common_config('site', 'mobileserver') != common_config('site', 'server')) {

            // FIXME: Redirect to equivalent page on mobile site instead
            common_redirect($this->_common_path(''), 302);
        }

        header('Content-Type: '.$type);

        if ($this->reallyMobile) {
           $action->setDTD('html', '-//WAPFORUM//DTD XHTML Mobile 1.0//EN', $this->DTD);
        }

        // continue
        return true;
    }

    function setMobileFeatures($useragent)
    {
        $mobiledeviceInputFileType = array(
            'nokia'
        );

        $this->mobileFeatures['inputfiletype'] = false;

        foreach ($mobiledeviceInputFileType as $md) {
            if (strstr($useragent, $md) !== false) {
                $this->mobileFeatures['inputfiletype'] = true;
                break;
            }
        }
    }

    public function onStartShowStylesheets(Action $action)
    {
        if (!$this->serveMobile) {
            return true;
        }

        $action->primaryCssLink();

        $action->cssLink($this->path('mp-screen.css'),null,'screen');
        if (file_exists(Theme::file('css/mp-screen.css'))) {
            $action->cssLink('css/mp-screen.css', null, 'screen');
        }

        $action->cssLink($this->path('mp-handheld.css'),null,'handheld');
        if (file_exists(Theme::file('css/mp-handheld.css'))) {
            $action->cssLink('css/mp-handheld.css', null, 'handheld');
        }

        // Allow other plugins to load their styles.
        Event::handle('EndShowStylesheets', array($action));

        return false;
    }

    public function onStartShowUAStyles(Action $action) {
        if (!$this->serveMobile) {
            return true;
        }

        return false;
    }

    public function onStartShowHeader(Action $action)
    {
        if (!$this->serveMobile) {
            return true;
        }

        $action->elementStart('div', array('id' => 'header'));
        $this->_showLogo($action);
        $action->showPrimaryNav();
        $action->elementEnd('div');

        return false;
    }

    protected function _showLogo(Action $action)
    {
        $action->elementStart('address');
        if (common_config('singleuser', 'enabled')) {
            $user = User::singleUser();
            $url = common_local_url('showstream', array('nickname' => $user->getNickname()));
        } else {
            $url = common_local_url('public');
        }

        $action->elementStart('a', array('class' => 'h-card home bookmark',
                                         'href' => $url));

        if (common_config('site', 'mobilelogo') ||
            file_exists(Theme::file('logo.png')) ||
            file_exists(Theme::file('mobilelogo.png'))) {

            $action->element('img', array('class' => 'u-photo',
                'src' => (common_config('site', 'mobilelogo')) ? common_config('site', 'mobilelogo') :
                            ((file_exists(Theme::file('mobilelogo.png'))) ? (Theme::path('mobilelogo.png')) : Theme::path('logo.png')),
                'alt' => common_config('site', 'name')));
        }
        $action->elementEnd('a');
        $action->elementEnd('address');
    }

    public function onStartShowAside(Action $action)
    {
        if ($this->serveMobile) {
            return false;
        }
    }

    public function onStartShowLocalNavBlock(Action $action)
    {
        if ($this->serveMobile) {
            // @todo FIXME: "Show Navigation" / "Hide Navigation" needs i18n
            $action->element('a', array('href' => '#', 'id' => 'navtoggle'), 'Show Navigation');
        }
    }

    public function onEndShowScripts(Action $action)
    {
        // @todo FIXME: "Show Navigation" / "Hide Navigation" needs i18n
        $action->inlineScript('
            $(function() {
                $("#mobile-toggle-disable").click(function() {
                    $.cookie("MobileOverride", "0", {path: "/"});
                    window.location.reload();
                    return false;
                });
                $("#mobile-toggle-enable").click(function() {
                    $.cookie("MobileOverride", "1", {path: "/"});
                    window.location.reload();
                    return false;
                });
                $("#navtoggle").click(function () {
                          $("#site_nav_local_views").fadeToggle();
                          var text = $("#navtoggle").text();
                          $("#navtoggle").text(
                          text == "Show Navigation" ? "Hide Navigation" : "Show Navigation");
                });
            });'
        );

        if ($this->serveMobile) {
            $action->inlineScript('$(function() { $(".checkbox-wrapper").unbind("click"); });');
        }
    }


    public function onEndShowInsideFooter(Action $action)
    {
        if ($this->serveMobile) {
            // TRANS: Link to switch site layout from mobile to desktop mode. Appears at very bottom of page.
            $linkText = _m('Switch to desktop site layout.');
            $key = 'mobile-toggle-disable';
        } else {
            // TRANS: Link to switch site layout from desktop to mobile mode. Appears at very bottom of page.
            $linkText = _m('Switch to mobile site layout.');
            $key = 'mobile-toggle-enable';
        }
        $action->elementStart('p');
        $action->element('a', array('href' => '#', 'id' => $key), $linkText);
        $action->elementEnd('p');
        return true;
    }

    function _common_path($relative, $ssl=false)
    {
        $pathpart = (common_config('site', 'path')) ? common_config('site', 'path')."/" : '';

        if (($ssl && (common_config('site', 'ssl') === 'sometimes'))
            || common_config('site', 'ssl') === 'always') {
            $proto = 'https';
            if (is_string(common_config('site', 'sslserver')) &&
                mb_strlen(common_config('site', 'sslserver')) > 0) {
                $serverpart = common_config('site', 'sslserver');
            } else {
                $serverpart = common_config('site', 'mobileserver');
            }
        } else {
            $proto      = 'http';
            $serverpart = common_config('site', 'mobileserver');
        }

        return $proto.'://'.$serverpart.'/'.$pathpart.$relative;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'MobileProfile',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Sarven Capadisli',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/MobileProfile',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('XHTML MobileProfile output for supporting user agents.'));
        return true;
    }
}
