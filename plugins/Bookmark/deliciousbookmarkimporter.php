<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Importer class for Delicious.com bookmarks
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

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Importer class for Delicious bookmarks
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class DeliciousBookmarkImporter extends QueueHandler
{
    /**
     * Return the transport for this queue handler
     *
     * @return string 'dlcsbkmk'
     */
    function transport()
    {
        return 'dlcsbkmk';
    }

    /**
     * Handle the data
     *
     * @param array $data associative array of user & bookmark info from DeliciousBackupImporter::importBookmark()
     *
     * @return boolean success value
     */
    function handle($data)
    {
        $profile = Profile::getKV('id', $data['profile_id']);

        try {
            $saved = Bookmark::saveNew($profile,
                                       $data['title'],
                                       $data['url'],
                                       $data['tags'],
                                       $data['description'],
                                       array('created' => $data['created'],
                                             'distribute' => false));
        } catch (ClientException $ce) {
            // Most likely a duplicate -- continue on with the rest!
            common_log(LOG_ERR, "Error importing delicious bookmark to $data[url]: " . $ce->getMessage());
            return true;
        } catch (Exception $ex) {
            if (preg_match("/DB Error: already exists/", $ex->getMessage())) {
                common_log(LOG_ERR, "Error importing delicious bookmark to $data[url]: " . $ce->getMessage());
                return true;
            } else {
                throw $ex;
            }
        }

        return true;
    }
}
