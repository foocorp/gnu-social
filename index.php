<?php
/**
 * StatusNet - the distributed open-source microblogging tool
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
 *
 * @category StatusNet
 * @package  StatusNet
 * @author   Brenda Wallace <shiny@cpan.org>
 * @author   Brion Vibber <brion@pobox.com>
 * @author   Christopher Vollick <psycotica0@gmail.com>
 * @author   CiaranG <ciaran@ciarang.com>
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@controlezvous.ca>
 * @author   Gina Haeussge <osd@foosel.net>
 * @author   James Walker <walkah@walkah.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Mike Cochrane <mikec@mikenz.geek.nz>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @author   Tom Adams <tom@holizz.com>
 * @author   Zach Copley <zach@status.net>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 *
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 */

$_startTime = microtime(true);
$_perfCounters = array();

// We provide all our dependencies through our own autoload.
// This will probably be configurable for distributing with
// system packages (like with Debian apt etc. where included
// libraries are maintained through repositories)
set_include_path('.');  // mainly fixes an issue where /usr/share/{pear,php*}/DB/DataObject.php is _old_ on various systems...

define('INSTALLDIR', dirname(__FILE__));
define('GNUSOCIAL', true);
define('STATUSNET', true);  // compatibility

$user = null;
$action = null;

function getPath($req)
{
    $p = null;

    if ((common_config('site', 'fancy') || !array_key_exists('PATH_INFO', $_SERVER))
        && array_key_exists('p', $req)
    ) {
        $p = $req['p'];
    } else if (array_key_exists('PATH_INFO', $_SERVER)) {
        $path = $_SERVER['PATH_INFO'];
        $script = $_SERVER['SCRIPT_NAME'];
        if (substr($path, 0, mb_strlen($script)) == $script) {
            $p = substr($path, mb_strlen($script) + 1);
        } else {
            $p = $path;
        }
    } else {
        $p = null;
    }

    // Trim all initial '/'

    $p = ltrim($p, '/');

    return $p;
}

/**
 * logs and then displays error messages
 *
 * @return void
 */
function handleError($error)
{
    try {

        if ($error->getCode() == DB_DATAOBJECT_ERROR_NODATA) {
            return;
        }

        $logmsg = "Exception thrown: " . _ve($error->getMessage());
        if ($error instanceof PEAR_Exception && common_config('site', 'logdebug')) {
            $logmsg .= " PEAR: ". $error->toText();
        }
        // DB queries often end up with a lot of newlines; merge to a single line
        // for easier grepability...
        $logmsg = str_replace("\n", " ", $logmsg);
        common_log(LOG_ERR, $logmsg);

        // @fixme backtrace output should be consistent with exception handling
        if (common_config('site', 'logdebug')) {
            $bt = $error->getTrace();
            foreach ($bt as $n => $line) {
                common_log(LOG_ERR, formatBacktraceLine($n, $line));
            }
        }
        if ($error instanceof DB_DataObject_Error
            || $error instanceof DB_Error
            || ($error instanceof PEAR_Exception && $error->getCode() == -24)
        ) {
            //If we run into a DB error, assume we can't connect to the DB at all
            //so set the current user to null, so we don't try to access the DB
            //while rendering the error page.
            global $_cur;
            $_cur = null;

            $msg = sprintf(
                // TRANS: Database error message.
                _('The database for %1$s is not responding correctly, '.
                  'so the site will not work properly. '.
                  'The site admins probably know about the problem, '.
                  'but you can contact them at %2$s to make sure. '.
                  'Otherwise, wait a few minutes and try again.'
                ),
                common_config('site', 'name'),
                common_config('site', 'email')
            );

            $erraction = new DBErrorAction($msg, 500);
        } elseif ($error instanceof ClientException) {
            $erraction = new ClientErrorAction($error->getMessage(), $error->getCode());
        } elseif ($error instanceof ServerException) {
            $erraction = new ServerErrorAction($error->getMessage(), $error->getCode(), $error);
        } else {
            // If it wasn't specified more closely which kind of exception it was
            $erraction = new ServerErrorAction($error->getMessage(), 500, $error);
        }
        $erraction->showPage();

    } catch (Exception $e) {
        // TRANS: Error message.
        echo _('An error occurred.');
        exit(-1);
    }
    exit(-1);
}

set_exception_handler('handleError');

// quick check for fancy URL auto-detection support in installer.
if (preg_replace("/\?.+$/", "", $_SERVER['REQUEST_URI']) === preg_replace("/^\/$/", "", (dirname($_SERVER['REQUEST_URI']))) . '/check-fancy') {
    die("Fancy URL support detection succeeded. We suggest you enable this to get fancy (pretty) URLs.");
}

