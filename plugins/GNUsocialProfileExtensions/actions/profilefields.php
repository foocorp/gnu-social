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
        $title = $this->trimmed('title');
        $description = $this->trimmed('description');
        $type = $this->trimmed('type');
        $systemname = $this->trimmed('systemname');

        $field = GNUsocialProfileExtensionField::newField($title, $description, $type, $systemname);

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
        //Listing all fields
        $this->out->elementStart('fieldset');
        $this->out->element('legend', null, _('Existing Custom Profile Fields'));
        $this->out->elementStart('ul', 'form_data');
        $fields = GNUsocialProfileExtensionField::allFields();
        foreach ($fields as $field) {
            $this->li();
            $this->out->element('div', array(), $field->title . ' (' .
                                $field->type . '): ' . $field->description);
            $this->unli();
        }
        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');

        //New fields
        $this->out->elementStart('fieldset');
        $this->out->element('legend', null, _('New Profile Field'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input('title', _('Title'),
                     _('The title of the field'));
        $this->unli();
        $this->li();
        $this->input('systemname', _('Internal name'), 
                    _('The name used internally for this field.  Also the key used in OStatus user info. (optional)'));
        $this->unli();
        $this->li();
        $this->input('description', _('Description'),
                     _('An optional more detailed description of the field'));
        $this->unli();
        $this->li();
        $this->out->dropdown('type', _('Type'), array('text' => _("Text"),
                                                      'str' => _("String")), 
                             _('The type of the datafield'));
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
