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
    function value($name)
    {
        if (isset($_POST[$name])) {
            return htmlspecialchars(strval($_POST[$name]));
        } else {
            return '';
        }
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
            $this->showForm();
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
        if (isset($_POST['dbtype'])) {
            $dbtype = $_POST['dbtype'];
        } else {
            $dbtype = null;
        }
        foreach (self::$dbModules as $type => $info) {
            if ($this->checkExtension($info['check_module'])) {
                if ($dbtype == null || $dbtype == $type) {
                    $checked = 'checked="checked" ';
                    $dbtype = $type; // if we didn't have one checked, hit the first
                } else {
                    $checked = '';
                }
                $dbRadios .= "<input type=\"radio\" name=\"dbtype\" id=\"dbtype-$type\" value=\"$type\" $checked/> $info[name]<br />\n";
            }
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
                        $dbRadios
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
                        <p class="form_guide">Nickname for the initial StatusNet user (administrator)</p>
                    </li>
                    <li>
                        <label for="admin_password">Administrator password</label>
                        <input type="password" id="admin_password" name="admin_password" value="{$post->value('admin_password')}" />
                        <p class="form_guide">Password for the initial StatusNet user (administrator)</p>
                    </li>
                    <li>
                        <label for="admin_password2">Confirm password</label>
                        <input type="password" id="admin_password2" name="admin_password2" value="{$post->value('admin_password2')}" />
                    </li>
                    <li>
                        <label for="admin_email">Administrator e-mail</label>
                        <input id="admin_email" name="admin_email" value="{$post->value('admin_email')}" />
                        <p class="form_guide">Optional email address for the initial StatusNet user (administrator)</p>
                    </li>
                    <li>
                        <label for="admin_updates">Subscribe to announcements</label>
                        <input type="checkbox" id="admin_updates" name="admin_updates" value="true" checked="checked" />
                        <p class="form_guide">Release and security feed from <a href="http://update.status.net/">update@status.net</a> (recommended)</p>
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
        $this->host     = $_POST['host'];
        $this->dbtype   = $_POST['dbtype'];
        $this->database = $_POST['database'];
        $this->username = $_POST['dbusername'];
        $this->password = $_POST['dbpassword'];
        $this->sitename = $_POST['sitename'];
        $this->fancy    = !empty($_POST['fancy']);

        $this->adminNick    = strtolower($_POST['admin_nickname']);
        $this->adminPass    = $_POST['admin_password'];
        $adminPass2         = $_POST['admin_password2'];
        $this->adminEmail   = $_POST['admin_email'];
        $this->adminUpdates = $_POST['admin_updates'];

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
        <title>Install StatusNet</title>
	<link rel="shortcut icon" href="favicon.ico"/>
        <link rel="stylesheet" type="text/css" href="theme/default/css/display.css" media="screen, projection, tv"/>
        <!--[if IE]><link rel="stylesheet" type="text/css" href="theme/base/css/ie.css" /><![endif]-->
        <!--[if lte IE 6]><link rel="stylesheet" type="text/css" theme/base/css/ie6.css" /><![endif]-->
        <!--[if IE]><link rel="stylesheet" type="text/css" href="theme/default/css/ie.css" /><![endif]-->
        <script src="js/jquery.min.js"></script>
        <script src="js/install.js"></script>
    </head>
    <body id="install">
        <div id="wrap">
            <div id="header">
                <address id="site_contact" class="vcard">
                    <a class="url home bookmark" href=".">
                        <img class="logo photo" src="theme/default/logo.png" alt="StatusNet"/>
                        <span class="fn org">StatusNet</span>
                    </a>
                </address>
            </div>
            <div id="core">
                <div id="content">
                     <div id="content_inner">
                        <h1>Install StatusNet</h1>
<?php 
$installer = new WebInstaller();
$installer->main();
?>
                   </div>
                </div>
            </div>
        </div>
    </body>
</html>
