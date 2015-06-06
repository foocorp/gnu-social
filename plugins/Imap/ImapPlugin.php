<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * IMAP plugin to allow StatusNet to grab incoming emails and handle them as new user posts
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
 * @author   Craig Andrews <candrews@integralblue.com
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * IMAP plugin to allow StatusNet to grab incoming emails and handle them as new user posts
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ImapPlugin extends Plugin
{
    public $mailbox;
    public $user;
    public $password;
    public $poll_frequency = 60;

    function initialize(){
        if(!isset($this->mailbox)){
            // TRANS: Exception thrown when configuration of the IMAP plugin is incorrect.
            throw new Exception(_m('A mailbox must be specified.'));
        }
        if(!isset($this->user)){
            // TRANS: Exception thrown when configuration of the IMAP plugin is incorrect.
            throw new Exception(_m('A user must be specified.'));
        }
        if(!isset($this->password)){
            // TRANS: Exception thrown when configuration of the IMAP plugin is incorrect.
            throw new Exception(_m('A password must be specified.'));
        }
        if(!isset($this->poll_frequency)){
            // TRANS: Exception thrown when configuration of the IMAP plugin is incorrect.
            // TRANS: poll_frequency is a setting that should not be translated.
            throw new Exception(_m('A poll_frequency must be specified.'));
        }

        return true;
    }

    function onStartQueueDaemonIoManagers(&$classes)
    {
        $classes[] = new ImapManager($this);
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'IMAP',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:IMAP',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The IMAP plugin allows for StatusNet to check a POP or IMAP mailbox for incoming mail containing user posts.'));
        return true;
    }
}
