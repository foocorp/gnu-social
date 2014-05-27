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

    protected $data      = null;    // When stored here it is _always_ base64url encoded
    protected $data_type = null;
    protected $encoding  = null;
    protected $alg       = null;
    protected $sig       = null;

    /**
     * Extract envelope data from an XML document containing an <me:env> or <me:provenance> element.
     *
     * @param string XML source
     * @return mixed associative array of envelope data, or false on unrecognized input
     *
     * @fixme will spew errors to logs or output in case of XML parse errors
     * @fixme may give fatal errors if some elements are missing or invalid XML
     * @fixme calling DOMDocument::loadXML statically triggers warnings in strict mode
     */
    public function __construct($xml=null) {
        if (!empty($xml)) {
            $dom = DOMDocument::loadXML($xml);
            if (!$dom instanceof DOMDocument) {
                throw new ServerException('Tried to load malformed XML as DOM');
            } elseif (!$this->fromDom($dom)) {
                throw new ServerException('Could not load MagicEnvelope from DOM');
            }
        }
    }

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
     * @return string
     */
    public function signingText() {
        return implode('.', array($this->data, // this field is pre-base64'd
                            Magicsig::base64_url_encode($this->data_type),
                            Magicsig::base64_url_encode($this->encoding),
                            Magicsig::base64_url_encode($this->alg)));
    }

    /**
     *
     * @param <type> $text
     * @param <type> $mimetype
     * @param Magicsig $magicsig    Magicsig with private key available.
     * @return MagicEnvelope object with all properties set
     */
    public static function signMessage($text, $mimetype, Magicsig $magicsig)
    {
        $magic_env = new MagicEnvelope();

        // Prepare text and metadata for signing
        $magic_env->data      = Magicsig::base64_url_encode($text);
        $magic_env->data_type = $mimetype;
        $magic_env->encoding  = self::ENCODING;
        $magic_env->alg       = $magicsig->getName();
        // Get the actual signature
        $magic_env->sig = $magicsig->sign($magic_env->signingText());

        return $magic_env;
    }

    /**
     * Create an <me:env> XML representation of the envelope.
     *
     * @return string representation of XML document
     */
    public function toXML() {
        $xs = new XMLStringer();
        $xs->startXML();
        $xs->elementStart('me:env', array('xmlns:me' => self::NS));
        $xs->element('me:data', array('type' => $this->data_type), $this->data);
        $xs->element('me:encoding', null, $this->encoding);
        $xs->element('me:alg', null, $this->alg);
        $xs->element('me:sig', null, $this->sig);
        $xs->elementEnd('me:env');

        $string =  $xs->getString();
        common_debug('MagicEnvelope XML: ' . $string);
        return $string;
    }

    /**
     * Extract the contained XML payload, and insert a copy of the envelope
     * signature data as an <me:provenance> section.
     *
     * @return string representation of modified XML document
     *
     * @fixme in case of XML parsing errors, this will spew to the error log or output
     */
    public function unfold()
    {
        $dom = new DOMDocument();
        $dom->loadXML(Magicsig::base64_url_decode($this->data));

        if ($dom->documentElement->tagName != 'entry') {
            return false;
        }

        $prov = $dom->createElementNS(self::NS, 'me:provenance');
        $prov->setAttribute('xmlns:me', self::NS);
        $data = $dom->createElementNS(self::NS, 'me:data', $this->data);
        $data->setAttribute('type', $this->data_type);
        $prov->appendChild($data);
        $enc = $dom->createElementNS(self::NS, 'me:encoding', $this->encoding);
        $prov->appendChild($enc);
        $alg = $dom->createElementNS(self::NS, 'me:alg', $this->alg);
        $prov->appendChild($alg);
        $sig = $dom->createElementNS(self::NS, 'me:sig', $this->sig);
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
     * @return boolean
     */
    public function verify()
    {
        if ($this->alg != 'RSA-SHA256') {
            common_log(LOG_DEBUG, "Salmon error: bad algorithm");
            return false;
        }

        if ($this->encoding != self::ENCODING) {
            common_log(LOG_DEBUG, "Salmon error: bad encoding");
            return false;
        }

        // $this->data is base64_url_encoded and should contain XML which is passed to getAuthorUri
        $text = Magicsig::base64_url_decode($this->data);
        $signer_uri = $this->getAuthorUri($text);

        try {
            $magicsig = $this->getKeyPair($signer_uri);
        } catch (Exception $e) {
            common_log(LOG_DEBUG, "Salmon error: ".$e->getMessage());
            return false;
        }

        return $magicsig->verify($this->signingText(), $this->sig);
    }

    /**
     * Extract envelope data from an XML document containing an <me:env> or <me:provenance> element.
     *
     * @param DOMDocument $dom
     * @return mixed associative array of envelope data, or false on unrecognized input
     *
     * @fixme may give fatal errors if some elements are missing
     */
    protected function fromDom(DOMDocument $dom)
    {
        $env_element = $dom->getElementsByTagNameNS(self::NS, 'env')->item(0);
        if (!$env_element) {
            $env_element = $dom->getElementsByTagNameNS(self::NS, 'provenance')->item(0);
        }

        if (!$env_element) {
            return false;
        }

        $data_element = $env_element->getElementsByTagNameNS(self::NS, 'data')->item(0);
        $sig_element = $env_element->getElementsByTagNameNS(self::NS, 'sig')->item(0);

        $this->data      = preg_replace('/\s/', '', $data_element->nodeValue);
        $this->data_type = $data_element->getAttribute('type');
        $this->encoding  = $env_element->getElementsByTagNameNS(self::NS, 'encoding')->item(0)->nodeValue;
        $this->alg       = $env_element->getElementsByTagNameNS(self::NS, 'alg')->item(0)->nodeValue;
        $this->sig       = preg_replace('/\s/', '', $sig_element->nodeValue);
        return true;
    }

    /**
     * Encode the given string as a signed MagicEnvelope XML document,
     * using the keypair for the given local user profile. We can of
     * course not sign a remote profile's slap, since we don't have the
     * private key.
     *
     * Side effects: will create and store a keypair on-demand if one
     * hasn't already been generated for this user. This can be very slow
     * on some systems.
     *
     * @param string $text XML fragment to sign, assumed to be Atom
     * @param Profile $actor Profile of a local user to use as signer
     *
     * @return string XML string representation of magic envelope
     *
     * @throws Exception on bad profile input or key generation problems
     * @fixme if signing fails, this seems to return the original text without warning. Is there a reason for this?
     */
    public static function signForProfile($text, Profile $actor)
    {
        // We only generate keys for our local users of course, so let
        // getUser throw an exception if the profile is not local.
        $user = $actor->getUser();

        // Find already stored key
        $magicsig = Magicsig::getKV('user_id', $user->id);
        if (!$magicsig instanceof Magicsig) {
            // No keypair yet, let's generate one.
            $magicsig = new Magicsig();
            $magicsig->generate($user->id);
        }

        $magic_env = self::signMessage($text, 'application/atom+xml', $magicsig);

        assert($magic_env instanceof MagicEnvelope);

        return $magic_env;
    }
}
