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
 * @copyright 2008-2009 StatusNet, Inc.
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
class ShowApplicationAction extends OwnerDesignAction
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

        $this->application  = Oauth_application::staticGet($id);
        $this->owner        = User::staticGet($this->application->owner);

        if (!common_logged_in()) {
            $this->clientError(_('You must be logged in to view an application.'));
            return false;
        }

        if (empty($this->application)) {
            $this->clientError(_('No such application.'), 404);
            return false;
        }

        $cur = common_current_user();

        if ($cur->id != $this->owner->id) {
            $this->clientError(_('You are not the owner of this application.'), 401);
            return false;
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
                $this->clientError(_('There was a problem with your session token.'));
                return;
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
        $profile = Profile::staticGet($this->application->owner);

        $appUsers = new Oauth_application_user();
        $appUsers->application_id = $this->application->id;
        $userCnt = $appUsers->count();

        $this->raw(sprintf(
            _('Created by %1$s - %2$s access by default - %3$d users'),
              $profile->getBestName(),
              $defaultAccess,
              $userCnt
            ));
        $this->elementEnd('div');

        $this->elementEnd('div');

        $this->elementStart('div', 'entity_actions');
        $this->element('h2', null, _('Application actions'));
        $this->elementStart('ul');
        $this->elementStart('li', 'entity_edit');
        $this->element('a',
                       array('href' => common_local_url('editapplication',
                                                        array('id' => $this->application->id))),
                       'Edit');
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
        $this->submit('delete', _('Delete'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
        $this->elementEnd('li');

        $this->elementEnd('ul');
        $this->elementEnd('div');

        $this->elementStart('div', 'entity_data');
        $this->element('h2', null, _('Application info'));
        $this->element('div',
                       'entity_consumer_key',
                       $consumer->consumer_key);

        $this->element('div',
                       'entity_consumer_secret',
                       $consumer->consumer_secret);

        $this->element('div',
                       'entity_request_token_url',
                       common_local_url('ApiOauthRequestToken'));

        $this->element('div', 'entity_access_token_url', common_local_url('ApiOauthAccessToken'));

        $this->element('div', 'entity_authorize_url', common_local_url('ApiOauthAuthorize'));

        $this->element('p', 'note',
            _('Note: We support HMAC-SHA1 signatures. We do not support the plaintext signature method.'));
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
