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
delete_orphan_files.php [options]
Deletes all files and their File entries where there is no link to a
Notice entry. Good for cleaning up after user deletion or so where the
attached files weren't removed as well.

  -y --yes      do not wait for confirmation

Will print '.' for each deleted File entry and 'x' if it also had a locally stored file.

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

print "Finding File entries that are not related to a Notice (or the notice has been deleted)...";
$file = new File();
$sql = 'SELECT file.* FROM file'.
        ' LEFT JOIN file_to_post ON file_to_post.file_id=file.id'.
        ' WHERE'.
            ' NOT EXISTS (SELECT file_to_post.file_id FROM file_to_post WHERE file.id=file_to_post.file_id)'.
            ' OR NOT EXISTS (SELECT notice.id FROM notice WHERE notice.id=file_to_post.post_id)'.
            ' GROUP BY file.id;';

if ($file->query($sql) !== false) {
    print " {$file->N} found.\n";
    if ($file->N == 0) {
        exit(0);
    }
} else {
    print "FAILED";
    exit(1);
}

if (!have_option('y', 'yes')) {
    print "About to delete the entries along with locally stored files. Are you sure? [y/N] ";
    $response = fgets(STDIN);
    if (strtolower(trim($response)) != 'y') {
        print "Aborting.\n";
        exit(0);
    }
}

print "\nDeleting: ";
while ($file->fetch()) {
    try {
        $file->getPath();
        $file->delete();
        print 'x';
    } catch (Exception $e) {
        // either FileNotFound exception or ClientException
        $file->delete();
        print '.';
    }
}
print "\nDONE.\n";
