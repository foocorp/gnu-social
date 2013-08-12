<?php

class YammerAuthVerifyForm extends Form
{
    private $runner;

    function __construct($out, YammerRunner $runner)
    {
        parent::__construct($out);
        $this->runner = $runner;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'yammer-auth-verify-form';
    }


    /**
     * class of the form
     *
     * @return string of the form class
     */

    function formClass()
    {
        return 'form_yammer_auth_verify form_settings';
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
        // TRANS: Form legend.
        $this->out->element('legend', null, _m('Connect to Yammer'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $this->out->hidden('subaction', 'authverify');

        $this->out->elementStart('fieldset');

        $this->out->elementStart('p');
        // TRANS: Form instructions.
        $this->out->text(_m('Follow this link to confirm authorization at Yammer; you will be prompted to log in if necessary:'));
        $this->out->elementEnd('p');

        // iframe would be nice to avoid leaving -- since they don't seem to have callback url O_O
        /*
        $this->out->element('iframe', array('id' => 'yammer-oauth',
                                            'src' => $this->runner->getAuthUrl()));
        */
        // yeah, it ignores the callback_url
        // soo... crappy link. :(

        $this->out->elementStart('p', array('class' => 'magiclink'));
        $this->out->element('a',
            array('href' => $this->runner->getAuthUrl(),
                  'target' => '_blank'),
            // TRANS: Link description for a link in an external Yammer system.
            _m('Open Yammer authentication window'));
        $this->out->elementEnd('p');

        // TRANS: Form instructions.
        $this->out->element('p', array(), _m('Copy the verification code you are given below:'));

        $this->out->elementStart('ul', array('class' => 'form_data'));
        $this->out->elementStart('li');
        // TRANS: Field label.
        $this->out->input('verify_token', _m('Verification code:'));
        $this->out->elementEnd('li');
        $this->out->elementEnd('ul');

        // TRANS: Button text for saving  Yammer authorisation data and starting Yammer import.
        $this->out->submit('submit', _m('BUTTON','Continue'),
                           // TRANS: Button title for saving  Yammer authorisation data and starting Yammer import.
                           'submit', null, _m('Save the verification code and begin import.'));
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
