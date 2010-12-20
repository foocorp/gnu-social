<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Settings for Twitter integration
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/connectsettingsaction.php';
require_once INSTALLDIR . '/plugins/TwitterBridge/twitter.php';

/**
 * Settings for Twitter integration
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      SettingsAction
 */
class TwittersettingsAction extends ConnectSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return _m('Twitter settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _m('Connect your Twitter account to share your updates ' .
                  'with your Twitter friends and vice-versa.');
    }

    /**
     * Content area of the page
     *
     * Shows a form for associating a Twitter account with this
     * StatusNet account. Also lets the user set preferences.
     *
     * @return void
     */
    function showContent()
    {

        $user = common_current_user();

        $profile = $user->getProfile();

        $fuser = null;

        $flink = Foreign_link::getByUserID($user->id, TWITTER_SERVICE);

        if (!empty($flink)) {
            $fuser = $flink->getForeignUser();
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_twitter',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('twittersettings')));

        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' => 'settings_twitter_account'));

        if (empty($fuser)) {
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li', array('id' => 'settings_twitter_login_button'));
            $this->element('a', array('href' => common_local_url('twitterauthorization')),
                           'Connect my Twitter account');
            $this->elementEnd('li');
            $this->elementEnd('ul');

            $this->elementEnd('fieldset');
        } else {
            $this->element('legend', null, _m('Twitter account'));
            $this->elementStart('p', array('id' => 'form_confirmed'));
            $this->element('a', array('href' => $fuser->uri), $fuser->nickname);
            $this->elementEnd('p');
            $this->element('p', 'form_note',
                           _m('Connected Twitter account'));
            $this->elementEnd('fieldset');

            $this->elementStart('fieldset');

            $this->element('legend', null, _m('Disconnect my account from Twitter'));

            if (!$user->password) {

                $this->elementStart('p', array('class' => 'form_guide'));
                // @todo FIXME: Bad i18n (patchwork in three parts).
                $this->text(_m('Disconnecting your Twitter ' .
                               'could make it impossible to log in! Please '));
                $this->element('a',
                    array('href' => common_local_url('passwordsettings')),
                        _m('set a password'));

                $this->text(_m(' first.'));
                $this->elementEnd('p');
            } else {
                // TRANS: %1$s is the current website name.
                $note = _m('Keep your %1$s account but disconnect from Twitter. ' .
                    'You can use your %1$s password to log in.');

                $site = common_config('site', 'name');

                $this->element('p', 'instructions',
                    sprintf($note, $site));

                $this->submit('disconnect', _m('Disconnect'));
            }

            $this->elementEnd('fieldset');

            $this->elementStart('fieldset', array('id' => 'settings_twitter_preferences'));

            $this->element('legend', null, _m('Preferences'));
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');
            $this->checkbox('noticesend',
                            _m('Automatically send my notices to Twitter.'),
                            ($flink) ?
                            ($flink->noticesync & FOREIGN_NOTICE_SEND) :
                            true);
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->checkbox('replysync',
                            _m('Send local "@" replies to Twitter.'),
                            ($flink) ?
                            ($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) :
                            true);
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->checkbox('friendsync',
                            _m('Subscribe to my Twitter friends here.'),
                            ($flink) ?
                            ($flink->friendsync & FOREIGN_FRIEND_RECV) :
                            false);
            $this->elementEnd('li');

            if (common_config('twitterimport','enabled')) {
                $this->elementStart('li');
                $this->checkbox('noticerecv',
                                _m('Import my friends timeline.'),
                                ($flink) ?
                                ($flink->noticesync & FOREIGN_NOTICE_RECV) :
                                false);
                $this->elementEnd('li');
            } else {
                // preserve setting even if bidrection bridge toggled off

                if ($flink && ($flink->noticesync & FOREIGN_NOTICE_RECV)) {
                    $this->hidden('noticerecv', true, 'noticerecv');
                }
            }

            $this->elementEnd('ul');

            if ($flink) {
                $this->submit('save', _m('Save'));
            } else {
                $this->submit('add', _m('Add'));
            }

            $this->elementEnd('fieldset');
        }

        $this->elementEnd('form');
    }

    /**
     * Handle posts to this form
     *
     * Based on the button that was pressed, muxes out to other functions
     * to do the actual task requested.
     *
     * All sub-functions reload the form with a message -- success or failure.
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

        if ($this->arg('save')) {
            $this->savePreferences();
        } else if ($this->arg('disconnect')) {
            $this->removeTwitterAccount();
        } else {
            $this->showForm(_m('Unexpected form submission.'));
        }
    }

    /**
     * Disassociate an existing Twitter account from this account
     *
     * @return void
     */
    function removeTwitterAccount()
    {
        $user = common_current_user();
        $flink = Foreign_link::getByUserID($user->id, TWITTER_SERVICE);

        if (empty($flink)) {
            $this->clientError(_m('No Twitter connection to remove.'));
            return;
        }

        $result = $flink->safeDelete();

        if (empty($result)) {
            common_log_db_error($flink, 'DELETE', __FILE__);
            $this->serverError(_m('Couldn\'t remove Twitter user.'));
            return;
        }

        $this->showForm(_m('Twitter account disconnected.'), true);
    }

    /**
     * Save user's Twitter-bridging preferences
     *
     * @return void
     */
    function savePreferences()
    {
        $noticesend = $this->boolean('noticesend');
        $noticerecv = $this->boolean('noticerecv');
        $friendsync = $this->boolean('friendsync');
        $replysync  = $this->boolean('replysync');

        $user = common_current_user();
        $flink = Foreign_link::getByUserID($user->id, TWITTER_SERVICE);

        if (empty($flink)) {
            common_log_db_error($flink, 'SELECT', __FILE__);
            $this->showForm(_m('Couldn\'t save Twitter preferences.'));
            return;
        }

        $original = clone($flink);
        $wasReceiving = (bool)($original->noticesync & FOREIGN_NOTICE_RECV);
        $flink->set_flags($noticesend, $noticerecv, $replysync, $friendsync);
        $result = $flink->update($original);

        if ($result === false) {
            common_log_db_error($flink, 'UPDATE', __FILE__);
            $this->showForm(_m('Couldn\'t save Twitter preferences.'));
            return;
        }

        if ($wasReceiving xor $noticerecv) {
            $this->notifyDaemon($flink->foreign_id, $noticerecv);
        }

        $this->showForm(_m('Twitter preferences saved.'), true);
    }

    /**
     * Tell the import daemon that we've updated a user's receive status.
     */
    function notifyDaemon($twitterUserId, $receiving)
    {
        // todo... should use control signals rather than queues
    }

}
