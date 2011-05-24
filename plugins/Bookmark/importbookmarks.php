<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010 StatusNet, Inc.
 *
 * Import a bookmarks file as notices
 *
 * PHP version 5
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
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

$shortoptions = 'i:n:f:';
$longoptions  = array('id=', 'nickname=', 'file=');

$helptext = <<<END_OF_IMPORTBOOKMARKS_HELP
importbookmarks.php [options]
Restore a backed-up Delicious.com bookmark file

-i --id       ID of user to import bookmarks for
-n --nickname nickname of the user to import for
-f --file     file to read from (STDIN by default)
END_OF_IMPORTBOOKMARKS_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

/**
 * Get the bookmarks file as a string
 *
 * Uses the -f or --file parameter to open and read a
 * a bookmarks file
 *
 * @return string Contents of the file
 */

function getBookmarksFile()
{
    $filename = get_option_value('f', 'file');

    if (empty($filename)) {
        show_help();
        exit(1);
    }

    if (!file_exists($filename)) {
        // TRANS: Exception thrown when a file upload cannot be found.
        // TRANS: %s is the file that could not be found.
        throw new Exception(sprintf(_m('No such file "%s".'),$filename));
    }

    if (!is_file($filename)) {
        // TRANS: Exception thrown when a file upload is incorrect.
        // TRANS: %s is the irregular file.
        throw new Exception(sprintf(_m('Not a regular file: "%s".'),$filename));
    }

    if (!is_readable($filename)) {
        // TRANS: Exception thrown when a file upload is not readable.
        // TRANS: %s is the file that could not be read.
        throw new Exception(sprintf(_m('File "%s" not readable.'),$filename));
    }

    // TRANS: %s is the filename that contains a backup for a user.
    printfv(_m('Getting backup from file "%s".')."\n", $filename);

    $html = file_get_contents($filename);

    return $html;
}

try {
    $user = getUser();
    $html = getBookmarksFile();

    $qm = QueueManager::get();

    $qm->enqueue(array($user, $html), 'dlcsback');

} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}
