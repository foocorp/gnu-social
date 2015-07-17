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

if (!defined('GNUSOCIAL')) { exit(1); }

class ProfileDetailSettingsAction extends ProfileSettingsAction
{
    function title()
    {
        // TRANS: Title for extended profile settings.
        return _m('Extended profile settings');
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

    protected function doPost()
    {
        if ($this->arg('save')) {
            return $this->saveDetails();
        }

        // TRANS: Message given submitting a form with an unknown action.
        throw new ClientException(_m('Unexpected form submission.'));
    }

    function showContent()
    {
        $widget = new ExtendedProfileWidget(
            $this,
            $this->scoped,
            ExtendedProfileWidget::EDITABLE
        );
        $widget->show();
    }

    function saveDetails()
    {
        common_debug(var_export($_POST, true));

        $this->saveStandardProfileDetails();

        $simpleFieldNames = array('title', 'spouse', 'kids', 'manager');
        $dateFieldNames   = array('birthday');

        foreach ($simpleFieldNames as $name) {
            $value = $this->trimmed('extprofile-' . $name);
            if (!empty($value)) {
                $this->saveField($name, $value);
            }
        }

        foreach ($dateFieldNames as $name) {
            $value = $this->trimmed('extprofile-' . $name);
            $dateVal = $this->parseDate($name, $value);
            $this->saveField(
                $name,
                null,
                null,
                null,
                $dateVal
            );
        }

        $this->savePhoneNumbers();
        $this->saveIms();
        $this->saveWebsites();
        $this->saveExperiences();
        $this->saveEducations();

        // TRANS: Success message after saving extended profile details.
        return _m('Details saved.');

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

    function savePhoneNumbers() {
        $phones = $this->findPhoneNumbers();
        $this->removeAll('phone');
        $i = 0;
        foreach($phones as $phone) {
            if (!empty($phone['value'])) {
                ++$i;
                $this->saveField(
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

    function saveIms() {
        $ims = $this->findIms();
        $this->removeAll('im');
        $i = 0;
        foreach($ims as $im) {
            if (!empty($im['value'])) {
                ++$i;
                $this->saveField(
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

    function saveWebsites() {
        $sites = $this->findWebsites();
        $this->removeAll('website');
        $i = 0;
        foreach($sites as $site) {
            if (!empty($site['value']) && !common_valid_http_url($site['value'])) {
                // TRANS: Exception thrown when entering an invalid URL.
                // TRANS: %s is the invalid URL.
                throw new Exception(sprintf(_m('Invalid URL: %s.'), $site['value']));
            }

            if (!empty($site['value'])) {
                ++$i;
                $this->saveField(
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

    function saveExperiences() {
        common_debug('save experiences');
        $experiences = $this->findExperiences();

        $this->removeAll('company');
        $this->removeAll('start');
        $this->removeAll('end'); // also stores 'current'

        $i = 0;
        foreach($experiences as $experience) {
            if (!empty($experience['company'])) {
                ++$i;
                $this->saveField(
                    'company',
                    $experience['company'],
                    null,
                    $i
                );

                $this->saveField(
                    'start',
                    null,
                    null,
                    $i,
                    $experience['start']
                );

                // Save "current" employer indicator in rel
                if ($experience['current']) {
                    $this->saveField(
                        'end',
                        null,
                        'current', // rel
                        $i
                    );
                } else {
                    $this->saveField(
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


    function saveEducations() {
         common_debug('save education');
         $edus = $this->findEducations();
         common_debug(var_export($edus, true));

         $this->removeAll('school');
         $this->removeAll('degree');
         $this->removeAll('degree_descr');
         $this->removeAll('school_start');
         $this->removeAll('school_end');

         $i = 0;
         foreach($edus as $edu) {
             if (!empty($edu['school'])) {
                 ++$i;
                 $this->saveField(
                     'school',
                     $edu['school'],
                     null,
                     $i
                 );
                 $this->saveField(
                     'degree',
                     $edu['degree'],
                     null,
                     $i
                 );
                 $this->saveField(
                     'degree_descr',
                     $edu['description'],
                     null,
                     $i
                 );
                 $this->saveField(
                     'school_start',
                     null,
                     null,
                     $i,
                     $edu['start']
                 );

                 $this->saveField(
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
     * @param string $name    field name
     * @param string $value   field value
     * @param string $rel     field rel (type)
     * @param int    $index   index (fields can have multiple values)
     * @param date   $date    related date
     */
    function saveField($name, $value, $rel = null, $index = null, $date = null)
    {
        $detail  = new Profile_detail();

        $detail->profile_id  = $this->scoped->getID();
        $detail->field_name  = $name;
        $detail->value_index = $index;

        $result = $detail->find(true);

        if (!$result instanceof Profile_detail) {
            $detail->value_index = $index;
            $detail->rel         = $rel;
            $detail->field_value = $value;
            $detail->date        = $date;
            $detail->created     = common_sql_now();
            $result = $detail->insert();
            if ($result === false) {
                common_log_db_error($detail, 'INSERT', __FILE__);
                // TRANS: Server error displayed when a field could not be saved in the database.
                throw new ServerException(_m('Could not save profile details.'));
            }
        } else {
            $orig = clone($detail);

            $detail->field_value = $value;
            $detail->rel         = $rel;
            $detail->date        = $date;

            $result = $detail->update($orig);
            if ($result === false) {
                common_log_db_error($detail, 'UPDATE', __FILE__);
                // TRANS: Server error displayed when a field could not be saved in the database.
                throw new ServerException(_m('Could not save profile details.'));
            }
        }

        $detail->free();
    }

    function removeAll($name)
    {
        $detail  = new Profile_detail();
        $detail->profile_id  = $this->scoped->getID();
        $detail->field_name  = $name;
        $detail->delete();
        $detail->free();
    }

    /**
     * Save fields that should be stored in the main profile object
     *
     * XXX: There's a lot of dupe code here from ProfileSettingsAction.
     *      Do not want.
     */
    function saveStandardProfileDetails()
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

        $oldTags = Profile_tag::getSelfTagsArray($this->scoped);
        $newTags = array_diff($tags, $oldTags);

        if ($fullname    != $this->scoped->getFullname()
            || $location != $this->scoped->location
            || !empty($newTags)
            || $bio      != $this->scoped->getDescription()) {

            $orig = clone($this->scoped);

            // Skipping nickname change here until we add logic for when the site allows it or not
            // old Profilesettings will still let us do that.

            $this->scoped->fullname = $fullname;
            $this->scoped->bio      = $bio;
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

            $result = $this->scoped->update($orig);

            if ($result === false) {
                common_log_db_error($this->scoped, 'UPDATE', __FILE__);
                // TRANS: Server error thrown when user profile settings could not be saved.
                throw new ServerException(_m('Could not save profile.'));
            }

            // Set the user tags
            $result = Profile_tag::setSelfTags($this->scoped, $tags);

            Event::handle('EndProfileSaveForm', array($this));
        }
    }

}
