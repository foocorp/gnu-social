<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 *
 * Handler for queue items of type 'sitesum', sends email summaries
 * to all users on the site.
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
 * @category  Sample
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 *
 * Handler for queue items of type 'sitesum', sends email summaries
 * to all users on the site.
 *
 * @category  Email
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class SiteEmailSummaryHandler extends QueueHandler
{
    /**
     * Return transport keyword which identifies items this queue handler
     * services; must be defined for all subclasses.
     *
     * Must be 8 characters or less to fit in the queue_item database.
     * ex "email", "jabber", "sms", "irc", ...
     *
     * @return string
     */
    function transport()
    {
        return 'sitesum';
    }

    /**
     * Handle the site
     *
     * @param mixed $object
     * @return boolean true on success, false on failure
     */
    function handle($object)
    {
        $qm = QueueManager::get();

        try {
            // Enqueue a summary for all users

            $user = new User();
            $user->find();

            while ($user->fetch()) {
                try {
                    $qm->enqueue($user->id, 'usersum');
                } catch (Exception $e) {
                    common_log(LOG_WARNING, $e->getMessage());
                    continue;
                }
            }
        } catch (Exception $e) {
            common_log(LOG_WARNING, $e->getMessage());
        }

        return true;
    }
}
