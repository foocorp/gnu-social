<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 * @category Installation
 * @package  Installation
 *
 * @author   Adrian Lang <mail@adrianlang.de>
 * @author   Brenda Wallace <shiny@cpan.org>
 * @author   Brett Taylor <brett@webfroot.co.nz>
 * @author   Brion Vibber <brion@pobox.com>
 * @author   CiaranG <ciaran@ciarang.com>
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Eric Helgeson <helfire@Erics-MBP.local>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@status.net>
 * @author   Tom Adams <tom@holizz.com>
 * @author   Zach Copley <zach@status.net>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 * @version  0.9.x
 * @link     http://status.net
 */

define('INSTALLDIR', dirname(__FILE__));

require INSTALLDIR . '/lib/installer.php';

/**
 * Helper class for building form
 */
class Posted {
    /**
     * HTML-friendly escaped string for the POST param of given name, or empty.
     * @param string $name
     * @return string
     */
    function value($name)
    {
        return htmlspecialchars($this->string($name));
    }

    /**
     * The given POST parameter value, forced to a string.
     * Missing value will give ''.
     *
     * @param string $name
     * @return string
     */
    function string($name)
    {
        return strval($this->raw($name));
    }

    /**
     * The given POST parameter value, in its original form.
     * Magic quotes are stripped, if provided.
     * Missing value will give null.
     *
     * @param string $name
     * @return mixed
     */
    function raw($name)
    {
        if (isset($_POST[$name])) {
            return $this->dequote($_POST[$name]);
        } else {
            return null;
        }
    }

    /**
     * If necessary, strip magic quotes from the given value.
     *
     * @param mixed $val
     * @return mixed
     */
    function dequote($val)
    {
        if (get_magic_quotes_gpc()) {
            if (is_string($val)) {
                return stripslashes($val);
            } else if (is_array($val)) {
                return array_map(array($this, 'dequote'), $val);
            }
        }
        return $val;
    }
}

/**
 * Web-based installer: provides a form and such.
 */
class WebInstaller extends Installer
{
    /**
     * the actual installation.
     * If call libraries are present, then install
     *
     * @return void
     */
    function main()
    {
        if (!$this->checkPrereqs()) {
            $this->warning(_('Please fix the above stated problems and refresh this page to continue installing.'));
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
        } else {
            $this->showForm();
        }
    }

    /**
     * Web implementation of warning output
     */
    function warning($message, $submessage='')
    {
        print "<p class=\"error\">$message</p>\n";
        if ($submessage != '') {
            print "<p>$submessage</p>\n";
        }
    }

    /**
     * Web implementation of status output
     */
    function updateStatus($status, $error=false)
    {
        echo '<li' . ($error ? ' class="error"': '' ) . ">$status</li>";
    }

