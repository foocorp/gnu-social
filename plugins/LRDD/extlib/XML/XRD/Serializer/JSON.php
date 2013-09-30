<?php
/**
 * Part of XML_XRD
 *
 * PHP version 5
 *
 * @category XML
 * @package  XML_XRD
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL
 * @link     http://pear.php.net/package/XML_XRD
 */

/**
 * Generate JSON from a XML_XRD object (for JRD files).
 *
 * @category XML
 * @package  XML_XRD
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/XML_XRD
 * @link     http://tools.ietf.org/html/rfc6415#appendix-A
 */
class XML_XRD_Serializer_JSON
{
    protected $xrd;

    /**
     * Create new instance
     *
     * @param XML_XRD $xrd XRD instance to convert to JSON
     */
    public function __construct(XML_XRD $xrd)
    {
        $this->xrd = $xrd;
    }

    /**
     * Generate JSON.
     *
     * @return string JSON code
     */
    public function __toString()
    {
        $o = new stdClass();
        if ($this->xrd->expires !== null) {
            $o->expires = gmdate('Y-m-d\TH:i:s\Z', $this->xrd->expires);
        }
        if ($this->xrd->subject !== null) {
            $o->subject = $this->xrd->subject;
        }
        foreach ($this->xrd->aliases as $alias) {
            $o->aliases[] = $alias;
        }
        foreach ($this->xrd->properties as $property) {
            $o->properties[$property->type] = $property->value;
        }
        $o->links = array();
        foreach ($this->xrd->links as $link) {
            $lid = count($o->links);
            $o->links[$lid] = new stdClass();
            if ($link->rel) {
                $o->links[$lid]->rel = $link->rel;
            }
            if ($link->type) {
                $o->links[$lid]->type = $link->type;
            }
            if ($link->href) {
                $o->links[$lid]->href = $link->href;
            }
            if ($link->template !== null && $link->href === null) {
                $o->links[$lid]->template = $link->template;
            }

            foreach ($link->titles as $lang => $value) {
                if ($lang == null) {
                    $lang = 'default';
                }
                $o->links[$lid]->titles[$lang] = $value;
            }
            foreach ($link->properties as $property) {
                $o->links[$lid]->properties[$property->type] = $property->value;
            }
        }
        if (count($o->links) == 0) {
            unset($o->links);
        }

        return json_encode($o);
    }
}

?>
