<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Change profile settings
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
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/accountsettingsaction.php';

/**
 * Change profile settings
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ProfilesettingsAction extends AccountSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        // TRANS: Page title for profile settings.
        return _('Profile settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        // TRANS: Usage instructions for profile settings.
        return _('You can update your personal profile info here '.
                 'so people know more about you.');
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('nickname');
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
        $user = common_current_user();
        $profile = $user->getProfile();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_profile',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('profilesettings')));
        $this->elementStart('fieldset');
        // TRANS: Profile settings form legend.
        $this->element('legend', null, _('Profile information'));
        $this->hidden('token', common_session_token());

        // too much common patterns here... abstractable?
        $this->elementStart('ul', 'form_data');
        if (Event::handle('StartProfileFormData', array($this))) {
            $this->elementStart('li');
            // TRANS: Field label in form for profile settings.
            $this->input('nickname', _('Nickname'),
                         ($this->arg('nickname')) ? $this->arg('nickname') : $profile->nickname,
                         // TRANS: Tooltip for field label in form for profile settings.
                         _('1-64 lowercase letters or numbers, no punctuation or spaces.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label in form for profile settings.
            $this->input('fullname', _('Full name'),
                         ($this->arg('fullname')) ? $this->arg('fullname') : $profile->fullname);
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label in form for profile settings.
            $this->input('homepage', _('Homepage'),
                         ($this->arg('homepage')) ? $this->arg('homepage') : $profile->homepage,
                         // TRANS: Tooltip for field label in form for profile settings.
                         _('URL of your homepage, blog, or profile on another site.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            $maxBio = Profile::maxBio();
            if ($maxBio > 0) {
                // TRANS: Tooltip for field label in form for profile settings. Plural
                // TRANS: is decided by the number of characters available for the
                // TRANS: biography (%d).
                $bioInstr = sprintf(_m('Describe yourself and your interests in %d character',
                                       'Describe yourself and your interests in %d characters',
                                       $maxBio),
                                    $maxBio);
            } else {
                // TRANS: Tooltip for field label in form for profile settings.
                $bioInstr = _('Describe yourself and your interests');
            }
            // TRANS: Text area label in form for profile settings where users can provide.
            // TRANS: their biography.
            $this->textarea('bio', _('Bio'),
                            ($this->arg('bio')) ? $this->arg('bio') : $profile->bio,
                            $bioInstr);
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label in form for profile settings.
            $this->input('location', _('Location'),
                         ($this->arg('location')) ? $this->arg('location') : $profile->location,
                         // TRANS: Tooltip for field label in form for profile settings.
                         _('Where you are, like "City, State (or Region), Country"'));
            $this->elementEnd('li');
            if (common_config('location', 'share') == 'user') {
                $this->elementStart('li');
                // TRANS: Checkbox label in form for profile settings.
                $this->checkbox('sharelocation', _('Share my current location when posting notices'),
                                ($this->arg('sharelocation')) ?
                                $this->arg('sharelocation') : $user->shareLocation());
                $this->elementEnd('li');
            }
            Event::handle('EndProfileFormData', array($this));
            $this->elementStart('li');
            // TRANS: Field label in form for profile settings.
            $this->input('tags', _('Tags'),
                         ($this->arg('tags')) ? $this->arg('tags') : implode(' ', $user->getSelfTags()),
                         // TRANS: Tooltip for field label in form for profile settings.
                         _('Tags for yourself (letters, numbers, -, ., and _), comma- or space- separated'));
            $this->elementEnd('li');
            $this->elementStart('li');
            $language = common_language();
            // TRANS: Dropdownlist label in form for profile settings.
            $this->dropdown('language', _('Language'),
                         // TRANS: Tooltip for dropdown list label in form for profile settings.
                            get_nice_language_list(), _('Preferred language'),
                            false, $language);
            $this->elementEnd('li');
            $timezone = common_timezone();
            $timezones = array();
            foreach(DateTimeZone::listIdentifiers() as $k => $v) {
                $timezones[$v] = $v;
            }
            $this->elementStart('li');
            // TRANS: Dropdownlist label in form for profile settings.
            $this->dropdown('timezone', _('Timezone'),
                         // TRANS: Tooltip for dropdown list label in form for profile settings.
                            $timezones, _('What timezone are you normally in?'),
                            true, $timezone);
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->checkbox('autosubscribe',
                            // TRANS: Checkbox label in form for profile settings.
                            _('Automatically subscribe to whoever '.
                              'subscribes to me (best for non-humans)'),
                            ($this->arg('autosubscribe')) ?
                            $this->boolean('autosubscribe') : $user->autosubscribe);
            $this->elementEnd('li');
        }
        $this->elementEnd('ul');
        // TRANS: Button to save input in profile settings.
        $this->submit('save', _m('BUTTON','Save'));

        $this->elementEnd('fieldset');
        $this->elementEnd('form');
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
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if (Event::handle('StartProfileSaveForm', array($this))) {

            try {
                $nickname = Nickname::normalize($this->trimmed('nickname'));
            } catch (NicknameException $e) {
                $this->showForm($e->getMessage());
                return;
            }

            $fullname = $this->trimmed('fullname');
            $homepage = $this->trimmed('homepage');
            $bio = $this->trimmed('bio');
            $location = $this->trimmed('location');
            $autosubscribe = $this->boolean('autosubscribe');
            $language = $this->trimmed('language');
            $timezone = $this->trimmed('timezone');
            $tagstring = $this->trimmed('tags');

            // Some validation
            if (!User::allowed_nickname($nickname)) {
                // TRANS: Validation error in form for profile settings.
                $this->showForm(_('Not a valid nickname.'));
                return;
            } else if (!is_null($homepage) && (strlen($homepage) > 0) &&
                       !Validate::uri($homepage, array('allowed_schemes' => array('http', 'https')))) {
                // TRANS: Validation error in form for profile settings.
                $this->showForm(_('Homepage is not a valid URL.'));
                return;
            } else if (!is_null($fullname) && mb_strlen($fullname) > 255) {
                // TRANS: Validation error in form for profile settings.
                $this->showForm(_('Full name is too long (maximum 255 characters).'));
                return;
            } else if (Profile::bioTooLong($bio)) {
                // TRANS: Validation error in form for profile settings.
                // TRANS: Plural form is used based on the maximum number of allowed
                // TRANS: characters for the biography (%d).
                $this->showForm(sprintf(_m('Bio is too long (maximum %d character).',
                                           'Bio is too long (maximum %d characters).',
                                           Profile::maxBio()),
                                        Profile::maxBio()));
                return;
            } else if (!is_null($location) && mb_strlen($location) > 255) {
                // TRANS: Validation error in form for profile settings.
                $this->showForm(_('Location is too long (maximum 255 characters).'));
                return;
            }  else if (is_null($timezone) || !in_array($timezone, DateTimeZone::listIdentifiers())) {
                // TRANS: Validation error in form for profile settings.
                $this->showForm(_('Timezone not selected.'));
                return;
            } else if ($this->nicknameExists($nickname)) {
                // TRANS: Validation error in form for profile settings.
                $this->showForm(_('Nickname already in use. Try another one.'));
                return;
            } else if (!is_null($language) && strlen($language) > 50) {
                // TRANS: Validation error in form for profile settings.
                $this->showForm(_('Language is too long (maximum 50 characters).'));
                return;
            }

            if ($tagstring) {
                $tags = array_map('common_canonical_tag', preg_split('/[\s,]+/', $tagstring));
            } else {
                $tags = array();
            }

            foreach ($tags as $tag) {
                if (!common_valid_profile_tag($tag)) {
                    // TRANS: Validation error in form for profile settings.
                    // TRANS: %s is an invalid tag.
                    $this->showForm(sprintf(_('Invalid tag: "%s"'), $tag));
                    return;
                }
            }

            $user = common_current_user();

            $user->query('BEGIN');

            if ($user->nickname != $nickname ||
                $user->language != $language ||
                $user->timezone != $timezone) {

                common_debug('Updating user nickname from ' . $user->nickname . ' to ' . $nickname,
                             __FILE__);
                common_debug('Updating user language from ' . $user->language . ' to ' . $language,
                             __FILE__);
                common_debug('Updating user timezone from ' . $user->timezone . ' to ' . $timezone,
                             __FILE__);

                $original = clone($user);

                $user->nickname = $nickname;
                $user->language = $language;
                $user->timezone = $timezone;

                $result = $user->updateKeys($original);

                if ($result === false) {
                    common_log_db_error($user, 'UPDATE', __FILE__);
                    // TRANS: Server error thrown when user profile settings could not be updated.
                    $this->serverError(_('Couldn\'t update user.'));
                    return;
                } else {
                    // Re-initialize language environment if it changed
                    common_init_language();
                    // Clear the site owner, in case nickname changed
                    if ($user->hasRole(Profile_role::OWNER)) {
                        User::blow('user:site_owner');
                    }
                }
            }

            // XXX: XOR
            if ($user->autosubscribe ^ $autosubscribe) {

                $original = clone($user);

                $user->autosubscribe = $autosubscribe;

                $result = $user->update($original);

                if ($result === false) {
                    common_log_db_error($user, 'UPDATE', __FILE__);
                    // TRANS: Server error thrown when user profile settings could not be updated to
                    // TRANS: automatically subscribe to any subscriber.
                    $this->serverError(_('Couldn\'t update user for autosubscribe.'));
                    return;
                }
            }

            $profile = $user->getProfile();

            $orig_profile = clone($profile);

            $profile->nickname = $user->nickname;
            $profile->fullname = $fullname;
            $profile->homepage = $homepage;
            $profile->bio = $bio;
            $profile->location = $location;

            $loc = Location::fromName($location);

            if (empty($loc)) {
                $profile->lat         = null;
                $profile->lon         = null;
                $profile->location_id = null;
                $profile->location_ns = null;
            } else {
                $profile->lat         = $loc->lat;
                $profile->lon         = $loc->lon;
                $profile->location_id = $loc->location_id;
                $profile->location_ns = $loc->location_ns;
            }

            $profile->profileurl = common_profile_url($nickname);

            if (common_config('location', 'share') == 'user') {

                $exists = false;

                $prefs = User_location_prefs::staticGet('user_id', $user->id);

                if (empty($prefs)) {
                    $prefs = new User_location_prefs();

                    $prefs->user_id = $user->id;
                    $prefs->created = common_sql_now();
                } else {
                    $exists = true;
                    $orig = clone($prefs);
                }

                $prefs->share_location = $this->boolean('sharelocation');

                if ($exists) {
                    $result = $prefs->update($orig);
                } else {
                    $result = $prefs->insert();
                }

                if ($result === false) {
                    common_log_db_error($prefs, ($exists) ? 'UPDATE' : 'INSERT', __FILE__);
                    // TRANS: Server error thrown when user profile location preference settings could not be updated.
                    $this->serverError(_('Couldn\'t save location prefs.'));
                    return;
                }
            }

            common_debug('Old profile: ' . common_log_objstring($orig_profile), __FILE__);
            common_debug('New profile: ' . common_log_objstring($profile), __FILE__);

            $result = $profile->update($orig_profile);

            if ($result === false) {
                common_log_db_error($profile, 'UPDATE', __FILE__);
                // TRANS: Server error thrown when user profile settings could not be saved.
                $this->serverError(_('Couldn\'t save profile.'));
                return;
            }

            // Set the user tags
            $result = $user->setSelfTags($tags);

            if (!$result) {
                // TRANS: Server error thrown when user profile settings tags could not be saved.
                $this->serverError(_('Couldn\'t save tags.'));
                return;
            }

            $user->query('COMMIT');
            Event::handle('EndProfileSaveForm', array($this));
            common_broadcast_profile($profile);

            // TRANS: Confirmation shown when user profile settings are saved.
            $this->showForm(_('Settings saved.'), true);

        }
    }

    function nicknameExists($nickname)
    {
        $user = common_current_user();
        $other = User::staticGet('nickname', $nickname);
        if (!$other) {
            return false;
        } else {
            return $other->id != $user->id;
        }
    }
}
