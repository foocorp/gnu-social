#!/usr/bin/env php
<?php
/*
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
 */

// Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('GNUSOCIAL', true);
define('STATUSNET', true);  // compatibility

require_once(INSTALLDIR . '/lib/common.php');

// Master StatusNet .pot file location (created by update_pot.sh)
$statusnet_pot = INSTALLDIR . '/locale/statusnet.pot';

set_time_limit(60);

/* Languages to pull */
$languages = get_all_languages();

/* Update the languages */
// Language code conversion for translatewiki.net (these are MediaWiki codes)
$codeMap = array(
	'nb'    => 'no',
	'pt_BR' => 'pt-br',
	'zh_CN' => 'zh-hans',
	'zh_TW' => 'zh-hant'
);

$doneCodes = array();

foreach ($languages as $language) {
	$code = $language['lang'];

	// Skip export of source language
	// and duplicates
	if( $code == 'en' || $code == 'no' ) {
		continue;
	}

	// Do not export codes twice (happens for 'nb')
	if( in_array( $code, $doneCodes ) ) {
		continue;
	} else {
		$doneCodes[] = $code;
	}

	// Convert code if needed
	if( isset( $codeMap[$code] ) ) {
		$twnCode = $codeMap[$code];
	} else {
		$twnCode = str_replace('_', '-', strtolower($code)); // pt_BR -> pt-br
	}

    // Fetch updates from translatewiki.net...
    $file_url = 'http://translatewiki.net/w/i.php?' .
        http_build_query(array(
            'title' => 'Special:Translate',
            'task' => 'export-to-file',
            'group' => 'out-statusnet-core',
            'language' => $twnCode));

    $lcdir = INSTALLDIR . '/locale/' . $code;
    $msgdir = "$lcdir/LC_MESSAGES";
    $pofile = "$msgdir/statusnet.po";
    $mofile = "$msgdir/statusnet.mo";

    /* Check for an existing */
    if (!is_dir($msgdir)) {
        mkdir($lcdir);
        mkdir($msgdir);
        $existingSHA1 = '';
    } else {
        $existingSHA1 = file_exists($pofile) ? sha1_file($pofile) : '';
    }

    /* Get the remote one */
    $new_file = curl_get_file($file_url);

    if ($new_file === FALSE) {
        echo "Could not retrieve .po file for $code: $file_url\n";
        continue;
    }

    // Update if the local .po file is different to the one downloaded, or
    // if the .mo file is not present.
    if (sha1($new_file) != $existingSHA1 || !file_exists($mofile)) {
        echo "Updating ".$code."\n";
        file_put_contents($pofile, $new_file);
        // --backup=off is workaround for Mac OS X fail
        system(sprintf('msgmerge -U --backup=off %s %s', $pofile, $statusnet_pot));
        /* Do not rebuild/add .mo files by default
         * FIXME: should be made a command line parameter.
        system(sprintf('msgfmt -o %s %s', $mofile, $pofile));
         */
    } else {
        echo "Unchanged - ".$code."\n";
    }
}

echo "Finished\n";


function curl_get_file($url)
{
    $c = curl_init();
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_URL, $url);
    $contents = curl_exec($c);
    curl_close($c);

    if (!empty($contents)) {
        return $contents;
    }

    return FALSE;
}
