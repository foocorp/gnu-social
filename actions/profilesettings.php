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

if (!defined('GNUSOCIAL')) { exit(1); }

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
class ProfilesettingsAction extends SettingsAction
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
        $this->autofocus('fullname');
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
        $user = $this->scoped->getUser();

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
                         $this->trimmed('nickname') ?: $this->scoped->getNickname(),
                         // TRANS: Tooltip for field label in form for profile settings.
                         _('1-64 lowercase letters or numbers, no punctuation or spaces.'),
                         null, false,   // "name" (will be set to id), then "required"
                         !common_config('profile', 'changenick')
                                        ? array('disabled' => 'disabled', 'placeholder' => null)
                                        : array('placeholder' => null));
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label in form for profile settings.
            $this->input('fullname', _('Full name'),
                         $this->trimmed('fullname') ?: $this->scoped->getFullname(),
                         // TRANS: Instructions for full name text field on profile settings
                         _('A full name is required, if empty it will be set to your nickname.'),
                         null, true);
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label in form for profile settings.
            $this->input('homepage', _('Homepage'),
                         $this->trimmed('homepage') ?: $this->scoped->getHomepage(),
                         // TRANS: Tooltip for field label in form for profile settings.
                         _('URL of your homepage, blog, or profile on another site.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            $maxBio = Profile::maxBio();
            if ($maxBio > 0) {
                // TRANS: Tooltip for field label in form for profile settings. Plural
                // TRANS: is decided by the number of characters available for the
                // TRANS: biography (%d).
                $bioInstr = sprintf(_m('Describe yourself and your interests in %d character.',
                                       'Describe yourself and your interests in %d characters.',
                                       $maxBio),
                                    $maxBio);
            } else {
                // TRANS: Tooltip for field label in form for profile settings.
                $bioInstr = _('Describe yourself and your interests.');
            }
            // TRANS: Text area label in form for profile settings where users can provide
            // TRANS: their biography.
            $this->textarea('bio', _('Bio'),
                            $this->trimmed('bio') ?: $this->scoped->getDescription(),
                            $bioInstr);
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label in form for profile settings.
            $this->input('location', _('Location'),
                         $this->trimmed('location') ?: $this->scoped->location,
                         // TRANS: Tooltip for field label in form for profile settings.
                         _('Where you are, like "City, State (or Region), Country".'));
            $this->elementEnd('li');
            if (common_config('location', 'share') == 'user') {
                $this->elementStart('li');
                // TRANS: Checkbox label in form for profile settings.
                $this->checkbox('sharelocation', _('Share my current location when posting notices'),
                                ($this->arg('sharelocation')) ?
                                $this->boolean('sharelocation') : $this->scoped->shareLocation());
                $this->elementEnd('li');
            }
            Event::handle('EndProfileFormData', array($this));
            $this->elementStart('li');
            // TRANS: Field label in form for profile settings.
            $this->input('tags', _('Tags'),
                         $this->trimmed('tags') ?: implode(' ', Profile_tag::getSelfTagsArray($this->scoped)),
                         // TRANS: Tooltip for field label in form for profile settings.
                         _('Tags for yourself (letters, numbers, -, ., and _), comma- or space- separated.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            $language = common_language();
            // TRANS: Dropdownlist label in form for profile settings.
            $this->dropdown('language', _('Language'),
                         // TRANS: Tooltip for dropdown list label in form for profile settings.
                            get_nice_language_list(), _('Preferred language.'),
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
            $this->elementStart('li');
            $this->dropdown('subscribe_policy',
                            // TRANS: Dropdown field label on profile settings, for what policies to apply when someone else tries to subscribe to your updates.
                            _('Subscription policy'),
                            // TRANS: Dropdown field option for following policy.
                            array(User::SUBSCRIBE_POLICY_OPEN     => _('Let anyone follow me'),
                                  // TRANS: Dropdown field option for following policy.
                                  User::SUBSCRIBE_POLICY_MODERATE => _('Ask me first')),
                            // TRANS: Dropdown field title on group edit form.
                            _('Whether other users need your permission to follow your updates.'),
                            false,
                            (empty($user->subscribe_policy)) ? User::SUBSCRIBE_POLICY_OPEN : $user->subscribe_policy);
            $this->elementEnd('li');
        }
        if (common_config('profile', 'allowprivate') || $user->private_stream) {
            $this->elementStart('li');
            $this->checkbox('private_stream',
                            // TRANS: Checkbox label in profile settings.
                            _('Make updates visible only to my followers'),
                            ($this->arg('private_stream')) ?
                            $this->boolean('private_stream') : $user->private_stream);
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
    protected function doPost()
    {
        if (Event::handle('StartProfileSaveForm', array($this))) {

            // $nickname will only be set if this changenick value is true.
            if (common_config('profile', 'changenick') == true) {
                try {
                    $nickname = Nickname::normalize($this->trimmed('nickname'), true);
                } catch (NicknameTakenException $e) {
                    // Abort only if the nickname is occupied by _another_ local user profile
                    if (!$this->scoped->sameAs($e->profile)) {
                        throw $e;
                    }
                    // Since the variable wasn't set before the exception was thrown, let's run
                    // the normalize sequence again, but without in-use check this time.
                    $nickname = Nickname::normalize($this->trimmed('nickname'));
                }
            }

            $fullname = $this->trimmed('fullname');
            $homepage = $this->trimmed('homepage');
            $bio = $this->trimmed('bio');
            $location = $this->trimmed('location');
            $autosubscribe = $this->booleanintstring('autosubscribe');
            $subscribe_policy = $this->trimmed('subscribe_policy');
            $language = $this->trimmed('language');
            $timezone = $this->trimmed('timezone');
            $tagstring = $this->trimmed('tags');

            // Some validation
            if (!is_null($homepage) && (strlen($homepage) > 0) &&
                       !common_valid_http_url($homepage)) {
                // TRANS: Validation error in form for profile settings.
                throw new ClientException(_('Homepage is not a valid URL.'));
            } else if (!is_null($fullname) && mb_strlen($fullname) > 191) {
                // TRANS: Validation error in form for profile settings.
                throw new ClientException(_('Full name is too long (maximum 191 characters).'));
            } else if (Profile::bioTooLong($bio)) {
                // TRANS: Validation error in form for profile settings.
                // TRANS: Plural form is used based on the maximum number of allowed
                // TRANS: characters for the biography (%d).
                throw new ClientException(sprintf(_m('Bio is too long (maximum %d character).',
                                           'Bio is too long (maximum %d characters).',
                                           Profile::maxBio()),
                                        Profile::maxBio()));
            } else if (!is_null($location) && mb_strlen($location) > 191) {
                // TRANS: Validation error in form for profile settings.
                throw new ClientException(_('Location is too long (maximum 191 characters).'));
            }  else if (is_null($timezone) || !in_array($timezone, DateTimeZone::listIdentifiers())) {
                // TRANS: Validation error in form for profile settings.
                throw new ClientException(_('Timezone not selected.'));
            } else if (!is_null($language) && strlen($language) > 50) {
                // TRANS: Validation error in form for profile settings.
                throw new ClientException(_('Language is too long (maximum 50 characters).'));
            }

            $tags = array();
            $tag_priv = array();
            if (is_string($tagstring) && strlen($tagstring) > 0) {

                $tags = preg_split('/[\s,]+/', $tagstring);

                foreach ($tags as &$tag) {
                    $private = @$tag[0] === '.';

                    $tag = common_canonical_tag($tag);
                    if (!common_valid_profile_tag($tag)) {
                        // TRANS: Validation error in form for profile settings.
                        // TRANS: %s is an invalid tag.
                        throw new ClientException(sprintf(_('Invalid tag: "%s".'), $tag));
                    }

                    $tag_priv[$tag] = $private;
                }
            }

            $user = $this->scoped->getUser();
            $user->query('BEGIN');

            // Only allow setting private_stream if site policy allows it
            // (or user already _has_ a private stream, then you can unset it)
            if (common_config('profile', 'allowprivate') || $user->private_stream) {
                $private_stream = $this->booleanintstring('private_stream');
            } else {
                // if not allowed, we set to the existing value
                $private_stream = $user->private_stream;
            }

            // $user->nickname is updated through Profile->update();

            // XXX: XOR
            if (($user->autosubscribe ^ $autosubscribe)
                    || ($user->private_stream ^ $private_stream)
                    || $user->timezone != $timezone
                    || $user->language != $language
                    || $user->subscribe_policy != $subscribe_policy) {

                $original = clone($user);

                $user->autosubscribe    = $autosubscribe;
                $user->language         = $language;
                $user->private_stream   = $private_stream;
                $user->subscribe_policy = $subscribe_policy;
                $user->timezone         = $timezone;

                $result = $user->update($original);
                if ($result === false) {
                    common_log_db_error($user, 'UPDATE', __FILE__);
                    $user->query('ROLLBACK');
                    // TRANS: Server error thrown when user profile settings could not be updated to
                    // TRANS: automatically subscribe to any subscriber.
                    throw new ServerException(_('Could not update user for autosubscribe or subscribe_policy.'));
                }

                // Re-initialize language environment if it changed
                common_init_language();
            }

            $original = clone($this->scoped);

            if (common_config('profile', 'changenick') == true && $this->scoped->getNickname() !== $nickname) {
                assert(Nickname::normalize($nickname)===$nickname);
                common_debug("Changing user nickname from '{$this->scoped->getNickname()}' to '{$nickname}'.");
                $this->scoped->nickname = $nickname;
                $this->scoped->profileurl = common_profile_url($this->scoped->getNickname());
            }
            $this->scoped->fullname = (mb_strlen($fullname)>0 ? $fullname : $this->scoped->nickname);
            $this->scoped->homepage = $homepage;
            $this->scoped->bio = $bio;
            $this->scoped->location = $location;

            $loc = Location::fromName($location);

            if (empty($loc)) {
                $this->scoped->lat         = null;
                $this->scoped->lon         = null;
                $this->scoped->location_id = null;
                $this->scoped->location_ns = null;
            } else {
                $this->scoped->lat         = $loc->lat;
                $this->scoped->lon         = $loc->lon;
                $this->scoped->location_id = $loc->location_id;
                $this->scoped->location_ns = $loc->location_ns;
            }

            if (common_config('location', 'share') == 'user') {

                $exists = false;

                $prefs = User_location_prefs::getKV('user_id', $this->scoped->getID());

                if (empty($prefs)) {
                    $prefs = new User_location_prefs();

                    $prefs->user_id = $this->scoped->getID();
                    $prefs->created = common_sql_now();
                } else {
                    $exists = true;
                    $orig = clone($prefs);
                }

                $prefs->share_location = $this->booleanintstring('sharelocation');

                if ($exists) {
                    $result = $prefs->update($orig);
                } else {
                    $result = $prefs->insert();
                }

                if ($result === false) {
                    common_log_db_error($prefs, ($exists) ? 'UPDATE' : 'INSERT', __FILE__);
                    $user->query('ROLLBACK');
                    // TRANS: Server error thrown when user profile location preference settings could not be updated.
                    throw new ServerException(_('Could not save location prefs.'));
                }
            }

            common_debug('Old profile: ' . common_log_objstring($original), __FILE__);
            common_debug('New profile: ' . common_log_objstring($this->scoped), __FILE__);

            $result = $this->scoped->update($original);

            if ($result === false) {
                common_log_db_error($this->scoped, 'UPDATE', __FILE__);
                $user->query('ROLLBACK');
                // TRANS: Server error thrown when user profile settings could not be saved.
                throw new ServerException(_('Could not save profile.'));
            }

            // Set the user tags
            $result = Profile_tag::setSelfTags($this->scoped, $tags, $tag_priv);

            $user->query('COMMIT');
            Event::handle('EndProfileSaveForm', array($this));

            // TRANS: Confirmation shown when user profile settings are saved.
            return _('Settings saved.');

        }
    }

    function showAside() {
        $this->elementStart('div', array('id' => 'aside_primary',
                                         'class' => 'aside'));

        $this->elementStart('div', array('id' => 'account_actions',
                                         'class' => 'section'));
        $this->elementStart('ul');
        if (Event::handle('StartProfileSettingsActions', array($this))) {
            if ($this->scoped->hasRight(Right::BACKUPACCOUNT)) {
                $this->elementStart('li');
                $this->element('a',
                               array('href' => common_local_url('backupaccount')),
                               // TRANS: Option in profile settings to create a backup of the account of the currently logged in user.
                               _('Backup account'));
                $this->elementEnd('li');
            }
            if ($this->scoped->hasRight(Right::DELETEACCOUNT)) {
                $this->elementStart('li');
                $this->element('a',
                               array('href' => common_local_url('deleteaccount')),
                               // TRANS: Option in profile settings to delete the account of the currently logged in user.
                               _('Delete account'));
                $this->elementEnd('li');
            }
            if ($this->scoped->hasRight(Right::RESTOREACCOUNT)) {
                $this->elementStart('li');
                $this->element('a',
                               array('href' => common_local_url('restoreaccount')),
                               // TRANS: Option in profile settings to restore the account of the currently logged in user from a backup.
                               _('Restore account'));
                $this->elementEnd('li');
            }
            Event::handle('EndProfileSettingsActions', array($this));
        }
        $this->elementEnd('ul');
        $this->elementEnd('div');
        $this->elementEnd('div');
    }
}
