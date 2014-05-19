#!/usr/bin/env php
<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 * @author   Brion Vibber <brion@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 * @version  1.1.x
 * @link     http://status.net
 */

if (php_sapi_name() !== 'cli') {
    exit(1);
}

define('INSTALLDIR', dirname(dirname(__FILE__)));
set_include_path(get_include_path() . PATH_SEPARATOR . INSTALLDIR . '/extlib');

require_once INSTALLDIR . '/lib/installer.php';
require_once 'Console/Getopt.php';

class CliInstaller extends Installer
{
    public $verbose = true;

    /**
     * Go for it!
     * @return boolean success
     */
    function main()
    {
        if (!$this->checkPrereqs()) {
            return false;
        }
        if ($this->prepare()) {
            return $this->handle();
       } else {
            $this->showHelp();
            return false;
        }
    }

    /**
     * Get our input parameters...
     * @return boolean success
     */
    function prepare()
    {
        $shortoptions = 'qvh';
        $longoptions = array('quiet', 'verbose', 'help', 'skip-config');
        $map = array(
            '-s'         => 'server',
            '--server'   => 'server',
            '-p'         => 'path',
            '--path'     => 'path',
            '--sitename' => 'sitename',
            '--fancy'    => 'fancy',
            '--ssl'      => 'ssl',

            '--dbtype'   => 'dbtype',
            '--host'     => 'host',
            '--database' => 'database',
            '--username' => 'username',
            '--password' => 'password',

            '--admin-nick' => 'adminNick',
            '--admin-pass' => 'adminPass',
            '--admin-email' => 'adminEmail',

            '--site-profile' => 'siteProfile'
        );
        foreach ($map as $arg => $target) {
            if (substr($arg, 0, 2) == '--') {
                $longoptions[] = substr($arg, 2) . '=';
            } else {
                $shortoptions .= substr($arg, 1) . ':';
            }
        }

        $parser = new Console_Getopt();
        $result = $parser->getopt($_SERVER['argv'], $shortoptions, $longoptions);
        if (PEAR::isError($result)) {
            $this->warning($result->getMessage());
            return false;
        }
        list($options, $args) = $result;

        // defaults
        $this->dbtype = 'mysql';
        $this->verbose = true;
        // ssl is defaulted in lib/installer.php

        foreach ($options as $option) {
            $arg = $option[0];
            if (isset($map[$arg])) {
                $var = $map[$arg];
                $this->$var = $option[1];
                if ($arg == '--fancy') {
                    $this->$var = ($option[1] != 'false') && ($option[1] != 'no');
                }
            } else if ($arg == '--skip-config') {
                $this->skipConfig = true;
            } else if ($arg == 'q' || $arg == '--quiet') {
                $this->verbose = false;
            } else if ($arg == 'v' || $arg == '--verbose') {
                $this->verbose = true;
            } else if ($arg == 'h' || $arg == '--help') {
                // will go back to show help
                return false;
            }
        }

        $fail = false;
        if (empty($this->server)) {
            $this->updateStatus("You must specify a web server for the site.", true);
            // path is optional though
            $fail = true;
        }

        if (!$this->validateDb()) {
            $fail = true;
        }

        if (!$this->validateAdmin()) {
            $fail = true;
        }

        return !$fail;
    }

    function handle()
    {
        return $this->doInstall();
    }

    function showHelp()
    {
        echo <<<END_HELP
install_cli.php - StatusNet command-line installer

    -s --server=<name>   Use <name> as server name (required)
    -p --path=<path>     Use <path> as path name
       --sitename        User-friendly site name (required)
       --fancy           Whether to use fancy URLs (default no)
       --ssl             Server SSL enabled (default never), 
                         [never | sometimes | always]

       --dbtype          'mysql' (default) or 'pgsql'
       --host            Database hostname (required)
       --database        Database/schema name (required)
       --username        Database username (required)
       --password        Database password (required)

       --admin-nick      Administrator nickname (required)
       --admin-pass      Initial password for admin user (required)
       --admin-email     Initial email address for admin user
       --admin-updates   'yes' (default) or 'no', whether to subscribe
                         admin to update@status.net (default yes)
       
       --site-profile    site profile ['public', 'private' (default), 'community', 'singleuser']
       
       --skip-config     Don't write a config.php -- use with caution,
                         requires a global configuration file.

      General options:

    -q --quiet           Quiet (little output)
    -v --verbose         Verbose (lots of output)
    -h --help            Show this message and quit.

END_HELP;
    }

    function warning($message, $submessage='')
    {
        print $this->html2text($message) . "\n";
        if ($submessage != '') {
            print "  " . $this->html2text($submessage) . "\n";
        }
        print "\n";
    }

    function updateStatus($status, $error=false)
    {
        if ($this->verbose || $error) {
            if ($error) {
                print "ERROR: ";
            }
            print $this->html2text($status);
            print "\n";
        }
    }

    private function html2text($html)
    {
        // break out any links for text legibility
        $breakout = preg_replace('/<a[^>+]\bhref="(.*)"[^>]*>(.*)<\/a>/',
                                 '\2 &lt;\1&gt;',
                                 $html);
        return html_entity_decode(strip_tags($breakout), ENT_QUOTES, 'UTF-8');
    }
}

$installer = new CliInstaller();
$ok = $installer->main();
exit($ok ? 0 : 1);
