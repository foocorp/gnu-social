#!/usr/bin/env php
<?php

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$shortoptions = 'u:';
$longoptions = array('url=');

$helptext = <<<END_OF_HELP
poll_oembed.php --url URL
Test oEmbed API on a URL.

  -u --url  URL to try oEmbed against

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (!have_option('u', 'url')) {
    echo 'No URL given.';
    exit(1);
}

$url = get_option_value('u', 'url');

print "Contacting URL\n";

$oEmbed = oEmbedHelper::getObject($url);
var_dump($oEmbed);

print "\nDONE.\n";
