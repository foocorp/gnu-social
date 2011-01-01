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

function gnusocial_profile_merge(&$profile)
{
    $responses = GNUsocialProfileExtensionResponse::findResponsesByProfile($profile->id);
    $profile->customfields = array();
    foreach ($responses as $response) {
        $title = $response->systemname;
        $profile->$title = $response->value;
        $profile->customfields[] = $title;
    }
}
    
function gnusocial_field_systemname_validate($systemname)
{
    $fields = GNUsocialProfileExtensionField::allFields();
    foreach ($fields as $field)
        if ($field->systemname == $systemname)
            return false;
    return ctype_alphanum($systemname);
}