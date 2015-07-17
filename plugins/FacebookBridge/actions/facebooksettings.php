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
if (!defined('GNUSOCIAL')) { exit(1); }

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
class FacebooksettingsAction extends SettingsAction {
    private $facebook; // Facebook PHP-SDK client obj
    private $flink;

    protected function doPreparation()
    {
        $this->facebook = new Facebook(
            array(
                'appId'  => common_config('facebook', 'appid'),
                'secret' => common_config('facebook', 'secret'),
                'cookie' => true,
            )
        );

        $this->flink = Foreign_link::getByUserID(
            $this->scoped->getID(),
            FACEBOOK_SERVICE
        );

        return true;
    }

    protected function doPost()
    {
        if ($this->arg('save')) {
            return $this->saveSettings();
        } else if ($this->arg('disconnect')) {
            return $this->disconnect();
        }

        throw new ClientException(_('No action to take on POST.'));
    }

    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title() {
        // TRANS: Page title for Facebook settings.
        return _m('TITLE','Facebook settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions() {
        // TRANS: Instructions for Facebook settings.
        return _m('Facebook settings');
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

            // TRANS: Form note. User is connected to facebook.
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
                // TRANS: Checkbox label in Facebook settings.
                _m('Publish my notices to Facebook.'),
                $this->flink->noticesync & FOREIGN_NOTICE_SEND
            );

            $this->elementEnd('li');

            $this->elementStart('li');

            $this->checkbox(
                    'replysync',
                    // TRANS: Checkbox label in Facebook settings.
                    _m('Send "@" replies to Facebook.'),
                    $this->flink->noticesync & FOREIGN_NOTICE_SEND_REPLY
            );

            $this->elementEnd('li');

            $this->elementStart('li');

            // TRANS: Submit button to save synchronisation settings.
            $this->submit('save', _m('BUTTON', 'Save'));

            $this->elementEnd('li');

            $this->elementEnd('ul');

            $this->elementStart('fieldset');

            // TRANS: Fieldset legend for form to disconnect from Facebook.
            $this->element('legend', null, _m('Disconnect my account from Facebook'));

            if (!$this->scoped->hasPassword()) {
                $this->elementStart('p', array('class' => 'form_guide'));

                $msg = sprintf(
                    // TRANS: Notice in disconnect from Facebook form if user has no local StatusNet password.
                    _m('Disconnecting your Faceboook would make it impossible to '.
                       'log in! Please [set a password](%s) first.'),
                    common_local_url('passwordsettings')
                );

                $this->raw(common_markup_to_html($msg));
                $this->elementEnd('p');
            } else {
                // @todo FIXME: i18n: This message is not being used.
                // TRANS: Message displayed when initiating disconnect of a StatusNet user
                // TRANS: from a Facebook account. %1$s is the StatusNet site name.
                $msg = sprintf(_m('Keep your %1$s account but disconnect from Facebook. ' .
                                  'You\'ll use your %1$s password to log in.'),
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
            // TRANS: Notice in case saving of synchronisation preferences fail.
            throw new ServerException(_m('There was a problem saving your sync preferences.'));
        }
        // TRANS: Confirmation that synchronisation settings have been saved into the system.
        return _m('Sync preferences saved.');
    }

    /*
     * Disconnect the user's Facebook account - deletes the Foreign_link
     * and shows the user a success message if all goes well.
     */
    function disconnect() {
        $result = $this->flink->delete();
        $this->flink = null;

        if ($result === false) {
            common_log_db_error($this->flink, 'DELETE', __FILE__);
            // TRANS: Server error displayed when deleting the link to a Facebook account fails.
            throw new ServerException(_m('Could not delete link to Facebook.'));
        }

        // TRANS: Confirmation message. GNU social account was unlinked from Facebook.
        return _m('You have disconnected this account from Facebook.');
    }
}
