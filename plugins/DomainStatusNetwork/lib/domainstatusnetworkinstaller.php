<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Installer class for domain-based multi-homing systems
 *
 * PHP version 5
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
 * @category  DomainStatusNetwork
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Installer class for domain-based multi-homing systems
 *
 * @category  DomainStatusNetwork
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class DomainStatusNetworkInstaller extends Installer
{
    protected $domain   = null;
    protected $rootname = null;
    protected $sitedb   = null;
    protected $rootpass = null;
    protected $nickname = null;
    protected $sn       = null;

    public $verbose     = false;

    function __construct($domain)
    {
        $this->domain = $domain;
    }

    /**
     * Go for it!
     * @return boolean success
     */
    function main()
    {
        // We don't check prereqs. Check 'em before setting up a
        // multi-home system, kthxbi
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
        $config = $this->getConfig();

        $this->nickname = DomainStatusNetworkPlugin::nicknameForDomain($this->domain);

        // XXX make this configurable

        $this->sitename = sprintf('The %s Status Network', $this->domain);

        $this->server   = $this->nickname.'.'.$config['WILDCARD'];
        $this->path     = null;
        $this->fancy    = true;

        $datanick = $this->databaseize($this->nickname);

        $this->host     = $config['DBHOSTNAME'];
        $this->database = $datanick.$config['DBBASE'];
        $this->dbtype   = 'mysql'; // XXX: support others... someday
        $this->username = $datanick.$config['USERBASE'];

        // Max size for MySQL

        if (strlen($this->username) > 16) {
            $this->username = sprintf('%s%08x', substr($this->username, 0, 8), crc32($this->username));
        }

        $pwgen = $config['PWDGEN'];

        $password = `$pwgen`;

        $this->password = trim($password);

        // For setting up the database

        $this->rootname = $config['ADMIN'];
        $this->rootpass = $config['ADMINPASS'];
        $this->sitehost = $config['SITEDBHOSTNAME'];
        $this->sitedb   = $config['SITEDB'];

        $tagstr = $config['TAGS'];

        if (!empty($tagstr)) {
            $this->tags = preg_split('/[\s,]+/', $tagstr);
        } else {
            $this->tags = array();
        }

        // Explicitly empty

        $this->adminNick    = null;
        $this->adminPass    = null;
        $this->adminEmail   = null;
        $this->adminUpdates = null;

        /** Should we skip writing the configuration file? */
        $this->skipConfig = true;

        if (!$this->validateDb()) {
            return false;
        }

        return true;
    }

    function handle()
    {
        return $this->doInstall();
    }

    function setupDatabase()
    {
        $this->updateStatus('Creating database...');
        $this->createDatabase();
        parent::setupDatabase();
        $this->updateStatus('Creating file directories...');
        $this->createDirectories();
        $this->updateStatus('Saving status network...');
        $this->saveStatusNetwork();
        $this->updateStatus('Checking schema for plugins...');
        $this->checkSchema();
    }

    function saveStatusNetwork()
    {
        Status_network::setupDB($this->sitehost,
                                $this->rootname,
                                $this->rootpass,
                                $this->sitedb, array());

        $sn = new Status_network();

        $sn->nickname = $this->nickname;
        $sn->dbhost   = $this->host;
        $sn->dbuser   = $this->username;
        $sn->dbpass   = $this->password;
        $sn->dbname   = $this->database;
        $sn->sitename = $this->sitename;
        $sn->created  = common_sql_now();

        $result = $sn->insert();

        if (!$result) {
            throw new ServerException("Could not create status_network: " . print_r($sn, true));
        }

        // Re-fetch; stupid auto-increment integer isn't working

        $sn = Status_network::staticGet('nickname', $sn->nickname);

        if (empty($sn)) {
            throw new ServerException("Created {$this->nickname} status_network and could not find it again.");
        }

        // Set default tags

        $tags = $this->tags;

        // Add domain tag

        $tags[] = 'domain='.$this->domain;

        $sn->setTags($tags);

        $this->sn = $sn;
    }

    function checkSchema()
    {
        $config = $this->getConfig();

        Status_network::$wildcard = $config['WILDCARD'];

        StatusNet::switchSite($this->nickname);

        // We need to initialize the schema_version stuff to make later setup easier

        $schema = array();
        require INSTALLDIR.'/db/core.php';
        $tableDefs = $schema;

        $schema = Schema::get();
        $schemaUpdater = new SchemaUpdater($schema);

        foreach ($tableDefs as $table => $def) {
            $schemaUpdater->register($table, $def);
        }

        $schemaUpdater->checkSchema();

        Event::handle('CheckSchema');
    }

    function getStatusNetwork()
    {
        return $this->sn;
    }

    function createDirectories()
    {
        $config = $this->getConfig();

        foreach (array('AVATARBASE', 'BACKGROUNDBASE', 'FILEBASE') as $key) {
            $base = $config[$key];
            $dirname = $base.'/'.$this->nickname;

            // Make sure our bits are set
            $mask = umask(0);
            mkdir($dirname, 0770, true);
            umask($mask);

            // If you set the setuid bit on your base dirs this should be
            // unnecessary, but just in case. You must be root for this
            // to work.

            if (array_key_exists('WEBUSER', $config)) {
                chown($dirname, $config['WEBUSER']);
            }
            if (array_key_exists('WEBGROUP', $config)) {
                chgrp($dirname, $config['WEBGROUP']);
            }
        }
    }

    function createDatabase()
    {
        // Create the New DB
        $res = mysql_connect($this->host, $this->rootname, $this->rootpass);
        if (!$res) {
            throw new ServerException("Cannot connect to {$this->host} as {$this->rootname}.");
        }

        mysql_query("CREATE DATABASE ". mysql_real_escape_string($this->database), $res);

        $return = mysql_select_db($this->database, $res);

        if (!$return) {
            throw new ServerException("Unable to connect to {$this->database} on {$this->host}.");
        }

        foreach (array('localhost', '%') as $src) {
            mysql_query("GRANT ALL ON " .
                        mysql_real_escape_string($this->database).".* TO '" .
                        $this->username . "'@'".$src."' ".
                        "IDENTIFIED BY '".$this->password."'", $res);
        }

        mysql_close($res);
    }

    function getConfig()
    {
        static $config;

        $cfg_file = "/etc/statusnet/setup.cfg";

        if (empty($config)) {
            $result = parse_ini_file($cfg_file);

            $config = array();
            foreach ($result as $key => $value) {
                $key = str_replace('export ', '', $key);
                $config[$key] = $value;
            }
        }

        return $config;
    }

    function showHelp()
    {
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

    function databaseize($nickname)
    {
        $nickname = str_replace('-', '_', $nickname);
        return $nickname;
    }
}
