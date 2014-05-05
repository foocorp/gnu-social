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
     * which can be very slow on some systems.
     *
     * @param string $endpoint_uri
     * @param string $xml string representation of payload
     * @param Profile $actor local user profile whose keys to sign with
     * @return boolean success
     */
    public function post($endpoint_uri, $xml, Profile $actor)
    {
        if (empty($endpoint_uri)) {
            common_debug('No endpoint URI for Salmon post to '.$actor->getUri());
            return false;
        }

        foreach ($this->formatClasses() as $class) {
            try {
                $envelope = $this->createMagicEnv($xml, $actor, $class);
            } catch (Exception $e) {
                common_log(LOG_ERR, "Salmon unable to sign: " . $e->getMessage());
                return false;
            }

            $headers = array('Content-Type: application/magic-envelope+xml');

            try {
                $client = new HTTPClient();
                $client->setBody($envelope);
                $response = $client->post($endpoint_uri, $headers);
            } catch (HTTP_Request2_Exception $e) {
                common_log(LOG_ERR, "Salmon ($class) post to $endpoint_uri failed: " . $e->getMessage());
                continue;
            }
            if ($response->getStatus() != 200) {
                common_log(LOG_ERR, "Salmon ($class) at $endpoint_uri returned status " .
                    $response->getStatus() . ': ' . $response->getBody());
                continue;
            }

            // Success!
            return true;
        }
        return false;
    }

    /**
     * List the magic envelope signature class variants in the order we try them.
     * Multiples are needed for backwards-compat with StatusNet prior to 0.9.7,
     * which used a draft version of the magic envelope spec.
     */
    protected function formatClasses() {
        return array('MagicEnvelope', 'MagicEnvelopeCompat');
    }

    /**
     * Encode the given string as a signed MagicEnvelope XML document,
     * using the keypair for the given local user profile.
     *
     * Side effects: will create and store a keypair on-demand if one
     * hasn't already been generated for this user. This can be very slow
     * on some systems.
     *
     * @param string $text XML fragment to sign, assumed to be Atom
     * @param Profile $actor Profile of a local user to use as signer
     * @param string $class to override the magic envelope signature version, pass a MagicEnvelope subclass here
     *
     * @return string XML string representation of magic envelope
     *
     * @throws Exception on bad profile input or key generation problems
     * @fixme if signing fails, this seems to return the original text without warning. Is there a reason for this?
     */
    public function createMagicEnv($text, $actor, $class='MagicEnvelope')
    {
        $magic_env = new $class();

        $user = User::getKV('id', $actor->id);
        if ($user->id) {
            // Use local key
            $magickey = Magicsig::getKV('user_id', $user->id);
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

    /**
     * Check if the given magic envelope is well-formed and correctly signed.
     * Needs to have network access to fetch public keys over the web.
     * Both current and back-compat signature formats will be checked.
     *
     * Side effects: exceptions and caching updates may occur during network
     * fetches.
     *
     * @param string $text XML fragment of magic envelope
     * @return boolean
     *
     * @throws Exception on bad profile input or key generation problems
     * @fixme could hit fatal errors or spew output on invalid XML
     */
    public function verifyMagicEnv($text)
    {
        foreach ($this->formatClasses() as $class) {
            $magic_env = new $class();

            $env = $magic_env->parse($text);

            if ($magic_env->verify($env)) {
                return true;
            }
        }

        return false;
    }
}