require_once INSTALLDIR . '/lib/common.php';

/**
 * Format a backtrace line for debug output roughly like debug_print_backtrace() does.
 * Exceptions already have this built in, but PEAR error objects just give us the array.
 *
 * @param int $n line number
 * @param array $line per-frame array item from debug_backtrace()
 * @return string
 */
function formatBacktraceLine($n, $line)
{
    $out = "#$n ";
    if (isset($line['class'])) $out .= $line['class'];
    if (isset($line['type'])) $out .= $line['type'];
    if (isset($line['function'])) $out .= $line['function'];
    $out .= '(';
    if (isset($line['args'])) {
        $args = array();
        foreach ($line['args'] as $arg) {
            // debug_print_backtrace seems to use var_export
            // but this gets *very* verbose!
            $args[] = gettype($arg);
        }
        $out .= implode(',', $args);
    }
    $out .= ')';
    $out .= ' called at [';
    if (isset($line['file'])) $out .= $line['file'];
    if (isset($line['line'])) $out .= ':' . $line['line'];
    $out .= ']';
    return $out;
}

function setupRW()
{
    global $config;

    static $alwaysRW = array('session', 'remember_me');

    $rwdb = $config['db']['database'];

    if (Event::handle('StartReadWriteTables', array(&$alwaysRW, &$rwdb))) {

        // We ensure that these tables always are used
        // on the master DB

        $config['db']['database_rw'] = $rwdb;
        $config['db']['ini_rw'] = INSTALLDIR.'/classes/statusnet.ini';

        foreach ($alwaysRW as $table) {
            $config['db']['table_'.$table] = 'rw';
        }

        Event::handle('EndReadWriteTables', array($alwaysRW, $rwdb));
    }

    return;
}

function isLoginAction($action)
{
    static $loginActions =  array('login', 'recoverpassword', 'api', 'doc', 'register', 'publicxrds', 'otp', 'opensearch', 'rsd');

    $login = null;

    if (Event::handle('LoginAction', array($action, &$login))) {
        $login = in_array($action, $loginActions);
    }

    return $login;
}

function main()
{
    global $user, $action;

    if (!_have_config()) {
        $msg = sprintf(
            // TRANS: Error message displayed when there is no StatusNet configuration file.
            _("No configuration file found. Try running ".
              "the installation program first."
            )
        );
        $sac = new ServerErrorAction($msg);
        $sac->showPage();
        return;
    }

    // Make sure RW database is setup

    setupRW();

    // XXX: we need a little more structure in this script

    // get and cache current user (may hit RW!)

    $user = common_current_user();

    // initialize language env

    common_init_language();

    $path = getPath($_REQUEST);

    $r = Router::get();

    $args = $r->map($path);

    // If the request is HTTP and it should be HTTPS...
    if (GNUsocial::useHTTPS() && !GNUsocial::isHTTPS()) {
        common_redirect(common_local_url($args['action'], $args));
    }

    $args = array_merge($args, $_REQUEST);

    Event::handle('ArgsInitialize', array(&$args));

    $action = basename($args['action']);

    if (!$action || !preg_match('/^[a-zA-Z0-9_-]*$/', $action)) {
        common_redirect(common_local_url('public'));
    }

    // If the site is private, and they're not on one of the "public"
    // parts of the site, redirect to login

    if (!$user && common_config('site', 'private')
        && !isLoginAction($action)
        && !preg_match('/rss$/', $action)
        && $action != 'robotstxt'
        && !preg_match('/^Api/', $action)) {

        // set returnto
        $rargs =& common_copy_args($args);
        unset($rargs['action']);
        if (common_config('site', 'fancy')) {
            unset($rargs['p']);
        }
        if (array_key_exists('submit', $rargs)) {
            unset($rargs['submit']);
        }
        foreach (array_keys($_COOKIE) as $cookie) {
            unset($rargs[$cookie]);
        }
        common_set_returnto(common_local_url($action, $rargs));

        common_redirect(common_local_url('login'));
    }

    $action_class = ucfirst($action).'Action';

    if (!class_exists($action_class)) {
        // TRANS: Error message displayed when trying to perform an undefined action.
        throw new ClientException(_('Unknown action'), 404);
    }

    call_user_func("$action_class::run", $args);
}

main();

// XXX: cleanup exit() calls or add an exit handler so
// this always gets called

Event::handle('CleanupPlugin');
