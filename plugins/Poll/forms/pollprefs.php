<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class PollPrefsForm extends Form
{
    function __construct(Action $out, User_poll_prefs $prefs=null)
    {
        parent::__construct($out);
        $this->prefs = $prefs;
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
        $this->elementStart('fieldset');
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->checkbox('hide_responses',
                        _('Do not deliver poll responses to my home timeline'),
                        ($this->prefs instanceof User_poll_prefs && $this->prefs->hide_responses));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');
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
        $this->submit('submit', _('Save'));
    }

    /**
     * ID of the form
     *
     * Should be unique on the page. Sub-classes should overload this
     * to show their own IDs.
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'form_poll_prefs';
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
        return common_local_url('pollsettings');
    }

    /**
     * Class of the form. May include space-separated list of multiple classes.
     *
     * @return string the form's class
     */

    function formClass()
    {
        return 'form_settings';
    }
}
