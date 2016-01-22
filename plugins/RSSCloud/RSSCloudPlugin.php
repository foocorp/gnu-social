<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to support RSSCloud
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

define('RSSCLOUDPLUGIN_VERSION', '0.1');

/**
 * Plugin class for adding RSSCloud capabilities to StatusNet
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class RSSCloudPlugin extends Plugin
{
    /**
     * Our friend, the constructor
     *
     * @return void
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Setup the info for the subscription handler. Allow overriding
     * to point at another cloud hub (not currently used).
     *
     * @return void
     */
    function onInitializePlugin()
    {
        $this->domain   = common_config('rsscloud', 'domain');
        $this->port     = common_config('rsscloud', 'port');
        $this->path     = common_config('rsscloud', 'path');
        $this->funct    = common_config('rsscloud', 'function');
        $this->protocol = common_config('rsscloud', 'protocol');

        // set defaults

        $local_server = parse_url(common_path('main/rsscloud/request_notify'));

        if (empty($this->domain)) {
            $this->domain = $local_server['host'];
        }

        if (empty($this->port)) {
            $this->port = '80';
        }

        if (empty($this->path)) {
            $this->path = $local_server['path'];
        }

        if (empty($this->funct)) {
            $this->funct = '';
        }

        if (empty($this->protocol)) {
            $this->protocol = 'http-post';
        }
    }

    /**
     * Add RSSCloud-related paths to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @param Mapper $m URL parser and mapper
     *
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        $m->connect('/main/rsscloud/request_notify',
                    array('action' => 'RSSCloudRequestNotify'));

        // XXX: This is just for end-to-end testing. Uncomment if you need to pretend
        //      to be a cloud hub for some reason.
        //$m->connect('/main/rsscloud/notify',
        //            array('action' => 'LoggingAggregator'));

        return true;
    }

    /**
     * Add a <cloud> element to the RSS feed (after the rss <channel>
     * element is started).
     *
     * @param Action $action the ApiAction
     *
     * @return void
     */
    function onStartApiRss($action)
    {
        if (get_class($action) == 'ApiTimelineUserAction') {

            $attrs = array('domain'            => $this->domain,
                           'port'              => $this->port,
                           'path'              => $this->path,
                           'registerProcedure' => $this->funct,
                           'protocol'          => $this->protocol);

            // Dipping into XMLWriter to avoid a full end element (</cloud>).

            $action->xw->startElement('cloud');
            foreach ($attrs as $name => $value) {
                $action->xw->writeAttribute($name, $value);
            }

            $action->xw->endElement();
        }
    }

    /**
     * Add an RSSCloud queue item for each notice
     *
     * @param Notice $notice      the notice
     * @param array  &$transports the list of transports (queues)
     *
     * @return boolean hook return
     */

    function onStartEnqueueNotice($notice, &$transports)
    {
        if ($notice->isLocal()) {
            array_push($transports, 'rsscloud');
        }
        return true;
    }

    /**
     * Create the rsscloud_subscription table if it's not
     * already in the DB
     *
     * @return boolean hook return
     */

    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('rsscloud_subscription',
            array(
                'fields' => array(
                    'subscribed' => array('type' => 'int', 'not null' => true),
                    'url' => array('type' => 'varchar', 'length' => '191', 'not null' => true),
                    'failures' => array('type' => 'int', 'not null' => true, 'default' => 0),
                    'created' => array('type' => 'datetime', 'not null' => true),
                    'modified' => array('type' => 'timestamp', 'not null' => true),
                ),
                'primary key' => array('subscribed', 'url'),
            ));
         return true;
    }

    /**
     * Register RSSCloud notice queue handler
     *
     * @param QueueManager $manager
     *
     * @return boolean hook return
     */
    function onEndInitializeQueueManager($manager)
    {
        $manager->connect('rsscloud', 'RSSCloudQueueHandler');
        return true;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'RSSCloud',
                            'version' => RSSCLOUDPLUGIN_VERSION,
                            'author' => 'Zach Copley',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/RSSCloud',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The RSSCloud plugin enables your StatusNet instance to publish ' .
                               'real-time updates for profile RSS feeds using the ' .
                               '<a href="http://rsscloud.org/">RSSCloud protocol</a>.'));

        return true;
    }
}
