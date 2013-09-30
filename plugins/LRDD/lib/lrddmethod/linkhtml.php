<?php
/**
 * Implementation of discovery using HTML <link> element
 *
 * Discovers XRD file for a user by fetching the URL and reading any
 * <link> elements in the HTML response.
 *
 * @category  Discovery
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class LRDDMethod_LinkHTML extends LRDDMethod
{
    /**
     * For HTTP IDs, fetch the URL and look for <link> elements
     * in the HTML response.
     *
     * @todo fail out of WebFinger URIs faster
     */
    public function discover($uri)
    {
        $response = self::fetchUrl($uri);

        return self::parse($response->getBody());
    }

    /**
     * Parse HTML and return <link> elements
     *
     * Given an HTML string, scans the string for <link> elements
     *
     * @param string $html HTML to scan
     *
     * @return array array of associative arrays in JRD-ish array format
     */
    public function parse($html)
    {
        $links = array();

        preg_match('/<head(\s[^>]*)?>(.*?)<\/head>/is', $html, $head_matches);
        $head_html = $head_matches[2];

        preg_match_all('/<link\s[^>]*>/i', $head_html, $link_matches);

        foreach ($link_matches[0] as $link_html) {
            $link_url  = null;
            $link_rel  = null;
            $link_type = null;

            preg_match('/\srel=(("|\')([^\\2]*?)\\2|[^"\'\s]+)/i', $link_html, $rel_matches);
            if ( isset($rel_matches[3]) ) {
                $link_rel = $rel_matches[3];
            } else if ( isset($rel_matches[1]) ) {
                $link_rel = $rel_matches[1];
            }

            preg_match('/\shref=(("|\')([^\\2]*?)\\2|[^"\'\s]+)/i', $link_html, $href_matches);
            if ( isset($href_matches[3]) ) {
                $link_uri = $href_matches[3];
            } else if ( isset($href_matches[1]) ) {
                $link_uri = $href_matches[1];
            }

            preg_match('/\stype=(("|\')([^\\2]*?)\\2|[^"\'\s]+)/i', $link_html, $type_matches);
            if ( isset($type_matches[3]) ) {
                $link_type = $type_matches[3];
            } else if ( isset($type_matches[1]) ) {
                $link_type = $type_matches[1];
            }

            $links[] = new XML_XRD_Element_Link($link_rel, $link_uri, $link_type);
        }

        return $links;
    }
}
