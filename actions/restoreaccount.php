<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Restore a backup of your own account from the browser
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
 * Restore a backup of your own account from the browser
 *
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class RestoreaccountAction extends Action
{
    private $success = false;
    private $inprogress = false;

    /**
     * Returns the title of the page
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Page title for page where a user account can be restored from backup.
        return _('Restore account');
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
            // TRANS: Client exception displayed when trying to restore an account while not logged in.
            throw new ClientException(_('Only logged-in users can restore their account.'), 403);
        }

        if (!$cur->hasRight(Right::RESTOREACCOUNT)) {
            // TRANS: Client exception displayed when trying to restore an account without having restore rights.
            throw new ClientException(_('You may not restore your account.'), 403);
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
            $this->restoreAccount();
        } else {
            $this->showPage();
        }
        return;
    }

    /**
     * Queue a file for restoration
     *
     * Uses the UserActivityStream class; may take a long time!
     *
     * @return void
     */
    function restoreAccount()
    {
        $this->checkSessionToken();

        if (!isset($_FILES['restorefile']['error'])) {
            // TRANS: Client exception displayed trying to restore an account while something went wrong uploading a file.
            throw new ClientException(_('No uploaded file.'));
        }

        switch ($_FILES['restorefile']['error']) {
        case UPLOAD_ERR_OK: // success, jump out
            break;
        case UPLOAD_ERR_INI_SIZE:
            // TRANS: Client exception thrown when an uploaded file is larger than set in php.ini.
            throw new ClientException(_('The uploaded file exceeds the ' .
                'upload_max_filesize directive in php.ini.'));
            return;
        case UPLOAD_ERR_FORM_SIZE:
            throw new ClientException(
                // TRANS: Client exception.
                _('The uploaded file exceeds the MAX_FILE_SIZE directive' .
                ' that was specified in the HTML form.'));
            return;
        case UPLOAD_ERR_PARTIAL:
            @unlink($_FILES['restorefile']['tmp_name']);
            // TRANS: Client exception.
            throw new ClientException(_('The uploaded file was only' .
                ' partially uploaded.'));
            return;
        case UPLOAD_ERR_NO_FILE:
            // TRANS: Client exception. No file; probably just a non-AJAX submission.
            throw new ClientException(_('No uploaded file.'));
            return;
        case UPLOAD_ERR_NO_TMP_DIR:
            // TRANS: Client exception thrown when a temporary folder is not present to store a file upload.
            throw new ClientException(_('Missing a temporary folder.'));
            return;
        case UPLOAD_ERR_CANT_WRITE:
            // TRANS: Client exception thrown when writing to disk is not possible during a file upload operation.
            throw new ClientException(_('Failed to write file to disk.'));
            return;
        case UPLOAD_ERR_EXTENSION:
            // TRANS: Client exception thrown when a file upload operation has been stopped by an extension.
            throw new ClientException(_('File upload stopped by extension.'));
            return;
        default:
            common_log(LOG_ERR, __METHOD__ . ": Unknown upload error " .
                $_FILES['restorefile']['error']);
            // TRANS: Client exception thrown when a file upload operation has failed with an unknown reason.
            throw new ClientException(_('System error uploading file.'));
            return;
        }

        $filename = $_FILES['restorefile']['tmp_name'];

        try {
            if (!file_exists($filename)) {
                // TRANS: Server exception thrown when an expected file upload could not be found.
                throw new ServerException(_("No such file '$filename'."));
            }

            if (!is_file($filename)) {
                // TRANS: Server exception thrown when an expected file upload is not an actual file.
                throw new ServerException(_("Not a regular file: '$filename'."));
            }

            if (!is_readable($filename)) {
                // TRANS: Server exception thrown when an expected file upload could not be read.
                throw new ServerException(_("File '$filename' not readable."));
            }

            common_debug(sprintf("Getting backup from file '%s'.", $filename));

            $xml = file_get_contents($filename);

            // This check is costly but we should probably give
            // the user some info ahead of time.
            $doc = new DOMDocument();

            // Disable PHP warnings so we don't spew low-level XML errors to output...
            // would be nice if we can just get exceptions instead.
            $old_err = error_reporting();
            error_reporting($old_err & ~E_WARNING);
            $doc->loadXML($xml);
            error_reporting($old_err);

            $feed = $doc->documentElement;

            if (!$feed ||
                $feed->namespaceURI != Activity::ATOM ||
                $feed->localName != 'feed') {
                // TRANS: Client exception thrown when a feed is not an Atom feed.
                throw new ClientException(_("Not an Atom feed."));
            }

            // Enqueue for processing.

            $qm = QueueManager::get();
            $qm->enqueue(array(common_current_user(), $xml, false), 'feedimp');

            if ($qm instanceof UnQueueManager) {
                // No active queuing means we've actually just completed the job!
                $this->success = true;
            } else {
                // We've fed data into background queues, and it's probably still running.
                $this->inprogress = true;
            }
            $this->showPage();

        } catch (Exception $e) {
            // Delete the file and re-throw
            @unlink($_FILES['restorefile']['tmp_name']);
            throw $e;
        }
    }

    /**
     * Show a little form so that the person can upload a file to restore
     *
     * @return void
     */
    function showContent()
    {
        if ($this->success) {
            $this->element('p', null,
                           // TRANS: Success message when a feed has been restored.
                           _('Feed has been restored. Your old posts should now appear in search and your profile page.'));
        } else if ($this->inprogress) {
            $this->element('p', null,
                           // TRANS: Message when a feed restore is in progress.
                           _('Feed will be restored. Please wait a few minutes for results.'));
        } else {
            $form = new RestoreAccountForm($this);
            $form->show();
        }
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
        return false;
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
class RestoreAccountForm extends Form
{
    function __construct($out=null) {
        parent::__construct($out);
        $this->enctype = 'multipart/form-data';
    }

    /**
     * Class of the form.
     *
     * @return string the form's class
     */
    function formClass()
    {
        return 'form_profile_restore';
    }

    /**
     * URL the form posts to
     *
     * @return string the form's action URL
     */
    function action()
    {
        return common_local_url('restoreaccount');
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
        $this->out->elementStart('p', 'instructions');

        // TRANS: Form instructions for feed restore.
        $this->out->raw(_('You can upload a backed-up timeline in '.
                          '<a href="http://activitystrea.ms/">Activity Streams</a> format.'));

        $this->out->elementEnd('p');

        $this->out->elementStart('ul', 'form_data');

        $this->out->elementStart('li', array ('id' => 'settings_attach'));
        $this->out->element('input', array('name' => 'restorefile',
                                           'type' => 'file',
                                           'id' => 'restorefile'));
        $this->out->elementEnd('li');

        $this->out->elementEnd('ul');
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
                           // TRANS: Submit button to confirm upload of a user backup file for account restore.
                           _m('BUTTON', 'Upload'),
                           'submit',
                           null,
                           // TRANS: Title for submit button to confirm upload of a user backup file for account restore.
                           _('Upload the file'));
    }
}
