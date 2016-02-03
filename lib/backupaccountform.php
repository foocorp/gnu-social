<?php
/**
 * A form for backing up the account.
 *
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class BackupAccountForm extends Form
{
    /**
     * Class of the form.
     *
     * @return string the form's class
     */
    function formClass()
    {
        return 'form_profile_backup';
    }

    /**
     * URL the form posts to
     *
     * @return string the form's action URL
     */
    function action()
    {
        return common_local_url('backupaccount');
    }

    /**
     * Output form data
     *
     * Really, just instructions for doing a backup.
     *
     * @return void
     */
    function formData()
    {
        $msg =
            // TRANS: Information displayed on the backup account page.
            _('You can backup your account data in '.
              '<a href="http://activitystrea.ms/">Activity Streams</a> '.
              'format. This is an experimental feature and provides an '.
              'incomplete backup; private account '.
              'information like email and IM addresses is not backed up. '.
              'Additionally, uploaded files and direct messages are not '.
              'backed up.');
        $this->out->elementStart('p');
        $this->out->raw($msg);
        $this->out->elementEnd('p');
    }

    /**
     * Buttons for the form
     *
     * In this case, a single submit button
     *
     * @return void
     */
    function formActions()
    {
        $this->out->submit('submit',
                           // TRANS: Submit button to backup an account on the backup account page.
                           _m('BUTTON', 'Backup'),
                           'submit',
                           null,
                           // TRANS: Title for submit button to backup an account on the backup account page.
                           _('Backup your account.'));
    }
}
