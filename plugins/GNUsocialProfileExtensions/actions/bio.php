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
 * @copyright 2011 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/personalgroupnav.php';
require_once INSTALLDIR . '/classes/Profile.php';
require_once INSTALLDIR . '/lib/profilelist.php';

class BioAction extends Action
{
    var $user = null;

    function prepare($args)
    {
        parent::prepare($args);

        $args = $this->returnToArgs();
        $this->profile = Profile::getKV('nickname', $args[1]['nickname']);
        //die(print_r($this->profile));
        gnusocial_profile_merge($this->profile);

        return true;

    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function title()
    {
        return sprintf(_m("%s's Bio."), $this->profile->nickname);
    }

    function showLocalNav()
    {
        $nav = new PersonalGroupNav($this);
        $nav->show();
    }

    function showContent()
    {
        if(empty($this->profile)) {
            return;
        }

        $profilelistitem = new ProfileListItem($this->profile, $this);
        $profilelistitem->show();
        $this->elementStart('ul');
        $fields = GNUsocialProfileExtensionField::allFields();
        foreach ($fields as $field) {
            $fieldname = $field->systemname;
            if (!empty($this->profile->$fieldname)) {
                $this->elementStart('li', array('class' => 'biolistitem'));
                $this->elementStart('div', array('class' => 'biolistitemcontainer'));
                if ($field->type == 'text') {
                    $this->element('h3', array(), $field->title);
                    $this->element('p', array('class' => 'biovalue'), $this->profile->$fieldname);
                }
                else {
                    $this->element('span', array('class' => 'biotitle'), $field->title);
                    $this->text(' ');
                    $this->element('span', array('class' => 'biovalue'), $this->profile->$fieldname);
                }
                $this->elementEnd('div');
                $this->elementEnd('li');
            }
        }
        $this->elementEnd('ul');
    }

}


