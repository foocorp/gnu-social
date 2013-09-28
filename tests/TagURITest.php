<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('GNUSOCIAL', true);
define('STATUSNET', true);  // compatibility

require_once INSTALLDIR . '/lib/common.php';

$config['site']['server'] = 'example.net';
$config['site']['path']   = '/apps/statusnet';

class TagURITest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider
     */
    public function testProduction($format, $args, $uri)
    {
        $minted = call_user_func_array(array('TagURI', 'mint'),
                                       array_merge(array($format), $args));

        $this->assertEquals($uri, $minted);
    }

    static public function provider()
    {
        return array(array('favorite:%d:%d',
                           array(1, 3),
                           'tag:example.net,'.date('Y-m-d').':apps:statusnet:favorite:1:3'));
    }
}

