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

class ExtendedProfile
{
    function __construct(Profile $profile)
    {
        $this->profile = $profile;
        $this->sections = $this->getSections();
        $this->fields = $this->loadFields();
    }

    function loadFields()
    {
        $detail = new Profile_detail();
        $detail->profile_id = $this->profile->id;
        $detail->find();
        
        while ($detail->get()) {
            $fields[$detail->field][] = clone($detail);
        }
        return $fields;
    }

    function getSections()
    {
        return array(
            'basic' => array(
                'label' => _m('Personal'),
                'fields' => array(
                    'fullname' => array(
                        'label' => _m('Full name'),
                        'profile' => 'fullname',
                        'vcard' => 'fn',
                    ),
                    'title' => array(
                        'label' => _m('Title'),
                        'vcard' => 'title',
                    ),
                    'manager' => array(
                        'label' => _m('Manager'),
                        'type' => 'person',
                        'vcard' => 'x-manager',
                    ),
                    'location' => array(
                        'label' => _m('Location'),
                        'profile' => 'location'
                    ),
                    'bio' => array(
                        'label' => _m('Bio'),
                        'type' => 'textarea',
                        'profile' => 'bio',
                    ),
                    'tags' => array(
                        'label' => _m('Tags'),
                        'type' => 'tags',
                        'profile' => 'tags',
                    ),
                ),
            ),
            'contact' => array(
                'label' => _m('Contact'),
                'fields' => array(
                    'phone' => array(
                        'label' => _m('Phone'),
                        'type' => 'phone',
                        'multi' => true,
                        'vcard' => 'tel',
                    ),
                    'im' => array(
                        'label' => _m('IM'),
                        'type' => 'im',
                        'multi' => true,
                    ),
                    'website' => array(
                        'label' => _m('Websites'),
                        'type' => 'website',
                        'multi' => true,
                    ),
                ),
            ),
            'personal' => array(
                'label' => _m('Personal'),
                'fields' => array(
                    'birthday' => array(
                        'label' => _m('Birthday'),
                        'type' => 'date',
                        'vcard' => 'bday',
                    ),
                    'spouse' => array(
                        'label' => _m('Spouse\'s name'),
                        'vcard' => 'x-spouse',
                    ),
                    'kids' => array(
                        'label' => _m('Kids\' names')
                    ),
                ),
            ),
            'experience' => array(
                'label' => _m('Work experience'),
                'fields' => array(
                    'experience' => array(
                        'type' => 'experience',
                        'label' => _m('Employer'),
                    ),
                ),
            ),
            'education' => array(
                'label' => _m('Education'),
                'fields' => array(
                    'education' => array(
                        'type' => 'education',
                        'label' => _m('Institution'),
                    ),
                ),
            ),
        );
    }
}
