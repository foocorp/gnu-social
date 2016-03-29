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
        $aliases = $this->object->getAliasesWithIDs();

        // Some sites have changed from http to https and still want
        // (because remote sites look for it) verify that they are still
        // the same identity as they were on HTTP. Should NOT be used if
        // you've run HTTPS all the time!
        if (common_config('fix', 'legacy_http')) {
            foreach ($aliases as $alias=>$id) {
                if (!strtolower(parse_url($alias, PHP_URL_SCHEME)) === 'https') {
                    continue;
                }
                $aliases[preg_replace('/^https:/i', 'http:', $alias, 1)] = $id;
            }
        }

        // return a unique set of aliases by extracting only the keys
        return array_keys($aliases);
    }

    abstract public function updateXRD(XML_XRD $xrd);
}
