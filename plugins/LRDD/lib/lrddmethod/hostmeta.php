<?php
/**
 * Implementation of discovery using host-meta file
 *
 * Discovers resource descriptor file for a user by going to the
 * organization's host-meta file and trying to find a template for LRDD.
 *
 * @category  Discovery
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class LRDDMethod_HostMeta extends LRDDMethod
{
    /**
     * For RFC6415 and HTTP URIs, fetch the host-meta file
     * and look for LRDD templates
     */
    public function discover($uri)
    {
        // This is allowed for RFC6415 but not the 'WebFinger' RFC7033.
        $try_schemes = array('https', 'http');

        $scheme = mb_strtolower(parse_url($uri, PHP_URL_SCHEME));
        switch ($scheme) {
        case 'acct':
            if (!Discovery::isAcct($uri)) {
                throw new Exception('Bad resource URI: '.$uri);
            }
            // We can't use parse_url data for this, since the 'host'
            // entry is only set if the scheme has '://' after it.
            list($user, $domain) = explode('@', parse_url($uri, PHP_URL_PATH));
            break;
        case 'http':
        case 'https':
            $domain = mb_strtolower(parse_url($uri, PHP_URL_HOST));
            $try_schemes = array($scheme);
            break;
        default:
            throw new Exception('Unable to discover resource descriptor endpoint.');
        }

        foreach ($try_schemes as $scheme) {
            $url = $scheme . '://' . $domain . '/.well-known/host-meta';
            
            try {
                $response = self::fetchUrl($url);
                $this->xrd->loadString($response->getBody());
            } catch (Exception $e) {
                common_debug('LRDD could not load resource descriptor: '.$url.' ('.$e->getMessage().')');
                continue;
            }
            return $this->xrd->links;
        }

        throw new Exception('Unable to retrieve resource descriptor links.');
    }
}
