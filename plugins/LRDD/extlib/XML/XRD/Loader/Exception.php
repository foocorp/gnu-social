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

require_once 'XML/XRD/Exception.php';

/**
 * XML_XRD exception that's thrown when loading the XRD fails.
 *
 * @category XML
 * @package  XML_XRD
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/XML_XRD
 */
class XML_XRD_Loader_Exception extends Exception implements XML_XRD_Exception
{
    /**
     * The document namespace is not the XRD 1.0 namespace
     */
    const DOC_NS = 10;

    /**
     * The document root element is not XRD
     */
    const DOC_ROOT = 11;

    /**
     * Error loading the XML|JSON file|string
     */
    const LOAD = 12;

    /**
     * Unsupported XRD file/string type (no loader)
     */
    const NO_LOADER = 13;

    /**
     * Error opening file
     */
    const OPEN_FILE = 14;

    /**
     * Detecting the file type failed
     */
    const DETECT_TYPE = 20;
}

?>