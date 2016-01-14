<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Abstract class for i/o managers
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
 * @category  QueueManager
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

/**
 * Completed child classes must implement the enqueue() method.
 *
 * For background processing, classes should implement either socket-based
 * input (handleInput(), getSockets()) or idle-loop polling (idle()).
 */
abstract class QueueManager extends IoManager
{
    static $qm = null;

    protected $master = null;
    protected $handlers = array();
    protected $groups = array();
    protected $activeGroups = array();
    protected $ignoredTransports = array();

    /**
     * Factory function to pull the appropriate QueueManager object
     * for this site's configuration. It can then be used to queue
     * events for later processing or to spawn a processing loop.
     *
     * Plugins can add to the built-in types by hooking StartNewQueueManager.
     *
     * @return QueueManager
     */
    public static function get()
    {
        if (empty(self::$qm)) {

            if (Event::handle('StartNewQueueManager', array(&self::$qm))) {

                $enabled = common_config('queue', 'enabled');
                $type = common_config('queue', 'subsystem');

                if (!$enabled) {
                    // does everything immediately
                    self::$qm = new UnQueueManager();
                } else {
                    switch ($type) {
                     case 'db':
                        self::$qm = new DBQueueManager();
                        break;
                     case 'stomp':
                        self::$qm = new StompQueueManager();
                        break;
                     default:
                        throw new ServerException("No queue manager class for type '$type'");
                    }
                }
            }
        }

        return self::$qm;
    }

    /**
     * @fixme wouldn't necessarily work with other class types.
     * Better to change the interface...?
     */
    public static function multiSite()
    {
        if (common_config('queue', 'subsystem') == 'stomp') {
            return IoManager::INSTANCE_PER_PROCESS;
        } else {
            return IoManager::SINGLE_ONLY;
        }
    }

    function __construct()
    {
        $this->initialize();
    }

    /**
     * Optional; ping any running queue handler daemons with a notification
     * such as announcing a new site to handle or requesting clean shutdown.
     * This avoids having to restart all the daemons manually to update configs
     * and such.
     *
     * Called from scripts/queuectl.php controller utility.
     *
     * @param string $event event key
     * @param string $param optional parameter to append to key
     * @return boolean success
     */
    public function sendControlSignal($event, $param='')
    {
        throw new Exception(get_class($this) . " does not support control signals.");
    }

    /**
     * Store an object (usually/always a Notice) into the given queue
     * for later processing. No guarantee is made on when it will be
     * processed; it could be immediately or at some unspecified point
     * in the future.
     *
     * Must be implemented by any queue manager.
     *
     * @param Notice $object
     * @param string $queue
     */
    abstract function enqueue($object, $queue);

    /**
     * Build a representation for an object for logging
     * @param mixed
     * @return string
     */
    function logrep($object) {
        if (is_object($object)) {
            $class = get_class($object);
            if (isset($object->id)) {
                return "$class $object->id";
            }
            return $class;
        } elseif (is_string($object)) {
            $len = strlen($object);
            $fragment = mb_substr($object, 0, 32);
            if (mb_strlen($object) > 32) {
                $fragment .= '...';
            }
            return "string '$fragment' ($len bytes)";
        } elseif (is_array($object)) {
            return 'array with ' . count($object) .
                   ' elements (keys:[' .  implode(',', array_keys($object)) . '])';
        }
        return strval($object);
    }

    /**
     * Encode an object for queued storage.
     *
     * @param mixed $item
     * @return string
     */
    protected function encode($item)
    {
        return serialize($item);
    }

    /**
     * Decode an object from queued storage.
     * Accepts notice reference entries and serialized items.
     *
     * @param string
     * @return mixed
     */
    protected function decode($frame)
    {
        $object = unserialize($frame);

        // If it is a string, we really store a JSON object in there
        // except if it begins with '<', because then it is XML.
        if (is_string($object) &&
            substr($object, 0, 1) != '<' &&
            !is_numeric($object))
        {
            $json = json_decode($object);
            if ($json === null) {
                throw new Exception('Bad frame in queue item');
            }

            // The JSON object has a type parameter which contains the class
            if (empty($json->type)) {
                throw new Exception('Type not specified for queue item');
            }
            if (!is_a($json->type, 'Managed_DataObject', true)) {
                throw new Exception('Managed_DataObject class does not exist for queue item');
            }

            // And each of these types should have a unique id (or uri)
            if (isset($json->id) && !empty($json->id)) {
                $object = call_user_func(array($json->type, 'getKV'), 'id', $json->id);
            } elseif (isset($json->uri) && !empty($json->uri)) {
                $object = call_user_func(array($json->type, 'getKV'), 'uri', $json->uri);
            }

            // But if no object was found, there's nothing we can handle
            if (!$object instanceof Managed_DataObject) {
                throw new Exception('Queue item frame referenced a non-existant object');
            }
        }

        // If the frame was not a string, it's either an array or an object.

        return $object;
    }

