<?php
/*
 * StatusNet Plugin: 0.9
 * Plugin Name: FirePHP
 * Description: Sends StatusNet log output to FirePHP
 * Version: 0.1
 * Author: Craig Andrews <candrews@integralblue.com>
 * Author URI: http://candrews.integralblue.com/
*/

/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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
 * @category  Plugin
 * @package MinifyPlugin
 * @maintainer Craig Andrews <candrews@integralblue.com>
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

// We bundle the FirePHP library...
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib/FirePHP/lib');

class FirePHPPlugin extends Plugin
{
    private $firephp;

    function onInitializePlugin()
    {
        //Output buffering has to be enabled so FirePHP can send the HTTP headers it needs
        ob_start();
        require_once('FirePHPCore/FirePHP.class.php');
        $this->firephp = FirePHP::getInstance(true);
    }

    function onStartLog(&$priority, &$msg, &$filename)
    {
        static $firephp_priorities = array(FirePHP::ERROR, FirePHP::ERROR, FirePHP::ERROR, FirePHP::ERROR,
                                      FirePHP::WARN, FirePHP::LOG, FirePHP::LOG, FirePHP::INFO);
        $fp_priority = $firephp_priorities[$priority];
        $this->firephp->fb($msg, $fp_priority);
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'FirePHP',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:FirePHP',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The FirePHP plugin writes StatusNet\'s log output to FirePHP.'));
        return true;
    }
}
