<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * A snapshot of site stats that can report itself to headquarters
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Stats
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * A snapshot of site stats that can report itself to headquarters
 *
 * This class will collect statistics on the site and report them to
 * a statistics server of the admin's choice. (Default is the big one
 * at status.net.)
 *
 * It can either be called from a cron job, or run occasionally by the
 * Web site.
 *
 * @category Stats
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */

class Snapshot
{
    var $stats = null;

    /**
     * Constructor for a snapshot
     */

    function __construct()
    {
    }

    /**
     * Static function for reporting statistics
     *
     * This function checks whether it should report statistics, based on
     * the current configuation settings. If it should, it creates a new
     * Snapshot object, takes a snapshot, and reports it to headquarters.
     *
     * @return void
     */

    static function check()
    {
        switch (common_config('snapshot', 'run')) {
        case 'web':
            // skip if we're not running on the Web.
            if (!isset($_SERVER) || !array_key_exists('REQUEST_METHOD', $_SERVER)) {
                break;
            }
            // Run once every frequency hits
            // XXX: do frequency by time (once a week, etc.) rather than
            // hits
            if (rand() % common_config('snapshot', 'frequency') == 0) {
                $snapshot = new Snapshot();
                $snapshot->take();
                $snapshot->report();
            }
            break;
        case 'cron':
            // skip if we're running on the Web
            if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
                break;
            }
            common_log(LOG_INFO, 'Running snapshot from cron job');
            // We're running from the command line; assume

            $snapshot = new Snapshot();
            $snapshot->take();
            common_log(LOG_INFO, count($snapshot->stats) . " statistics being uploaded.");
            $snapshot->report();

            break;
        case 'never':
            break;
        default:
            common_log(LOG_WARNING, "Unrecognized value for snapshot run config.");
        }
    }

    /**
     * Take a snapshot of the server
     *
     * Builds an array of statistical and configuration data based
     * on the local database and config files. We avoid grabbing any
     * information that could be personal or private.
     *
     * @return void
     */

    function take()
    {
        $this->stats = array();

        // Some basic identification stuff

        $this->stats['version']    = GNUSOCIAL_VERSION;
        $this->stats['phpversion'] = phpversion();
        $this->stats['name']       = common_config('site', 'name');
        $this->stats['root']       = common_root_url();

        // non-identifying stats on various tables. Primary
        // interest is size and rate of activity of service.

        $tables = array('user',
                        'notice',
                        'subscription',
                        'user_group');

        foreach ($tables as $table) {
            $this->tableStats($table);
        }

        // stats on some important config options

        $this->stats['theme']     = common_config('site', 'theme');
        $this->stats['dbtype']    = common_config('db', 'type');
        $this->stats['xmpp']      = common_config('xmpp', 'enabled');
        $this->stats['inboxes']   = common_config('inboxes', 'enabled');
        $this->stats['queue']     = common_config('queue', 'enabled');
        $this->stats['license']   = common_config('license', 'url');
        $this->stats['fancy']     = common_config('site', 'fancy');
        $this->stats['private']   = common_config('site', 'private');
        $this->stats['closed']    = common_config('site', 'closed');
        $this->stats['memcached'] = common_config('memcached', 'enabled');
        $this->stats['language']  = common_config('site', 'language');
        $this->stats['timezone']  = common_config('site', 'timezone');

    }

    /**
     * Reports statistics to headquarters
     *
     * Posts statistics to a reporting server.
     *
     * @return void
     */

    function report()
    {
        // XXX: Use OICU2 and OAuth to make authorized requests

        $reporturl = common_config('snapshot', 'reporturl');
        try {
            $request = HTTPClient::start();
            $request->post($reporturl, null, $this->stats);
        } catch (Exception $e) {
            common_log(LOG_WARNING, "Error in snapshot: " . $e->getMessage());
        }
    }

    /**
     * Updates statistics for a single table
     *
     * Determines the size of a table and its oldest and newest rows.
     * Goal here is to see how active a site is. Note that it
     * fills up the instance stats variable.
     *
     * @param string $table name of table to check
     *
     * @return void
     */

    function tableStats($table)
    {
        $inst = DB_DataObject::factory($table);

        $inst->selectAdd();
        $inst->selectAdd('count(*) as cnt, '.
                         'min(created) as first, '.
                         'max(created) as last');

        if ($inst->find(true)) {
            $this->stats[$table.'count'] = $inst->cnt;
            $this->stats[$table.'first'] = $inst->first;
            $this->stats[$table.'last']  = $inst->last;
        }

        $inst->free();
        unset($inst);
    }
}
