<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * StatusNet-only extensions to the Twitter-like API
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Oembed proxy implementation
 *
 * This class provides an interface for our JS-side code to pull info on
 * links from other sites, using either native oEmbed, our own custom
 * handlers, or the noembed.com offsite proxy service as configured.
 *
 * @category  oEmbed
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */
class OembedproxyAction extends OembedAction
{
    function handle($args)
    {
        // Trigger short error responses; not a human-readable web page.
        GNUsocial::setApi(true);

        // We're not a general oEmbed proxy service; limit to valid sessions.
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->clientError(_m('There was a problem with your session token. '.
                                 'Try again, please.'));
        }

        $format = $this->arg('format');
        if ($format && $format != 'json') {
            // TRANS: Client exception thrown when requesting a different format than JSON.
            throw new ClientException(_m('Invalid format; only JSON supported.'));
        }

        $url = $this->arg('url');
        if (!common_valid_http_url($url)) {
            // TRANS: Client exception thrown when not providing a valid URL.
            throw new ClientException(_m('Invalid URL.'));
        }

        $params = array();
        if ($this->arg('maxwidth')) {
            $params['maxwidth'] = $this->arg('maxwidth');
        }
        if ($this->arg('maxheight')) {
            $params['maxheight'] = $this->arg('maxheight');
        }

        $data = oEmbedHelper::getObject($url, $params);

        $this->init_document('json');
        print json_encode($data);
    }
}
