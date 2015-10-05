<?php
/*
 * GNU Social - a federating social network
 * Copyright (C) 2015, Free Software Foundation, Inc.
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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Diaspora federation protocol plugin for GNU Social
 *
 * Depends on:
 *  - OStatus plugin
 *  - WebFinger plugin
 *
 * @package ProtocolDiasporaPlugin
 * @maintainer Mikael Nordfeldth <mmn@hethane.se>
 */

// Depends on OStatus of course.
addPlugin('OStatus');

//Since Magicsig hasn't loaded yet
require_once('Crypt/AES.php');

class DiasporaPlugin extends Plugin
{
    const REL_SEED_LOCATION = 'http://joindiaspora.com/seed_location';
    const REL_GUID          = 'http://joindiaspora.com/guid';
    const REL_PUBLIC_KEY    = 'diaspora-public-key';

    public function onEndAttachPubkeyToUserXRD(Magicsig $magicsig, XML_XRD $xrd, Profile $target)
    {
        // So far we've only handled RSA keys, but it can change in the future,
        // so be prepared. And remember to change the statically assigned type attribute below!
        assert($magicsig->publicKey instanceof Crypt_RSA);
        $xrd->links[] = new XML_XRD_Element_Link(self::REL_PUBLIC_KEY,
                                    base64_encode($magicsig->exportPublicKey()), 'RSA');

        // Instead of choosing a random string, we calculate our GUID from the public key
        // by fingerprint through a sha256 hash.
        $xrd->links[] = new XML_XRD_Element_Link(self::REL_GUID,
                                    strtolower($magicsig->toFingerprint()));
    }

    public function onMagicsigPublicKeyFromXRD(XML_XRD $xrd, &$pubkey)
    {
        // See if we have a Diaspora public key in the XRD response
        $link = $xrd->get(self::REL_PUBLIC_KEY, 'RSA');
        if (!is_null($link)) {
            // If we do, decode it so we have the PKCS1 format (starts with -----BEGIN PUBLIC KEY-----)
            $pkcs1 = base64_decode($link->href);
            $magicsig = new Magicsig(Magicsig::DEFAULT_SIGALG); // Diaspora uses RSA-SHA256 (we do too)
            try {
                // Try to load the public key so we can get it in the standard Magic signature format
                $magicsig->loadPublicKeyPKCS1($pkcs1);
                // We found it and will now store it in $pubkey in a proper format!
                // This is how it would be found in a well implemented XRD according to the standard.
                $pubkey = 'data:application/magic-public-key,'.$magicsig->toString();
                common_debug('magic-public-key found in diaspora-public-key: '.$pubkey);
                return false;
            } catch (ServerException $e) {
                common_log(LOG_WARNING, $e->getMessage());
            }
        }
        return true;
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Diaspora',
                            'version' => '0.1',
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'https://gnu.io/social',
                            // TRANS: Plugin description.
                            'rawdescription' => _m('Follow people across social networks that implement '.
                               'the <a href="https://diasporafoundation.org/">Diaspora</a> federation protocol.'));

