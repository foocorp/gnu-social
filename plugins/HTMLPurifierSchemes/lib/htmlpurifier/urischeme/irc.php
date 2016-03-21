<?php

/**
 * Validates irc
 */
class HTMLPurifier_URIScheme_irc extends HTMLPurifier_URIScheme
{
    /**
     * @type int
     */
//    public $default_port = 6667;

    /**
     * @type bool
     */
    public $browsable = false;

    /**
     * @type bool
     */
    public $hierarchical = true;

    /**
     * @param HTMLPurifier_URI $uri
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return bool
     */
    public function doValidate(&$uri, $config, $context)
    {
        $uri->userinfo = null;
        return true;
    }
}

// vim: et sw=4 sts=4
