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
    public function testSignatureText(MagicEnvelope $env, $expected)
    {
        $text = $env->signingText();

        $this->assertEquals($expected, $text, "'$text' should be '$expected'");
    }

    static public function provider()
    {
        // Sample case given in spec:
        // http://salmon-protocol.googlecode.com/svn/trunk/draft-panzer-magicsig-00.html#signing
        $magic_env = new MagicEnvelope();
        $magic_env->data = 'Tm90IHJlYWxseSBBdG9t';
        $magic_env->data_type = 'application/atom+xml';
        $magic_env->encoding = 'base64url';
        $magic_env->alg = 'RSA-SHA256';

        return array(
            array(
                $magic_env,
                'Tm90IHJlYWxseSBBdG9t.YXBwbGljYXRpb24vYXRvbSt4bWw=.YmFzZTY0dXJs.UlNBLVNIQTI1Ng=='
            )
        );
    }
}
