<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 *  Edit user settings for Facebook
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
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */
if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Edit user settings for Facebook
 *
 * @category Settings
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      SettingsAction
 */
class FacebooksettingsAction extends ConnectSettingsAction {
    private $facebook; // Facebook PHP-SDK client obj
    private $flink;
    private $user;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($args) {
        parent::prepare($args);

        $this->facebook = new Facebook(
            array(
                'appId'  => common_config('facebook', 'appid'),
                'secret' => common_config('facebook', 'secret'),
                'cookie' => true,
            )
        );

        $this->user = common_current_user();

        $this->flink = Foreign_link::getByUserID(
            $this->user->id,
            FACEBOOK_SERVICE
        );

        return true;
    }

    /*
     * Check the sessions token and dispatch
     */
    function handlePost($args) {
        // CSRF protection

        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(
                _m('There was a problem with your session token. Try again, please.')
            );
            return;
        }

        if ($this->arg('save')) {
            $this->saveSettings();
        } else if ($this->arg('disconnect')) {
            $this->disconnect();
        }
    }

    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title() {
        // TRANS: Page title for Facebook settings.
        return _m('Facebook settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions() {
        return _('Facebook settings');
    }

    /*
     * Show the settings form if he/she has a link to Facebook
     *
     * @return void
     */
    function showContent() {
        if (!empty($this->flink)) {

            $this->elementStart(
                'form',
                array(
                    'method' => 'post',
                    'id' => 'form_settings_facebook',
                    'class' => 'form_settings',
                    'action' => common_local_url('facebooksettings')
                )
            );

            $this->hidden('token', common_session_token());

            $this->element('p', 'form_note', _m('Connected Facebook user'));

            $this->elementStart('p', array('class' => 'facebook-user-display'));

            $this->element(
                'fb:profile-pic',
                array(
                    'uid' => $this->flink->foreign_id,
                    'size' => 'small',
                    'linked' => 'true',
                    'facebook-logo' => 'true'
                )
            );

            $this->element(
                'fb:name',
                array('uid' => $this->flink->foreign_id, 'useyou' => 'false')
            );

            $this->elementEnd('p');

            $this->elementStart('ul', 'form_data');

            $this->elementStart('li');

            $this->checkbox(
                'noticesync',
                _m('Publish my notices to Facebook.'),
                ($this->flink) ? ($this->flink->noticesync & FOREIGN_NOTICE_SEND) : true
            );

            $this->elementEnd('li');

            $this->elementStart('li');

            $this->checkbox(
                    'replysync',
                    _m('Send "@" replies to Facebook.'),
                    ($this->flink) ? ($this->flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) : true
            );

            $this->elementEnd('li');

            $this->elementStart('li');

            // TRANS: Submit button to save synchronisation settings.
            $this->submit('save', _m('BUTTON', 'Save'));

            $this->elementEnd('li');

            $this->elementEnd('ul');

            $this->elementStart('fieldset');

            // TRANS: Legend.
            $this->element('legend', null, _m('Disconnect my account from Facebook'));

            if (empty($this->user->password)) {
                $this->elementStart('p', array('class' => 'form_guide'));

                $msg = sprintf(
                    _m(
                        'Disconnecting your Faceboook would make it impossible to '
                            . 'log in! Please [set a password](%s) first.'
                    ),
                    common_local_url('passwordsettings')
                );

                $this->raw(common_markup_to_html($msg));
                $this->elementEnd('p');
            } else {
                // @todo FIXME: i18n: This message is not being used.
                $msg = sprintf(
                    // TRANS: Message displayed when initiating disconnect of a StatusNet user
                    // TRANS: from a Facebook account. %1$s is the StatusNet site name.
                    _m(
                        'Keep your %1$s account but disconnect from Facebook. ' .
                        'You\'ll use your %1$s password to log in.'
                    ),
                    common_config('site', 'name')
                );

                // TRANS: Submit button.
                $this->submit('disconnect', _m('BUTTON', 'Disconnect'));
            }

            $this->elementEnd('fieldset');

            $this->elementEnd('form');
         }
    }

    /*
     * Save the user's Facebook settings
     *
     * @return void
     */
    function saveSettings() {
        $noticesync = $this->boolean('noticesync');
        $replysync  = $this->boolean('replysync');

        $original = clone($this->flink);
        $this->flink->set_flags($noticesync, false, $replysync, false);
        $result = $this->flink->update($original);

        if ($result === false) {
            $this->showForm(_m('There was a problem saving your sync preferences.'));
        } else {
            // TRANS: Confirmation that synchronisation settings have been saved into the system.
            $this->showForm(_m('Sync preferences saved.'), true);
        }
    }

    /*
     * Disconnect the user's Facebook account - deletes the Foreign_link
     * and shows the user a success message if all goes well.
     */
    function disconnect() {
        $result = $this->flink->delete();
        $this->flink = null;

        if ($result === false) {
            common_log_db_error($user, 'DELETE', __FILE__);
            $this->serverError(_m('Couldn\'t delete link to Facebook.'));
            return;
        }

        $this->showForm(_m('You have disconnected from Facebook.'), true);
    }
}
