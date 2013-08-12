<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for editing an application
 *
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
 * @category  Form
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/form.php';

/**
 * Form for editing an application
 *
 * @category Form
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class ApplicationEditForm extends Form
{
    /**
     * group for user to join
     */
    var $application = null;

    /**
     * Constructor
     *
     * @param Action     $out   output channel
     * @param User_group $group group to join
     */
    function __construct($out=null, $application=null)
    {
        parent::__construct($out);

        $this->application = $application;
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */
    function id()
    {
        if ($this->application) {
            return 'form_application_edit-' . $this->application->id;
        } else {
            return 'form_application_add';
        }
    }

    /**
     * HTTP method used to submit the form
     *
     * For image data we need to send multipart/form-data
     * so we set that here too
     *
     * @return string the method to use for submitting
     */
    function method()
    {
        $this->enctype = 'multipart/form-data';
        return 'post';
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */
    function formClass()
    {
        return 'form_settings';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        $cur = common_current_user();

        if (!empty($this->application)) {
            return common_local_url('editapplication',
                                    array('id' => $this->application->id));
        } else {
            return common_local_url('newapplication');
        }
    }

    /**
     * Name of the form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Form legend.
        $this->out->element('legend', null, _('Edit application'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        if ($this->application) {
            $id                = $this->application->id;
            $icon              = $this->application->icon;
            $name              = $this->application->name;
            $description       = $this->application->description;
            $source_url        = $this->application->source_url;
            $organization      = $this->application->organization;
            $homepage          = $this->application->homepage;
            $callback_url      = $this->application->callback_url;
            $this->type        = $this->application->type;
            $this->access_type = $this->application->access_type;
        } else {
            $id                = '';
            $icon              = '';
            $name              = '';
            $description       = '';
            $source_url        = '';
            $organization      = '';
            $homepage          = '';
            $callback_url      = '';
            $this->type        = '';
            $this->access_type = '';
        }

        $this->out->elementStart('ul', 'form_data');

        $this->out->elementStart('li', array('id' => 'application_icon'));

        if (!empty($icon)) {
            $this->out->element('img', array('src' => $icon));
        }

        $this->out->element('input', array('name' => 'MAX_FILE_SIZE',
                                           'type' => 'hidden',
                                           'id' => 'MAX_FILE_SIZE',
                                           'value' => ImageFile::maxFileSizeInt()));
        $this->out->element('label', array('for' => 'app_icon'),
                            // TRANS: Form input field label for application icon.
                            _('Icon'));
        $this->out->element('input', array('name' => 'app_icon',
                                           'type' => 'file',
                                           'id' => 'app_icon'));
        // TRANS: Form guide.
        $this->out->element('p', 'form_guide', _('Icon for this application'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li');

        $this->out->hidden('application_id', $id);

        // TRANS: Form input field label for application name.
        $this->out->input('name', _('Name'),
                          ($this->out->arg('name')) ? $this->out->arg('name') : $name);

        $this->out->elementEnd('li');

        $this->out->elementStart('li');

        $maxDesc = Oauth_application::maxDesc();
        if ($maxDesc > 0) {
            // TRANS: Form input field instructions.
            // TRANS: %d is the number of available characters for the description.
            $descInstr = sprintf(_m('Describe your application in %d character','Describe your application in %d characters',$maxDesc),
                                 $maxDesc);
        } else {
            // TRANS: Form input field instructions.
            $descInstr = _('Describe your application');
        }
        // TRANS: Form input field label.
        $this->out->textarea('description', _('Description'),
                        ($this->out->arg('description')) ? $this->out->arg('description') : $description,
                             $descInstr);

        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        // TRANS: Form input field instructions.
        $instruction = _('URL of the homepage of this application');
        // TRANS: Form input field label.
        $this->out->input('source_url', _('Source URL'),
                          ($this->out->arg('source_url')) ? $this->out->arg('source_url') : $source_url,
                          $instruction);
        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        // TRANS: Form input field instructions.
        $instruction = _('Organization responsible for this application');
        // TRANS: Form input field label.
        $this->out->input('organization', _('Organization'),
                          ($this->out->arg('organization')) ? $this->out->arg('organization') : $organization,
                          $instruction);
        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        // TRANS: Form input field instructions.
        $instruction = _('URL for the homepage of the organization');
        // TRANS: Form input field label.
        $this->out->input('homepage', _('Homepage'),
                          ($this->out->arg('homepage')) ? $this->out->arg('homepage') : $homepage,
                          $instruction);
        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        // TRANS: Form input field instructions.
        $instruction = _('URL to redirect to after authentication');
        // TRANS: Form input field label.
        $this->out->input('callback_url', ('Callback URL'),
                          ($this->out->arg('callback_url')) ? $this->out->arg('callback_url') : $callback_url,
                          $instruction);
        $this->out->elementEnd('li');

        $this->out->elementStart('li', array('id' => 'application_types'));

        $attrs = array('name' => 'app_type',
                       'type' => 'radio',
                       'id' => 'app_type-browser',
                       'class' => 'radio',
                       'value' => Oauth_application::$browser);

        // Default to Browser

        if (empty($this->application)
            || empty($this->application->type)
            || $this->application->type == Oauth_application::$browser) {
            $attrs['checked'] = 'checked';
        }

        $this->out->element('input', $attrs);

        $this->out->element('label', array('for' => 'app_type-browser',
                                           'class' => 'radio'),
                            // TRANS: Radio button label for application type
                            _('Browser'));

        $attrs = array('name' => 'app_type',
                       'type' => 'radio',
                       'id' => 'app_type-dekstop',
                       'class' => 'radio',
                       'value' => Oauth_application::$desktop);

        if (!empty($this->application) && $this->application->type == Oauth_application::$desktop) {
            $attrs['checked'] = 'checked';
        }

        $this->out->element('input', $attrs);

        $this->out->element('label', array('for' => 'app_type-desktop',
                                           'class' => 'radio'),
                            // TRANS: Radio button label for application type
                            _('Desktop'));
        // TRANS: Form guide.
        $this->out->element('p', 'form_guide', _('Type of application, browser or desktop'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li', array('id' => 'default_access_types'));

        $attrs = array('name' => 'default_access_type',
                       'type' => 'radio',
                       'id' => 'default_access_type-r',
                       'class' => 'radio',
                       'value' => 'r');

        // default to read-only access

        if (empty($this->application)
            || empty($this->application->access_type)
            || $this->application->access_type & Oauth_application::$readAccess) {
            $attrs['checked'] = 'checked';
        }

        $this->out->element('input', $attrs);

        $this->out->element('label', array('for' => 'default_access_type-ro',
                                           'class' => 'radio'),
                            // TRANS: Radio button label for access type.
                            _('Read-only'));

        $attrs = array('name' => 'default_access_type',
                       'type' => 'radio',
                       'id' => 'default_access_type-rw',
                       'class' => 'radio',
                       'value' => 'rw');

        if (!empty($this->application)
            && $this->application->access_type & Oauth_application::$readAccess
            && $this->application->access_type & Oauth_application::$writeAccess
            ) {
            $attrs['checked'] = 'checked';
        }

        $this->out->element('input', $attrs);

        $this->out->element('label', array('for' => 'default_access_type-rw',
                                           'class' => 'radio'),
                            // TRANS: Radio button label for access type.
                            _('Read-write'));
        // TRANS: Form guide.
        $this->out->element('p', 'form_guide', _('Default access for this application: read-only, or read-write'));

        $this->out->elementEnd('li');

        $this->out->elementEnd('ul');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // TRANS: Button label in the "Edit application" form.
        $this->out->submit('cancel', _m('BUTTON','Cancel'), 'submit form_action-primary',
                           // TRANS: Submit button title.
                           'cancel', _('Cancel application changes.'));
        // TRANS: Button label in the "Edit application" form.
        $this->out->submit('save', _m('BUTTON','Save'), 'submit form_action-secondary',
                           // TRANS: Submit button title.
                           'save', _('Save application changes.'));
    }
}
