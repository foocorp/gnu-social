<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Settings for Jabber/XMPP integration
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

/**
 * Settings for Jabber/XMPP integration
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      SettingsAction
 */

class ImsettingsAction extends SettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */
    function title()
    {
        // TRANS: Title for Instant Messaging settings.
        return _('IM settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: Instant messaging settings page instructions.
        // TRANS: [instant messages] is link text, "(%%doc.im%%)" is the link.
        // TRANS: the order and formatting of link text and link should remain unchanged.
        return _('You can send and receive notices through '.
                 '[instant messaging](%%doc.im%%). '.
                 'Configure your addresses and settings below.');
    }

    /**
     * Content area of the page
     *
     * We make different sections of the form for the different kinds of
     * functions, and have submit buttons with different names. These
     * are muxed by handlePost() to see what the user really wants to do.
     *
     * @return void
     */
    function showContent()
    {
        $transports = array();
        Event::handle('GetImTransports', array(&$transports));
        if (! $transports) {
            $this->element('div', array('class' => 'error'),
                           // TRANS: Message given in the IM settings if IM is not enabled on the site.
                           _('IM is not available.'));
            return;
        }

        $user = common_current_user();

        $user_im_prefs_by_transport = array();
        
        foreach($transports as $transport=>$transport_info)
        {
            $this->elementStart('form', array('method' => 'post',
                                              'id' => 'form_settings_im',
                                              'class' => 'form_settings',
                                              'action' =>
                                              common_local_url('imsettings')));
            $this->elementStart('fieldset', array('id' => 'settings_im_address'));
            // TRANS: Form legend for IM settings form.
            $this->element('legend', null, $transport_info['display']);
            $this->hidden('token', common_session_token());
            $this->hidden('transport', $transport);

            if ($user_im_prefs = User_im_prefs::pkeyGet( array('transport' => $transport, 'user_id' => $user->id) )) {
                $user_im_prefs_by_transport[$transport] = $user_im_prefs;
                $this->element('p', 'form_confirmed', $user_im_prefs->screenname);
                $this->element('p', 'form_note',
                               // TRANS: Form note in IM settings form. %s is the type of IM address that was confirmed.
                               sprintf(_('Current confirmed %s address.'),$transport_info['display']));
                $this->hidden('screenname', $user_im_prefs->screenname);
                // TRANS: Button label to remove a confirmed IM address.
                $this->submit('remove', _m('BUTTON','Remove'));
            } else {
                try {
                    $confirm = $this->getConfirmation($transport);
                    $this->element('p', 'form_unconfirmed', $confirm->address);
                    // TRANS: Form note in IM settings form.
                    $this->element('p', 'form_note',
                                   // TRANS: Form note in IM settings form.
                                   // TRANS: %s is the IM service name, %2$s is the IM address set.
                                   sprintf(_('Awaiting confirmation on this address. '.
                                             'Check your %1$s account for a '.
                                             'message with further instructions. '.
                                             '(Did you add %2$s to your buddy list?)'),
                                             $transport_info['display'],
                                             $transport_info['daemonScreenname']));
                    $this->hidden('screenname', $confirm->address);
                    // TRANS: Button label to cancel an IM address confirmation procedure.
                    $this->submit('cancel', _m('BUTTON','Cancel'));
                } catch (NoResultException $e) {
                    $this->elementStart('ul', 'form_data');
                    $this->elementStart('li');
                    // TRANS: Field label for IM address.
                    $this->input('screenname', _('IM address'),
                                 ($this->arg('screenname')) ? $this->arg('screenname') : null,
                                 // TRANS: Field title for IM address. %s is the IM service name.
                                 sprintf(_('%s screenname.'),
                                         $transport_info['display']));
                    $this->elementEnd('li');
                    $this->elementEnd('ul');
                    // TRANS: Button label for adding an IM address in IM settings form.
                    $this->submit('add', _m('BUTTON','Add'));
                }
            }
            $this->elementEnd('fieldset');
            $this->elementEnd('form');
        }

        if($user_im_prefs_by_transport)
        {
            $this->elementStart('form', array('method' => 'post',
                                              'id' => 'form_settings_im',
                                              'class' => 'form_settings',
                                              'action' =>
                                              common_local_url('imsettings')));
            $this->elementStart('fieldset', array('id' => 'settings_im_preferences'));
            // TRANS: Header for IM preferences form.
            $this->element('legend', null, _('IM Preferences'));
            $this->hidden('token', common_session_token());
            $this->elementStart('table');
            $this->elementStart('tr');
            foreach($user_im_prefs_by_transport as $transport=>$user_im_prefs)
            {
                $this->element('th', null, $transports[$transport]['display']);
            }
            $this->elementEnd('tr');
            $preferences = array(
                // TRANS: Checkbox label in IM preferences form.
                array('name'=>'notify', 'description'=>_('Send me notices')),
                // TRANS: Checkbox label in IM preferences form.
                array('name'=>'updatefrompresence', 'description'=>_('Post a notice when my status changes.')),
                // TRANS: Checkbox label in IM preferences form.
                array('name'=>'replies', 'description'=>_('Send me replies '.
                              'from people I\'m not subscribed to.')),
            );
            foreach($preferences as $preference)
            {
                $this->elementStart('tr');
                foreach($user_im_prefs_by_transport as $transport=>$user_im_prefs)
                {
                    $preference_name = $preference['name'];
                    $this->elementStart('td');
                    $this->checkbox($transport . '_' . $preference['name'],
                                $preference['description'],
                                $user_im_prefs->$preference_name);
                    $this->elementEnd('td');
                }
                $this->elementEnd('tr');
            }
            $this->elementEnd('table');
            // TRANS: Button label to save IM preferences.
            $this->submit('save', _m('BUTTON','Save'));
            $this->elementEnd('fieldset');
            $this->elementEnd('form');
        }
    }

    /**
     * Get a confirmation code for this user
     *
     * @return Confirm_address address object for this user
     */
    function getConfirmation($transport)
    {
        $confirm = new Confirm_address();

        $confirm->user_id      = $this->scoped->getID();
        $confirm->address_type = $transport;

        if ($confirm->find(true)) {
            return $confirm;
        }

        throw new NoResultException($confirm);
    }

    protected function doPost()
    {
        if ($this->arg('save')) {
            return $this->savePreferences();
        } else if ($this->arg('add')) {
            return $this->addAddress();
        } else if ($this->arg('cancel')) {
            return $this->cancelConfirmation();
        } else if ($this->arg('remove')) {
            return $this->removeAddress();
        }
        // TRANS: Message given submitting a form with an unknown action in Instant Messaging settings.
        throw new ClientException(_('Unexpected form submission.'));
    }

    /**
     * Save user's XMPP preferences
     *
     * These are the checkboxes at the bottom of the page. They're used to
     * set different settings
     *
     * @return void
     */
    function savePreferences()
    {
        $user_im_prefs = new User_im_prefs();
        $user_im_prefs->query('BEGIN');
        $user_im_prefs->user_id = $this->scoped->getID();
        if($user_im_prefs->find() && $user_im_prefs->fetch())
        {
            $preferences = array('notify', 'updatefrompresence', 'replies');
            do
            {
                $original = clone($user_im_prefs);
                $new = clone($user_im_prefs);
                foreach($preferences as $preference)
                {
                    $new->$preference = $this->boolean($new->transport . '_' . $preference);
                }
                $result = $new->update($original);

                if ($result === false) {
                    common_log_db_error($user_im_prefs, 'UPDATE', __FILE__);
                    // TRANS: Server error thrown on database error updating IM preferences.
                    throw new ServerException(_('Could not update IM preferences.'));
                }
            }while($user_im_prefs->fetch());
        }
        $user_im_prefs->query('COMMIT');
        // TRANS: Confirmation message for successful IM preferences save.
        return _('Preferences saved.');
    }

    /**
     * Sends a confirmation to the address given
     *
     * Stores a confirmation record and sends out a
     * message with the confirmation info.
     *
     * @return void
     */
    function addAddress()
    {
        $screenname = $this->trimmed('screenname');
        $transport = $this->trimmed('transport');

        // Some validation

        if (empty($screenname)) {
            // TRANS: Message given saving IM address without having provided one.
            throw new ClientException(_('No screenname.'));
        }

        if (empty($transport)) {
            // TRANS: Form validation error when no transport is available setting an IM address.
            throw new ClientException(_('No transport.'));
        }

        Event::handle('NormalizeImScreenname', array($transport, &$screenname));

        if (empty($screenname)) {
            // TRANS: Message given saving IM address that cannot be normalised.
            throw new ClientException(_('Cannot normalize that screenname.'));
        }
        $valid = false;
        Event::handle('ValidateImScreenname', array($transport, $screenname, &$valid));
        if (!$valid) {
            // TRANS: Message given saving IM address that not valid.
            throw new ClientException(_('Not a valid screenname.'));
        } else if ($this->screennameExists($transport, $screenname)) {
            // TRANS: Message given saving IM address that is already set for another user.
            throw new ClientException(_('Screenname already belongs to another user.'));
        }

        $confirm = new Confirm_address();

        $confirm->address      = $screenname;
        $confirm->address_type = $transport;
        $confirm->user_id      = $this->scoped->getID();
        $confirm->code         = common_confirmation_code(64);
        $confirm->sent         = common_sql_now();
        $confirm->claimed      = common_sql_now();

        $result = $confirm->insert();

        if ($result === false) {
            common_log_db_error($confirm, 'INSERT', __FILE__);
            // TRANS: Server error thrown on database error adding Instant Messaging confirmation code.
            $this->serverError(_('Could not insert confirmation code.'));
        }

        Event::handle('SendImConfirmationCode', array($transport, $screenname, $confirm->code, $this->scoped));

        // TRANS: Message given saving valid IM address that is to be confirmed.
        return _('A confirmation code was sent to the IM address you added.');
    }

    /**
     * Cancel a confirmation
     *
     * If a confirmation exists, cancel it.
     *
     * @return void
     */
    function cancelConfirmation()
    {
        $screenname = $this->trimmed('screenname');
        $transport = $this->trimmed('transport');

        try {
            $confirm = $this->getConfirmation($transport);
            if ($confirm->address != $screenname) {
                // TRANS: Message given canceling IM address confirmation for the wrong IM address.
                throw new ClientException(_('That is the wrong IM address.'));
            }
        } catch (NoResultException $e) {
            // TRANS: Message given canceling Instant Messaging address confirmation that is not pending.
            throw new AlreadyFulfilledException(_('No pending confirmation to cancel.'));
        }

        $confirm->delete();

        // TRANS: Message given after successfully canceling IM address confirmation.
        return _('IM confirmation cancelled.');
    }

    /**
     * Remove an address
     *
     * If the user has a confirmed address, remove it.
     *
     * @return void
     */
    function removeAddress()
    {
        $screenname = $this->trimmed('screenname');
        $transport = $this->trimmed('transport');

        // Maybe an old tab open...?

        $user_im_prefs = new User_im_prefs();
        $user_im_prefs->user_id = $this->scoped->getID();
        $user_im_prefs->transport = $transport;
        if (!$user_im_prefs->find(true)) {
            // TRANS: Message given trying to remove an IM address that is not
            // TRANS: registered for the active user.
            throw new AlreadyFulfilledException(_('There were no preferences stored for this transport.'));
        }

        $result = $user_im_prefs->delete();

        if ($result === false) {
            common_log_db_error($user_im_prefs, 'UPDATE', __FILE__);
            // TRANS: Server error thrown on database error removing a registered IM address.
            throw new ServerException(_('Could not update user IM preferences.'));
        }

        // XXX: unsubscribe to the old address

        // TRANS: Message given after successfully removing a registered Instant Messaging address.
        return _('The IM address was removed.');
    }

    /**
     * Does this screenname exist?
     *
     * Checks if we already have another user with this address.
     *
     * @param string $transport Transport to check
     * @param string $screenname Screenname to check
     *
     * @return boolean whether the screenname exists
     */

    function screennameExists($transport, $screenname)
    {
        $user_im_prefs = new User_im_prefs();
        $user_im_prefs->transport = $transport;
        $user_im_prefs->screenname = $screenname;
        return $user_im_prefs->find(true) ? true : false;
    }
}
