<?php

/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

abstract class Installer
{
    /** Web site info */
    public $sitename, $server, $path, $fancy;
    /** DB info */
    public $host, $dbname, $dbtype, $username, $password, $db;
    /** Administrator info */
    public $adminNick, $adminPass, $adminEmail, $adminUpdates;
    /** Should we skip writing the configuration file? */
    public $skipConfig = false;

    public static $dbModules = array(
        'mysql' => array(
            'name' => 'MySQL',
            'check_module' => 'mysqli',
            'installer' => 'mysql_db_installer',
        ),
        'pgsql' => array(
            'name' => 'PostgreSQL',
            'check_module' => 'pgsql',
            'installer' => 'pgsql_db_installer',
        ),
    );

    /**
     * Attempt to include a PHP file and report if it worked, while
     * suppressing the annoying warning messages on failure.
     */
    private function haveIncludeFile($filename) {
        $old = error_reporting(error_reporting() & ~E_WARNING);
        $ok = include_once($filename);
        error_reporting($old);
        return $ok;
    }
    
    /**
     * Check if all is ready for installation
     *
     * @return void
     */
    function checkPrereqs()
    {
        $pass = true;

        $config = INSTALLDIR.'/config.php';
        if (file_exists($config)) {
            if (!is_writable($config) || filesize($config) > 0) {
                $this->warning('Config file "config.php" already exists.');
                $pass = false;
            }
        }

        if (version_compare(PHP_VERSION, '5.2.3', '<')) {
            $this->warning('Require PHP version 5.2.3 or greater.');
            $pass = false;
        }

        // Look for known library bugs
        $str = "abcdefghijklmnopqrstuvwxyz";
        $replaced = preg_replace('/[\p{Cc}\p{Cs}]/u', '*', $str);
        if ($str != $replaced) {
            $this->warning('PHP is linked to a version of the PCRE library ' .
                           'that does not support Unicode properties. ' .
                           'If you are running Red Hat Enterprise Linux / ' .
                           'CentOS 5.4 or earlier, see <a href="' .
                           'http://status.net/wiki/Red_Hat_Enterprise_Linux#PCRE_library' .
                           '">our documentation page</a> on fixing this.');
            $pass = false;
        }

        $reqs = array('gd', 'curl',
                      'xmlwriter', 'mbstring', 'xml', 'dom', 'simplexml');

        foreach ($reqs as $req) {
            if (!$this->checkExtension($req)) {
                $this->warning(sprintf('Cannot load required extension: <code>%s</code>', $req));
                $pass = false;
            }
        }

        // Make sure we have at least one database module available
        $missingExtensions = array();
        foreach (self::$dbModules as $type => $info) {
            if (!$this->checkExtension($info['check_module'])) {
                $missingExtensions[] = $info['check_module'];
            }
        }

        if (count($missingExtensions) == count(self::$dbModules)) {
            $req = implode(', ', $missingExtensions);
            $this->warning(sprintf('Cannot find a database extension. You need at least one of %s.', $req));
            $pass = false;
        }

        // @fixme this check seems to be insufficient with Windows ACLs
        if (!is_writable(INSTALLDIR)) {
            $this->warning(sprintf('Cannot write config file to: <code>%s</code></p>', INSTALLDIR),
                           sprintf('On your server, try this command: <code>chmod a+w %s</code>', INSTALLDIR));
            $pass = false;
        }

        // Check the subdirs used for file uploads
        $fileSubdirs = array('avatar', 'background', 'file');
        foreach ($fileSubdirs as $fileSubdir) {
            $fileFullPath = INSTALLDIR."/$fileSubdir/";
            if (!is_writable($fileFullPath)) {
                $this->warning(sprintf('Cannot write to %s directory: <code>%s</code>', $fileSubdir, $fileFullPath),
                               sprintf('On your server, try this command: <code>chmod a+w %s</code>', $fileFullPath));
                $pass = false;
            }
        }

        return $pass;
    }

