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

if (!defined('STATUSNET')) {
    exit(1);
}

require_once 'Crypt/RSA.php';

class Magicsig extends Managed_DataObject
{
    const PUBLICKEYREL = 'magic-public-key';

    const DEFAULT_KEYLEN = 1024;
    const DEFAULT_SIGALG = 'RSA-SHA256';

    public $__table = 'magicsig';

    /**
     * Key to user.id/profile.id for the local user whose key we're storing.
     *
     * @var int
     */
    public $user_id;

    /**
     * Flattened string representation of the key pair; callers should
     * usually use $this->publicKey and $this->privateKey directly,
     * which hold live Crypt_RSA key objects.
     *
     * @var string
     */
    public $keypair;

    /**
     * Crypto algorithm used for this key; currently only RSA-SHA256 is supported.
     *
     * @var string
     */
    public $alg;

    /**
     * Public RSA key; gets serialized in/out via $this->keypair string.
     *
     * @var Crypt_RSA
     */
    public $publicKey;

    /**
     * PrivateRSA key; gets serialized in/out via $this->keypair string.
     *
     * @var Crypt_RSA
     */
    public $privateKey;

    public function __construct($alg=self::DEFAULT_SIGALG)
    {
        $this->alg = $alg;
    }

    /**
     * Fetch a Magicsig object from the cache or database on a field match.
     *
     * @param string $k
     * @param mixed $v
     * @return Magicsig
     */
    static function getKV($k, $v=null)
    {
        $obj =  parent::getKV($k, $v);
        if ($obj instanceof Magicsig) {
            $obj->importKeys(); // Loads Crypt_RSA objects etc.

            // Throw out a big fat warning for keys of less than 1024 bits. (
            // The only case these show up in would be imported or
            // legacy very-old-StatusNet generated keypairs.
            if (strlen($obj->publicKey->modulus->toBits()) < 1024) {
                common_log(LOG_WARNING, sprintf('Salmon key with <1024 bits (%d) belongs to profile with id==%d',
                                            strlen($obj->publicKey->modulus->toBits()),
                                            $obj->user_id));
            }
        }

        return $obj;
    }

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user id'),
                'keypair' => array('type' => 'text', 'description' => 'keypair text representation'),
                'alg' => array('type' => 'varchar', 'length' => 64, 'description' => 'algorithm'),
            ),
            'primary key' => array('user_id'),
            'foreign keys' => array(
                'magicsig_user_id_fkey' => array('profile', array('user_id' => 'id')),
            ),
        );
    }

    /**
     * Save this keypair into the database.
     *
     * Overloads default insert behavior to encode the live key objects
     * as a flat string for storage.
     *
     * @return mixed
     */
    function insert()
    {
        $this->keypair = $this->toString(true);

        return parent::insert();
    }

    /**
     * Generate a new keypair for a local user and store in the database.
     *
     * Warning: this can be very slow on systems without the GMP module.
     * Runtimes of 20-30 seconds are not unheard-of.
     *
     * FIXME: More than 1024 bits please. But StatusNet _discards_ non-1024 bits,
     *        so we'll have to wait the last mohican out before switching defaults.
     *
     * @param User $user the local user (since we don't have remote private keys)
     */
    public static function generate(User $user, $bits=self::DEFAULT_KEYLEN, $alg=self::DEFAULT_SIGALG)
    {
        $magicsig = new Magicsig($alg);
        $magicsig->user_id = $user->id;

        $rsa = new Crypt_RSA();

        $keypair = $rsa->createKey($bits);

        $magicsig->privateKey = new Crypt_RSA();
        $magicsig->privateKey->loadKey($keypair['privatekey']);

        $magicsig->publicKey = new Crypt_RSA();
        $magicsig->publicKey->loadKey($keypair['publickey']);

        $magicsig->insert();        // will do $this->keypair = $this->toString(true);
        $magicsig->importKeys();    // seems it's necessary to re-read keys from text keypair

        return $magicsig;
    }

    /**
     * Encode the keypair or public key as a string.
     *
     * @param boolean $full_pair set to true to include the private key.
     * @return string
     */
    public function toString($full_pair=false, $base64url=true)
    {
        $base64_func = $base64url ? 'Magicsig::base64_url_encode' : 'base64_encode';
        $mod = call_user_func($base64_func, $this->publicKey->modulus->toBytes());
        $exp = call_user_func($base64_func, $this->publicKey->exponent->toBytes());

        $private_exp = '';
        if ($full_pair && $this->privateKey instanceof Crypt_RSA && $this->privateKey->exponent->toBytes()) {
            $private_exp = '.' . call_user_func($base64_func, $this->privateKey->exponent->toBytes());
        }

        return 'RSA.' . $mod . '.' . $exp . $private_exp;
    }

    public function toFingerprint()
    {
        // This assumes a specific behaviour from toString, to format as such:
        //    "RSA." + base64(pubkey.modulus_as_bytes) + "." + base64(pubkey.exponent_as_bytes)
        // We don't want the base64 string to be the "url encoding" version because it is not
        // as common in programming libraries. And we want it to be base64 encoded since ASCII
        // representation avoids any problems with NULL etc. in less forgiving languages and also
        // just easier to debug...
        return strtolower(hash('sha256', $this->toString(false, false)));
    }

    public function exportPublicKey($format=CRYPT_RSA_PUBLIC_FORMAT_PKCS1)
    {
        $this->publicKey->setPublicKey();
        return $this->publicKey->getPublicKey($format);
    }

    /**
     * importKeys will load the object's keypair string, which initiates
     * loadKey() and configures Crypt_RSA objects.
     *
     * @param string $keypair optional, otherwise the object's "keypair" property will be used
     */
    public function importKeys($keypair=null)
    {
        $this->keypair = $keypair===null ? $this->keypair : preg_replace('/\s+/', '', $keypair);

        // parse components
        if (!preg_match('/RSA\.([^\.]+)\.([^\.]+)(\.([^\.]+))?/', $this->keypair, $matches)) {
            common_debug('Magicsig error: RSA key not found in provided string.');
            throw new ServerException('RSA key not found in keypair string.');
        }

        $mod = $matches[1];
        $exp = $matches[2];
        if (!empty($matches[4])) {
            $private_exp = $matches[4];
        } else {
            $private_exp = false;
        }

        $this->loadKey($mod, $exp, 'public');
        if ($private_exp) {
            $this->loadKey($mod, $private_exp, 'private');
        }
    }

    /**
     * Fill out $this->privateKey or $this->publicKey with a Crypt_RSA object
     * representing the give key (as mod/exponent pair).
     *
     * @param string $mod base64-encoded
     * @param string $exp base64-encoded exponent
     * @param string $type one of 'public' or 'private'
     */
    public function loadKey($mod, $exp, $type = 'public')
    {
        $rsa = new Crypt_RSA();
        $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
        $rsa->setHash($this->getHash());
        $rsa->modulus = new Math_BigInteger(Magicsig::base64_url_decode($mod), 256);
        $rsa->k = strlen($rsa->modulus->toBytes());
        $rsa->exponent = new Math_BigInteger(Magicsig::base64_url_decode($exp), 256);

        if ($type == 'private') {
            $this->privateKey = $rsa;
        } else {
            $this->publicKey = $rsa;
        }
    }

    /**
     * Returns the name of the crypto algorithm used for this key.
     *
     * @return string
     */
    public function getName()
    {
        return $this->alg;
    }

    /**
     * Returns the name of a hash function to use for signing with this key.
     *
     * @return string
     */
    public function getHash()
    {
        switch ($this->alg) {
        case 'RSA-SHA256':
            return 'sha256';
        }
        throw new ServerException('Unknown or unsupported hash algorithm for Salmon');
    }

    /**
     * Generate base64-encoded signature for the given byte string
     * using our private key.
     *
     * @param string $bytes as raw byte string
     * @return string base64url-encoded signature
     */
    public function sign($bytes)
    {
        $sig = $this->privateKey->sign($bytes);
        if ($sig === false) {
            throw new ServerException('Could not sign data');
        }
        return Magicsig::base64_url_encode($sig);
    }

    /**
     *
     * @param string $signed_bytes as raw byte string
     * @param string $signature as base64url encoded
     * @return boolean
     */
    public function verify($signed_bytes, $signature)
    {
        $signature = self::base64_url_decode($signature);
        return $this->publicKey->verify($signed_bytes, $signature);
    }

    /**
     * URL-encoding-friendly base64 variant encoding.
     *
     * @param string $input
     * @return string
     */
    public static function base64_url_encode($input)
    {
        return strtr(base64_encode($input), '+/', '-_');
    }

    /**
     * URL-encoding-friendly base64 variant decoding.
     *
     * @param string $input
     * @return string
     */
    public static function base64_url_decode($input)
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
