<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('STATUSNET', true);

require_once INSTALLDIR . '/lib/common.php';

class UserRightsTest extends PHPUnit_Framework_TestCase
{
    protected $user = null;

    function setUp()
    {
        $user = User::getKV('nickname', 'userrightstestuser');
        if ($user) {
            // Leftover from a broken test run?
            $profile = $user->getProfile();
            $user->delete();
            $profile->delete();
        }
        $this->user = User::register(array('nickname' => 'userrightstestuser'));
        if (!$this->user) {
            throw new Exception("Couldn't register userrightstestuser");
        }
    }

    function tearDown()
    {
        if ($this->user) {
            $profile = $this->user->getProfile();
            $this->user->delete();
            $profile->delete();
        }
    }

    function testInvalidRole()
    {
        $this->assertFalse($this->user->hasRole('invalidrole'));
    }

    function standardRoles()
    {
        return array(array('admin'),
                     array('moderator'));
    }

    /**
     * @dataProvider standardRoles
     *
     */

    function testUngrantedRole($role)
    {
        $this->assertFalse($this->user->hasRole($role));
    }

    /**
     * @dataProvider standardRoles
     *
     */

    function testGrantedRole($role)
    {
        $this->user->grantRole($role);
        $this->assertTrue($this->user->hasRole($role));
    }
}