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

class AddMirrorForm extends Form
{
    /**
     * Name of the form
     *
     * Sub-classes should overload this with the name of their form.
     *
     * @return void
     */
    function formLegend()
    {
    }

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
        $this->out->hidden('provider', 'feed');
        $this->out->elementStart('fieldset');

        $this->out->elementStart('ul');

        $this->li();
        $this->doInput('addmirror-feedurl',
                       'feedurl',
                       // TRANS: Field label.
                       _m('Web page or feed URL:'),
                       $this->out->trimmed('feedurl'));
        $this->unli();

        $this->li();
        // TRANS: Button text for adding a feed.
        $this->out->submit('addmirror-save', _m('BUTTON','Add feed'));
        $this->unli();
        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');
    }

    protected function doInput($id, $name, $label, $value=null, $instructions=null)
    {
        $this->out->element('label', array('for' => $id), $label);
        $attrs = array('name' => $name,
                       'type' => 'text',
                       'id' => $id,
                       'style' => 'width: 80%');
        if ($value) {
            $attrs['value'] = $value;
        }
        $this->out->element('input', $attrs);
        if ($instructions) {
            $this->out->element('p', 'form_guide', $instructions);
        }
    }

    /**
     * Buttons for form actions
     *
     * Submit and cancel buttons (or whatever)
     * Sub-classes should overload this to show their own buttons.
     *
     * @return void
     */
    function formActions()
    {
    }

    /**
     * ID of the form
     *
     * Should be unique on the page. Sub-classes should overload this
     * to show their own IDs.
     *
     * @return string ID of the form
     */
    function id()
    {
        return 'add-mirror-form';
    }

    /**
     * Action of the form.
     *
     * URL to post to. Should be overloaded by subclasses to give
     * somewhere to post to.
     *
     * @return string URL to post to
     */
    function action()
    {
        return common_local_url('addmirror');
    }

    /**
     * Class of the form.
     *
     * @return string the form's class
     */
    function formClass()
    {
        return 'form_settings';
    }
}
