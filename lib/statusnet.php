<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Backwards compatible class for plugins for GNU social <1.2
 * and thus only have the class StatusNet defined.
 */
class StatusNet
{
    public static function getActivePlugins()
    {
        return GNUsocial::getActivePlugins();
    }

    public static function isHTTPS()
    {
        return GNUsocial::isHTTPS();
    }
}
