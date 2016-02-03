<?php

/**
 * Validates magnet (BitTorrent hash links)
 * @todo require xt= GET parameter
 */

class HTMLPurifier_URIScheme_magnet extends HTMLPurifier_URIScheme
{
    /**
     * @type bool
     */
    public $browsable = false;

    /**
     * @type bool
     */
    public $may_omit_host = true;

    /**
     * @param HTMLPurifier_URI $uri
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return bool
     */
    public function doValidate(&$uri, $config, $context)
    {
        $uri->userinfo = null;
        $uri->host     = null;
        $uri->port     = null;
        return true;
    }
}

// vim: et sw=4 sts=4
