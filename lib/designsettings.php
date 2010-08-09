<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Change user password
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
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/accountsettingsaction.php';
require_once INSTALLDIR . '/lib/webcolor.php';

/**
 * Base class for setting a user or group design
 *
 * Shows the design setting form and also handles some things like saving
 * background images, and fetching a default design
 *
 * @category Settings
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class DesignSettingsAction extends AccountSettingsAction
{

    var $submitaction = null;

    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return _('Profile design');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _('Customize the way your profile looks ' .
        'with a background image and a colour palette of your choice.');
    }

    /**
     * Shows the design settings form
     *
     * @param Design $design a working design to show
     *
     * @return nothing
     */

    function showDesignForm($design)
    {
        $form = new DesignForm($this, $design, $this->selfUrl());
        $form->show();
    }

    /**
     * Handle a post
     *
     * Validate input and save changes. Reload the form with a success
     * or error message.
     *
     * @return void
     */

    function handlePost()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            // Workaround for PHP returning empty $_POST and $_FILES when POST
            // length > post_max_size in php.ini

            if (empty($_FILES)
                && empty($_POST)
                && ($_SERVER['CONTENT_LENGTH'] > 0)
            ) {
                $msg = _('The server was unable to handle that much POST ' .
                    'data (%s bytes) due to its current configuration.');

                $this->showForm(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
                return;
            }
        }

        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if ($this->arg('save')) {
            $this->saveDesign();
        } else if ($this->arg('defaults')) {
            $this->restoreDefaults();
        } else {
            $this->showForm(_('Unexpected form submission.'));
        }
    }

    /**
     * Add the Farbtastic stylesheet
     *
     * @return void
     */

    function showStylesheets()
    {
        parent::showStylesheets();
        $this->cssLink('js/farbtastic/farbtastic.css',null,'screen, projection, tv');
    }

    /**
     * Add the Farbtastic scripts
     *
     * @return void
     */

    function showScripts()
    {
        parent::showScripts();

        $this->script('farbtastic/farbtastic.js');
        $this->script('userdesign.go.js');

        $this->autofocus('design_background-image_file');
    }

    /**
     * Save the background image, if any, and set its disposition
     *
     * @param Design $design a working design to attach the img to
     *
     * @return nothing
     */

    function saveBackgroundImage($design)
    {

        // Now that we have a Design ID we can add a file to the design.
        // XXX: This is an additional DB hit, but figured having the image
        // associated with the Design rather than the User was worth
        // it. -- Zach

        if ($_FILES['design_background-image_file']['error'] ==
            UPLOAD_ERR_OK) {

            $filepath = null;

            try {
                $imagefile =
                    ImageFile::fromUpload('design_background-image_file');
            } catch (Exception $e) {
                $this->showForm($e->getMessage());
                return;
            }

            $filename = Design::filename($design->id,
                image_type_to_extension($imagefile->type),
                    common_timestamp());

            $filepath = Design::path($filename);

            move_uploaded_file($imagefile->filepath, $filepath);

            // delete any old backround img laying around

            if (isset($design->backgroundimage)) {
                @unlink(Design::path($design->backgroundimage));
            }

            $original = clone($design);

            $design->backgroundimage = $filename;

            // default to on, no tile

            $design->setDisposition(true, false, false);

            $result = $design->update($original);

            if ($result === false) {
                common_log_db_error($design, 'UPDATE', __FILE__);
                $this->showForm(_('Couldn\'t update your design.'));
                return;
            }
        }
    }

    /**
     * Restore the user or group design to system defaults
     *
     * @return nothing
     */

    function restoreDefaults()
    {
        $design = $this->getWorkingDesign();

        if (!empty($design)) {

            $result = $design->delete();

            if ($result === false) {
                common_log_db_error($design, 'DELETE', __FILE__);
                $this->showForm(_('Couldn\'t update your design.'));
                return;
            }
        }

        $this->showForm(_('Design defaults restored.'), true);
    }

}
