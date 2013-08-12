<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @package   StatusNet
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class AddTwitterMirrorForm extends AddMirrorForm
{

    /**
     * Visible or invisible data elements
     *
     * Display the form fields that make up the data of the form.
     * Sub-classes should overload this to show their data.
     *
     * @return void
     */
    function formData()
    {
        $this->out->hidden('provider', 'twitter');
        $this->out->elementStart('fieldset');

        $this->out->elementStart('ul');

        $this->li();
        $this->doInput('addmirror-feedurl',
                       'screen_name',
                       // TRANS: Field label.
                       _m('Twitter username:'),
                       $this->out->trimmed('screen_name'));
        $this->unli();

        $this->li();
        // TRANS: Button text for adding a Twitter feed mirror.
        $this->out->submit('addmirror-save', _m('BUTTON','Add feed'));
        $this->unli();
        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');
    }
}
