<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A sample module to show best practices for StatusNet plugins
 *
 * PHP version 5
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
 *
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class Salmon
{
    const REL_SALMON = 'salmon';
    const REL_MENTIONED = 'mentioned';

    // XXX: these are deprecated
    const NS_REPLIES = "http://salmon-protocol.org/ns/salmon-replies";
    const NS_MENTIONS = "http://salmon-protocol.org/ns/salmon-mention";

    /**
     * Sign and post the given Atom entry as a Salmon message.
     *
     * Side effects: may generate a keypair on-demand for the given user,
     * which can be very slow on some systems (like those without php5-gmp).
     *
     * @param string $endpoint_uri
     * @param string $xml string representation of payload
     * @param User $user local user profile whose keys we sign with
     * @return boolean success
     */
    public static function post($endpoint_uri, $xml, User $user)
    {
        if (empty($endpoint_uri)) {
            common_debug('No endpoint URI for Salmon post to '.$user->getUri());
            return false;
        }

        try {
            $magic_env = MagicEnvelope::signAsUser($xml, $user);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Salmon unable to sign: " . $e->getMessage());
            return false;
        }

        $envxml = $magic_env->toXML();

        $headers = array('Content-Type: application/magic-envelope+xml');

        try {
            $client = new HTTPClient();
            $client->setBody($envxml);
            $response = $client->post($endpoint_uri, $headers);
        } catch (HTTP_Request2_Exception $e) {
            common_log(LOG_ERR, "Salmon post to $endpoint_uri failed: " . $e->getMessage());
            return false;
        }

        // Diaspora wants a slightly different formatting on the POST (other Content-type, so body needs "xml=")
        // This also gives us the opportunity to send the specially formatted Diaspora salmon slap, which
        // encrypts the content of me:data
        if ($response->getStatus() === 422) {
            common_debug(sprintf('Salmon (from profile %d) endpoint %s returned status %s. We assume it is a Diaspora seed, will adapt and try again! Body: %s',
                                $user->id, $endpoint_uri, $response->getStatus(), $response->getBody()));
            $headers = array('Content-Type: application/x-www-form-urlencoded');
            $client->setBody('xml=' . Magicsig::base64_url_encode($envxml));
            $response = $client->post($endpoint_uri, $headers);
        }

        // 200 OK is the best response
        // 202 Accepted is what we get from Diaspora for example
        if (!in_array($response->getStatus(), array(200, 202))) {
            common_log(LOG_ERR, sprintf('Salmon (from profile %d) endpoint %s returned status %s: %s',
                                $user->id, $endpoint_uri, $response->getStatus(), $response->getBody()));
            return false;
        }

        // Success!
        return true;
    }
}