        return true;
    }

    public function onStartMagicEnvelopeToXML(MagicEnvelope $magic_env, XMLStringer $xs, $flavour=null, Profile $target=null)
    {
        // Since Diaspora doesn't use a separate namespace for their "extended"
        // salmon slap, we'll have to resort to this workaround hack.
        if ($flavour !== 'diaspora') {
            return true;
        }

        // WARNING: This changes the $magic_env contents! Be aware of it.

        /**
         * https://wiki.diasporafoundation.org/Federation_protocol_overview
         *
         * Constructing the encryption header
         */

        // For some reason it's supposed to be inside an <atom:entry>
        $xs->elementStart('entry', array('xmlns'=>'http://www.w3.org/2005/Atom'));

        /**
         * Choose an AES key and initialization vector, suitable for the
         * aes-256-cbc cipher. I shall refer to this as the “inner key”
         * and the “inner initialization vector (iv)”.
         */
        $inner_key = new Crypt_AES(CRYPT_AES_MODE_CBC);
        $inner_key->setKeyLength(256);  // set length to 256 bits (could be calculated, but let's be sure)
        $inner_key->setKey(common_random_rawstr(32));   // 32 bytes from a (pseudo) random source
        $inner_key->setIV(common_random_rawstr(16));    // 16 bytes is the block length

        /**
         * Construct the following XML snippet:
         *  <decrypted_header>
         *      <iv>((base64-encoded inner iv))</iv>
         *      <aes_key>((base64-encoded inner key))</aes_key>
         *      <author>
         *          <name>Alice Exampleman</name>
         *          <uri>acct:user@sender.example</uri>
         *      </author>
         *  </decrypted_header>
         */
        $decrypted_header = sprintf('<decrypted_header><iv>%1$s</iv><aes_key>%2$s</aes_key><author_id>%3$s</author_id></decrypted_header>',
                                    base64_encode($inner_key->iv),
                                    base64_encode($inner_key->key),
                                    $magic_env->getActor()->getAcctUri());

        /**
         * Construct another AES key and initialization vector suitable
         * for the aes-256-cbc cipher. I shall refer to this as the
         * “outer key” and the “outer initialization vector (iv)”.
         */
        $outer_key = new Crypt_AES(CRYPT_AES_MODE_CBC);
        $outer_key->setKeyLength(256);  // set length to 256 bits (could be calculated, but let's be sure)
        $outer_key->setKey(common_random_rawstr(32));   // 32 bytes from a (pseudo) random source
        $outer_key->setIV(common_random_rawstr(16));    // 16 bytes is the block length

        /**
         * Encrypt your <decrypted_header> XML snippet using the “outer key”
         * and “outer iv” (using the aes-256-cbc cipher). This encrypted
         * blob shall be referred to as “the ciphertext”. 
         */
        $ciphertext = $outer_key->encrypt($decrypted_header);

        /**
         * Construct the following JSON object, which shall be referred to
         * as “the outer aes key bundle”:
         *  {
         *      "iv": ((base64-encoded AES outer iv)),
         *      "key": ((base64-encoded AES outer key))
         *  }
         */
        $outer_bundle = json_encode(array(
                                'iv' => base64_encode($outer_key->iv),
                                'key' => base64_encode($outer_key->key),
                            ));
        /**
         * Encrypt the “outer aes key bundle” with Bob’s RSA public key.
         * I shall refer to this as the “encrypted outer aes key bundle”.
         */
        common_debug('Diaspora creating "outer aes key bundle", will require magic-public-key');
        $key_fetcher = new MagicEnvelope();
        $remote_keys = $key_fetcher->getKeyPair($target, true); // actually just gets the public key
        $enc_outer = $remote_keys->publicKey->encrypt($outer_bundle);

        /**
         * Construct the following JSON object, which I shall refer to as
         * the “encrypted header json object”:
         *  {
         *      "aes_key": ((base64-encoded encrypted outer aes key bundle)),
         *      "ciphertext": ((base64-encoded ciphertextm from above))
         *  }
         */
        $enc_header = json_encode(array(
                            'aes_key' => base64_encode($enc_outer),
                            'ciphertext' => base64_encode($ciphertext),
                        ));

        /**
         * Construct the xml snippet:
         *  <encrypted_header>((base64-encoded encrypted header json object))</encrypted_header>
         */
        $xs->element('encrypted_header', null, base64_encode($enc_header));

        /**
         * In order to prepare the payload message for inclusion in your
         * salmon slap, you will:
         *
         * 1. Encrypt the payload message using the aes-256-cbc cipher and
         *      the “inner encryption key” and “inner encryption iv” you
         *      chose earlier.
         * 2. Base64-encode the encrypted payload message.
         */
        $payload = $inner_key->encrypt($magic_env->getData());
        $magic_env->signMessage(base64_encode($payload), 'application/xml');


        // Since we have to change the content of me:data we'll just write the
        // whole thing from scratch. We _could_ otherwise have just manipulated
        // that element and added the encrypted_header in the EndMagicEnvelopeToXML event.
        $xs->elementStart('me:env', array('xmlns:me' => MagicEnvelope::NS));
        $xs->element('me:data', array('type' => $magic_env->getDataType()), $magic_env->getData());
        $xs->element('me:encoding', null, $magic_env->getEncoding());
        $xs->element('me:alg', null, $magic_env->getSignatureAlgorithm());
        $xs->element('me:sig', null, $magic_env->getSignature());
        $xs->elementEnd('me:env');

        $xs->elementEnd('entry');

        return false;
    }

    public function onSalmonSlap($endpoint_uri, MagicEnvelope $magic_env, Profile $target=null)
    {
        $envxml = $magic_env->toXML($target, 'diaspora');

        // Diaspora wants another POST format (base64url-encoded POST variable 'xml')
        $headers = array('Content-Type: application/x-www-form-urlencoded');

        // Another way to distinguish Diaspora from GNU social is that a POST with
        // $headers=array('Content-Type: application/magic-envelope+xml') would return
        // HTTP status code 422 Unprocessable Entity, at least as of 2015-10-04.
        try {
            $client = new HTTPClient();
            $client->setBody('xml=' . Magicsig::base64_url_encode($envxml));
            $response = $client->post($endpoint_uri, $headers);
        } catch (HTTP_Request2_Exception $e) {
            common_log(LOG_ERR, "Diaspora-flavoured Salmon post to $endpoint_uri failed: " . $e->getMessage());
            return false;
        }

        // 200 OK is the best response
        // 202 Accepted is what we get from Diaspora for example
        if (!in_array($response->getStatus(), array(200, 202))) {
            common_log(LOG_ERR, sprintf('Salmon (from profile %d) endpoint %s returned status %s: %s',
                                $magic_env->getActor()->getID(), $endpoint_uri, $response->getStatus(), $response->getBody()));
            return true;
        }

        // Success!
        return false;
    }
}
