<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Upload an avatar
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}



define('MAX_ORIGINAL', 480);

/**
 * Upload an avatar
 *
 * We use jCrop plugin for jQuery to crop the image after upload.
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AvatarsettingsAction extends SettingsAction
{
    var $mode = null;
    var $imagefile = null;
    var $filename = null;

    /**
     * Title of the page
     *
     * @return string Title of the page
     */
    function title()
    {
        // TRANS: Title for avatar upload page.
        return _('Avatar');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: Instruction for avatar upload page.
        // TRANS: %s is the maximum file size, for example "500b", "10kB" or "2MB".
        return sprintf(_('You can upload your personal avatar. The maximum file size is %s.'),
                       ImageFile::maxFileSize());
    }

    /**
     * Content area of the page
     *
     * Shows a form for uploading an avatar.
     *
     * @return void
     */

    function showContent()
    {
        if ($this->mode == 'crop') {
            $this->showCropForm();
        } else {
            $this->showUploadForm();
        }
    }

    function showUploadForm()
    {
        $user = common_current_user();

        $profile = $user->getProfile();

        if (!$profile) {
            common_log_db_error($user, 'SELECT', __FILE__);
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->serverError(_('User has no profile.'));
            return;
        }

        $this->elementStart('form', array('enctype' => 'multipart/form-data',
                                          'method' => 'post',
                                          'id' => 'form_settings_avatar',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('avatarsettings')));
        $this->elementStart('fieldset');
        // TRANS: Avatar upload page form legend.
        $this->element('legend', null, _('Avatar settings'));
        $this->hidden('token', common_session_token());

        if (Event::handle('StartAvatarFormData', array($this))) {
            $this->elementStart('ul', 'form_data');
            try {
                $original = Avatar::getOriginal($profile);

                $this->elementStart('li', array('id' => 'avatar_original',
                                                'class' => 'avatar_view'));
                // TRANS: Header on avatar upload page for thumbnail of originally uploaded avatar (h2).
                $this->element('h2', null, _("Original"));
                $this->elementStart('div', array('id'=>'avatar_original_view'));
                $this->element('img', array('src' => $original->displayUrl(),
                                            'width' => $original->width,
                                            'height' => $original->height,
                                            'alt' => $user->nickname));
                $this->elementEnd('div');
                $this->elementEnd('li');
            } catch (NoResultException $e) {
                // No original avatar found!
            }

            try {
                $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
                $this->elementStart('li', array('id' => 'avatar_preview',
                                                'class' => 'avatar_view'));
                // TRANS: Header on avatar upload page for thumbnail of to be used rendition of uploaded avatar (h2).
                $this->element('h2', null, _("Preview"));
                $this->elementStart('div', array('id'=>'avatar_preview_view'));
                $this->element('img', array('src' => $avatar->displayUrl(),
                                            'width' => AVATAR_PROFILE_SIZE,
                                            'height' => AVATAR_PROFILE_SIZE,
                                            'alt' => $user->nickname));
                $this->elementEnd('div');
                if (!empty($avatar->filename)) {
                    // TRANS: Button on avatar upload page to delete current avatar.
                    $this->submit('delete', _m('BUTTON','Delete'));
                }
                $this->elementEnd('li');
            } catch (Exception $e) {
                // No previously uploaded avatar to preview.
            }

            $this->elementStart('li', array ('id' => 'settings_attach'));
            $this->element('input', array('name' => 'MAX_FILE_SIZE',
                                          'type' => 'hidden',
                                          'id' => 'MAX_FILE_SIZE',
                                          'value' => ImageFile::maxFileSizeInt()));
            $this->element('input', array('name' => 'avatarfile',
                                          'type' => 'file',
                                          'id' => 'avatarfile'));
            $this->elementEnd('li');
            $this->elementEnd('ul');

            $this->elementStart('ul', 'form_actions');
            $this->elementStart('li');
                // TRANS: Button on avatar upload page to upload an avatar.
            $this->submit('upload', _m('BUTTON','Upload'));
            $this->elementEnd('li');
            $this->elementEnd('ul');
        }
        Event::handle('EndAvatarFormData', array($this));

        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function showCropForm()
    {
        $user = common_current_user();

        $profile = $user->getProfile();

        if (!$profile) {
            common_log_db_error($user, 'SELECT', __FILE__);
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->serverError(_('User has no profile.'));
            return;
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_avatar',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('avatarsettings')));
        $this->elementStart('fieldset');
        // TRANS: Avatar upload page crop form legend.
        $this->element('legend', null, _('Avatar settings'));
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');

        $this->elementStart('li',
                            array('id' => 'avatar_original',
                                  'class' => 'avatar_view'));
        // TRANS: Header on avatar upload crop form for thumbnail of originally uploaded avatar (h2).
        $this->element('h2', null, _('Original'));
        $this->elementStart('div', array('id'=>'avatar_original_view'));
        $this->element('img', array('src' => Avatar::url($this->filedata['filename']),
                                    'width' => $this->filedata['width'],
                                    'height' => $this->filedata['height'],
                                    'alt' => $user->nickname));
        $this->elementEnd('div');
        $this->elementEnd('li');

        $this->elementStart('li',
                            array('id' => 'avatar_preview',
                                  'class' => 'avatar_view'));
        // TRANS: Header on avatar upload crop form for thumbnail of to be used rendition of uploaded avatar (h2).
        $this->element('h2', null, _('Preview'));
        $this->elementStart('div', array('id'=>'avatar_preview_view'));
        $this->element('img', array('src' => Avatar::url($this->filedata['filename']),
                                    'width' => AVATAR_PROFILE_SIZE,
                                    'height' => AVATAR_PROFILE_SIZE,
                                    'alt' => $user->nickname));
        $this->elementEnd('div');

        foreach (array('avatar_crop_x', 'avatar_crop_y',
                       'avatar_crop_w', 'avatar_crop_h') as $crop_info) {
            $this->element('input', array('name' => $crop_info,
                                          'type' => 'hidden',
                                          'id' => $crop_info));
        }

        // TRANS: Button on avatar upload crop form to confirm a selected crop as avatar.
        $this->submit('crop', _m('BUTTON','Crop'));

        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Handle a post
     *
     * We mux on the button name to figure out what the user actually wanted.
     *
     * @return void
     */
    function handlePost()
    {
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
            $this->showForm(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
            return;
        }

        // CSRF protection

        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->showForm(_('There was a problem with your session token. '.
                               'Try again, please.'));
            return;
        }

        if (Event::handle('StartAvatarSaveForm', array($this))) {
            if ($this->arg('upload')) {
                $this->uploadAvatar();
                } else if ($this->arg('crop')) {
                    $this->cropAvatar();
                } else if ($this->arg('delete')) {
                    $this->deleteAvatar();
                } else {
                    // TRANS: Unexpected validation error on avatar upload form.
                    $this->showForm(_('Unexpected form submission.'));
                }
            Event::handle('EndAvatarSaveForm', array($this));
        }
    }

    /**
     * Handle an image upload
     *
     * Does all the magic for handling an image upload, and crops the
     * image by default.
     *
     * @return void
     */
    function uploadAvatar()
    {
        try {
            $imagefile = ImageFile::fromUpload('avatarfile');
        } catch (Exception $e) {
            $this->showForm($e->getMessage());
            return;
        }
        if ($imagefile === null) {
            // TRANS: Validation error on avatar upload form when no file was uploaded.
            $this->showForm(_('No file uploaded.'));
            return;
        }

        $cur = common_current_user();
        $type = $imagefile->preferredType();
        $filename = Avatar::filename($cur->id,
                                     image_type_to_extension($type),
                                     null,
                                     'tmp'.common_timestamp());

        $filepath = Avatar::path($filename);
        $imagefile->copyTo($filepath);

        $filedata = array('filename' => $filename,
                          'filepath' => $filepath,
                          'width' => $imagefile->width,
                          'height' => $imagefile->height,
                          'type' => $type);

        $_SESSION['FILEDATA'] = $filedata;

        $this->filedata = $filedata;

        $this->mode = 'crop';

        // TRANS: Avatar upload form instruction after uploading a file.
        $this->showForm(_('Pick a square area of the image to be your avatar.'),
                        true);
    }

    /**
     * Handle the results of jcrop.
     *
     * @return void
     */
    public function cropAvatar()
    {
        $filedata = $_SESSION['FILEDATA'];

        if (!$filedata) {
            // TRANS: Server error displayed if an avatar upload went wrong somehow server side.
            $this->serverError(_('Lost our file data.'));
            return;
        }

        $file_d = ($filedata['width'] > $filedata['height'])
                     ? $filedata['height'] : $filedata['width'];

        $dest_x = $this->arg('avatar_crop_x') ? $this->arg('avatar_crop_x'):0;
        $dest_y = $this->arg('avatar_crop_y') ? $this->arg('avatar_crop_y'):0;
        $dest_w = $this->arg('avatar_crop_w') ? $this->arg('avatar_crop_w'):$file_d;
        $dest_h = $this->arg('avatar_crop_h') ? $this->arg('avatar_crop_h'):$file_d;
        $size = floor(min($dest_w, $dest_h, MAX_ORIGINAL));

        $user = common_current_user();
        $profile = $user->getProfile();

        $imagefile = new ImageFile($user->id, $filedata['filepath']);
        $filename = $imagefile->resize($size, $dest_x, $dest_y, $dest_w, $dest_h);

        if ($profile->setOriginal($filename)) {
            @unlink($filedata['filepath']);
            unset($_SESSION['FILEDATA']);
            $this->mode = 'upload';
            // TRANS: Success message for having updated a user avatar.
            $this->showForm(_('Avatar updated.'), true);
            common_broadcast_profile($profile);
        } else {
            // TRANS: Error displayed on the avatar upload page if the avatar could not be updated for an unknown reason.
            $this->showForm(_('Failed updating avatar.'));
        }
    }

    /**
     * Get rid of the current avatar.
     *
     * @return void
     */
    function deleteAvatar()
    {
        $user = common_current_user();
        $profile = $user->getProfile();

        Avatar::deleteFromProfile($profile);

        // TRANS: Success message for deleting a user avatar.
        $this->showForm(_('Avatar deleted.'), true);
    }

    /**
     * Add the jCrop stylesheet
     *
     * @return void
     */

    function showStylesheets()
    {
        parent::showStylesheets();
        $this->cssLink('js/extlib/jquery-jcrop/css/jcrop.css','base','screen, projection, tv');
    }

    /**
     * Add the jCrop scripts
     *
     * @return void
     */
    function showScripts()
    {
        parent::showScripts();

        if ($this->mode == 'crop') {
            $this->script('extlib/jquery-jcrop/jcrop.js');
            $this->script('jcrop.go.js');
        }

        $this->autofocus('avatarfile');
    }
}
