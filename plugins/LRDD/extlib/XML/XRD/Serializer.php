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

require_once 'XML/XRD/Serializer/Exception.php';

/**
 * Serialization dispatcher - loads the correct serializer for saving XRD data.
 *
 * @category XML
 * @package  XML_XRD
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/XML_XRD
 */
class XML_XRD_Serializer
{
    /**
     * XRD data storage
     *
     * @var XML_XRD
     */
    protected $xrd;

    /**
     * Init object with xrd object
     *
     * @param XML_XRD $xrd Data storage the data are fetched from
     */
    public function __construct(XML_XRD $xrd)
    {
        $this->xrd = $xrd;
    }

    /**
     * Convert the XRD data into a string of the given type
     *
     * @param string $type File type: xml or json
     *
     * @return string Serialized data
     */
    public function to($type)
    {
        return (string)$this->getSerializer($type);
    }

    /**
     * Creates a XRD loader object for the given type
     *
     * @param string $type File type: xml or json
     *
     * @return XML_XRD_Loader
     */
    protected function getSerializer($type)
    {
        $class = 'XML_XRD_Serializer_' . strtoupper($type);
        $file = str_replace('_', '/', $class) . '.php';
        include_once $file;
        if (class_exists($class)) {
            return new $class($this->xrd);
        }

        throw new XML_XRD_Serializer_Exception(
            'No serializer for type "' . $type . '"',
            XML_XRD_Loader_Exception::NO_LOADER
        );
    }
}
?>
