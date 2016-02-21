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
        $uri = $this->object->getUri();
        $aliases[$uri] = true;
        if (common_config('webfinger', 'http_alias')
                && strtolower(parse_url($uri, PHP_URL_SCHEME)) === 'https') {
            $aliases[preg_replace('/^https:/', 'http:', $uri, 1)] = true;
        }

        try {
            $aliases[$this->object->getUrl()] = true;
        } catch (InvalidUrlException $e) {
            // getUrl failed because no valid URL could be returned, just ignore it
        }

        if (common_config('webfinger', 'fancyurlfix')) {
            /**
             * Here we add some hacky hotfixes for remote lookups that have been taught the
             * (at least now) wrong URI but it's still obviously the same user. Such as:
             * - https://site.example/user/1 even if the client requests https://site.example/index.php/user/1
             * - https://site.example/user/1 even if the client requests https://site.example//index.php/user/1
             * - https://site.example/index.php/user/1 even if the client requests https://site.example/user/1
             * - https://site.example/index.php/user/1 even if the client requests https://site.example///index.php/user/1
             */
            foreach(array_keys($aliases) as $alias) {
                try {
                    // get a "fancy url" version of the alias, even without index.php/
                    $alt_url = common_fake_local_fancy_url($alias);
                    // store this as well so remote sites can be sure we really are the same profile
                    $aliases[$alt_url] = true;
                } catch (Exception $e) {
                    // Apparently we couldn't rewrite that, the $alias was as the function wanted it to be
                }
    
                try {
                    // get a non-"fancy url" version of the alias, i.e. add index.php/
                    $alt_url = common_fake_local_nonfancy_url($alias);
                    // store this as well so remote sites can be sure we really are the same profile
                    $aliases[$alt_url] = true;
                } catch (Exception $e) {
                    // Apparently we couldn't rewrite that, the $alias was as the function wanted it to be
                }
            }
        }

        // return a unique set of aliases by extracting only the keys
        return array_keys($aliases);
    }

    abstract public function updateXRD(XML_XRD $xrd);
}
