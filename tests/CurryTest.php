<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('GNUSOCIAL', true);
define('STATUSNET', true);  // compatibility

require_once INSTALLDIR . '/lib/common.php';

class CurryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider
     *
     */
    public function testProduction($callback, $curry_params, $call_params, $expected)
    {
        $params = array_merge(array($callback), $curry_params);
        $curried = call_user_func_array('curry', $params);
        $result = call_user_func_array($curried, $call_params);
        $this->assertEquals($expected, $result);
    }

    static public function provider()
    {
        $obj = new CurryTestHelperObj('oldval');
        return array(array(array('CurryTest', 'callback'),
                           array('curried'),
                           array('called'),
                           'called|curried'),
                     array(array('CurryTest', 'callback'),
                           array('curried1', 'curried2'),
                           array('called1', 'called2'),
                           'called1|called2|curried1|curried2'),
                     array(array('CurryTest', 'callbackObj'),
                           array($obj),
                           array('newval1'),
                           'oldval|newval1'),
                     // Confirm object identity is retained...
                     array(array('CurryTest', 'callbackObj'),
                           array($obj),
                           array('newval2'),
                           'newval1|newval2'));
    }

    static function callback()
    {
        $args = func_get_args();
        return implode("|", $args);
    }

    static function callbackObj($val, $obj)
    {
        $old = $obj->val;
        $obj->val = $val;
        return "$old|$val";
    }
}

class CurryTestHelperObj
{
    public $val='';

    function __construct($val)
    {
        $this->val = $val;
    }
}
