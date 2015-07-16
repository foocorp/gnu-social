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

if (!defined('GNUSOCIAL')) { exit(1); }

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
     * Shows a form for uploading an avatar. Currently overrides FormAction's showContent
     * since we haven't made classes out of AvatarCropForm and AvatarUploadForm.
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
                $original = Avatar::getUploaded($profile);

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
            } catch (NoAvatarException $e) {
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
            } catch (NoAvatarException $e) {
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

    protected function doPost()
    {
        if (Event::handle('StartAvatarSaveForm', array($this))) {
            if ($this->trimmed('upload')) {
                return $this->uploadAvatar();
            } else if ($this->trimmed('crop')) {
                return $this->cropAvatar();
            } else if ($this->trimmed('delete')) {
                return $this->deleteAvatar();
            } else {
                // TRANS: Unexpected validation error on avatar upload form.
                throw new ClientException(_('Unexpected form submission.'));
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
        // ImageFile throws exception if something goes wrong, which we'll
        // pick up and show as an error message above the form.
        $imagefile = ImageFile::fromUpload('avatarfile');

        $type = $imagefile->preferredType();
        $filename = Avatar::filename($this->scoped->getID(),
                                     image_type_to_extension($type),
                                     null,
                                     'tmp'.common_timestamp());

        $filepath = Avatar::path($filename);
        $imagefile = $imagefile->copyTo($filepath);

        $filedata = array('filename' => $filename,
                          'filepath' => $filepath,
                          'width' => $imagefile->width,
                          'height' => $imagefile->height,
                          'type' => $type);

        $_SESSION['FILEDATA'] = $filedata;

        $this->filedata = $filedata;

        $this->mode = 'crop';

        // TRANS: Avatar upload form instruction after uploading a file.
        return _('Pick a square area of the image to be your avatar.');
    }

    /**
     * Handle the results of jcrop.
     *
     * @return void
     */
    public function cropAvatar()
    {
        $filedata = $_SESSION['FILEDATA'];

        if (empty($filedata)) {
            // TRANS: Server error displayed if an avatar upload went wrong somehow server side.
            throw new ServerException(_('Lost our file data.'));
        }

        $file_d = min($filedata['width'],  $filedata['height']);

        $dest_x = $this->arg('avatar_crop_x') ? $this->arg('avatar_crop_x'):0;
        $dest_y = $this->arg('avatar_crop_y') ? $this->arg('avatar_crop_y'):0;
        $dest_w = $this->arg('avatar_crop_w') ? $this->arg('avatar_crop_w'):$file_d;
        $dest_h = $this->arg('avatar_crop_h') ? $this->arg('avatar_crop_h'):$file_d;
        $size = intval(min($dest_w, $dest_h, common_config('avatar', 'maxsize')));

        $box = array('width' => $size, 'height' => $size,
                     'x' => $dest_x,   'y' => $dest_y,
                     'w' => $dest_w,   'h' => $dest_h);

        $imagefile = new ImageFile(null, $filedata['filepath']);
        $filename = Avatar::filename($this->scoped->getID(), image_type_to_extension($imagefile->preferredType()),
                                     $size, common_timestamp());
        try {
            $imagefile->resizeTo(Avatar::path($filename), $box);
        } catch (UseFileAsThumbnailException $e) {
            common_debug('Using uploaded avatar directly without resizing, copying it to: '.$filename);
            if (!copy($filedata['filepath'], Avatar::path($filename))) {
                common_debug('Tried to copy image file '.$filedata['filepath'].' to destination '.Avatar::path($filename));
                throw new ServerException('Could not copy file to destination.');
            }
        }

        if ($this->scoped->setOriginal($filename)) {
            @unlink($filedata['filepath']);
            unset($_SESSION['FILEDATA']);
            $this->mode = 'upload';
            // TRANS: Success message for having updated a user avatar.
            return _('Avatar updated.');
        }

        // TRANS: Error displayed on the avatar upload page if the avatar could not be updated for an unknown reason.
        throw new ServerException(_('Failed updating avatar.'));
    }

    /**
     * Get rid of the current avatar.
     *
     * @return void
     */
    function deleteAvatar()
    {
        Avatar::deleteFromProfile($this->scoped);

        // TRANS: Success message for deleting a user avatar.
        return _('Avatar deleted.');
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
