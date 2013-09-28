<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));
define('GNUSOCIAL', true);
define('STATUSNET', true);  // compatibility

require_once INSTALLDIR . '/lib/common.php';

class MagicEnvelopeTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test that MagicEnvelope builds the correct plaintext for signing.
     * @dataProvider provider
     */
    public function testSignatureText($env, $expected)
    {
        $magic = new MagicEnvelope;
        $text = $magic->signingText($env);

        $this->assertEquals($expected, $text, "'$text' should be '$expected'");
    }

    static public function provider()
    {
        return array(
            array(
                // Sample case given in spec:
                // http://salmon-protocol.googlecode.com/svn/trunk/draft-panzer-magicsig-00.html#signing
                array(
                    'data' => 'Tm90IHJlYWxseSBBdG9t',
                    'data_type' => 'application/atom+xml',
                    'encoding' => 'base64url',
                    'alg' => 'RSA-SHA256'
                ),
                'Tm90IHJlYWxseSBBdG9t.YXBwbGljYXRpb24vYXRvbSt4bWw=.YmFzZTY0dXJs.UlNBLVNIQTI1Ng=='
            )
        );
    }


    /**
     * Test that MagicEnvelope builds the correct plaintext for signing.
     * @dataProvider provider
     */
    public function testSignatureTextCompat($env, $expected)
    {
        // Our old code didn't add the extra fields, just used the armored text.
        $alt = $env['data'];

        $magic = new MagicEnvelopeCompat;
        $text = $magic->signingText($env);

        $this->assertEquals($alt, $text, "'$text' should be '$alt'");
    }

}
