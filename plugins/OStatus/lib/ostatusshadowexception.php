<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * @package OStatusPlugin
 * @maintainer Mikael Nordfeldth <mmn@hethane.se>
 */

/**
 * Exception indicating we've got a remote reference to a local user,
 * not a remote user!
 *
 * If we can ue a local profile after all, it's available as $e->profile.
 */
class OStatusShadowException extends Exception
{
    public $profile;

    /**
     * @param Profile $profile
     * @param string $message
     */
    function __construct(Profile $profile, $message) {
        $this->profile = $profile;
        parent::__construct($message);
    }
}