    /**
     * Show the web form!
     */
    function showForm()
    {
        global $dbModules;
        $post = new Posted();
        $dbRadios = '';
        $dbtype = $post->raw('dbtype');
        foreach (self::$dbModules as $type => $info) {
            if ($this->checkExtension($info['check_module'])) {
                if ($dbtype == null || $dbtype == $type) {
                    $checked = 'checked="checked" ';
                    $dbtype = $type; // if we didn't have one checked, hit the first
                } else {
                    $checked = '';
                }
                $dbRadios .= sprintf('<input type="radio" name="dbtype" id="dbtype-%1$s" value="%1$s" %2$s/>%3$s<br />',
                                htmlspecialchars($type), $checked,
                                htmlspecialchars($info['name']));
            }
        }

        $ssl = array('always'=>null, 'never'=>null);
        if (!empty($_SERVER['HTTPS'])) {
            $ssl['always'] = 'checked="checked"';
        } else {
            $ssl['never'] = 'checked="checked"';
        }

        echo<<<E_O_T
    <form method="post" action="install.php" class="form_settings" id="form_install">
        <fieldset>
            <fieldset id="settings_site">
                <legend>Site settings</legend>
                <ul class="form_data">
                    <li>
                        <label for="sitename">Site name</label>
                        <input type="text" id="sitename" name="sitename" value="{$post->value('sitename')}" />
                        <p class="form_guide">The name of your site</p>
                    </li>
                    <li>
                        <label for="fancy-enable">Fancy URLs</label>
                        <input type="radio" name="fancy" id="fancy-enable" value="enable" checked='checked' /> enable<br />
                        <input type="radio" name="fancy" id="fancy-disable" value="" /> disable<br />
                        <p class="form_guide" id='fancy-form_guide'>Enable fancy (pretty) URLs. Auto-detection failed, it depends on Javascript.</p>
                    </li>
                    <li>
                        <label for="ssl">Server SSL</label>
                        <input type="radio" name="ssl" id="ssl-always" value="always" {$ssl['always']} /> enable<br />
                        <input type="radio" name="ssl" id="ssl-never" value="never" {$ssl['never']} /> disable<br />
                        <p class="form_guide" id="ssl-form_guide">Enabling SSL (https://) requires extra webserver configuration and certificate generation not offered by this installation.</p>
                    </li>
                </ul>
            </fieldset>

            <fieldset id="settings_db">
                <legend>Database settings</legend>
                <ul class="form_data">
                    <li>
                        <label for="host">Hostname</label>
                        <input type="text" id="host" name="host" value="{$post->value('host')}" />
                        <p class="form_guide">Database hostname</p>
                    </li>
                    <li>
                        <label for="dbtype">Type</label>
                        {$dbRadios}
                        <p class="form_guide">Database type</p>
                    </li>
                    <li>
                        <label for="database">Name</label>
                        <input type="text" id="database" name="database" value="{$post->value('database')}" />
                        <p class="form_guide">Database name</p>
                    </li>
                    <li>
                        <label for="dbusername">DB username</label>
                        <input type="text" id="dbusername" name="dbusername" value="{$post->value('dbusername')}" />
                        <p class="form_guide">Database username</p>
                    </li>
                    <li>
                        <label for="dbpassword">DB password</label>
                        <input type="password" id="dbpassword" name="dbpassword" value="{$post->value('dbpassword')}" />
                        <p class="form_guide">Database password (optional)</p>
                    </li>
                </ul>
            </fieldset>

            <fieldset id="settings_admin">
                <legend>Administrator settings</legend>
                <ul class="form_data">
                    <li>
                        <label for="admin_nickname">Administrator nickname</label>
                        <input type="text" id="admin_nickname" name="admin_nickname" value="{$post->value('admin_nickname')}" />
                        <p class="form_guide">Nickname for the initial user (administrator)</p>
                    </li>
                    <li>
                        <label for="admin_password">Administrator password</label>
                        <input type="password" id="admin_password" name="admin_password" value="{$post->value('admin_password')}" />
                        <p class="form_guide">Password for the initial user (administrator)</p>
                    </li>
                    <li>
                        <label for="admin_password2">Confirm password</label>
                        <input type="password" id="admin_password2" name="admin_password2" value="{$post->value('admin_password2')}" />
                    </li>
                    <li>
                        <label for="admin_email">Administrator e-mail</label>
                        <input id="admin_email" name="admin_email" value="{$post->value('admin_email')}" />
                        <p class="form_guide">Optional email address for the initial user (administrator)</p>
                    </li>
                    <li>
                        <label for="admin_updates">Subscribe to announcements</label>
                        <input type="checkbox" id="admin_updates" name="admin_updates" value="true" checked="checked" />
                        <p class="form_guide">Release and security feed from <a href="http://update.status.net/">update@status.net</a> (recommended)</p>
                    </li>
                </ul>
            </fieldset>
            <fieldset id="settings_profile">
                <legend>Site profile</legend>
                <ul class="form_data">
                    <li>
                        <label for="site_profile">Type of site</label>
                        <select id="site_profile" name="site_profile">
                            <option value="private">Private</option>
                            <option value="community">Community</option>
                            <option value ="public">Public</option>
                            <option value ="singleuser">Single User</option>
                        </select>
                        <p class="form_guide">Initial access settings for your site</p>
                    </li>
                </ul>
            </fieldset>
            <input type="submit" name="submit" class="submit" value="Submit" />
        </fieldset>
    </form>

E_O_T;
    }

    /**
     * Handle a POST submission... if we have valid input, start the install!
     * Otherwise shows the form along with any error messages.
     */
    function handlePost()
    {
        echo <<<STR
        <dl class="system_notice">
            <dt>Page notice</dt>
            <dd>
                <ul>
STR;
        $this->validated = $this->prepare();
        if ($this->validated) {
            $this->doInstall();
        }
        echo <<<STR
            </ul>
        </dd>
    </dl>
STR;
        if (!$this->validated) {
            $this->showForm();
        }
    }

    /**
     * Read and validate input data.
     * May output side effects.
     *
     * @return boolean success
     */
    function prepare()
    {
        $post = new Posted();
        $this->host     = $post->string('host');
        $this->dbtype   = $post->string('dbtype');
        $this->database = $post->string('database');
        $this->username = $post->string('dbusername');
        $this->password = $post->string('dbpassword');
        $this->sitename = $post->string('sitename');
        $this->fancy    = (bool)$post->string('fancy');

        $this->adminNick    = strtolower($post->string('admin_nickname'));
        $this->adminPass    = $post->string('admin_password');
        $adminPass2         = $post->string('admin_password2');
        $this->adminEmail   = $post->string('admin_email');
        $this->adminUpdates = $post->string('admin_updates');

        $this->siteProfile = $post->string('site_profile');

        $this->ssl = $post->string('ssl');

        $this->server = $_SERVER['HTTP_HOST'];
        $this->path = substr(dirname($_SERVER['PHP_SELF']), 1);

        $fail = false;
        if (!$this->validateDb()) {
            $fail = true;
        }

        if (!$this->validateAdmin()) {
            $fail = true;
        }

        if ($this->adminPass != $adminPass2) {
            $this->updateStatus("Administrator passwords do not match. Did you mistype?", true);
            $fail = true;
        }

        if (!in_array($this->ssl, array('never', 'sometimes', 'always'))) {
            $this->updateStatus("Bad value for server SSL enabling.");
            $fail = true;
        }

        if (!$this->validateSiteProfile()) {
            $fail = true;
        }

        return !$fail;
    }

}

?>
<?php echo"<?"; ?> xml version="1.0" encoding="UTF-8" <?php echo "?>"; ?>
<!DOCTYPE html
PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
       "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en_US" lang="en_US">
    <head>
        <title>Install GNU social</title>
        <link rel="shortcut icon" href="favicon.ico"/>
        <link rel="stylesheet" type="text/css" href="theme/base/css/display.css" media="screen, projection, tv"/>
        <link rel="stylesheet" type="text/css" href="theme/neo/css/display.css" media="screen, projection, tv"/>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <!--[if IE]><link rel="stylesheet" type="text/css" href="theme/base/css/ie.css" /><![endif]-->
        <!--[if lte IE 6]><link rel="stylesheet" type="text/css" theme/base/css/ie6.css" /><![endif]-->
        <!--[if lte IE 7]><link rel="stylesheet" type="text/css" theme/base/css/ie7.css" /><![endif]-->
        <script src="js/extlib/jquery.js"></script>
        <script src="js/install.js"></script>
    </head>
    <body id="install">
        <div id="wrap">
            <div id="header">
                <address id="site_contact" class="vcard">
                    <a class="url home bookmark" href=".">
                        <img class="logo photo" src="theme/neo/logo.png" alt="GNU social"/>
                        <span class="fn org">GNU social</span>
                    </a>
                </address>
                <div id="site_nav_global_primary"></div>
            </div>
            <div id="core">
             <div id="aside_primary_wrapper">
              <div id="content_wrapper">
               <div id="site_nav_local_views_wrapper">
                <div id="site_nav_local_views"></div>

                <div id="content">
                     <div id="content_inner">
                        <h1>Install GNU social</h1>
<?php
$installer = new WebInstaller();
$installer->main();
?>
                   </div>
                </div>

                <div id="aside_primary" class="aside"></div>
               </div>
              </div>
             </div>
            </div>
            <div id="footer"></div>
        </div>
    </body>
</html>
