<?php
/**
 * GNU Social
 * Copyright (C) 2010, Free Software Foundation, Inc.
 *
 * PHP version 5
 *
 * LICENCE:
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
 *
 * @category  Widget
 * @package   GNU Social
 * @author    Max Shinn <trombonechamp@gmail.com>
 * @copyright 2010 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/adminpanelaction.php';

class ProfilefieldsAdminPanelAction extends AdminPanelAction
{

    function title()
    {
        return _('Profile fields');
    }

    function getInstructions()
    {
        return _('GNU Social custom profile fields');
    }

    function showForm()
    {
        $form = new ProfilefieldsAdminForm($this);
        $form->show();
        return;
    }

    function saveSettings()
    {
        $field = GNUsocialProfileExtensionField::getKV('id', $this->trimmed('id'));
        if (!$field)
            $field = new GNUsocialProfileExtensionField();
        $field->title = $this->trimmed('title');
        $field->description = $this->trimmed('description');
        $field->type = $this->trimmed('type');
        $field->systemname = $this->trimmed('systemname');
        if (!gnusocial_field_systemname_validate($field->systemname)) {
            $this->clientError(_('Internal system name must be unique and consist of only alphanumeric characters!'));
            return false;
        }
        if ($field->id) {
            if ($field->validate())
                $field->update();
            else {
                $this->clientError(_('There was an error with the field data.'));
                return false;
            }
        }
        else {
            $field->insert();
        }

        return;
    }

}

class ProfilefieldsAdminForm extends AdminForm
{

    function id()
    {
        return 'form_profilefields_admin_panel';
    }

    function formClass()
    {
        return 'form_settings';
    }

    function action()
    {
        return '/admin/profilefields';
    }

    function formData()
    {
        $title = null;
        $description = null;
        $type = null;
        $systemname = null;
        $id = null;
        $fieldsettitle = _("New Profile Field");
        //Edit a field
        if ($this->out->trimmed('edit')) { 
            $field = GNUsocialProfileExtensionField::getKV('id', $this->out->trimmed('edit'));
            $title = $field->title;
            $description = $field->description;
            $type = $field->type;
            $systemname = $field->systemname;
            $this->out->hidden('id', $field->id, 'id');
            $fieldsettitle = _("Edit Profile Field");
        }
        //Don't show the list of all fields when editing one
        else { 
            $this->out->elementStart('fieldset');
            $this->out->element('legend', null, _('Existing Custom Profile Fields'));
            $this->out->elementStart('ul', 'form_data');
            $fields = GNUsocialProfileExtensionField::allFields();
            foreach ($fields as $field) {
                $this->li();
                $content = 
                $this->out->elementStart('div');
                $this->out->element('a', array('href' => '/admin/profilefields?edit=' . $field->id),
                                    $field->title);
                $this->out->text(' (' . $field->type . '): ' . $field->description);
                $this->out->elementEnd('div');
                $this->unli();
            }
            $this->out->elementEnd('ul');
            $this->out->elementEnd('fieldset');
        }

        //New fields
        $this->out->elementStart('fieldset');
        $this->out->element('legend', null, $fieldsettitle);
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->out->input('title', _('Title'), $title,
                     _('The title of the field'));
        $this->unli();
        $this->li();
        $this->out->input('systemname', _('Internal name'), $systemname,
                     _('The alphanumeric name used internally for this field.  Also the key used in OStatus user info. (optional)'));
        $this->unli();
        $this->li();
        $this->out->input('description', _('Description'), $description,
                     _('An optional more detailed description of the field'));
        $this->unli();
        $this->li();
        $this->out->dropdown('type', _('Type'), array('text' => _("Text"),
                                                      'str' => _("String")), 
                             _('The type of the datafield'), false, $type);
        $this->unli();
        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', _('Save'), 'submit', null, _('Save new field'));
    }
}
