<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Settings for OpenID
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
 * @category  Settings
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR.'/plugins/OpenID/openid.php';

/**
 * Settings for OpenID
 *
 * Lets users add, edit and delete OpenIDs from their account
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class OpenidsettingsAction extends AccountSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Page title
     */

    function title()
    {
        return _m('OpenID settings');
    }

    /**
     * Instructions for use
     *
     * @return string Instructions for use
     */

    function getInstructions()
    {
        return _m('[OpenID](%%doc.openid%%) lets you log into many sites' .
                 ' with the same user account.'.
                 ' Manage your associated OpenIDs from here.');
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('openid_url');
    }

    /**
     * Show the form for OpenID management
     *
     * We have one form with a few different submit buttons to do different things.
     *
     * @return void
     */

    function showContent()
    {
        $user = common_current_user();

        if (!common_config('openid', 'trusted_provider')) {
            $this->elementStart('form', array('method' => 'post',
                                              'id' => 'form_settings_openid_add',
                                              'class' => 'form_settings',
                                              'action' =>
                                              common_local_url('openidsettings')));
            $this->elementStart('fieldset', array('id' => 'settings_openid_add'));
    
            $this->element('legend', null, _m('Add OpenID'));
            $this->hidden('token', common_session_token());
            $this->element('p', 'form_guide',
                           _m('If you want to add an OpenID to your account, ' .
                             'enter it in the box below and click "Add".'));
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');
            $this->element('label', array('for' => 'openid_url'),
                           _m('OpenID URL'));
            $this->element('input', array('name' => 'openid_url',
                                          'type' => 'text',
                                          'id' => 'openid_url'));
            $this->elementEnd('li');
            $this->elementEnd('ul');
            $this->element('input', array('type' => 'submit',
                                          'id' => 'settings_openid_add_action-submit',
                                          'name' => 'add',
                                          'class' => 'submit',
                                          'value' => _m('Add')));
            $this->elementEnd('fieldset');
            $this->elementEnd('form');
        }
        $oid = new User_openid();

        $oid->user_id = $user->id;

        $cnt = $oid->find();

        if ($cnt > 0) {

            $this->element('h2', null, _m('Remove OpenID'));

            if ($cnt == 1 && !$user->password) {

                $this->element('p', 'form_guide',
                               _m('Removing your only OpenID '.
                                 'would make it impossible to log in! ' .
                                 'If you need to remove it, '.
                                 'add another OpenID first.'));

                if ($oid->fetch()) {
                    $this->elementStart('p');
                    $this->element('a', array('href' => $oid->canonical),
                                   $oid->display);
                    $this->elementEnd('p');
                }

            } else {

                $this->element('p', 'form_guide',
                               _m('You can remove an OpenID from your account '.
                                 'by clicking the button marked "Remove".'));
                $idx = 0;

                while ($oid->fetch()) {
                    $this->elementStart('form',
                                        array('method' => 'POST',
                                              'id' => 'form_settings_openid_delete' . $idx,
                                              'class' => 'form_settings',
                                              'action' =>
                                              common_local_url('openidsettings')));
                    $this->elementStart('fieldset');
                    $this->hidden('token', common_session_token());
                    $this->element('a', array('href' => $oid->canonical),
                                   $oid->display);
                    $this->element('input', array('type' => 'hidden',
                                                  'id' => 'openid_url'.$idx,
                                                  'name' => 'openid_url',
                                                  'value' => $oid->canonical));
                    $this->element('input', array('type' => 'submit',
                                                  'id' => 'remove'.$idx,
                                                  'name' => 'remove',
                                                  'class' => 'submit remove',
                                                  'value' => _m('Remove')));
                    $this->elementEnd('fieldset');
                    $this->elementEnd('form');
                    $idx++;
                }
            }
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_openid_trustroots',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('openidsettings')));
        $this->elementStart('fieldset', array('id' => 'settings_openid_trustroots'));
        $this->element('legend', null, _m('OpenID Trusted Sites'));
        $this->hidden('token', common_session_token());
        $this->element('p', 'form_guide',
                       _m('The following sites are allowed to access your ' .
                       'identity and log you in. You can remove a site from ' .
                       'this list to deny it access to your OpenID.'));
        $this->elementStart('ul', 'form_data');
        $user_openid_trustroot = new User_openid_trustroot();
        $user_openid_trustroot->user_id=$user->id;
        if($user_openid_trustroot->find()) {
            while($user_openid_trustroot->fetch()) {
                $this->elementStart('li');
                $this->element('input', array('name' => 'openid_trustroot[]',
                                              'type' => 'checkbox',
                                              'class' => 'checkbox',
                                              'value' => $user_openid_trustroot->trustroot,
                                              'id' => 'openid_trustroot_' . crc32($user_openid_trustroot->trustroot)));
                $this->element('label', array('class'=>'checkbox', 'for' => 'openid_trustroot_' . crc32($user_openid_trustroot->trustroot)),
                               $user_openid_trustroot->trustroot);
                $this->elementEnd('li');
            }
        }
        $this->elementEnd('ul');
        $this->element('input', array('type' => 'submit',
                                      'id' => 'settings_openid_trustroots_action-submit',
                                      'name' => 'remove_trustroots',
                                      'class' => 'submit',
                                      'value' => _m('Remove')));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Handle a POST request
     *
     * Muxes to different sub-functions based on which button was pushed
     *
     * @return void
     */

    function handlePost()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_m('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if ($this->arg('add')) {
            if (common_config('openid', 'trusted_provider')) {
                $this->showForm(_m("Can't add new providers."));
            } else {
                $result = oid_authenticate($this->trimmed('openid_url'),
                                           'finishaddopenid');
                if (is_string($result)) { // error message
                    $this->showForm($result);
                }
            }
        } else if ($this->arg('remove')) {
            $this->removeOpenid();
        } else if($this->arg('remove_trustroots')) {
            $this->removeTrustroots();
        } else {
            $this->showForm(_m('Something weird happened.'));
        }
    }

    /**
     * Handles a request to remove OpenID trustroots from the user's account
     *
     * Validates input and, if everything is OK, deletes the trustroots.
     * Reloads the form with a success or error notification.
     *
     * @return void
     */

    function removeTrustroots()
    {
        $user = common_current_user();
        $trustroots = $this->arg('openid_trustroot');
        if($trustroots) {
            foreach($trustroots as $trustroot) {
                $user_openid_trustroot = User_openid_trustroot::pkeyGet(
                                                array('user_id'=>$user->id, 'trustroot'=>$trustroot));
                if($user_openid_trustroot) {
                    $user_openid_trustroot->delete();
                } else {
                    $this->showForm(_m('No such OpenID trustroot.'));
                    return;
                }
            }
            $this->showForm(_m('Trustroots removed'), true);
        } else {
            $this->showForm();
        }
        return;
    }

    /**
     * Handles a request to remove an OpenID from the user's account
     *
     * Validates input and, if everything is OK, deletes the OpenID.
     * Reloads the form with a success or error notification.
     *
     * @return void
     */

    function removeOpenid()
    {
        $openid_url = $this->trimmed('openid_url');

        $oid = User_openid::staticGet('canonical', $openid_url);

        if (!$oid) {
            $this->showForm(_m('No such OpenID.'));
            return;
        }
        $cur = common_current_user();
        if (!$cur || $oid->user_id != $cur->id) {
            $this->showForm(_m('That OpenID does not belong to you.'));
            return;
        }
        $oid->delete();
        $this->showForm(_m('OpenID removed.'), true);
        return;
    }
}
