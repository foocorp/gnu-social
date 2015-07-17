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

if (!defined('GNUSOCIAL')) { exit(1); }

require_once dirname(__DIR__) . '/twitter.php';

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
class TwittersettingsAction extends ProfileSettingsAction
{
    protected $flink = null;
    protected $fuser = null;

    protected function doPreparation()
    {
        try {
            $this->flink = Foreign_link::getByUserID($this->scoped->getID(), TWITTER_SERVICE);
            $this->fuser = $this->flink->getForeignUser();
        } catch (NoResultException $e) {
            // No foreign link found for this user!
        }
    }
    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        // TRANS: Title for page with Twitter integration settings.
        return _m('Twitter settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        // TRANS: Instructions for page with Twitter integration settings.
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
        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_twitter',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('twittersettings')));

        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' => 'settings_twitter_account'));

        if ($this->fuser instanceof Foreign_user) {
            // TRANS: Fieldset legend.
            $this->element('legend', null, _m('Twitter account'));
            $this->elementStart('p', array('id' => 'form_confirmed'));
            $this->element('a', array('href' => $this->fuser->uri), $this->fuser->nickname);
            $this->elementEnd('p');
            $this->element('p', 'form_note',
                           // TRANS: Form note when a Twitter account has been connected.
                           _m('Connected Twitter account'));
            $this->elementEnd('fieldset');

            $this->elementStart('fieldset');

            // TRANS: Fieldset legend.
            $this->element('legend', null, _m('Disconnect my account from Twitter'));

            if (!$this->scoped->hasPassword()) {
                $this->elementStart('p', array('class' => 'form_guide'));
                // TRANS: Form guide. %s is a URL to the password settings.
                // TRANS: This message contains a Markdown link in the form [description](link).
                $message = sprintf(_m('Disconnecting your Twitter account ' .
                                      'could make it impossible to log in! Please ' .
                                      '[set a password](%s) first.'),
                                   common_local_url('passwordsettings'));
                $message = common_markup_to_html($message);
                $this->text($message);
                $this->elementEnd('p');
            } else {
                // TRANS: Form instructions. %1$s is the StatusNet sitename.
                $note = _m('Keep your %1$s account but disconnect from Twitter. ' .
                    'You can use your %1$s password to log in.');
                $site = common_config('site', 'name');

                $this->element('p', 'instructions',
                    sprintf($note, $site));

                // TRANS: Button text for disconnecting a Twitter account.
                $this->submit('disconnect', _m('BUTTON','Disconnect'));
            }

            $this->elementEnd('fieldset');

            $this->elementStart('fieldset', array('id' => 'settings_twitter_preferences'));

            // TRANS: Fieldset legend.
            $this->element('legend', null, _m('Preferences'));
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');
            $this->checkbox('noticesend',
                            // TRANS: Checkbox label.
                            _m('Automatically send my notices to Twitter.'),
                            $this->flink->noticesync & FOREIGN_NOTICE_SEND);
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->checkbox('replysync',
                            // TRANS: Checkbox label.
                            _m('Send local "@" replies to Twitter.'),
                            $this->flink->noticesync & FOREIGN_NOTICE_SEND_REPLY);
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->checkbox('friendsync',
                            // TRANS: Checkbox label.
                            _m('Subscribe to my Twitter friends here.'),
                            $this->flink->friendsync & FOREIGN_FRIEND_RECV);
            $this->elementEnd('li');

            if (common_config('twitterimport','enabled')) {
                $this->elementStart('li');
                $this->checkbox('noticerecv',
                                // TRANS: Checkbox label.
                                _m('Import my friends timeline.'),
                                $this->flink->noticesync & FOREIGN_NOTICE_RECV);
                $this->elementEnd('li');
            } else {
                // preserve setting even if bidrection bridge toggled off

                if ($this->flink->noticesync & FOREIGN_NOTICE_RECV) {
                    $this->hidden('noticerecv', true, 'noticerecv');
                }
            }

            $this->elementEnd('ul');

            if ($this->flink) {
                // TRANS: Button text for saving Twitter integration settings.
                $this->submit('save', _m('BUTTON','Save'));
            } else {
                // TRANS: Button text for adding Twitter integration.
                $this->submit('add', _m('BUTTON','Add'));
            }
        } else {
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li', array('id' => 'settings_twitter_login_button'));
            $this->element('a', array('href' => common_local_url('twitterauthorization')),
                           // TRANS: Link description to connect to a Twitter account.
                           'Connect my Twitter account');
            $this->elementEnd('li');
            $this->elementEnd('ul');
        }

        $this->elementEnd('fieldset');

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
    protected function doPost()
    {
        if ($this->arg('save')) {
            return $this->savePreferences();
        } else if ($this->arg('disconnect')) {
            return $this->removeTwitterAccount();
        }
        // TRANS: Client error displayed when the submitted form contains unexpected data.
        throw new ClientException(_m('Unexpected form submission.'));
    }

    /**
     * Disassociate an existing Twitter account from this account
     *
     * @return void
     */
    protected function removeTwitterAccount()
    {
        if (!$this->flink instanceof Foreign_link) {
            // TRANS: Error message possibly displayed when trying to remove a connected Twitter account when there isn't one connected.
            throw new AlreadyFulfilledException(_m('No Twitter connection to remove.'));
        }

        $result = $this->flink->safeDelete();

        if ($result === false) {
            common_log_db_error($this->flink, 'DELETE', __FILE__);
            // TRANS: Server error displayed when trying to remove a connected Twitter account fails.
            throw new ServerException(_m('Could not remove Twitter user.'));
        }

        $this->flink = null;
        $this->fuser = null;

        // TRANS: Success message displayed after disconnecting a Twitter account.
        return _m('Twitter account disconnected.');
    }

    /**
     * Save user's Twitter-bridging preferences
     *
     * @return void
     */
    protected function savePreferences()
    {
        $noticesend = $this->boolean('noticesend');
        $noticerecv = $this->boolean('noticerecv');
        $friendsync = $this->boolean('friendsync');
        $replysync  = $this->boolean('replysync');

        if (!$this->flink instanceof Foreign_link) {
            common_log_db_error($this->flink, 'SELECT', __FILE__);
            // TRANS: Server error displayed when saving Twitter integration preferences fails.
            throw new ServerException(_m('Your account is not linked to Twitter.'));
        }

        $original = clone($this->flink);
        $wasReceiving = (bool)($original->noticesync & FOREIGN_NOTICE_RECV);
        $this->flink->set_flags($noticesend, $noticerecv, $replysync, $friendsync);
        $result = $this->flink->update($original);

        if ($result === false) {
            common_log_db_error($this->flink, 'UPDATE', __FILE__);
            // TRANS: Server error displayed when saving Twitter integration preferences fails.
            throw new ServerException(_m('Could not save Twitter preferences.'));
        }

        if ($wasReceiving xor $noticerecv) {
            $this->notifyDaemon($this->flink->foreign_id, $noticerecv);
        }

        // TRANS: Success message after saving Twitter integration preferences.
        return _m('Twitter preferences saved.');
    }

    /**
     * Tell the import daemon that we've updated a user's receive status.
     */
    function notifyDaemon($twitterUserId, $receiving)
    {
        // @todo... should use control signals rather than queues
    }
}
