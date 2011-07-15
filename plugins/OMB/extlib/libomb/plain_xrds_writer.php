<?php
/**
 * This file is part of libomb
 *
 * PHP version 5
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OMB
 * @author  Adrian Lang <mail@adrianlang.de>
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL 3.0
 * @version 0.1a-20090828
 * @link    http://adrianlang.de/libomb
 */

require_once 'xrds_writer.php';

/**
 * Write OMB-specific XRDS using XMLWriter
 *
 * This class writes the XRDS file announcing the OMB server. It uses XMLWriter.
 * An instance of OMB_Plain_XRDS_Writer should be passed to
 * OMB_Service_Provider->writeXRDS.
 */
class OMB_Plain_XRDS_Writer implements OMB_XRDS_Writer
{
    /**
     * Write XRDS using XMLWriter
     *
     * Outputs a XRDS document specifying an OMB service.
     *
     * @param OMB_profile     $user   The target user for the OMB service
     * @param OMB_XRDS_Mapper $mapper An OMB_XRDS_Mapper providing endpoint URLs
     */
    public function writeXRDS($user, $mapper)
    {
        header('Content-Type: application/xrds+xml');
        $xw = new XMLWriter();
        $xw->openURI('php://output');
        $xw->setIndent(true);

        $xw->startDocument('1.0', 'UTF-8');
        $this->_writeFullElement($xw, 'XRDS', array('xmlns' => 'xri://$xrds'), array(
        array('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                            'xml:id' => 'oauth',
                            'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
                            'version' => '2.0'), array(
          array('Type', null, 'xri://$xrds*simple'),
          array('Service', null, array(
            array('Type', null, OAUTH_ENDPOINT_REQUEST),
            array('URI', null, $mapper->getURL(OAUTH_ENDPOINT_REQUEST)),
            array('Type', null, OAUTH_AUTH_HEADER),
            array('Type', null, OAUTH_POST_BODY),
            array('Type', null, OAUTH_HMAC_SHA1),
            array('LocalID', null, $user->getIdentifierURI())
          )),
          array('Service', null, array(
            array('Type', null, OAUTH_ENDPOINT_AUTHORIZE),
            array('URI', null, $mapper->getURL(OAUTH_ENDPOINT_AUTHORIZE)),
            array('Type', null, OAUTH_AUTH_HEADER),
            array('Type', null, OAUTH_POST_BODY),
            array('Type', null, OAUTH_HMAC_SHA1)
          )),
          array('Service', null, array(
            array('Type', null, OAUTH_ENDPOINT_ACCESS),
            array('URI', null, $mapper->getURL(OAUTH_ENDPOINT_ACCESS)),
            array('Type', null, OAUTH_AUTH_HEADER),
            array('Type', null, OAUTH_POST_BODY),
            array('Type', null, OAUTH_HMAC_SHA1)
          )),
          array('Service', null, array(
            array('Type', null, OAUTH_ENDPOINT_RESOURCE),
            array('Type', null, OAUTH_AUTH_HEADER),
            array('Type', null, OAUTH_POST_BODY),
            array('Type', null, OAUTH_HMAC_SHA1)
          ))
        )),
        array('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                           'xml:id' => 'omb',
                           'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
                           'version' => '2.0'), array(
          array('Type', null, 'xri://$xrds*simple'),
          array('Service', null, array(
            array('Type', null, OMB_ENDPOINT_POSTNOTICE),
            array('URI', null, $mapper->getURL(OMB_ENDPOINT_POSTNOTICE))
          )),
          array('Service', null, array(
            array('Type', null, OMB_ENDPOINT_UPDATEPROFILE),
            array('URI', null, $mapper->getURL(OMB_ENDPOINT_UPDATEPROFILE))
          ))
        )),
        array('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                           'version' => '2.0'), array(
          array('Type', null, 'xri://$xrds*simple'),
          array('Service', null, array(
            array('Type', null, OAUTH_DISCOVERY),
            array('URI', null, '#oauth')
          )),
          array('Service', null, array(
            array('Type', null, OMB_VERSION),
            array('URI', null, '#omb')
          ))
        ))));
        $xw->endDocument();
        $xw->flush();
    }

    /**
     * Write a complex XML element
     *
     * Outputs a XML element with attributes and content.
     *
     * @param XMLWriter    $xw         The XMLWriter used to output the element
     * @param string       $tag        The tag name
     * @param array|null   $attributes A map of XML attributes
     * @param array|string $content    The content of the element; either an
     *                                 array of child nodes each specified by a
     *                                 three entry-array ($tag, $attributes,
     *                                 $content) or a string
     */
    private function _writeFullElement($xw, $tag, $attributes, $content)
    {
        $xw->startElement($tag);
        if (!is_null($attributes)) {
            foreach ($attributes as $name => $value) {
                $xw->writeAttribute($name, $value);
            }
        }
        if (is_array($content)) {
            foreach ($content as $val) {
                $this->_writeFullElement($xw, $val[0], $val[1], $val[2]);
            }
        } else {
            $xw->text($content);
        }
        $xw->fullEndElement();
    }
}
?>
