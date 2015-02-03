<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

// -*- mode: php -*-

# Abort if called from a web server

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('GNUSOCIAL', true);
define('STATUSNET', true); //compatibility

// Set various flags so we don't time out on long-running processes

ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
set_time_limit(0);
mb_internal_encoding('UTF-8');
error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);

// Add extlib to our path so we can get Console_Getopt

$_extra_path = array(INSTALLDIR.'/extlib/');

set_include_path(implode(PATH_SEPARATOR, $_extra_path) . PATH_SEPARATOR . get_include_path());

require_once 'Console/Getopt.php';

// Note: $shortoptions and $longoptions should be pre-defined!

$_default_shortoptions = 'qvhc:s:p:';

$_default_longoptions = array('quiet', 'verbose', 'help', 'conf=', 'server=', 'path=');

if (isset($shortoptions)) {
    $shortoptions .= $_default_shortoptions;
} else {
    $shortoptions = $_default_shortoptions;
}

if (isset($longoptions)) {
    $longoptions = array_merge($longoptions, $_default_longoptions);
} else {
    $longoptions = $_default_longoptions;
}

$parser = new Console_Getopt();

$result = $parser->getopt($argv, $shortoptions, $longoptions);

if (PEAR::isError($result)) {
    print $result->getMessage()."\n";
    exit(1);
} else {
    list($options, $args) = $result;
}

function show_help()
{
    global $helptext;

    $_default_help_text = <<<END_OF_DEFAULT
      General options:

    -q --quiet           Quiet (little output)
    -v --verbose         Verbose (lots of output)
    -c --conf=<filename> Use <filename> as config file
    -s --server=<name>   Use <name> as server name
    -p --path=<path>     Use <path> as path name
    -h --help            Show this message and quit.

END_OF_DEFAULT;
    if (isset($helptext)) {
        print $helptext;
    }
    print $_default_help_text;
    exit(0);
}

foreach ($options as $option) {

    switch ($option[0]) {
     case '--server':
     case 's':
        $server = $option[1];
        break;

     case '--path':
     case 'p':
        $path = $option[1];
        break;

     case '--conf':
     case 'c':
        $conffile = $option[1];
        break;

     case '--help':
     case 'h':
        show_help();
    }
}

require_once INSTALLDIR . '/lib/common.php';

set_error_handler('common_error_handler');

// Set up the language infrastructure so we can localize anything that
// needs to be sent out to users, such as mail notifications.
common_init_language();

function _make_matches($opt, $alt)
{
    $matches = array();

    if (strlen($opt) > 1 && 0 != strncmp($opt, '--', 2)) {
        $matches[] = '--'.$opt;
    } else {
        $matches[] = $opt;
    }

    if (!empty($alt)) {
        if (strlen($alt) > 1 && 0 != strncmp($alt, '--', 2)) {
            $matches[] = '--'.$alt;
        } else {
            $matches[] = $alt;
        }
    }

    return $matches;
}

function have_option($opt, $alt=null)
{
    global $options;

    $matches = _make_matches($opt, $alt);

    foreach ($options as $option) {
        if (in_array($option[0], $matches)) {
            return true;
        }
    }

    return false;
}

function get_option_value($opt, $alt=null)
{
    global $options;

    $matches = _make_matches($opt, $alt);

    foreach ($options as $option) {
        if (in_array($option[0], $matches)) {
            return $option[1];
        }
    }

    return null;
}

class NoUserArgumentException extends Exception
{
}

function getUser()
{
    $user = null;

    if (have_option('i', 'id')) {
        $id = get_option_value('i', 'id');
        $user = User::getKV('id', $id);
        if (empty($user)) {
            throw new Exception("Can't find user with id '$id'.");
        }
    } else if (have_option('n', 'nickname')) {
        $nickname = get_option_value('n', 'nickname');
        $user = User::getKV('nickname', $nickname);
        if (empty($user)) {
            throw new Exception("Can't find user with nickname '$nickname'");
        }
    } else {
        throw new NoUserArgumentException("No user argument specified.");
    }

    return $user;
}

/** "Printf not quiet" */

function printfnq()
{
    if (have_option('q', 'quiet')) {
        return null;
    }

    $cargs  = func_num_args();

    if ($cargs == 0) {
        return 0;
    }

    $args   = func_get_args();
    $format = array_shift($args);

    return vprintf($format, $args);
}

/** "Print when verbose" */

function printfv()
{
    if (!have_option('v', 'verbose')) {
        return null;
    }

    $cargs  = func_num_args();

    if ($cargs == 0) {
        return 0;
    }

    $args   = func_get_args();
    $format = array_shift($args);

    return vprintf($format, $args);
}
