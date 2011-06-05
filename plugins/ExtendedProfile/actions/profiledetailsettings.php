<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
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
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class ProfileDetailSettingsAction extends ProfileSettingsAction
{
    function title()
    {
        // TRANS: Title for extended profile settings.
        return _m('Extended profile settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: Usage instructions for profile settings.
        return _m('You can update your personal profile info here '.
                 'so people know more about you.');
    }

    function showStylesheets() {
        parent::showStylesheets();
        $this->cssLink('plugins/ExtendedProfile/css/profiledetail.css');
        return true;
    }

    function  showScripts() {
        parent::showScripts();
        $this->script('plugins/ExtendedProfile/js/profiledetail.js');
        return true;
    }

    function handlePost()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(
                // TRANS: Client error displayed when the session token does not match or is not given.
                _m('There was a problem with your session token. '
                    .   'Try again, please.'
                  )
            );
            return;
        }

        if ($this->arg('save')) {
            $this->saveDetails();
        } else {
            // TRANS: Message given submitting a form with an unknown action.
            $this->showForm(_m('Unexpected form submission.'));
        }
    }

    function showContent()
    {
        $cur = common_current_user();
        $profile = $cur->getProfile();

        $widget = new ExtendedProfileWidget(
            $this,
            $profile,
            ExtendedProfileWidget::EDITABLE
        );
        $widget->show();
    }

    function saveDetails()
    {
        common_debug(var_export($_POST, true));

        $user = common_current_user();

        try {
            $this->saveStandardProfileDetails($user);

            $profile = $user->getProfile();

            $simpleFieldNames = array('title', 'spouse', 'kids', 'manager');
            $dateFieldNames   = array('birthday');

            foreach ($simpleFieldNames as $name) {
                $value = $this->trimmed('extprofile-' . $name);
                if (!empty($value)) {
                    $this->saveField($user, $name, $value);
                }
            }

            foreach ($dateFieldNames as $name) {
                $value = $this->trimmed('extprofile-' . $name);
                $dateVal = $this->parseDate($name, $value);
                $this->saveField(
                    $user,
                    $name,
                    null,
                    null,
                    null,
                    $dateVal
                );
            }

            $this->savePhoneNumbers($user);
            $this->saveIms($user);
            $this->saveWebsites($user);
            $this->saveExperiences($user);
            $this->saveEducations($user);

        } catch (Exception $e) {
            $this->showForm($e->getMessage(), false);
            return;
        }

        // TRANS: Success message after saving extended profile details.
        $this->showForm(_m('Details saved.'), true);

    }

    function parseDate($fieldname, $datestr, $required = false)
    {
        if (empty($datestr)) {
            if ($required) {
                $msg = sprintf(
                    // TRANS: Exception thrown when no date was entered in a required date field.
                    // TRANS: %s is the field name.
                    _m('You must supply a date for "%s".'),
                    $fieldname
                );
                throw new Exception($msg);
            }
        } else {
            $ts = strtotime($datestr);
            if ($ts === false) {
                throw new Exception(
                    sprintf(
                        // TRANS: Exception thrown on incorrect data input.
                        // TRANS: %1$s is a field name, %2$s is the incorrect input.
                        _m('Invalid date entered for "%1$s": %2$s.'),
                        $fieldname,
                        $ts
                    )
                );
            }
            return common_sql_date($ts);
        }
        return null;
    }

    function savePhoneNumbers($user) {
        $phones = $this->findPhoneNumbers();
        $this->removeAll($user, 'phone');
        $i = 0;
        foreach($phones as $phone) {
            if (!empty($phone['value'])) {
                ++$i;
                $this->saveField(
                    $user,
                    'phone',
                    $phone['value'],
                    $phone['rel'],
                    $i
                );
            }
        }
    }

    function findPhoneNumbers() {

        // Form vals look like this:
        // 'extprofile-phone-1' => '11332',
        // 'extprofile-phone-1-rel' => 'mobile',

        $phones     = $this->sliceParams('phone', 2);
        $phoneArray = array();

        foreach ($phones as $phone) {
            list($number, $rel) = array_values($phone);
            $phoneArray[] = array(
                'value' => $number,
                'rel'   => $rel
            );
        }

        return $phoneArray;
    }

    function findIms() {

        //  Form vals look like this:
        // 'extprofile-im-0' => 'jed',
        // 'extprofile-im-0-rel' => 'yahoo',

        $ims     = $this->sliceParams('im', 2);
        $imArray = array();

        foreach ($ims as $im) {
            list($id, $rel) = array_values($im);
            $imArray[] = array(
                'value' => $id,
                'rel'   => $rel
            );
        }

        return $imArray;
    }

    function saveIms($user) {
        $ims = $this->findIms();
        $this->removeAll($user, 'im');
        $i = 0;
        foreach($ims as $im) {
            if (!empty($im['value'])) {
                ++$i;
                $this->saveField(
                    $user,
                    'im',
                    $im['value'],
                    $im['rel'],
                    $i
                );
            }
        }
    }

    function findWebsites() {

        //  Form vals look like this:

        $sites = $this->sliceParams('website', 2);
        $wsArray = array();

        foreach ($sites as $site) {
            list($id, $rel) = array_values($site);
            $wsArray[] = array(
                'value' => $id,
                'rel'   => $rel
            );
        }

        return $wsArray;
    }

    function saveWebsites($user) {
        $sites = $this->findWebsites();
        $this->removeAll($user, 'website');
        $i = 0;
        foreach($sites as $site) {
            if (!empty($site['value']) && !Validate::uri(
                $site['value'],
                array('allowed_schemes' => array('http', 'https')))
            ) {
                // TRANS: Exception thrown when entering an invalid URL.
                // TRANS: %s is the invalid URL.
                throw new Exception(sprintf(_m('Invalid URL: %s.'), $site['value']));
            }

            if (!empty($site['value'])) {
                ++$i;
                $this->saveField(
                    $user,
                    'website',
                    $site['value'],
                    $site['rel'],
                    $i
                );
            }
        }
    }

    function findExperiences() {

        // Form vals look like this:
        // 'extprofile-experience-0'         => 'Bozotronix',
        // 'extprofile-experience-0-current' => 'true'
        // 'extprofile-experience-0-start'   => '1/5/10',
        // 'extprofile-experience-0-end'     => '2/3/11',

        $experiences = $this->sliceParams('experience', 4);
        $expArray = array();

        foreach ($experiences as $exp) {
            if (sizeof($experiences) == 4) {
                list($company, $current, $end, $start) = array_values($exp);
            } else {
                $end = null;
                list($company, $current, $start) = array_values($exp);
            }
            if (!empty($company)) {
                $expArray[] = array(
                    'company' => $company,
                    'start'   => $this->parseDate('Start', $start, true),
                    'end'     => ($current == 'false') ? $this->parseDate('End', $end, true) : null,
                    'current' => ($current == 'false') ? false : true
                );
            }
        }

        return $expArray;
    }

    function saveExperiences($user) {
        common_debug('save experiences');
        $experiences = $this->findExperiences();

        $this->removeAll($user, 'company');
        $this->removeAll($user, 'start');
        $this->removeAll($user, 'end'); // also stores 'current'

        $i = 0;
        foreach($experiences as $experience) {
            if (!empty($experience['company'])) {
                ++$i;
                $this->saveField(
                    $user,
                    'company',
                    $experience['company'],
                    null,
                    $i
                );

                $this->saveField(
                    $user,
                    'start',
                    null,
                    null,
                    $i,
                    $experience['start']
                );

                // Save "current" employer indicator in rel
                if ($experience['current']) {
                    $this->saveField(
                        $user,
                        'end',
                        null,
                        'current', // rel
                        $i
                    );
                } else {
                    $this->saveField(
                        $user,
                        'end',
                        null,
                        null,
                        $i,
                        $experience['end']
                    );
                }

            }
        }
    }

    function findEducations() {

        // Form vals look like this:
        // 'extprofile-education-0-school' => 'Pigdog',
        // 'extprofile-education-0-degree' => 'BA',
        // 'extprofile-education-0-description' => 'Blar',
        // 'extprofile-education-0-start' => '05/22/99',
        // 'extprofile-education-0-end' => '05/22/05',

        $edus = $this->sliceParams('education', 5);
        $eduArray = array();

        foreach ($edus as $edu) {
            list($school, $degree, $description, $end, $start) = array_values($edu);
            if (!empty($school)) {
                $eduArray[] = array(
                    'school'      => $school,
                    'degree'      => $degree,
                    'description' => $description,
                    'start'       => $this->parseDate('Start', $start, true),
                    'end'         => $this->parseDate('End', $end, true)
                );
            }
        }

        return $eduArray;
    }


    function saveEducations($user) {
         common_debug('save education');
         $edus = $this->findEducations();
         common_debug(var_export($edus, true));

         $this->removeAll($user, 'school');
         $this->removeAll($user, 'degree');
         $this->removeAll($user, 'degree_descr');
         $this->removeAll($user, 'school_start');
         $this->removeAll($user, 'school_end');

         $i = 0;
         foreach($edus as $edu) {
             if (!empty($edu['school'])) {
                 ++$i;
                 $this->saveField(
                     $user,
                     'school',
                     $edu['school'],
                     null,
                     $i
                 );
                 $this->saveField(
                     $user,
                     'degree',
                     $edu['degree'],
                     null,
                     $i
                 );
                 $this->saveField(
                     $user,
                     'degree_descr',
                     $edu['description'],
                     null,
                     $i
                 );
                 $this->saveField(
                     $user,
                     'school_start',
                     null,
                     null,
                     $i,
                     $edu['start']
                 );

                 $this->saveField(
                     $user,
                     'school_end',
                     null,
                     null,
                     $i,
                     $edu['end']
                 );
            }
         }
     }

    function arraySplit($array, $pieces)
    {
        if ($pieces < 2) {
            return array($array);
        }

        $newCount = ceil(count($array) / $pieces);
        $a = array_slice($array, 0, $newCount);
        $b = $this->arraySplit(array_slice($array, $newCount), $pieces - 1);

        return array_merge(array($a), $b);
    }

    function findMultiParams($type) {
        $formVals = array();
        $target   = $type;
        foreach ($_POST as $key => $val) {
            if (strrpos('extprofile-' . $key, $target) !== false) {
                $formVals[$key] = $val;
            }
        }
        return $formVals;
    }

    function sliceParams($key, $size) {
        $slice = array();
        $params = $this->findMultiParams($key);
        ksort($params);
        $slice = $this->arraySplit($params, sizeof($params) / $size);
        return $slice;
    }

    /**
     * Save an extended profile field as a Profile_detail
     *
     * @param User   $user    the current user
     * @param string $name    field name
     * @param string $value   field value
     * @param string $rel     field rel (type)
     * @param int    $index   index (fields can have multiple values)
     * @param date   $date    related date
     */
    function saveField($user, $name, $value, $rel = null, $index = null, $date = null)
    {
        $profile = $user->getProfile();
        $detail  = new Profile_detail();

        $detail->profile_id  = $profile->id;
        $detail->field_name  = $name;
        $detail->value_index = $index;

        $result = $detail->find(true);

        if (empty($result)) {
            $detial->value_index = $index;
            $detail->rel         = $rel;
            $detail->field_value = $value;
            $detail->date        = $date;
            $detail->created     = common_sql_now();
            $result = $detail->insert();
            if (empty($result)) {
                common_log_db_error($detail, 'INSERT', __FILE__);
                // TRANS: Server error displayed when a field could not be saved in the database.
                $this->serverError(_m('Could not save profile details.'));
            }
        } else {
            $orig = clone($detail);

            $detail->field_value = $value;
            $detail->rel         = $rel;
            $detail->date        = $date;

            $result = $detail->update($orig);
            if (empty($result)) {
                common_log_db_error($detail, 'UPDATE', __FILE__);
                // TRANS: Server error displayed when a field could not be saved in the database.
                $this->serverError(_m('Could not save profile details.'));
            }
        }

        $detail->free();
    }

    function removeAll($user, $name)
    {
        $profile = $user->getProfile();
        $detail  = new Profile_detail();
        $detail->profile_id  = $profile->id;
        $detail->field_name  = $name;
        $detail->delete();
        $detail->free();
    }

    /**
     * Save fields that should be stored in the main profile object
     *
     * XXX: There's a lot of dupe code here from ProfileSettingsAction.
     *      Do not want.
     *
     * @param User $user the current user
     */
    function saveStandardProfileDetails($user)
    {
        $fullname  = $this->trimmed('extprofile-fullname');
        $location  = $this->trimmed('extprofile-location');
        $tagstring = $this->trimmed('extprofile-tags');
        $bio       = $this->trimmed('extprofile-bio');

        if ($tagstring) {
            $tags = array_map(
                'common_canonical_tag',
                preg_split('/[\s,]+/', $tagstring)
            );
        } else {
            $tags = array();
        }

        foreach ($tags as $tag) {
            if (!common_valid_profile_tag($tag)) {
                // TRANS: Validation error in form for profile settings.
                // TRANS: %s is an invalid tag.
                throw new Exception(sprintf(_m('Invalid tag: "%s".'), $tag));
            }
        }

        $profile = $user->getProfile();

        $oldTags = $user->getSelfTags();
        $newTags = array_diff($tags, $oldTags);

        if ($fullname    != $profile->fullname
            || $location != $profile->location
            || !empty($newTags)
            || $bio      != $profile->bio) {

            $orig = clone($profile);

            $profile->nickname = $user->nickname;
            $profile->fullname = $fullname;
            $profile->bio      = $bio;
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

            $profile->profileurl = common_profile_url($user->nickname);

            $result = $profile->update($orig);

            if ($result === false) {
                common_log_db_error($profile, 'UPDATE', __FILE__);
                // TRANS: Server error thrown when user profile settings could not be saved.
                $this->serverError(_m('Could not save profile.'));
                return;
            }

            // Set the user tags
            $result = $user->setSelfTags($tags);

            if (!$result) {
                // TRANS: Server error thrown when user profile settings tags could not be saved.
                $this->serverError(_m('Could not save tags.'));
                return;
            }

            Event::handle('EndProfileSaveForm', array($this));
            common_broadcast_profile($profile);
        }
    }

}
