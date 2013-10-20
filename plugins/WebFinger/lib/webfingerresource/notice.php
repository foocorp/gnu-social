<?php
/**
 * WebFinger resource for Notice objects
 *
 * @package   GNUsocial
 * @author    Mikael Nordfeldth
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class WebFingerResource_Notice extends WebFingerResource
{
    public function __construct(Notice $object)
    {
        // The type argument above verifies that it's our class
        parent::__construct($object);
    }

    public function updateXRD(XML_XRD $xrd)
    {
        parent::updateXRD($xrd);

        // TODO: Add atom and json representation links here
        // TODO: Add Salmon/callback links and stuff here
    }
}
