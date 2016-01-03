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

    protected $actor = null;    // Profile of user who has signed the envelope

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
    public function __construct($xml=null, Profile $actor=null) {
        if (!empty($xml)) {
            $dom = new DOMDocument();
            if (!$dom->loadXML($xml)) {
                throw new ServerException('Tried to load malformed XML as DOM');
            } elseif (!$this->fromDom($dom)) {
                throw new ServerException('Could not load MagicEnvelope from DOM');
            }
        } elseif ($actor instanceof Profile) {
            // So far we only allow setting with _either_ $xml _or_ $actor as that's
            // all our circumstances require. But it may be confusing for new developers.
            // The idea is that feeding XML must be followed by interpretation and then
            // running $magic_env->verify($profile), just as in SalmonAction->prepare(...)
            // and supplying an $actor (which right now has to be a User) will require
            // defining the $data, $data_type etc. attributes manually afterwards before
            // signing the envelope..
            $this->setActor($actor);
        }
    }

    /**
     * Retrieve Salmon keypair first by checking local database, but
     * if it's not found, attempt discovery if it has been requested.
     *
     * @param Profile $profile      The profile we're looking up keys for.
     * @param boolean $discovery    Network discovery if no local cache?
     */
    public function getKeyPair(Profile $profile, $discovery=false) {
        if (!$profile->isLocal()) common_debug('Getting magic-public-key for non-local profile id=='.$profile->getID());
        $magicsig = Magicsig::getKV('user_id', $profile->getID());

        if ($discovery && !$magicsig instanceof Magicsig) {
            if (!$profile->isLocal()) common_debug('magic-public-key not found, will do discovery for profile id=='.$profile->getID());
            // Throws exception on failure, but does not try to _load_ the keypair string.
            $keypair = $this->discoverKeyPair($profile);

            $magicsig = new Magicsig();
            $magicsig->user_id = $profile->getID();
            $magicsig->importKeys($keypair);
            // save the public key for this profile in our database.
            // TODO: If the profile generates a new key remotely, we must be able to replace
            //       this (of course after callback-verification).
            $magicsig->insert();
        } elseif (!$magicsig instanceof Magicsig) { // No discovery request, so we'll give up.
            throw new ServerException(sprintf('No public key found for profile (id==%d)', $profile->id));
        }

        assert($magicsig->publicKey instanceof Crypt_RSA);

        return $magicsig;
    }

    /**
     * Get the Salmon keypair from a URI, uses XRD Discovery etc. Reasonably
     * you'll only get the public key ;)
     *
     * The string will (hopefully) be formatted as described in Magicsig specification:
     * https://salmon-protocol.googlecode.com/svn/trunk/draft-panzer-magicsig-01.html#anchor13
     *
     * @return string formatted as Magicsig keypair
     */
    public function discoverKeyPair(Profile $profile)
    {
        $signer_uri = $profile->getUri();
        if (empty($signer_uri)) {
            throw new ServerException(sprintf('Profile missing URI (id==%d)', $profile->getID()));
        }

        $disco = new Discovery();

        // Throws exception on lookup problems
        try {
            $xrd = $disco->lookup($signer_uri);
        } catch (Exception $e) {
            // Diaspora seems to require us to request the acct: uri
            $xrd = $disco->lookup($profile->getAcctUri());
        }

        common_debug('Will try to find magic-public-key from XRD of profile id=='.$profile->getID());
        $pubkey = null;
        if (Event::handle('MagicsigPublicKeyFromXRD', array($xrd, &$pubkey))) {
            $link = $xrd->get(Magicsig::PUBLICKEYREL);
            if (is_null($link)) {
                // TRANS: Exception.
                throw new Exception(_m('Unable to locate signer public key.'));
            }
            $pubkey = $link->href;
        }
        if (empty($pubkey)) {
            throw new ServerException('Empty Magicsig public key. A bug?');
        }

        // We have a public key element, let's hope it has proper key data.
        $keypair = false;
        $parts = explode(',', $pubkey);
        if (count($parts) == 2) {
            $keypair = $parts[1];
        } else {
            // Backwards compatibility check for separator bug in 0.9.0
            $parts = explode(';', $pubkey);
            if (count($parts) == 2) {
                $keypair = $parts[1];
            }
        }

        if ($keypair === false) {
            // For debugging clarity. Keypair did not pass count()-check above. 
            // TRANS: Exception when public key was not properly formatted.
            throw new Exception(_m('Incorrectly formatted public key element.'));
        }

        return $keypair;
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
     *
     * @return MagicEnvelope object with all properties set
     *
     * @throws Exception of various kinds on signing failure
     */
    public function signMessage($text, $mimetype)
    {
        if (!$this->actor instanceof Profile) {
            throw new ServerException('No profile to sign message with is set.');
        } elseif (!$this->actor->isLocal()) {
            throw new ServerException('Cannot sign magic envelopes with remote users since we have no private key.');
        }

        // Find already stored key
        $magicsig = Magicsig::getKV('user_id', $this->actor->getID());
        if (!$magicsig instanceof Magicsig) {
            // and if it doesn't exist, it is time to create one!
            $magicsig = Magicsig::generate($this->actor->getUser());
        }
        assert($magicsig instanceof Magicsig);
        assert($magicsig->privateKey instanceof Crypt_RSA);

        // Prepare text and metadata for signing
        $this->data = Magicsig::base64_url_encode($text);
        $this->data_type = $mimetype;
        $this->encoding  = self::ENCODING;
        $this->alg       = $magicsig->getName();

        // Get the actual signature
        $this->sig = $magicsig->sign($this->signingText());
    }

    /**
     * Create an <me:env> XML representation of the envelope.
     *
     * @return string representation of XML document
     */
    public function toXML(Profile $target=null, $flavour=null) {
        $xs = new XMLStringer();
        $xs->startXML();    // header, to point out it's not HTML or anything...
        if (Event::handle('StartMagicEnvelopeToXML', array($this, $xs, $flavour, $target))) {
            // fall back to our default, normal Magic Envelope XML.
            // the $xs element _may_ have had elements added, or could get in the end event
            $xs->elementStart('me:env', array('xmlns:me' => self::NS));
            $xs->element('me:data', array('type' => $this->data_type), $this->data);
            $xs->element('me:encoding', null, $this->encoding);
            $xs->element('me:alg', null, $this->alg);
            $xs->element('me:sig', null, $this->getSignature());
            $xs->elementEnd('me:env');

            Event::handle('EndMagicEnvelopeToXML', array($this, $xs, $flavour, $target));
        }
        return $xs->getString();
    }

    /*
     * Extract the contained XML payload, and insert a copy of the envelope
     * signature data as an <me:provenance> section.
     *
     * @return DOMDocument of Atom entry
     *
     * @fixme in case of XML parsing errors, this will spew to the error log or output
     */
    public function getPayload()
    {
        $dom = new DOMDocument();
        if (!$dom->loadXML(Magicsig::base64_url_decode($this->data))) {
            throw new ServerException('Malformed XML in Salmon payload');
        }

        switch ($this->data_type) {
        case 'application/atom+xml':
            if ($dom->documentElement->namespaceURI !== Activity::ATOM
                    || $dom->documentElement->tagName !== 'entry') {
                throw new ServerException(_m('Salmon post must be an Atom entry.'));
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
            $sig = $dom->createElementNS(self::NS, 'me:sig', $this->getSignature());
            $prov->appendChild($sig);
    
            $dom->documentElement->appendChild($prov);
            break;
        default:
            throw new ServerException('Unknown Salmon payload data type');
        }
        return $dom;
    }

    public function getSignature()
    {
        if (empty($this->sig)) {
            throw new ServerException('You must first call signMessage before getSignature');
        }
        return $this->sig;
    }

    public function getSignatureAlgorithm()
    {
        return $this->alg;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getDataType()
    {
        return $this->data_type;
    }

    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * Find the author URI referenced in the payload Atom entry.
     *
     * @return string URI for author
     * @throws ServerException on failure
     */
    public function getAuthorUri() {
        $doc = $this->getPayload();

        $authors = $doc->documentElement->getElementsByTagName('author');
        foreach ($authors as $author) {
            $uris = $author->getElementsByTagName('uri');
            foreach ($uris as $uri) {
                return $uri->nodeValue;
            }
        }
        throw new ServerException('No author URI found in Salmon payload data');
    }

    /**
     * Attempt to verify cryptographic signing for parsed envelope data.
     * Requires network access to retrieve public key referenced by the envelope signer.
     *
     * Details of failure conditions are dumped to output log and not exposed to caller.
     *
     * @param Profile $profile profile used to get locally cached public signature key
     *                         or if necessary perform discovery on.
     *
     * @return boolean
     */
    public function verify(Profile $profile)
    {
        if ($this->alg != 'RSA-SHA256') {
            common_log(LOG_DEBUG, "Salmon error: bad algorithm");
            return false;
        }

        if ($this->encoding != self::ENCODING) {
            common_log(LOG_DEBUG, "Salmon error: bad encoding");
            return false;
        }

        try {
            $magicsig = $this->getKeyPair($profile, true);    // Do discovery too if necessary
        } catch (Exception $e) {
            common_log(LOG_DEBUG, "Salmon error: ".$e->getMessage());
            return false;
        }

        if (!$magicsig->verify($this->signingText(), $this->getSignature())) {
            // TRANS: Client error when incoming salmon slap signature does not verify cryptographically.
            throw new ClientException(_m('Salmon signature verification failed.'));
        }
        $this->setActor($profile);
        return true;
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

    public function setActor(Profile $actor)
    {
        if ($this->actor instanceof Profile) {
            throw new ServerException('Cannot set a new actor profile for MagicEnvelope object.');
        }
        $this->actor = $actor;
    }

    public function getActor()
    {
        if (!$this->actor instanceof Profile) {
            throw new ServerException('No actor set for this magic envelope.');
        }
        return $this->actor;
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
     * @param User $user User who cryptographically signs $text
     *
     * @return MagicEnvelope object complete with signature
     *
     * @throws Exception on bad profile input or key generation problems
     */
    public static function signAsUser($text, User $user)
    {
        $magic_env = new MagicEnvelope(null, $user->getProfile());
        $magic_env->signMessage($text, 'application/atom+xml');

        return $magic_env;
    }
}
