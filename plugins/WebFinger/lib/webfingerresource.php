<?php
/**
 * WebFinger resource parent class
 *
 * @package   GNUsocial
 * @author    Mikael Nordfeldth
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

abstract class WebFingerResource
{
    protected $identities = array();

    protected $object = null;
    protected $type   = null;

    public function __construct(Managed_DataObject $object)
    {
        $this->object = $object;
    }

    public function getObject()
    {
        if ($this->object === null) {
            throw new ServerException('Object is not set');
        }
        return $this->object;
    }

    public function getAliases()
    {
        $aliases = array();

        // Add the URI as an identity, this is _not_ necessarily an HTTP url
        $aliases[] = $this->object->getUri();

        try {
            $aliases[] = $this->object->getUrl();
        } catch (InvalidUrlException $e) {
            // getUrl failed because no valid URL could be returned, just ignore it
        }

        return $aliases;
    }

    abstract public function updateXRD(XML_XRD $xrd);
}
