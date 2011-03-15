<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Update the authenticating user's profile background image
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
 * @category  API
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';

/**
 * Update the authenticating user's profile background image
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiAccountUpdateProfileBackgroundImageAction extends ApiAuthAction
{
    var $tile = false;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->user  = $this->auth_user;
        $this->tile  = $this->arg('tile');

        return true;
    }

    /**
     * Handle the request
     *
     * Check whether the credentials are valid and output the result
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(
                // TRANS: Client error. POST is a HTTP command. It should not be translated.
                _('This method requires a POST.'),
                400, $this->format
            );
            return;
        }

        if (!in_array($this->format, array('xml', 'json'))) {
            $this->clientError(
                // TRANS: Client error displayed when trying to handle an unknown API method.
                _('API method not found.'),
                404,
                $this->format
            );
            return;
        }

        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
        ) {
             // TRANS: Client error displayed when the number of bytes in a POST request exceeds a limit.
             // TRANS: %s is the number of bytes of the CONTENT_LENGTH.
             $msg = _m('The server was unable to handle that much POST data (%s byte) due to its current configuration.',
                      'The server was unable to handle that much POST data (%s bytes) due to its current configuration.',
                      intval($_SERVER['CONTENT_LENGTH']));

            $this->clientError(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
            return;
        }

        if (empty($this->user)) {
            // TRANS: Client error when user not found updating a profile background image.
            $this->clientError(_('No such user.'), 404, $this->format);
            return;
        }

        $design = $this->user->getDesign();

        // XXX: This is kinda gross, but before we can add a background
        // img we have to make sure there's a Design because design ID
        // is part of the img filename.

        if (empty($design)) {
            $this->user->query('BEGIN');

            // save new design
            $design = new Design();
            $id = $design->insert();

            if (empty($id)) {
                common_log_db_error($id, 'INSERT', __FILE__);
                // TRANS: Client error displayed when saving design settings fails because of an empty id.
                $this->clientError(_('Unable to save your design settings.'));
                return;
            }

            $original              = clone($this->user);
            $this->user->design_id = $id;
            $result                = $this->user->update($original);

            if (empty($result)) {
                common_log_db_error($original, 'UPDATE', __FILE__);
                // TRANS: Client error displayed when saving design settings fails because of an empty result.
                $this->clientError(_('Unable to save your design settings.'));
                $this->user->query('ROLLBACK');
                return;
            }

            $this->user->query('COMMIT');
        }

        // Okay, now get the image and add it to the design

        try {
            $imagefile = ImageFile::fromUpload('image');
        } catch (Exception $e) {
            $this->clientError($e->getMessage(), 400, $this->format);
            return;
        }

        $filename = Design::filename(
            $design->id,
            image_type_to_extension($imagefile->type),
            common_timestamp()
        );

        $filepath = Design::path($filename);

        move_uploaded_file($imagefile->filepath, $filepath);

        // delete any old backround img laying around

        if (isset($design->backgroundimage)) {
            @unlink(Design::path($design->backgroundimage));
        }

        $original = clone($design);
        $design->backgroundimage = $filename;
        $design->setDisposition(true, false, ($this->tile == 'true'));

        $result = $design->update($original);

        if ($result === false) {
            common_log_db_error($design, 'UPDATE', __FILE__);
            // TRANS: Error displayed when updating design settings fails.
            $this->showForm(_('Could not update your design.'));
            return;
        }

        $profile = $this->user->getProfile();

        if (empty($profile)) {
            // TRANS: Client error displayed when a user has no profile.
            $this->clientError(_('User has no profile.'));
            return;
        }

        $twitter_user = $this->twitterUserArray($profile, true);

        if ($this->format == 'xml') {
            $this->initDocument('xml');
            $this->showTwitterXmlUser($twitter_user, 'user', true);
            $this->endDocument('xml');
        } elseif ($this->format == 'json') {
            $this->initDocument('json');
            $this->showJsonObjects($twitter_user);
            $this->endDocument('json');
        }
    }
}