    /**
     * Checks if a php extension is both installed and loaded
     *
     * @param string $name of extension to check
     *
     * @return boolean whether extension is installed and loaded
     */
    function checkExtension($name)
    {
        if (extension_loaded($name)) {
            return true;
        } elseif (function_exists('dl') && ini_get('enable_dl') && !ini_get('safe_mode')) {
            // dl will throw a fatal error if it's disabled or we're in safe mode.
            // More fun, it may not even exist under some SAPIs in 5.3.0 or later...
            $soname = $name . '.' . PHP_SHLIB_SUFFIX;
            if (PHP_SHLIB_SUFFIX == 'dll') {
                $soname = "php_" . $soname;
            }
            return @dl($soname);
        } else {
            return false;
        }
    }

    /**
     * Basic validation on the database paramters
     * Side effects: error output if not valid
     * 
     * @return boolean success
     */
    function validateDb()
    {
        $fail = false;

        if (empty($this->host)) {
            $this->updateStatus("No hostname specified.", true);
            $fail = true;
        }

        if (empty($this->database)) {
            $this->updateStatus("No database specified.", true);
            $fail = true;
        }

        if (empty($this->username)) {
            $this->updateStatus("No username specified.", true);
            $fail = true;
        }

        if (empty($this->sitename)) {
            $this->updateStatus("No sitename specified.", true);
            $fail = true;
        }

        return !$fail;
    }

    /**
     * Basic validation on the administrator user paramters
     * Side effects: error output if not valid
     * 
     * @return boolean success
     */
    function validateAdmin()
    {
        $fail = false;

        if (empty($this->adminNick)) {
            $this->updateStatus("No initial StatusNet user nickname specified.", true);
            $fail = true;
        }
        if ($this->adminNick && !preg_match('/^[0-9a-z]{1,64}$/', $this->adminNick)) {
            $this->updateStatus('The user nickname "' . htmlspecialchars($this->adminNick) .
                         '" is invalid; should be plain letters and numbers no longer than 64 characters.', true);
            $fail = true;
        }
        // @fixme hardcoded list; should use User::allowed_nickname()
        // if/when it's safe to have loaded the infrastructure here
        $blacklist = array('main', 'admin', 'twitter', 'settings', 'rsd.xml', 'favorited', 'featured', 'favoritedrss', 'featuredrss', 'rss', 'getfile', 'api', 'groups', 'group', 'peopletag', 'tag', 'user', 'message', 'conversation', 'bookmarklet', 'notice', 'attachment', 'search', 'index.php', 'doc', 'opensearch', 'robots.txt', 'xd_receiver.html', 'facebook');
        if (in_array($this->adminNick, $blacklist)) {
            $this->updateStatus('The user nickname "' . htmlspecialchars($this->adminNick) .
                         '" is reserved.', true);
            $fail = true;
        }

        if (empty($this->adminPass)) {
            $this->updateStatus("No initial StatusNet user password specified.", true);
            $fail = true;
        }

        return !$fail;
    }

    /**
     * Set up the database with the appropriate function for the selected type...
     * Saves database info into $this->db.
     * 
     * @return mixed array of database connection params on success, false on failure
     */
    function setupDatabase()
    {
        if ($this->db) {
            throw new Exception("Bad order of operations: DB already set up.");
        }
        $method = self::$dbModules[$this->dbtype]['installer'];
        $db = call_user_func(array($this, $method),
                             $this->host,
                             $this->database,
                             $this->username,
                             $this->password);
        $this->db = $db;
        return $this->db;
    }

