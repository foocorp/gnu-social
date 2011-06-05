<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));
define('STATUSNET', true);
define('LACONICA', true);

require_once INSTALLDIR . '/scripts/commandline.inc';
require_once INSTALLDIR . '/extlib/php-gettext/gettext.inc';

common_init_locale("en_US");
common_init_locale('fr');


putenv("LANG=fr");
putenv("LANGUAGE=fr");
setlocale('fr.utf8');
_setlocale('fr.utf8');

_bindtextdomain("statusnet", INSTALLDIR . '/locale');
_bindtextdomain("FeedSub", INSTALLDIR . '/plugins/FeedSub/locale');

$times = 10000;
$delta = array();

$start = microtime(true);
for($i = 0; $i < $times; $i++) {
    $result = _("Send");
}
$delta["_"] = array((microtime(true) - $start) / $times, $result);

$start = microtime(true);
for($i = 0; $i < $times; $i++) {
    $result = __("Send");
}
$delta["__"] = array((microtime(true) - $start) / $times, $result);

$start = microtime(true);
for($i = 0; $i < $times; $i++) {
    $result = dgettext("FeedSub", "Feeds");
}
$delta["dgettext"] = array((microtime(true) - $start) / $times, $result);

$start = microtime(true);
for($i = 0; $i < $times; $i++) {
    $result = _dgettext("FeedSub", "Feeds");
}
$delta["_dgettext"] = array((microtime(true) - $start) / $times, $result);


$start = microtime(true);
for($i = 0; $i < $times; $i++) {
    // TRANS: String in the gettext speed test script. Unimportant.
    $result = _m("Feeds");
}
$delta["_m"] = array((microtime(true) - $start) / $times, $result);


$start = microtime(true);
for($i = 0; $i < $times; $i++) {
    $result = fake("Feeds");
}
$delta["fake"] = array((microtime(true) - $start) / $times, $result);

foreach ($delta as $func => $bits) {
    list($time, $result) = $bits;
    $ms = $time * 1000.0;
    printf("%10s %2.4fms %s\n", $func, $ms, $result);
}


function fake($str) {
    return $str;
}