    /**
     * Instantiate the appropriate QueueHandler class for the given queue.
     *
     * @param string $queue
     * @return mixed QueueHandler or null
     */
    function getHandler($queue)
    {
        if (isset($this->handlers[$queue])) {
            $class = $this->handlers[$queue];
            if(is_object($class)) {
                return $class;
            } else if (class_exists($class)) {
                return new $class();
            } else {
                $this->_log(LOG_ERR, "Nonexistent handler class '$class' for queue '$queue'");
            }
        }
        throw new NoQueueHandlerException($queue);
    }

    /**
     * Get a list of registered queue transport names to be used
     * for listening in this daemon.
     *
     * @return array of strings
     */
    function activeQueues()
    {
        $queues = array();
        foreach ($this->activeGroups as $group) {
            if (isset($this->groups[$group])) {
                $queues = array_merge($queues, $this->groups[$group]);
            }
        }

        return array_keys($queues);
    }

    function getIgnoredTransports()
    {
        return array_keys($this->ignoredTransports);
    }

    function ignoreTransport($transport)
    {
        // key is used for uniqueness, value doesn't mean anything
        $this->ignoredTransports[$transport] = true;
    }

    /**
     * Initialize the list of queue handlers for the current site.
     *
     * @event StartInitializeQueueManager
     * @event EndInitializeQueueManager
     */
    function initialize()
    {
        $this->handlers = array();
        $this->groups = array();
        $this->groupsByTransport = array();

        if (Event::handle('StartInitializeQueueManager', array($this))) {
            $this->connect('distrib', 'DistribQueueHandler');
            $this->connect('ping', 'PingQueueHandler');
            if (common_config('sms', 'enabled')) {
                $this->connect('sms', 'SmsQueueHandler');
            }

            // Background user management tasks...
            $this->connect('deluser', 'DelUserQueueHandler');
            $this->connect('feedimp', 'FeedImporter');
            $this->connect('actimp', 'ActivityImporter');
            $this->connect('acctmove', 'AccountMover');
            $this->connect('actmove', 'ActivityMover');

            // For compat with old plugins not registering their own handlers.
            $this->connect('plugin', 'PluginQueueHandler');
        }
        Event::handle('EndInitializeQueueManager', array($this));
    }

    /**
     * Register a queue transport name and handler class for your plugin.
     * Only registered transports will be reliably picked up!
     *
     * @param string $transport
     * @param string $class class name or object instance
     * @param string $group
     */
    public function connect($transport, $class, $group='main')
    {
        $this->handlers[$transport] = $class;
        $this->groups[$group][$transport] = $class;
        $this->groupsByTransport[$transport] = $group;
    }

    /**
     * Set the active group which will be used for listening.
     * @param string $group
     */
    function setActiveGroup($group)
    {
        $this->activeGroups = array($group);
    }

    /**
     * Set the active group(s) which will be used for listening.
     * @param array $groups
     */
    function setActiveGroups($groups)
    {
        $this->activeGroups = $groups;
    }

    /**
     * @return string queue group for this queue
     */
    function queueGroup($queue)
    {
        if (isset($this->groupsByTransport[$queue])) {
            return $this->groupsByTransport[$queue];
        } else {
            throw new Exception("Requested group for unregistered transport $queue");
        }
    }

    /**
     * Send a statistic ping to the queue monitoring system,
     * optionally with a per-queue id.
     *
     * @param string $key
     * @param string $queue
     */
    function stats($key, $queue=false)
    {
        $owners = array();
        if ($queue) {
            $owners[] = "queue:$queue";
            $owners[] = "site:" . common_config('site', 'server');
        }
        if (isset($this->master)) {
            $this->master->stats($key, $owners);
        } else {
            $monitor = new QueueMonitor();
            $monitor->stats($key, $owners);
        }
    }

    protected function _log($level, $msg)
    {
        $class = get_class($this);
        if ($this->activeGroups) {
            $groups = ' (' . implode(',', $this->activeGroups) . ')';
        } else {
            $groups = '';
        }
        common_log($level, "$class$groups: $msg");
    }
}