    /**
     * Set up a database on PostgreSQL.
     * Will output status updates during the operation.
     * 
     * @param string $host
     * @param string $database
     * @param string $username
     * @param string $password
     * @return mixed array of database connection params on success, false on failure
     * 
     * @fixme escape things in the connection string in case we have a funny pass etc
     */
    function Pgsql_Db_installer($host, $database, $username, $password)
    {
        $connstring = "dbname=$database host=$host user=$username";

        //No password would mean trust authentication used.
        if (!empty($password)) {
            $connstring .= " password=$password";
        }
        $this->updateStatus("Starting installation...");
        $this->updateStatus("Checking database...");
        $conn = pg_connect($connstring);

        if ($conn ===false) {
            $this->updateStatus("Failed to connect to database: $connstring");
            return false;
        }

        //ensure database encoding is UTF8
        $record = pg_fetch_object(pg_query($conn, 'SHOW server_encoding'));
        if ($record->server_encoding != 'UTF8') {
            $this->updateStatus("StatusNet requires UTF8 character encoding. Your database is ". htmlentities($record->server_encoding));
            return false;
        }

        $this->updateStatus("Running database script...");
        //wrap in transaction;
        pg_query($conn, 'BEGIN');
        $res = $this->runDbScript('statusnet_pg.sql', $conn, 'pgsql');

        if ($res === false) {
            $this->updateStatus("Can't run database script.", true);
            return false;
        }
        foreach (array('sms_carrier' => 'SMS carrier',
                    'notice_source' => 'notice source',
                    'foreign_services' => 'foreign service')
              as $scr => $name) {
            $this->updateStatus(sprintf("Adding %s data to database...", $name));
            $res = $this->runDbScript($scr.'.sql', $conn, 'pgsql');
            if ($res === false) {
                $this->updateStatus(sprintf("Can't run %d script.", $name), true);
                return false;
            }
        }
        pg_query($conn, 'COMMIT');

        if (empty($password)) {
            $sqlUrl = "pgsql://$username@$host/$database";
        } else {
            $sqlUrl = "pgsql://$username:$password@$host/$database";
        }

        $db = array('type' => 'pgsql', 'database' => $sqlUrl);

        return $db;
    }

    /**
     * Set up a database on MySQL.
     * Will output status updates during the operation.
     * 
     * @param string $host
     * @param string $database
     * @param string $username
     * @param string $password
     * @return mixed array of database connection params on success, false on failure
     * 
     * @fixme escape things in the connection string in case we have a funny pass etc
     */
    function Mysql_Db_installer($host, $database, $username, $password)
    {
        $this->updateStatus("Starting installation...");
        $this->updateStatus("Checking database...");

        $conn = mysqli_init();
        if (!$conn->real_connect($host, $username, $password)) {
            $this->updateStatus("Can't connect to server '$host' as '$username'.", true);
            return false;
        }
        $this->updateStatus("Changing to database...");
        if (!$conn->select_db($database)) {
            $this->updateStatus("Can't change to database.", true);
            return false;
        }

        $this->updateStatus("Running database script...");
        $res = $this->runDbScript('statusnet.sql', $conn);
        if ($res === false) {
            $this->updateStatus("Can't run database script.", true);
            return false;
        }
        foreach (array('sms_carrier' => 'SMS carrier',
                    'notice_source' => 'notice source',
                    'foreign_services' => 'foreign service')
              as $scr => $name) {
            $this->updateStatus(sprintf("Adding %s data to database...", $name));
            $res = $this->runDbScript($scr.'.sql', $conn);
            if ($res === false) {
                $this->updateStatus(sprintf("Can't run %d script.", $name), true);
                return false;
            }
        }

        $sqlUrl = "mysqli://$username:$password@$host/$database";
        $db = array('type' => 'mysql', 'database' => $sqlUrl);
        return $db;
    }

    /**
     * Write a stock configuration file.
     *
     * @return boolean success
     * 
     * @fixme escape variables in output in case we have funny chars, apostrophes etc
     */
    function writeConf()
    {
        // assemble configuration file in a string
        $cfg =  "<?php\n".
                "if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }\n\n".

                // site name
                "\$config['site']['name'] = '{$this->sitename}';\n\n".

                // site location
                "\$config['site']['server'] = '{$this->server}';\n".
                "\$config['site']['path'] = '{$this->path}'; \n\n".

                // checks if fancy URLs are enabled
                ($this->fancy ? "\$config['site']['fancy'] = true;\n\n":'').

                // database
                "\$config['db']['database'] = '{$this->db['database']}';\n\n".
                ($this->db['type'] == 'pgsql' ? "\$config['db']['quote_identifiers'] = true;\n\n":'').
                "\$config['db']['type'] = '{$this->db['type']}';\n\n";

        // Normalize line endings for Windows servers
        $cfg = str_replace("\n", PHP_EOL, $cfg);

        // write configuration file out to install directory
        $res = file_put_contents(INSTALLDIR.'/config.php', $cfg);

        return $res;
    }

