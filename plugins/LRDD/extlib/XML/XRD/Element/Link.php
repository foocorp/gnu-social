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

require_once 'XML/XRD/PropertyAccess.php';

/**
 * Link element in a XRD file. Attribute access via object properties.
 *
 * Retrieving the title of a link is possible with the getTitle() convenience
 * method.
 *
 * @category XML
 * @package  XML_XRD
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/XML_XRD
 */
class XML_XRD_Element_Link extends XML_XRD_PropertyAccess
{
    /**
     * Link relation
     *
     * @var string
     */
    public $rel;

    /**
     * Link type (MIME type)
     *
     * @var string
     */
    public $type;

    /**
     * Link URL
     *
     * @var string
     */
    public $href;

    /**
     * Link URL template
     *
     * @var string
     */
    public $template;

    /**
     * Array of key-value pairs: Key is the language, value the title
     *
     * @var array
     */
    public $titles = array();



    /**
     * Create a new instance and load data from the XML element
     *
     * @param string  $relOrXml   string with the relation name/URL
     * @param string  $href       HREF value
     * @param string  $type       Type value
     * @param boolean $isTemplate When set to true, the $href is
     *                            used as template
     */
    public function __construct(
        $rel = null, $href = null, $type = null, $isTemplate = false
    ) {
        $this->rel = $rel;
        if ($isTemplate) {
            $this->template = $href;
        } else {
            $this->href = $href;
        }
        $this->type = $type;
    }

    /**
     * Returns the title of the link in the given language.
     * If the language is not available, the first title without the language
     * is returned. If no such one exists, the first title is returned.
     *
     * @param string $lang 2-letter language name
     *
     * @return string|null Link title
     */
    public function getTitle($lang = null)
    {
        if (count($this->titles) == 0) {
            return null;
        }

        if ($lang == null) {
            return reset($this->titles);
        }

        if (isset($this->titles[$lang])) {
            return $this->titles[$lang];
        }
        if (isset($this->titles[''])) {
            return $this->titles[''];
        }

        //return first
        return reset($this->titles);
    }
}

?>