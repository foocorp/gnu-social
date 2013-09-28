<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('GNUSOCIAL', true);
define('STATUSNET', true);  // compatibility

require_once INSTALLDIR . '/lib/common.php';

class oEmbedTest extends PHPUnit_Framework_TestCase
{

    public function setup()
    {
        $this->old_ohembed = common_config('ohembed', 'endpoint');
    }

    public function tearDown()
    {
        $GLOBALS['config']['oembed']['endpoint'] = $this->old_ohembed;
    }

    /**
     * Test with ohembed DISABLED.
     *
     * @dataProvider discoverableSources
     */
    public function testoEmbed($url, $expectedType)
    {
        $GLOBALS['config']['oembed']['endpoint'] = false;
        $this->_doTest($url, $expectedType);
    }

    /**
     * Test with oohembed ENABLED.
     *
     * @dataProvider fallbackSources
     */
    public function testnoEmbed($url, $expectedType)
    {
        $GLOBALS['config']['oembed']['endpoint'] = $this->_endpoint();
        $this->_doTest($url, $expectedType);
    }

    /**
     * Get default oembed endpoint.
     *
     * @return string
     */
    function _endpoint()
    {
        $default = array();
        $_server = 'localhost'; $_path = '';
        require INSTALLDIR . '/lib/default.php';
        return $default['oembed']['endpoint'];
    }

    /**
     * Actually run an individual test.
     *
     * @param string $url
     * @param string $expectedType
     */
    function _doTest($url, $expectedType)
    {
        try {
            $data = oEmbedHelper::getObject($url);
            $this->assertEquals($expectedType, $data->type);
            if ($data->type == 'photo') {
                $this->assertTrue(!empty($data->url), 'Photo must have a URL.');
                $this->assertTrue(!empty($data->width), 'Photo must have a width.');
                $this->assertTrue(!empty($data->height), 'Photo must have a height.');
            } else if ($data->type == 'video') {
                $this->assertTrue(!empty($data->html), 'Video must have embedding HTML.');
                $this->assertTrue(!empty($data->thumbnail_url), 'Video should have a thumbnail.');
            }
            if (!empty($data->thumbnail_url)) {
                $this->assertTrue(!empty($data->thumbnail_width), 'Thumbnail must list a width.');
                $this->assertTrue(!empty($data->thumbnail_height), 'Thumbnail must list a height.');
            }
        } catch (Exception $e) {
            if ($expectedType == 'none') {
                $this->assertEquals($expectedType, 'none', 'Should not have data for this URL.');
            } else {
                throw $e;
            }
        }
    }

    /**
     * Sample oEmbed targets for sites we know ourselves...
     * @return array
     */
    static public function knownSources()
    {
        $sources = array(
            array('http://www.flickr.com/photos/brionv/5172500179/', 'photo'),
            array('http://yfrog.com/fy42747177j', 'photo'),
            array('http://twitpic.com/36adw6', 'photo'),
        );
        return $sources;
    }

    /**
     * Sample oEmbed targets that can be found via discovery.
     * Includes also knownSources() output.
     *
     * @return array
     */
    static public function discoverableSources()
    {
        $sources = array(

            array('http://www.youtube.com/watch?v=eUgLR232Cnw', 'video'),
            array('http://vimeo.com/9283184', 'video'),

            // Will fail discovery:
            array('http://leuksman.com/log/2010/10/29/statusnet-0-9-6-release/', 'none'),
        );
        return array_merge(self::knownSources(), $sources);
    }

    /**
     * Sample oEmbed targets that can be found via noembed.com.
     * Includes also discoverableSources() output.
     *
     * @return array
     */
    static public function fallbackSources()
    {

        $sources = array(
            array('https://github.com/git/git/commit/85e9c7e1d42849c5c3084a9da748608468310c0e', 'Github Commit'), // @fixme in future there may be a native provider -- will change to 'photo'
        );

        $sources = array();

        return array_merge(self::discoverableSources(), $sources);
    }
}
