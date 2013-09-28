<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('GNUSOCIAL', true);
define('STATUSNET', true);  // compatibility

require_once INSTALLDIR . '/lib/common.php';

class UUIDTest extends PHPUnit_Framework_TestCase
{
    public function testGenerate()
    {
        $result = UUID::gen();
        $this->assertRegExp('/^[0-9a-z]{8}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{12}$/',
                            $result);
        // Check version number
        $this->assertEquals(0x4000, hexdec(substr($result, 14, 4)) & 0xF000);
        $this->assertEquals(0x8000, hexdec(substr($result, 19, 4)) & 0xC000);
    }

    public function testUnique()
    {
        $reps = 100;
        $ids = array();

        for ($i = 0; $i < $reps; $i++) {
            $ids[] = UUID::gen();
        }

        $this->assertEquals(count($ids), count(array_unique($ids)), "UUIDs must be unique");
    }
}

