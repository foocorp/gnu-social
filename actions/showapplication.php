<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show an OAuth application
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
 * @category  Application
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Show an OAuth application
 *
 * @category Application
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ShowApplicationAction extends Action
{
    /**
     * Application to show
     */
    var $application = null;

    /**
     * User who owns the app
     */
    var $owner = null;

    var $msg = null;

    var $success = null;

    /**
     * Load attributes based on database arguments
     *
     * Loads all the DB stuff
     *
     * @param array $args $_REQUEST array
     *
     * @return success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        $id = (int)$this->arg('id');

        $this->application  = Oauth_application::getKV($id);
        $this->owner        = User::getKV($this->application->owner);

        if (!common_logged_in()) {
            // TRANS: Client error displayed trying to display an OAuth application while not logged in.
            $this->clientError(_('You must be logged in to view an application.'));
        }

        if (empty($this->application)) {
            // TRANS: Client error displayed trying to display a non-existing OAuth application.
            $this->clientError(_('No such application.'), 404);
        }

        $cur = common_current_user();

        if ($cur->id != $this->owner->id) {
            // TRANS: Client error displayed trying to display an OAuth application for which the logged in user is not the owner.
            $this->clientError(_('You are not the owner of this application.'), 401);
        }

        return true;
    }

    /**
     * Handle the request
     *
     * Shows info about the app
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            // CSRF protection
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                // TRANS: Client error displayed when the session token does not match or is not given.
                $this->clientError(_('There was a problem with your session token.'));
            }

            if ($this->arg('reset')) {
                $this->resetKey();
            }
        } else {
            $this->showPage();
        }
    }

    /**
     * Title of the page
     *
     * @return string title of the page
     */
    function title()
    {
        if (!empty($this->application->name)) {
            return 'Application: ' . $this->application->name;
        }
    }

    function showPageNotice()
    {
        if (!empty($this->msg)) {
            $this->element('div', ($this->success) ? 'success' : 'error', $this->msg);
        }
    }

    function showContent()
    {
        $cur = common_current_user();

        $consumer = $this->application->getConsumer();

        $this->elementStart('div', 'entity_profile vcard');
        // TRANS: Header on the OAuth application page.
        $this->element('h2', null, _('Application profile'));
        if (!empty($this->application->icon)) {
            $this->element('img', array('src' => $this->application->icon,
                                        'class' => 'photo logo entity_depiction'));
        }

        $this->element('a', array('href' =>  $this->application->source_url,
                                  'class' => 'url fn entity_fn'),
                            $this->application->name);

        $this->element('a', array('href' =>  $this->application->homepage,
                                  'class' => 'url entity_org'),
                            $this->application->organization);

        $this->element('div',
                       'note entity_note',
                       $this->application->description);

        $this->elementStart('div', 'entity_statistics');
        $defaultAccess = ($this->application->access_type & Oauth_application::$writeAccess)
            ? 'read-write' : 'read-only';
        $profile = Profile::getKV($this->application->owner);

        $appUsers = new Oauth_application_user();
        $appUsers->application_id = $this->application->id;
        $userCnt = $appUsers->count();

        $this->raw(sprintf(
            // TRANS: Information output on an OAuth application page.
            // TRANS: %1$s is the application creator, %2$s is "read-only" or "read-write",
            // TRANS: %3$d is the number of users using the OAuth application.
            _m('Created by %1$s - %2$s access by default - %3$d user',
               'Created by %1$s - %2$s access by default - %3$d users',
               $userCnt),
              $profile->getBestName(),
              $defaultAccess,
              $userCnt
            ));
        $this->elementEnd('div');

        $this->elementEnd('div');

        $this->elementStart('div', 'entity_actions');
        // TRANS: Header on the OAuth application page.
        $this->element('h2', null, _('Application actions'));
        $this->elementStart('ul');
        $this->elementStart('li', 'entity_edit');
        $this->element('a',
                       array('href' => common_local_url('editapplication',
                                                        array('id' => $this->application->id))),
                       // TRANS: Link text to edit application on the OAuth application page.
                       _m('EDITAPP','Edit'));
        $this->elementEnd('li');

        $this->elementStart('li', 'entity_reset_keysecret');
        $this->elementStart('form', array(
            'id' => 'form_reset_key',
            'class' => 'form_reset_key',
            'method' => 'POST',
            'action' => common_local_url('showapplication',
                                         array('id' => $this->application->id))));
        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());

        $this->element('input', array('type' => 'submit',
                                      'id' => 'reset',
                                      'name' => 'reset',
                                      'class' => 'submit',
                                      // TRANS: Button text on the OAuth application page.
                                      // TRANS: Resets the OAuth consumer key and secret.
                                      'value' => _('Reset key & secret'),
                                      'onClick' => 'return confirmReset()'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
        $this->elementEnd('li');

        $this->elementStart('li', 'entity_delete');
        $this->elementStart('form', array(
                                          'id' => 'form_delete_application',
                                          'class' => 'form_delete_application',
                                          'method' => 'POST',
                                          'action' => common_local_url('deleteapplication',
                                                                       array('id' => $this->application->id))));

        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        // TRANS: Submit button text the OAuth application page to delete an application.
        $this->submit('delete', _m('BUTTON','Delete'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
        $this->elementEnd('li');

        $this->elementEnd('ul');
        $this->elementEnd('div');

        $this->elementStart('div', 'entity_data');
        // TRANS: Header on the OAuth application page.
        $this->element('h2', null, _('Application info'));

        $this->elementStart('dl');
        // TRANS: Field label on application page.
        $this->element('dt', null, _('Consumer key'));
        $this->element('dd', null, $consumer->consumer_key);
        // TRANS: Field label on application page.
        $this->element('dt', null, _('Consumer secret'));
        $this->element('dd', null, $consumer->consumer_secret);
        // TRANS: Field label on application page.
        $this->element('dt', null, _('Request token URL'));
        $this->element('dd', null, common_local_url('ApiOAuthRequestToken'));
        // TRANS: Field label on application page.
        $this->element('dt', null, _('Access token URL'));
        $this->element('dd', null, common_local_url('ApiOAuthAccessToken'));
        // TRANS: Field label on application page.
        $this->element('dt', null, _('Authorize URL'));
        $this->element('dd', null, common_local_url('ApiOAuthAuthorize'));
        $this->elementEnd('dl');

        $this->element('p', 'note',
            // TRANS: Note on the OAuth application page about signature support.
            _('Note: HMAC-SHA1 signatures are supported. The plaintext signature method is not supported.'));
        $this->elementEnd('div');

        $this->elementStart('p', array('id' => 'application_action'));
        $this->element('a',
            array('href' => common_local_url('oauthappssettings'),
                  'class' => 'more'),
                  'View your applications');
        $this->elementEnd('p');
    }

    /**
     * Add a confirm script for Consumer key/secret reset
     *
     * @return void
     */
    function showScripts()
    {
        parent::showScripts();

        // TRANS: Text in confirmation dialog to reset consumer key and secret for an OAuth application.
        $msg = _('Are you sure you want to reset your consumer key and secret?');

        $js  = 'function confirmReset() { ';
        $js .= '    var agree = confirm("' . $msg . '"); ';
        $js .= '    return agree;';
        $js .= '}';

        $this->inlineScript($js);
    }

    /**
     * Reset an application's Consumer key and secret
     *
     * XXX: Should this be moved to its own page with a confirm?
     *
     */
    function resetKey()
    {
        $this->application->query('BEGIN');

        $oauser = new Oauth_application_user();
        $oauser->application_id = $this->application->id;
        $result = $oauser->delete();

        if ($result === false) {
            common_log_db_error($oauser, 'DELETE', __FILE__);
            $this->success = false;
            $this->msg = ('Unable to reset consumer key and secret.');
            $this->showPage();
            return;
        }

        $consumer = $this->application->getConsumer();
        $result = $consumer->delete();

        if ($result === false) {
            common_log_db_error($consumer, 'DELETE', __FILE__);
            $this->success = false;
            $this->msg = ('Unable to reset consumer key and secret.');
            $this->showPage();
            return;
        }

        $consumer = Consumer::generateNew();

        $result = $consumer->insert();

        if (empty($result)) {
            common_log_db_error($consumer, 'INSERT', __FILE__);
            $this->application->query('ROLLBACK');
            $this->success = false;
            $this->msg = ('Unable to reset consumer key and secret.');
            $this->showPage();
            return;
        }

        $orig = clone($this->application);
        $this->application->consumer_key = $consumer->consumer_key;
        $result = $this->application->update($orig);

        if ($result === false) {
            common_log_db_error($application, 'UPDATE', __FILE__);
            $this->application->query('ROLLBACK');
            $this->success = false;
            $this->msg = ('Unable to reset consumer key and secret.');
            $this->showPage();
            return;
        }

        $this->application->query('COMMIT');

        $this->success = true;
        $this->msg = ('Consumer key and secret reset.');
        $this->showPage();
    }
}
