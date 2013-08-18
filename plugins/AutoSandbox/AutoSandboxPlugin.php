<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to automatically sandbox newly registered users in an effort to beat
 * spammers. If the user proves to be legitimate, moderators can un-sandbox them.
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
 * @author    Sean Carmody<seancarmody@gmail.com>
 * @copyright 2010
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

define('AUTOSANDBOX', '0.1');

//require_once(INSTALLDIR.'/plugins/AutoSandbox/autosandbox.php');

class AutoSandboxPlugin extends Plugin
{
    var $contact;
    var $debug;

    function onInitializePlugin()
    {
        if(!isset($this->debug))
        {
            $this->debug = 0;
        }

        if(!isset($this->contact)) {
           $default = common_config('newuser', 'default');
           if (!empty($default)) {
               $this->contact = $default;
           }
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'AutoSandbox',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Sean Carmody',
                            'homepage' => 'http://status.net/wiki/Plugin:AutoSandbox',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Automatically sandboxes newly registered members.'));
        return true;
    }

    function onStartRegistrationFormData($action)
    {
         // TRANS: User instructions after registration.
         $instr = _m('Note you will initially be "sandboxed" so your posts will not appear in the public timeline.');

         if (isset($this->contact)) {
             $contactuser = User::getKV('nickname', $this->contact);
             if (!empty($contactuser)) {
                 $contactlink = "@<a href=\"$contactuser->uri\">$contactuser->nickname</a>";
                 // TRANS: User instructions after registration.
                 // TRANS: %s is a clickable e-mailaddress.
                 $instr = sprintf(_m('Note you will initially be "sandboxed" so your posts will not appear in the public timeline. '.
                   'Send a message to %s to speed up the unsandboxing process.'),$contactlink);
             }
         }

         $output = common_markup_to_html($instr);
         $action->elementStart('div', 'instructions');
         $action->raw($output);
         $action->elementEnd('div');
    }

    function onEndUserRegister(&$profile,&$user)
    {
	$profile->sandbox();
	if ($this->debug) {
	    common_log(LOG_WARNING, "AutoSandbox: sandboxed of $user->nickname");
        }
    }
}
