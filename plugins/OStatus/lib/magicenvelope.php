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

class MagicEnvelope
{
    const ENCODING = 'base64url';

    const NS = 'http://salmon-protocol.org/ns/magic-env';

    private function normalizeUser($user_id)
    {
        if (substr($user_id, 0, 5) == 'http:' ||
            substr($user_id, 0, 6) == 'https:' ||
            substr($user_id, 0, 5) == 'acct:') {
            return $user_id;
        }

        if (strpos($user_id, '@') !== FALSE) {
            return 'acct:' . $user_id;
        }

        return 'http://' . $user_id;
    }

    public function getKeyPair($signer_uri)
    {
        $disco = new Discovery();

        try {
            $xrd = $disco->lookup($signer_uri);
        } catch (Exception $e) {
            return false;
        }
        if ($xrd->links) {
            if ($link = Discovery::getService($xrd->links, Magicsig::PUBLICKEYREL)) {
                $keypair = false;
                $parts = explode(',', $link['href']);
                if (count($parts) == 2) {
                    $keypair = $parts[1];
                } else {
                    // Backwards compatibility check for separator bug in 0.9.0
                    $parts = explode(';', $link['href']);
                    if (count($parts) == 2) {
                        $keypair = $parts[1];
                    }
                }

                if ($keypair) {
                    return $keypair;
                }
            }
        }
        // TRANS: Exception.
        throw new Exception(_m('Unable to locate signer public key.'));
    }


    public function signMessage($text, $mimetype, $keypair)
    {
        $signature_alg = Magicsig::fromString($keypair);
        $armored_text = Magicsig::base64_url_encode($text);

        return array(
            'data' => $armored_text,
            'encoding' => MagicEnvelope::ENCODING,
            'data_type' => $mimetype,
            'sig' => $signature_alg->sign($armored_text),
            'alg' => $signature_alg->getName()
        );
    }

    public function toXML($env) {
        $xs = new XMLStringer();
        $xs->startXML();
        $xs->elementStart('me:env', array('xmlns:me' => MagicEnvelope::NS));
        $xs->element('me:data', array('type' => $env['data_type']), $env['data']);
        $xs->element('me:encoding', null, $env['encoding']);
        $xs->element('me:alg', null, $env['alg']);
        $xs->element('me:sig', null, $env['sig']);
        $xs->elementEnd('me:env');

        $string =  $xs->getString();
        common_debug($string);
        return $string;
    }

    public function unfold($env)
    {
        $dom = new DOMDocument();
        $dom->loadXML(Magicsig::base64_url_decode($env['data']));

        if ($dom->documentElement->tagName != 'entry') {
            return false;
        }

        $prov = $dom->createElementNS(MagicEnvelope::NS, 'me:provenance');
        $prov->setAttribute('xmlns:me', MagicEnvelope::NS);
        $data = $dom->createElementNS(MagicEnvelope::NS, 'me:data', $env['data']);
        $data->setAttribute('type', $env['data_type']);
        $prov->appendChild($data);
        $enc = $dom->createElementNS(MagicEnvelope::NS, 'me:encoding', $env['encoding']);
        $prov->appendChild($enc);
        $alg = $dom->createElementNS(MagicEnvelope::NS, 'me:alg', $env['alg']);
        $prov->appendChild($alg);
        $sig = $dom->createElementNS(MagicEnvelope::NS, 'me:sig', $env['sig']);
        $prov->appendChild($sig);

        $dom->documentElement->appendChild($prov);

        return $dom->saveXML();
    }

    public function getAuthor($text) {
        $doc = new DOMDocument();
        if (!$doc->loadXML($text)) {
            return FALSE;
        }

        if ($doc->documentElement->tagName == 'entry') {
            $authors = $doc->documentElement->getElementsByTagName('author');
            foreach ($authors as $author) {
                $uris = $author->getElementsByTagName('uri');
                foreach ($uris as $uri) {
                    return $this->normalizeUser($uri->nodeValue);
                }
            }
        }
    }

    public function checkAuthor($text, $signer_uri)
    {
        return ($this->getAuthor($text) == $signer_uri);
    }

    public function verify($env)
    {
        if ($env['alg'] != 'RSA-SHA256') {
            common_log(LOG_DEBUG, "Salmon error: bad algorithm");
            return false;
        }

        if ($env['encoding'] != MagicEnvelope::ENCODING) {
            common_log(LOG_DEBUG, "Salmon error: bad encoding");
            return false;
        }

        $text = Magicsig::base64_url_decode($env['data']);
        $signer_uri = $this->getAuthor($text);

        try {
            $keypair = $this->getKeyPair($signer_uri);
        } catch (Exception $e) {
            common_log(LOG_DEBUG, "Salmon error: ".$e->getMessage());
            return false;
        }

        $verifier = Magicsig::fromString($keypair);

        if (!$verifier) {
            common_log(LOG_DEBUG, "Salmon error: unable to parse keypair");
            return false;
        }

        return $verifier->verify($env['data'], $env['sig']);
    }

    public function parse($text)
    {
        $dom = DOMDocument::loadXML($text);
        return $this->fromDom($dom);
    }

    public function fromDom($dom)
    {
        $env_element = $dom->getElementsByTagNameNS(MagicEnvelope::NS, 'env')->item(0);
        if (!$env_element) {
            $env_element = $dom->getElementsByTagNameNS(MagicEnvelope::NS, 'provenance')->item(0);
        }

        if (!$env_element) {
            return false;
        }

        $data_element = $env_element->getElementsByTagNameNS(MagicEnvelope::NS, 'data')->item(0);
        $sig_element = $env_element->getElementsByTagNameNS(MagicEnvelope::NS, 'sig')->item(0);
        return array(
            'data' => preg_replace('/\s/', '', $data_element->nodeValue),
            'data_type' => $data_element->getAttribute('type'),
            'encoding' => $env_element->getElementsByTagNameNS(MagicEnvelope::NS, 'encoding')->item(0)->nodeValue,
            'alg' => $env_element->getElementsByTagNameNS(MagicEnvelope::NS, 'alg')->item(0)->nodeValue,
            'sig' => preg_replace('/\s/', '', $sig_element->nodeValue),
        );
    }
}
