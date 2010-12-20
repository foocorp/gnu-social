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
     * @fixme pass through the actor for signing?
     *
     * @param string $endpoint_uri
     * @param string $xml
     * @return boolean success
     */
    public function post($endpoint_uri, $xml, $actor)
    {
        if (empty($endpoint_uri)) {
            return false;
        }

        try {
            $xml = $this->createMagicEnv($xml, $actor);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Salmon unable to sign: " . $e->getMessage());
            return false;
        }

        $headers = array('Content-Type: application/magic-envelope+xml');

        try {
            $client = new HTTPClient();
            $client->setBody($xml);
            $response = $client->post($endpoint_uri, $headers);
        } catch (HTTP_Request2_Exception $e) {
            common_log(LOG_ERR, "Salmon post to $endpoint_uri failed: " . $e->getMessage());
            return false;
        }
        if ($response->getStatus() != 200) {
            common_log(LOG_ERR, "Salmon at $endpoint_uri returned status " .
                $response->getStatus() . ': ' . $response->getBody());
            return false;
        }
        return true;
    }

    public function createMagicEnv($text, $actor)
    {
        $magic_env = new MagicEnvelope();

        $user = User::staticGet('id', $actor->id);
        if ($user->id) {
            // Use local key
            $magickey = Magicsig::staticGet('user_id', $user->id);
            if (!$magickey) {
                // No keypair yet, let's generate one.
                $magickey = new Magicsig();
                $magickey->generate($user->id);
            }
        } else {
            // TRANS: Exception.
            throw new Exception(_m('Salmon invalid actor for signing.'));
        }

        try {
            $env = $magic_env->signMessage($text, 'application/atom+xml', $magickey->toString());
        } catch (Exception $e) {
            return $text;
        }
        return $magic_env->toXML($env);
    }

    public function verifyMagicEnv($text)
    {
        $magic_env = new MagicEnvelope();

        $env = $magic_env->parse($text);

        return $magic_env->verify($env);
    }
}
