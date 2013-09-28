<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));
define('GNUSOCIAL', true);
define('STATUSNET', true);  // compatibility

require_once INSTALLDIR . '/lib/common.php';
require_once INSTALLDIR . '/plugins/OStatus/lib/feeddiscovery.php';

class FeedDiscoveryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider
     *
     */
    public function testProduction($url, $html, $expected)
    {
        $sub = new FeedDiscovery();
        $url = $sub->discoverFromHTML($url, $html);
        $this->assertEquals($expected, $url);
    }

    static public function provider()
    {
        $sampleHeader = <<<END
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head profile="http://gmpg.org/xfn/11">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

<title>leŭksman  </title>

<meta name="generator" content="WordPress 2.8.6" /> <!-- leave this for stats -->

<link rel="stylesheet" href="http://leuksman.com/log/wp-content/themes/leuksman/style.css" type="text/css" media="screen" />
<link rel="alternate" type="application/rss+xml" title="leŭksman RSS Feed" href="http://leuksman.com/log/feed/" />
<link rel="pingback" href="http://leuksman.com/log/xmlrpc.php" />

<meta name="viewport" content="width = 640" />

<xmeta name="viewport" content="initial-scale=2.3, user-scalable=no" />

<style type="text/css" media="screen">

	#page { background: url("http://leuksman.com/log/wp-content/themes/leuksman/images/kubrickbg.jpg") repeat-y top; border: none; }

</style>

<link rel="EditURI" type="application/rsd+xml" title="RSD" href="http://leuksman.com/log/xmlrpc.php?rsd" />
<link rel="wlwmanifest" type="application/wlwmanifest+xml" href="http://leuksman.com/log/wp-includes/wlwmanifest.xml" />
<link rel='index' title='leŭksman' href='http://leuksman.com/log' />
<meta name="generator" content="WordPress 2.8.6" />
</head>
<body>
</body>
</html>
END;
        return array(
                     array('http://example.com/',
                           '<html><link rel="alternate" href="http://example.com/feed/rss" type="application/rss+xml">',
                           'http://example.com/feed/rss'),
                     array('http://example.com/atom',
                           '<html><link rel="alternate" href="http://example.com/feed/atom" type="application/atom+xml">',
                           'http://example.com/feed/atom'),
                     array('http://example.com/empty',
                           '<html><link rel="alternate" href="http://example.com/index.pdf" type="application/pdf">',
                           false),
                     array('http://example.com/tagsoup',
                           '<body><pre><LINK rel=alternate hRef=http://example.com/feed/rss type=application/rss+xml><fnork',
                           'http://example.com/feed/rss'),
                     // 'rel' attribute must be lowercase, alone per http://www.rssboard.org/rss-autodiscovery
                     // but we're going to be liberal in what we receive.
                     array('http://example.com/tagsoup2',
                           '<body><pre><LINK rel=" feeders    alternate 467" hRef=http://example.com/feed/rss type=application/rss+xml><fnork',
                           'http://example.com/feed/rss'),
                     array('http://example.com/tagsoup3',
                           '<body><pre><LINK rel=ALTERNATE hRef=http://example.com/feed/rss type=application/rss+xml><fnork',
                           false),
                     array('http://example.com/relative/link1',
                           '<html><link rel="alternate" href="/feed/rss" type="application/rss+xml">',
                           'http://example.com/feed/rss'),
                     array('http://example.com/relative/link2',
                           '<html><link rel="alternate" href="../feed/rss" type="application/rss+xml">',
                           'http://example.com/feed/rss'),
                     // This one can't resolve correctly; relative link is bogus.
                     array('http://example.com/relative/link3',
                           '<html><link rel="alternate" href="http:/feed/rss" type="application/rss+xml">',
                           'http:/feed/rss'),
                     array('http://example.com/base/link1',
                           '<html><link rel="alternate" href="/feed/rss" type="application/rss+xml"><base href="http://target.example.com/">',
                           'http://target.example.com/feed/rss'),
                     array('http://example.com/base/link2',
                           '<html><link rel="alternate" href="feed/rss" type="application/rss+xml"><base href="http://target.example.com/">',
                           'http://target.example.com/feed/rss'),
                     // This one can't resolve; relative link is bogus.
                     array('http://example.com/base/link3',
                           '<html><link rel="alternate" href="http:/feed/rss" type="application/rss+xml"><base href="http://target.example.com/">',
                           'http:/feed/rss'),
                     // Trick question! There's a <base> but no href on it
                     array('http://example.com/relative/fauxbase',
                           '<html><link rel="alternate" href="../feed/rss" type="application/rss+xml"><base target="top">',
                           'http://example.com/feed/rss'),
                     // Actual WordPress blog header example
                     array('http://leuksman.com/log/',
                           $sampleHeader,
                           'http://leuksman.com/log/feed/'));
    }
}
