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

$shortoptions = 'i::yv';
$longoptions = array('id=', 'yes', 'verbose');

$helptext = <<<END_OF_HELP
nukefile.php [options]
deletes a file and related notices from the database

  -i --id       ID of the file
  -v --verbose  Be verbose (print the contents of the notices deleted).

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (have_option('i', 'id')) {
    $id = get_option_value('i', 'id');
    $file = File::getKV('id', $id);
    if (!$file instanceof File) {
        print "Can't find file with ID $id\n";
        exit(1);
    }
} else {
    print "You must provide a file ID.\n";
    exit(1);
}

$verbose = have_option('v', 'verbose');

if (!have_option('y', 'yes')) {
    try {
        $filename = $file->getFilename();
    } catch (Exception $e) {
        $filename = '(remote file or no filename)';
    }
    print "About to PERMANENTLY delete file ($filename) with id=={$file->id}) AND its related notices. Are you sure? [y/N] ";
    $response = fgets(STDIN);
    if (strtolower(trim($response)) != 'y') {
        print "Aborting.\n";
        exit(0);
    }
}

print "Finding notices...\n";
try {
    $ids = File_to_post::getNoticeIDsByFile($file);
    $notice = Notice::multiGet('id', $ids);
    while ($notice->fetch()) {
        print "Deleting notice {$notice->id}".($verbose ? ": $notice->content\n" : "\n");
        $notice->delete();
    }
} catch (NoResultException $e) {
    print "No notices found with this File attached.\n";
}
print "Deleting File object together with possibly locally stored copy.\n";
$file->delete();
print "DONE.\n";
