<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for deleting a notice
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
 * @category  Personal
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

// @todo FIXME: documentation needed.
class DeletenoticeAction extends Action
{
    var $error        = null;
    var $user         = null;
    var $notice       = null;
    var $profile      = null;
    var $user_profile = null;

    function prepare($args)
    {
        parent::prepare($args);

        $this->user   = common_current_user();

        if (!$this->user) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            common_user_error(_('Not logged in.'));
            exit;
        }

        $notice_id    = $this->trimmed('notice');
        $this->notice = Notice::getKV($notice_id);

        if (!$this->notice) {
            // TRANS: Error message displayed trying to delete a non-existing notice.
            common_user_error(_('No such notice.'));
            exit;
        }

        $this->profile      = $this->notice->getProfile();
        $this->user_profile = $this->user->getProfile();

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if ($this->notice->profile_id != $this->user_profile->id &&
                   !$this->user->hasRight(Right::DELETEOTHERSNOTICE)) {
            // TRANS: Error message displayed trying to delete a notice that was not made by the current user.
            common_user_error(_('Cannot delete this notice.'));
            exit;
        }
        // XXX: Ajax!

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->deleteNotice();
        } else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $this->showForm();
        }
    }

    /**
     * Show the page notice
     *
     * Shows instructions for the page
     *
     * @return void
     */
    function showPageNotice()
    {
        $instr  = $this->getInstructions();
        $output = common_markup_to_html($instr);

        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }

    function getInstructions()
    {
        // TRANS: Instructions for deleting a notice.
        return _('You are about to permanently delete a notice. ' .
                 'Once this is done, it cannot be undone.');
    }

    function title()
    {
        // TRANS: Page title when deleting a notice.
        return _('Delete notice');
    }

    /**
     * Wrapper for showing a page
     *
     * Stores an error and shows the page
     *
     * @param string $error Error, if any
     *
     * @return void
     */
    function showForm($error = null)
    {
        $this->error = $error;
        $this->showPage();
    }

    /**
     * Insert delete notice form into the content
     *
     * @return void
     */
    function showContent()
    {
        $this->elementStart('form', array('id' => 'form_notice_delete',
                                          'class' => 'form_settings',
                                          'method' => 'post',
                                          'action' => common_local_url('deletenotice')));
        $this->elementStart('fieldset');
        // TRANS: Fieldset legend for the delete notice form.
        $this->element('legend', null, _('Delete notice'));
        $this->hidden('token', common_session_token());
        $this->hidden('notice', $this->trimmed('notice'));
        // TRANS: Message for the delete notice form.
        $this->element('p', null, _('Are you sure you want to delete this notice?'));
        $this->submit('form_action-no',
                      // TRANS: Button label on the delete notice form.
                      _m('BUTTON','No'),
                      'submit form_action-primary',
                      'no',
                      // TRANS: Submit button title for 'No' when deleting a notice.
                      _('Do not delete this notice.'));
        $this->submit('form_action-yes',
                      // TRANS: Button label on the delete notice form.
                      _m('BUTTON','Yes'),
                      'submit form_action-secondary',
                      'yes',
                      // TRANS: Submit button title for 'Yes' when deleting a notice.
                      _('Delete this notice.'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function deleteNotice()
    {
        // CSRF protection
        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->showForm(_('There was a problem with your session token. ' .
                              'Try again, please.'));
            return;
        }

        if ($this->arg('yes')) {
            if (Event::handle('StartDeleteOwnNotice', array($this->user, $this->notice))) {
                $this->notice->delete();
                Event::handle('EndDeleteOwnNotice', array($this->user, $this->notice));
            }
        }

        $url = common_get_returnto();

        if ($url) {
            common_set_returnto(null);
        } else {
            $url = common_local_url('public');
        }

        common_redirect($url, 303);
    }
}
