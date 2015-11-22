<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
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

if (!defined('GNUSOCIAL')) { exit(1); }

define('GNUSOCIAL_ENGINE', 'GNU social');
define('GNUSOCIAL_ENGINE_URL', 'https://www.gnu.org/software/social/');

define('GNUSOCIAL_BASE_VERSION', '1.2.0');
define('GNUSOCIAL_LIFECYCLE', 'beta2'); // 'dev', 'alpha[0-9]+', 'beta[0-9]+', 'rc[0-9]+', 'release'

define('GNUSOCIAL_VERSION', GNUSOCIAL_BASE_VERSION . '-' . GNUSOCIAL_LIFECYCLE);

define('GNUSOCIAL_CODENAME', 'Not decided yet');

define('AVATAR_PROFILE_SIZE', 96);
define('AVATAR_STREAM_SIZE', 48);
define('AVATAR_MINI_SIZE', 24);

define('NOTICES_PER_PAGE', 20);
define('PROFILES_PER_PAGE', 20);
define('MESSAGES_PER_PAGE', 20);
define('GROUPS_PER_PAGE', 20);
define('APPS_PER_PAGE', 20);
define('PEOPLETAGS_PER_PAGE', 20);

define('GROUPS_PER_MINILIST', 8);
define('PROFILES_PER_MINILIST', 8);

define('FOREIGN_NOTICE_SEND', 1);
define('FOREIGN_NOTICE_RECV', 2);
define('FOREIGN_NOTICE_SEND_REPLY', 4);

define('FOREIGN_FRIEND_SEND', 1);
define('FOREIGN_FRIEND_RECV', 2);

define('NOTICE_INBOX_SOURCE_SUB', 1);
define('NOTICE_INBOX_SOURCE_GROUP', 2);
define('NOTICE_INBOX_SOURCE_REPLY', 3);
define('NOTICE_INBOX_SOURCE_FORWARD', 4);
define('NOTICE_INBOX_SOURCE_PROFILE_TAG', 5);
define('NOTICE_INBOX_SOURCE_GATEWAY', -1);

// append our extlib dir as the last-resort place to find libs

set_include_path(get_include_path() . PATH_SEPARATOR . INSTALLDIR . '/extlib/');

// To protect against upstream libraries which haven't updated
// for PHP 5.3 where dl() function may not be present...
if (!function_exists('dl')) {
    // function_exists() returns false for things in disable_functions,
    // but they still exist and we'll die if we try to redefine them.
    //
    // Fortunately trying to call the disabled one will only trigger
    // a warning, not a fatal, so it's safe to leave it for our case.
    // Callers will be suppressing warnings anyway.
    $disabled = array_filter(array_map('trim', explode(',', ini_get('disable_functions'))));
    if (!in_array('dl', $disabled)) {
        function dl($library) {
            return false;
        }
    }
}

// global configuration object

require_once 'PEAR.php';
require_once 'PEAR/Exception.php';
global $_PEAR;
$_PEAR = new PEAR;
$_PEAR->setErrorHandling(PEAR_ERROR_CALLBACK, 'PEAR_ErrorToPEAR_Exception');

require_once 'DB.php';
require_once 'DB/DataObject.php';
require_once 'DB/DataObject/Cast.php'; # for dates
global $_DB;
$_DB = new DB;

require_once(INSTALLDIR.'/lib/language.php');

// This gets included before the config file, so that admin code and plugins
// can use it

require_once(INSTALLDIR.'/lib/event.php');
require_once(INSTALLDIR.'/lib/plugin.php');

function addPlugin($name, array $attrs=array())
{
    return GNUsocial::addPlugin($name, $attrs);
}

function _have_config()
{
    return GNUsocial::haveConfig();
}

function GNUsocial_class_autoload($cls)
{
    if (file_exists(INSTALLDIR.'/classes/' . $cls . '.php')) {
        require_once(INSTALLDIR.'/classes/' . $cls . '.php');
    } else if (file_exists(INSTALLDIR.'/lib/' . strtolower($cls) . '.php')) {
        require_once(INSTALLDIR.'/lib/' . strtolower($cls) . '.php');
    } else if (mb_substr($cls, -6) == 'Action' &&
               file_exists(INSTALLDIR.'/actions/' . strtolower(mb_substr($cls, 0, -6)) . '.php')) {
        require_once(INSTALLDIR.'/actions/' . strtolower(mb_substr($cls, 0, -6)) . '.php');
    } else if ($cls === 'OAuthRequest' || $cls === 'OAuthException') {
        require_once('OAuth.php');
    } else {
        Event::handle('Autoload', array(&$cls));
    }
}

// Autoload function queue, starting with our own discovery method
spl_autoload_register('GNUsocial_class_autoload');

/**
 * Extlibs with namespaces (or directly in extlib/)
 * This covers libraries such as: Validate and \Michelf\Markdown
 *
 * The namespaced based structure is called "PSR-0 autoloading standard":
 *    \<Vendor Name>\(<Namespace>\)*<Class Name>
 * and is available here: http://www.php-fig.org/psr/psr-0/
*/
spl_autoload_register(function($class){
    $class_path = preg_replace('{\\\\|_(?!.*\\\\)}', DIRECTORY_SEPARATOR, ltrim($class, '\\')).'.php';
    $file = INSTALLDIR.'/extlib/'.$class_path;
    if (file_exists($file)) {
        require_once $file;
        return;
    }

    # Try if the system has this external library
    $file = '/usr/share/php/'.$class_path;
    if (file_exists($file)) {
        require_once $file;
        return;
    }
});

require_once INSTALLDIR.'/lib/util.php';
require_once INSTALLDIR.'/lib/action.php';
require_once INSTALLDIR.'/lib/mail.php';

//set PEAR error handling to use regular PHP exceptions
function PEAR_ErrorToPEAR_Exception(PEAR_Error $err)
{
    //DB_DataObject throws error when an empty set would be returned
    //That behavior is weird, and not how the rest of StatusNet works.
    //So just ignore those errors.
    if ($err->getCode() == DB_DATAOBJECT_ERROR_NODATA) {
        return;
    }

    $msg      = $err->getMessage();
    $userInfo = $err->getUserInfo();

    // Log this; push the message up as an exception

    common_log(LOG_ERR, "PEAR Error: $msg ($userInfo)");

    // HACK: queue handlers get kicked by the long-query killer, and 
    // keep the same broken connection. We die here to get a new
    // process started.

    if (php_sapi_name() == 'cli' && preg_match('/nativecode=2006/', $userInfo)) {
        common_log(LOG_ERR, "Lost DB connection; dying.");
        exit(100);
    }

    if ($err->getCode()) {
        throw new PEAR_Exception($err->getMessage(), $err->getCode());
    }
    throw new PEAR_Exception($err->getMessage());
}
