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
 * Loads XRD data from a JSON file
 *
 * @category XML
 * @package  XML_XRD
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/XML_XRD
 */
class XML_XRD_Loader_JSON
{
    /**
     * Data storage the XML data get loaded into
     *
     * @var XML_XRD
     */
    protected $xrd;



    /**
     * Init object with xrd object
     *
     * @param XML_XRD $xrd Data storage the JSON data get loaded into
     */
    public function __construct(XML_XRD $xrd)
    {
        $this->xrd = $xrd;
    }

    /**
     * Loads the contents of the given file
     *
     * @param string $file Path to an JRD file
     *
     * @return void
     *
     * @throws XML_XRD_Loader_Exception When the JSON is invalid or cannot be
     *                                   loaded
     */
    public function loadFile($file)
    {
        $json = file_get_contents($file);
        if ($json === false) {
            throw new XML_XRD_Loader_Exception(
                'Error loading JRD file: ' . $file,
                XML_XRD_Loader_Exception::LOAD
            );
        }
        return $this->loadString($json);
    }

    /**
     * Loads the contents of the given string
     *
     * @param string $json JSON string
     *
     * @return void
     *
     * @throws XML_XRD_Loader_Exception When the JSON is invalid or cannot be
     *                                   loaded
     */
    public function loadString($json)
    {
        if ($json == '') {
            throw new XML_XRD_Loader_Exception(
                'Error loading JRD: string empty',
                XML_XRD_Loader_Exception::LOAD
            );
        }

        $obj = json_decode($json);
        if ($obj !== null) {
            return $this->load($obj);
        }

        $constants = get_defined_constants(true);
        $json_errors = array();
        foreach ($constants['json'] as $name => $value) {
            if (!strncmp($name, 'JSON_ERROR_', 11)) {
                $json_errors[$value] = $name;
            }
        }
        throw new XML_XRD_Loader_Exception(
            'Error loading JRD: ' . $json_errors[json_last_error()],
            XML_XRD_Loader_Exception::LOAD
        );
    }

    /**
     * Loads the JSON object into the classes' data structures
     *
     * @param object $j JSON object containing the whole JSON document
     *
     * @return void
     */
    public function load(stdClass $j)
    {
        if (isset($j->subject)) {
            $this->xrd->subject = (string)$j->subject;
        }
        if (isset($j->aliases)) {
            foreach ($j->aliases as $jAlias) {
                $this->xrd->aliases[] = (string)$jAlias;
            }
        }

        if (isset($j->links)) {
            foreach ($j->links as $jLink) {
                $this->xrd->links[] = $this->loadLink($jLink);
            }
        }

        $this->loadProperties($this->xrd, $j);

        if (isset($j->expires)) {
            $this->xrd->expires = strtotime($j->expires);
        }
    }

    /**
     * Loads the Property elements from XML
     *
     * @param object $store Data store where the properties get stored
     * @param object $j     JSON element with "properties" variable
     *
     * @return boolean True when all went well
     */
    protected function loadProperties(
        XML_XRD_PropertyAccess $store, stdClass $j
    ) {
        if (!isset($j->properties)) {
            return true;
        }

        foreach ($j->properties as $type => $jProp) {
            $store->properties[] = new XML_XRD_Element_Property(
                $type, (string)$jProp
            );
        }
        
        return true;
    }

    /**
     * Create a link element object from XML element
     *
     * @param object $j JSON link object
     *
     * @return XML_XRD_Element_Link Created link object
     */
    protected function loadLink(stdClass $j)
    {
        $link = new XML_XRD_Element_Link();
        foreach (array('rel', 'type', 'href', 'template') as $var) {
            if (isset($j->$var)) {
                $link->$var = (string)$j->$var;
            }
        }

        if (isset($j->titles)) {
            foreach ($j->titles as $lang => $jTitle) {
                if (!isset($link->titles[$lang])) {
                    $link->titles[$lang] = (string)$jTitle;
                }
            }
        }
        $this->loadProperties($link, $j);

        return $link;
    }
}
?>
