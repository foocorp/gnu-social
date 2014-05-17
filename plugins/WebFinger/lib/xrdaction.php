<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

/**
 * @package WebFingerPlugin
 * @author James Walker <james@status.net>
 * @author Mikael Nordfeldth <mmn@hethane.se>
 */

if (!defined('GNUSOCIAL')) { exit(1); }

abstract class XrdAction extends ManagedAction
{
    // json or xml for now, this may still be overriden because of
    // our back-compatibility with StatusNet <=1.1.1
    protected $defaultformat = null;

    protected $xrd      = null;

    public function isReadOnly($args)
    {
        return true;
    }

    /*
     * Configures $this->xrd which will later be printed. Must be
     * implemented by child classes.
     */
    abstract protected function setXRD();

    protected function prepare(array $args=array())
    {
        if (!isset($args['format'])) {
            $args['format'] = $this->defaultformat;
        }

        parent::prepare($args);
        
        $this->xrd = new XML_XRD();

        return true;
    }

    protected function handle()
    {
        $this->setXRD();

        if (common_config('discovery', 'cors')) {
            header('Access-Control-Allow-Origin: *');
        }

        parent::handle();
    }

    public function mimeType()
    {
        try {
            return $this->checkAccept();
        } catch (Exception $e) {
            $supported = Discovery::supportedMimeTypes();
            $docformat = $this->arg('format');

            if (!empty($docformat) && isset($supported[$docformat])) {
                return $supported[$docformat];
            }
        }

        /*
         * "A WebFinger resource MUST return a JRD as the representation
         *  for the resource if the client requests no other supported
         *  format explicitly via the HTTP "Accept" header. [...]
         *  The WebFinger resource MUST silently ignore any requested
         *  representations that it does not understand and support."
         *                                       -- RFC 7033 (WebFinger)
         *                            http://tools.ietf.org/html/rfc7033
         */
        return Discovery::JRD_MIMETYPE;
    }

    public function showPage()
    {
        $mimeType = $this->mimeType();
        header("Content-type: {$mimeType}");

        switch ($mimeType) {
        case Discovery::XRD_MIMETYPE:
            print $this->xrd->toXML();
            break;
        case Discovery::JRD_MIMETYPE:
        case Discovery::JRD_MIMETYPE_OLD:
            print $this->xrd->to('json');
            break;
        default:
            throw new Exception(_('No supported MIME type in Accept header.'));
        }
    }

    protected function checkAccept()
    {
        $type = null;
        $httpaccept = isset($_SERVER['HTTP_ACCEPT'])
                        ? $_SERVER['HTTP_ACCEPT'] : null;
        $useragent  = isset($_SERVER['HTTP_USER_AGENT'])
                        ? $_SERVER['HTTP_USER_AGENT'] : null;

        if ($httpaccept !== null && $httpaccept != '*/*') {
            $can_serve  = implode(',', Discovery::supportedMimeTypes());
            $type       = common_negotiate_type(common_accept_to_prefs($httpaccept),
                                                common_accept_to_prefs($can_serve));
        } else {
            /*
             * HACK: for StatusNet to work against us, we must always serve an
             * XRD to at least versions <1.1.1 (at time of writing) since they
             * don't send Accept headers (in their 'Discovery::fetchXrd' calls)
             */
            $matches = array();
            preg_match('/(StatusNet)\/(\d+\.\d+(\.\d+)?)/', $useragent, $browser);
            if (count($browser)>2 && $browser[1] === 'StatusNet'
                    && version_compare($browser[2], '1.1.1') < 1) {
                return Discovery::XRD_MIMETYPE;
            }
        }

        if (empty($type)) {
            throw new Exception(_('No specified MIME type in Accept header.'));
        }

        return $type;
    }
}
