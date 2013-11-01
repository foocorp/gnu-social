<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Example to disable all debug messages and those containing 'About to push':
 * addPlugin('LogFilter', array(
 *    'priority' => array(LOG_DEBUG => false),
 *    'regex' => array('/About to push/' => false)
 * ));
 *
 * @todo add an admin panel
 *
 * @package LogFilterPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class LogFilterPlugin extends Plugin
{
    public $default = true;     // Set to false to require opting things in
    public $priority = array(); // override by priority: array(LOG_ERR => true, LOG_DEBUG => false)
    public $regex = array();    // override by regex match of message: array('/twitter/i' => false)

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'LogFilter',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:LogFilter',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Provides server-side setting to filter log output by type or keyword.'));

        return true;
    }

    /**
     * Hook for the StartLog event in common_log().
     * If a message doesn't pass our filters, we'll abort it.
     *
     * @param string $priority
     * @param string $msg
     * @param string $filename
     * @return boolean hook result code
     */
    function onStartLog(&$priority, &$msg, &$filename)
    {
        if ($this->filter($priority, $msg)) {
            // Let it through
            return true;
        } else {
            // Abort -- this line will go to /dev/null :)
            return false;
        }
    }

    /**
     * Do the filtering...
     *
     * @param string $priority
     * @param string $msg
     * @return boolean true to let the log message be processed
     */
    function filter($priority, $msg)
    {
        $state = $this->default;
        if (array_key_exists($priority, $this->priority)) {
            $state = $this->priority[$priority];
        }
        foreach ($this->regex as $regex => $override) {
            if (preg_match($regex, $msg)) {
                $state = $override;
            }
        }
        return $state;
    }
}