    /**
     * Install schema into the database
     *
     * @param string $filename location of database schema file
     * @param dbconn $conn     connection to database
     * @param string $type     type of database, currently mysql or pgsql
     *
     * @return boolean - indicating success or failure
     */
    function runDbScript($filename, $conn, $type = 'mysqli')
    {
        $sql = trim(file_get_contents(INSTALLDIR . '/db/' . $filename));
        $stmts = explode(';', $sql);
        foreach ($stmts as $stmt) {
            $stmt = trim($stmt);
            if (!mb_strlen($stmt)) {
                continue;
            }
            // FIXME: use PEAR::DB or PDO instead of our own switch
            switch ($type) {
            case 'mysqli':
                $res = $conn->query($stmt);
                if ($res === false) {
                    $error = $conn->error;
                }
                break;
            case 'pgsql':
                $res = pg_query($conn, $stmt);
                if ($res === false) {
                    $error = pg_last_error();
                }
                break;
            default:
                $this->updateStatus("runDbScript() error: unknown database type ". $type ." provided.");
            }
            if ($res === false) {
                $this->updateStatus("ERROR ($error) for SQL '$stmt'");
                return $res;
            }
        }
        return true;
    }

    /**
     * Create the initial admin user account.
     * Side effect: may load portions of StatusNet framework.
     * Side effect: outputs program info
     */
    function registerInitialUser()
    {
        define('STATUSNET', true);
        define('LACONICA', true); // compatibility

        require_once INSTALLDIR . '/lib/common.php';

        $data = array('nickname' => $this->adminNick,
                      'password' => $this->adminPass,
                      'fullname' => $this->adminNick);
        if ($this->adminEmail) {
            $data['email'] = $this->adminEmail;
        }
        $user = User::register($data);

        if (empty($user)) {
            return false;
        }

        // give initial user carte blanche

        $user->grantRole('owner');
        $user->grantRole('moderator');
        $user->grantRole('administrator');
        
        // Attempt to do a remote subscribe to update@status.net
        // Will fail if instance is on a private network.

        if ($this->adminUpdates && class_exists('Ostatus_profile')) {
            try {
                $oprofile = Ostatus_profile::ensureProfileURL('http://update.status.net/');
                Subscription::start($user->getProfile(), $oprofile->localProfile());
                $this->updateStatus("Set up subscription to <a href='http://update.status.net/'>update@status.net</a>.");
            } catch (Exception $e) {
                $this->updateStatus("Could not set up subscription to <a href='http://update.status.net/'>update@status.net</a>.", true);
            }
        }

        return true;
    }

    /**
     * The beef of the installer!
     * Create database, config file, and admin user.
     * 
     * Prerequisites: validation of input data.
     * 
     * @return boolean success
     */
    function doInstall()
    {
        $this->db = $this->setupDatabase();

        if (!$this->db) {
            // database connection failed, do not move on to create config file.
            return false;
        }

        if (!$this->skipConfig) {
            $this->updateStatus("Writing config file...");
            $res = $this->writeConf();

            if (!$res) {
                $this->updateStatus("Can't write config file.", true);
                return false;
            }
        }

        if (!empty($this->adminNick)) {
            // Okay, cross fingers and try to register an initial user
            if ($this->registerInitialUser()) {
                $this->updateStatus(
                    "An initial user with the administrator role has been created."
                );
            } else {
                $this->updateStatus(
                    "Could not create initial StatusNet user (administrator).",
                    true
                );
                return false;
            }
        }

        /*
            TODO https needs to be considered
        */
        $link = "http://".$this->server.'/'.$this->path;

        $this->updateStatus("StatusNet has been installed at $link");
        $this->updateStatus(
            "<strong>DONE!</strong> You can visit your <a href='$link'>new StatusNet site</a> (login as '$this->adminNick'). If this is your first StatusNet install, you may want to poke around our <a href='http://status.net/wiki/Getting_started'>Getting Started guide</a>."
        );

        return true;
    }

    /**
     * Output a pre-install-time warning message
     * @param string $message HTML ok, but should be plaintext-able
     * @param string $submessage HTML ok, but should be plaintext-able
     */
    abstract function warning($message, $submessage='');

    /**
     * Output an install-time progress message
     * @param string $message HTML ok, but should be plaintext-able
     * @param boolean $error true if this should be marked as an error condition
     */
    abstract function updateStatus($status, $error=false);

}
