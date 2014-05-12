#!/usr/bin/env php
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'y';
$longoptions = array('yes');

$helptext = <<<END_OF_HELP
clean_thumbnails.php [options]
Deletes all local thumbnails so they can be regenerated. Also deletes
if the original File object does not exist, even for remote entries.

  -y --yes      do not wait for confirmation

Will print '.' for deleted local files and 'x' where File entry was missing.
If the script seems to stop, it is processing correct File_thumbnail entries.

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (!have_option('y', 'yes')) {
    print "About to delete locally generated thumbnails to allow regeneration. Are you sure? [y/N] ";
    $response = fgets(STDIN);
    if (strtolower(trim($response)) != 'y') {
        print "Aborting.\n";
        exit(0);
    }
}

print "Deleting";
$thumbs = new File_thumbnail();
$thumbs->find();
while ($thumbs->fetch()) {
    try {
        $file = $thumbs->getFile();
        if ($file->isLocal()) {
            // only delete properly linked thumbnails if they're local
            $thumbs->delete();
            print '.';
        }
    } catch (NoResultException $e) {
        // No File object for thumbnail, let's delete the thumbnail entry
        $thumbs->delete();
        print 'x';
    }
}
print "\nDONE.\n";
