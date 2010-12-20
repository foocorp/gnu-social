<?php

class YammerAuthInitForm extends Form
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'yammer-auth-init-form';
    }


    /**
     * class of the form
     *
     * @return string of the form class
     */

    function formClass()
    {
        return 'form_yammer_auth_init form_settings';
    }


    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('yammeradminpanel');
    }


    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        $this->out->element('legend', null, _m('Connect to Yammer'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $this->out->hidden('subaction', 'authinit');

        $this->out->elementStart('fieldset');
        $this->out->submit('submit', _m('Start authentication'), 'submit', null, _m('Request authorization to connect to Yammer account'));
        $this->out->submit('change-apikey', _m('Change API key'));
        $this->out->elementEnd('fieldset');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
    }
}
