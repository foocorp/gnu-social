<?php
/**
 * @todo Add header and documentation
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/*
 * Generates the cross domain communication channel file
 * (xd_receiver.html). By generating it we can add some caching
 * instructions.
 *
 * See: http://wiki.developers.facebook.com/index.php/Cross_Domain_Communication_Channel
 */
class FBC_XDReceiverAction extends Action
{
    /**
     * Do we need to write to the database?
     *
     * @return boolean true
     */
    function isReadonly()
    {
        return true;
    }

    /**
     * Handle a request
     *
     * @param array $args Arguments from $_REQUEST
     *
     * @return void
     */
    function handle($args)
    {
        // Parent handling, including cache check
        parent::handle($args);
        $this->showPage();
    }

    function showPage()
    {
        // cache the xd_receiver
        header('Cache-Control: max-age=225065900');
        header('Expires:');
        header('Pragma:');

        $this->startXML('html');

        $language = $this->getLanguage();

        $this->elementStart('html', array('xmlns' => 'http://www.w3.org/1999/xhtml',
                                          'xml:lang' => $language,
                                          'lang' => $language));
        $this->elementStart('head');
        $this->element('title', null, 'cross domain receiver page');
        $this->script('http://static.ak.connect.facebook.com/js/api_lib/v0.4/XdCommReceiver.debug.js');
        $this->elementEnd('head');
        $this->elementStart('body');
        $this->elementEnd('body');

        $this->elementEnd('html');
    }
}
