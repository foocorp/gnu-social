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

class ExtendedProfileWidget extends Widget
{
    const EDITABLE=true;

    protected $profile;
    protected $ext;

    public function __construct(XMLOutputter $out=null, Profile $profile=null, $editable=false)
    {
        parent::__construct($out);

        $this->profile = $profile;
        $this->ext = new ExtendedProfile($this->profile);

        $this->editable = $editable;
    }

    public function show()
    {
        $sections = $this->ext->getSections();
        foreach ($sections as $name => $section) {
            $this->showExtendedProfileSection($name, $section);
        }
    }

    protected function showExtendedProfileSection($name, $section)
    {
        $this->out->element('h3', null, $section['label']);
        $this->out->elementStart('table', array('class' => 'extended-profile'));
        foreach ($section['fields'] as $fieldName => $field) {
            $this->showExtendedProfileField($fieldName, $field);
        }
        $this->out->elementEnd('table');
    }

    protected function showExtendedProfileField($name, $field)
    {
        $this->out->elementStart('tr');

        $this->out->element('th', null, $field['label']);

        $this->out->elementStart('td');
        if ($this->editable) {
            $this->showEditableField($name, $field);
        } else {
            $this->showFieldValue($name, $field);
        }
        $this->out->elementEnd('td');

        $this->out->elementEnd('tr');
    }

    protected function showFieldValue($name, $field)
    {
        $this->out->text($name);
    }

    protected function showEditableField($name, $field)
    {
        $out = $this->out;
        //$out = new HTMLOutputter();
        // @fixme
        $type = strval(@$field['type']);
        $id = "extprofile-" . $name;
        $value = 'placeholder';

        switch ($type) {
            case '':
            case 'text':
                $out->input($id, null, $value);
                break;
            case 'textarea':
                $out->textarea($id, null, $value);
                break;
            default:
                $out->input($id, null, "TYPE: $type");
        }
    }
}
