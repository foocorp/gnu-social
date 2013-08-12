<?php

class YammerApikeyForm extends Form
{
    private $runner;

    function __construct($out)
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
        return 'yammer-apikey-form';
    }


    /**
     * class of the form
     *
     * @return string of the form class
     */

    function formClass()
    {
        return 'form_yammer_apikey form_settings';
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
        // TRANS: Form legend for adding details to connect to a remote Yammer API.
        $this->out->element('legend', null, _m('Yammer API registration'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $this->out->hidden('subaction', 'apikey');

        $this->out->elementStart('fieldset');

        $this->out->elementStart('p');
        // TRANS: Explanation of what needs to be done to connect to a Yammer network.
        $this->out->text(_m('Before we can connect to your Yammer network, ' .
                            'you will need to register the importer as an ' .
                            'application authorized to pull data on your behalf. ' .
                            'This registration will work only for your own network. ' .
                            'Follow this link to register the app at Yammer; ' .
                            'you will be prompted to log in if necessary:'));
        $this->out->elementEnd('p');

        $this->out->elementStart('p', array('class' => 'magiclink'));
        $this->out->element('a',
            array('href' => 'https://www.yammer.com/client_applications/new',
                  'target' => '_blank'),
            // TRANS: Link description to a Yammer application registration form.
            _m('Open Yammer application registration form'));
        $this->out->elementEnd('p');

        // TRANS: Instructions.
        $this->out->element('p', array(), _m('Copy the consumer key and secret you are given into the form below:'));

        $this->out->elementStart('ul', array('class' => 'form_data'));
        $this->out->elementStart('li');
        // TRANS: Field label for a Yammer consumer key.
        $this->out->input('consumer_key', _m('Consumer key:'), common_config('yammer', 'consumer_key'));
        $this->out->elementEnd('li');
        $this->out->elementStart('li');
        // TRANS: Field label for a Yammer consumer secret.
        $this->out->input('consumer_secret', _m('Consumer secret:'), common_config('yammer', 'consumer_secret'));
        $this->out->elementEnd('li');
        $this->out->elementEnd('ul');

        // TRANS: Button text for saving a Yammer API registration.
        $this->out->submit('submit', _m('BUTTON','Save'),
                           // TRANS: Button title for saving a Yammer API registration.
                           'submit', null, _m('Save the entered consumer key and consumer secret.'));

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
