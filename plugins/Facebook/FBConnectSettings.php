<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Facebook Connect settings
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/connectsettingsaction.php';

/**
 * Facebook Connect settings action
 *
 * @category Settings
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class FBConnectSettingsAction extends ConnectSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */
    function title()
    {
        // TRANS: Page title.
        return _m('Facebook Connect Settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
    	// TRANS: Instructions.
        return _m('Manage how your account connects to Facebook');
    }

    /**
     * Content area of the page
     *
     * Shows a form for uploading an avatar.
     *
     * @return void
     */
    function showContent()
    {
        $user = common_current_user();
        $flink = Foreign_link::getByUserID($user->id, FACEBOOK_CONNECT_SERVICE);

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_facebook',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('FBConnectSettings')));

        if (!$flink) {

            $this->element('p', 'instructions',
                _m('There is no Facebook user connected to this account.'));

            $this->element('fb:login-button', array('onlogin' => 'goto_login()',
                'length' => 'long'));

        } else {

            $this->element('p', 'form_note',
                           _m('Connected Facebook user'));

            $this->elementStart('p', array('class' => 'facebook-user-display'));
            $this->elementStart('fb:profile-pic',
                array('uid' => $flink->foreign_id,
                      'size' => 'small',
                      'linked' => 'true',
                      'facebook-logo' => 'true'));
            $this->elementEnd('fb:profile-pic');

            $this->elementStart('fb:name', array('uid' => $flink->foreign_id,
                                                 'useyou' => 'false'));
            $this->elementEnd('fb:name');
            $this->elementEnd('p');

            $this->hidden('token', common_session_token());

            $this->elementStart('fieldset');

            // TRANS: Legend.
            $this->element('legend', null, _m('Disconnect my account from Facebook'));

            if (!$user->password) {

                $this->elementStart('p', array('class' => 'form_guide'));
                // @todo FIXME: Bad i18n. Patchwork message in three parts.
                // TRANS: Followed by a link containing text "set a password".
                $this->text(_m('Disconnecting your Faceboook ' .
                               'would make it impossible to log in! Please '));
                $this->element('a',
                    array('href' => common_local_url('passwordsettings')),
                        // TRANS: Preceded by "Please " and followed by " first."
                        _m('set a password'));
                // TRANS: Preceded by "Please set a password".
                $this->text(_m(' first.'));
                $this->elementEnd('p');
            } else {

                $note = 'Keep your %s account but disconnect from Facebook. ' .
                    'You\'ll use your %s password to log in.';

                $site = common_config('site', 'name');

                $this->element('p', 'instructions',
                    sprintf($note, $site, $site));

                // TRANS: Submit button.
                $this->submit('disconnect', _m('BUTTON','Disconnect'));
            }

            $this->elementEnd('fieldset');
        }

        $this->elementEnd('form');
    }

    /**
     * Handle post
     *
     * Disconnects the current Facebook user from the current user's account
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

        if ($this->arg('disconnect')) {

            $user = common_current_user();

            $flink = Foreign_link::getByUserID($user->id, FACEBOOK_CONNECT_SERVICE);
            $result = $flink->delete();

            if ($result === false) {
                common_log_db_error($user, 'DELETE', __FILE__);
                $this->serverError(_m('Couldn\'t delete link to Facebook.'));
                return;
            }

            try {

                // Clear FB Connect cookies out
                $facebook = getFacebook();
                $facebook->clear_cookie_state();

            } catch (Exception $e) {
                common_log(LOG_WARNING, 'Facebook Connect Plugin - ' .
                           'Couldn\'t clear Facebook cookies: ' .
                           $e->getMessage());
            }

            $this->showForm(_m('You have disconnected from Facebook.'), true);

        } else {
            $this->showForm(_m('Not sure what you\'re trying to do.'));
            return;
        }
    }
}
