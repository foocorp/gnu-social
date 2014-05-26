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

    /**
     * Get the Salmon keypair from a URI, uses XRD Discovery etc.
     *
     * @return Magicsig with loaded keypair
     */
    public function getKeyPair($signer_uri)
    {
        $disco = new Discovery();

        // Throws exception on lookup problems
        $xrd = $disco->lookup($signer_uri);

        $link = $xrd->get(Magicsig::PUBLICKEYREL);
        if (is_null($link)) {
            // TRANS: Exception.
            throw new Exception(_m('Unable to locate signer public key.'));
        }

        // We have a public key element, let's hope it has proper key data.
        $keypair = false;
        $parts = explode(',', $link->href);
        if (count($parts) == 2) {
            $keypair = $parts[1];
        } else {
            // Backwards compatibility check for separator bug in 0.9.0
            $parts = explode(';', $link->href);
            if (count($parts) == 2) {
                $keypair = $parts[1];
            }
        }

        if ($keypair === false) {
            // For debugging clarity. Keypair did not pass count()-check above. 
            // TRANS: Exception when public key was not properly formatted.
            throw new Exception(_m('Incorrectly formatted public key element.'));
        }

        $magicsig = Magicsig::fromString($keypair);
        if (!$magicsig instanceof Magicsig) {
            common_debug('Salmon error: unable to parse keypair: '.var_export($keypair,true));
            // TRANS: Exception when public key was properly formatted but not parsable.
            throw new ServerException(_m('Retrieved Salmon keypair could not be parsed.'));
        }

        return $magicsig;
    }

    /**
     * The current MagicEnvelope spec as used in StatusNet 0.9.7 and later
     * includes both the original data and some signing metadata fields as
     * the input plaintext for the signature hash.
     *
     * @param array $env
     * @return string
     */
    public function signingText($env) {
        return implode('.', array($env['data'], // this field is pre-base64'd
                            Magicsig::base64_url_encode($env['data_type']),
                            Magicsig::base64_url_encode($env['encoding']),
                            Magicsig::base64_url_encode($env['alg'])));
    }

    /**
     *
     * @param <type> $text
     * @param <type> $mimetype
     * @param <type> $keypair
     * @return array: associative array of envelope properties
     * @fixme it might be easier to work with storing envelope data these in the object instead of passing arrays around
     */
    public function signMessage($text, $mimetype, $keypair)
    {
        $signature_alg = Magicsig::fromString($keypair);
        $armored_text = Magicsig::base64_url_encode($text);
        $env = array(
            'data' => $armored_text,
            'encoding' => MagicEnvelope::ENCODING,
            'data_type' => $mimetype,
            'sig' => '',
            'alg' => $signature_alg->getName()
        );

        $env['sig'] = $signature_alg->sign($this->signingText($env));

        return $env;
    }

    /**
     * Create an <me:env> XML representation of the envelope.
     *
     * @param array $env associative array with envelope data
     * @return string representation of XML document
     * @fixme it might be easier to work with storing envelope data these in the object instead of passing arrays around
     */
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

    /**
     * Extract the contained XML payload, and insert a copy of the envelope
     * signature data as an <me:provenance> section.
     *
     * @param array $env associative array with envelope data
     * @return string representation of modified XML document
     *
     * @fixme in case of XML parsing errors, this will spew to the error log or output
     * @fixme it might be easier to work with storing envelope data these in the object instead of passing arrays around
     */
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

    /**
     * Find the author URI referenced in the given Atom entry.
     *
     * @param string $text string containing Atom entry XML
     * @return mixed URI string or false if XML parsing fails, or null if no author URI can be found
     *
     * @fixme XML parsing failures will spew to error logs/output
     */
    public function getAuthorUri($text) {
        $doc = new DOMDocument();
        if (!$doc->loadXML($text)) {
            return FALSE;
        }

        if ($doc->documentElement->tagName == 'entry') {
            $authors = $doc->documentElement->getElementsByTagName('author');
            foreach ($authors as $author) {
                $uris = $author->getElementsByTagName('uri');
                foreach ($uris as $uri) {
                    return $uri->nodeValue;
                }
            }
        }
    }

    /**
     * Attempt to verify cryptographic signing for parsed envelope data.
     * Requires network access to retrieve public key referenced by the envelope signer.
     *
     * Details of failure conditions are dumped to output log and not exposed to caller.
     *
     * @param array $env array representation of magic envelope data, as returned from MagicEnvelope::parse()
     * @return boolean
     *
     * @fixme it might be easier to work with storing envelope data these in the object instead of passing arrays around
     */
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
        $signer_uri = $this->getAuthorUri($text);

        try {
            $magicsig = $this->getKeyPair($signer_uri);
        } catch (Exception $e) {
            common_log(LOG_DEBUG, "Salmon error: ".$e->getMessage());
            return false;
        }

        return $magicsig->verify($this->signingText($env), $env['sig']);
    }

    /**
     * Extract envelope data from an XML document containing an <me:env> or <me:provenance> element.
     *
     * @param string XML source
     * @return mixed associative array of envelope data, or false on unrecognized input
     *
     * @fixme it might be easier to work with storing envelope data these in the object instead of passing arrays around
     * @fixme will spew errors to logs or output in case of XML parse errors
     * @fixme may give fatal errors if some elements are missing or invalid XML
     * @fixme calling DOMDocument::loadXML statically triggers warnings in strict mode
     */
    public function parse($text)
    {
        $dom = DOMDocument::loadXML($text);
        return $this->fromDom($dom);
    }

    /**
     * Extract envelope data from an XML document containing an <me:env> or <me:provenance> element.
     *
     * @param DOMDocument $dom
     * @return mixed associative array of envelope data, or false on unrecognized input
     *
     * @fixme it might be easier to work with storing envelope data these in the object instead of passing arrays around
     * @fixme may give fatal errors if some elements are missing
     */
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
