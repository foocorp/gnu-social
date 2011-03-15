<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Download a backup of your own account to the browser
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
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
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Download a backup of your own account to the browser
 *
 * We go through some hoops to make this only respond to POST, since
 * it's kind of expensive and there's probably some downside to having
 * your account in all kinds of search engines.
 *
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class BackupaccountAction extends Action
{
    /**
     * Returns the title of the page
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Title for backup account page.
        return _('Backup account');
    }

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        parent::prepare($argarray);

        $cur = common_current_user();

        if (empty($cur)) {
            // TRANS: Client exception thrown when trying to backup an account while not logged in.
            throw new ClientException(_('Only logged-in users can backup their account.'), 403);
        }

        if (!$cur->hasRight(Right::BACKUPACCOUNT)) {
            // TRANS: Client exception thrown when trying to backup an account without having backup rights.
            throw new ClientException(_('You may not backup your account.'), 403);
        }

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */
    function handle($argarray=null)
    {
        parent::handle($argarray);

        if ($this->isPost()) {
            $this->sendFeed();
        } else {
            $this->showPage();
        }
        return;
    }

    /**
     * Send a feed of the user's activities to the browser
     *
     * Uses the UserActivityStream class; may take a long time!
     *
     * @return void
     */

    function sendFeed()
    {
        $cur = common_current_user();

        $stream = new UserActivityStream($cur, true, UserActivityStream::OUTPUT_RAW);

        header('Content-Disposition: attachment; filename='.$cur->nickname.'.atom');
        header('Content-Type: application/atom+xml; charset=utf-8');

        // @fixme atom feed logic is in getString...
        // but we just want it to output to the outputter.
        $this->raw($stream->getString());
    }

    /**
     * Show a little form so that the person can request a backup.
     *
     * @return void
     */

    function showContent()
    {
        $form = new BackupAccountForm($this);
        $form->show();
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Return last modified, if applicable.
     *
     * MAY override
     *
     * @return string last modified http header
     */
    function lastModified()
    {
        // For comparison with If-Last-Modified
        // If not applicable, return null
        return null;
    }

    /**
     * Return etag, if applicable.
     *
     * MAY override
     *
     * @return string etag http header
     */
    function etag()
    {
        return null;
    }
}

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
