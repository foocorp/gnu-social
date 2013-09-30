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

require_once 'XML/XRD/Loader/Exception.php';

/**
 * File/string loading dispatcher.
 * Loads the correct loader for the type of XRD file (XML or JSON).
 * Also provides type auto-detection.
 *
 * @category XML
 * @package  XML_XRD
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/XML_XRD
 */
class XML_XRD_Loader
{
    public function __construct(XML_XRD $xrd)
    {
        $this->xrd = $xrd;
    }

    /**
     * Loads the contents of the given file.
     *
     * Note: Only use file type auto-detection for local files.
     * Do not use it on remote files as the file gets requested several times.
     *
     * @param string $file Path to an XRD file
     * @param string $type File type: xml or json, NULL for auto-detection
     *
     * @return void
     *
     * @throws XML_XRD_Loader_Exception When the file is invalid or cannot be
     *                                   loaded
     */
    public function loadFile($file, $type = null)
    {
        if ($type === null) {
            $type = $this->detectTypeFromFile($file);
        }
        $loader = $this->getLoader($type);
        $loader->loadFile($file);
    }

    /**
     * Loads the contents of the given string
     *
     * @param string $str  XRD string
     * @param string $type File type: xml or json, NULL for auto-detection
     *
     * @return void
     *
     * @throws XML_XRD_Loader_Exception When the string is invalid or cannot be
     *                                   loaded
     */
    public function loadString($str, $type = null)
    {
        if ($type === null) {
            $type = $this->detectTypeFromString($str);
        }
        $loader = $this->getLoader($type);
        $loader->loadString($str);
    }

    /**
     * Creates a XRD loader object for the given type
     *
     * @param string $type File type: xml or json
     *
     * @return XML_XRD_Loader
     */
    protected function getLoader($type)
    {
        $class = 'XML_XRD_Loader_' . strtoupper($type);
        $file = str_replace('_', '/', $class) . '.php';
        include_once $file;
        if (class_exists($class)) {
            return new $class($this->xrd);
        }

        throw new XML_XRD_Loader_Exception(
            'No loader for XRD type "' . $type . '"',
            XML_XRD_Loader_Exception::NO_LOADER
        );
    }

    /**
     * Tries to detect the file type (xml or json) from the file content
     *
     * @param string $file File name to check
     *
     * @return string File type ('xml' or 'json')
     *
     * @throws XML_XRD_Loader_Exception When opening the file fails.
     */
    public function detectTypeFromFile($file)
    {
        if (!file_exists($file)) {
            throw new XML_XRD_Loader_Exception(
                'Error loading XRD file: File does not exist',
                XML_XRD_Loader_Exception::OPEN_FILE
            );
        }
        $handle = fopen($file, 'r');
        if (!$handle) {
            throw new XML_XRD_Loader_Exception(
                'Cannot open file to determine type',
                XML_XRD_Loader_Exception::OPEN_FILE
            );
        }

        $str = (string)fgets($handle, 10);
        fclose($handle);
        return $this->detectTypeFromString($str);
    }

    /**
     * Tries to detect the file type from the content of the file
     *
     * @param string $str Content of XRD file
     *
     * @return string File type ('xml' or 'json')
     *
     * @throws XML_XRD_Loader_Exception When the type cannot be detected
     */
    public function detectTypeFromString($str)
    {
        if (substr($str, 0, 1) == '{') {
            return 'json';
        } else if (substr($str, 0, 5) == '<?xml') {
            return 'xml';
        }

        throw new XML_XRD_Loader_Exception(
            'Detecting file type failed',
            XML_XRD_Loader_Exception::DETECT_TYPE
        );
    }


}

?>
