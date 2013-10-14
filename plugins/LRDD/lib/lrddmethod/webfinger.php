<?php
/**
 * Implementation of WebFinger resource discovery (RFC7033)
 *
 * @category  Discovery
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class LRDDMethod_WebFinger extends LRDDMethod
{
    /**
     * Simply returns the WebFinger URL over HTTPS at the uri's domain:
     * https://{domain}/.well-known/webfinger?resource={uri}
     */
    public function discover($uri)
    {
        if (!Discovery::isAcct($uri)) {
            throw new Exception('Bad resource URI: '.$uri);
        }
        list($user, $domain) = explode('@', parse_url($uri, PHP_URL_PATH));
        if (!filter_var($domain, FILTER_VALIDATE_IP)
                && !filter_var(gethostbyname($domain), FILTER_VALIDATE_IP)) {
            throw new Exception('Bad resource host.');
        }

        $link = new XML_XRD_Element_Link(
                        Discovery::LRDD_REL,
                        'https://' . $domain . '/.well-known/webfinger?resource={uri}',
                        Discovery::JRD_MIMETYPE,
                        true);  //isTemplate

        return array($link);
    }
}
