<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

// XXX: we should probably have some common source for this stuff

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('GNUSOCIAL', true);
define('STATUSNET', true);  // compatibility

require_once INSTALLDIR . '/lib/common.php';

class ActivityGenerationTests extends PHPUnit_Framework_TestCase
{
    var $author1 = null;
    var $author2 = null;

    var $targetUser1 = null;
    var $targetUser2 = null;

    var $targetGroup1 = null;
    var $targetGroup2 = null;

    function __construct()
    {
        parent::__construct();

        $authorNick1 = 'activitygenerationtestsuser' . common_random_hexstr(4);
        $authorNick2 = 'activitygenerationtestsuser' . common_random_hexstr(4);

        $targetNick1 = 'activitygenerationteststarget' . common_random_hexstr(4);
        $targetNick2 = 'activitygenerationteststarget' . common_random_hexstr(4);

        $groupNick1 = 'activitygenerationtestsgroup' . common_random_hexstr(4);
        $groupNick2 = 'activitygenerationtestsgroup' . common_random_hexstr(4);

        $this->author1 = User::register(array('nickname' => $authorNick1,
                                              'email' => $authorNick1 . '@example.net',
                                              'email_confirmed' => true));

        $this->author2 = User::register(array('nickname' => $authorNick2,
                                              'email' => $authorNick2 . '@example.net',
                                              'email_confirmed' => true));

        $this->targetUser1 = User::register(array('nickname' => $targetNick1,
                                                  'email' => $targetNick1 . '@example.net',
                                                  'email_confirmed' => true));

        $this->targetUser2 = User::register(array('nickname' => $targetNick2,
                                                  'email' => $targetNick2 . '@example.net',
                                                  'email_confirmed' => true));

        $this->targetGroup1 = User_group::register(array('nickname' => $groupNick1,
                                                         'userid' => $this->author1->id,
                                                         'aliases' => array(),
                                                         'local' => true,
                                                         'location' => null,
                                                         'description' => null,
                                                         'fullname' => null,
                                                         'homepage' => null,
                                                         'mainpage' => null));
        $this->targetGroup2 = User_group::register(array('nickname' => $groupNick2,
                                                         'userid' => $this->author1->id,
                                                         'aliases' => array(),
                                                         'local' => true,
                                                         'location' => null,
                                                         'description' => null,
                                                         'fullname' => null,
                                                         'homepage' => null,
                                                         'mainpage' => null));
    }

    public function testBasicNoticeActivity()
    {
        $notice = $this->_fakeNotice();

        $entry = $notice->asAtomEntry(true);

        $element = $this->_entryToElement($entry, false);

        $this->assertEquals($notice->getUri(), ActivityUtils::childContent($element, 'id'));
        $this->assertEquals($notice->content, ActivityUtils::childContent($element, 'title'));
        $this->assertEquals($notice->rendered, ActivityUtils::childContent($element, 'content'));
        $this->assertEquals(strtotime($notice->created), strtotime(ActivityUtils::childContent($element, 'published')));
        $this->assertEquals(strtotime($notice->created), strtotime(ActivityUtils::childContent($element, 'updated')));
        $this->assertEquals(ActivityVerb::POST, ActivityUtils::childContent($element, 'verb', Activity::SPEC));
        $this->assertEquals(ActivityObject::NOTE, ActivityUtils::childContent($element, 'object-type', Activity::SPEC));
    }

    public function testNamespaceFlag()
    {
        $notice = $this->_fakeNotice();

        $entry = $notice->asAtomEntry(true);

        $element = $this->_entryToElement($entry, false);

        $this->assertTrue($element->hasAttribute('xmlns'));
        $this->assertTrue($element->hasAttribute('xmlns:thr'));
        $this->assertTrue($element->hasAttribute('xmlns:georss'));
        $this->assertTrue($element->hasAttribute('xmlns:activity'));
        $this->assertTrue($element->hasAttribute('xmlns:media'));
        $this->assertTrue($element->hasAttribute('xmlns:poco'));
        $this->assertTrue($element->hasAttribute('xmlns:ostatus'));
        $this->assertTrue($element->hasAttribute('xmlns:statusnet'));

        $entry = $notice->asAtomEntry(false);

        $element = $this->_entryToElement($entry, true);

        $this->assertFalse($element->hasAttribute('xmlns'));
        $this->assertFalse($element->hasAttribute('xmlns:thr'));
        $this->assertFalse($element->hasAttribute('xmlns:georss'));
        $this->assertFalse($element->hasAttribute('xmlns:activity'));
        $this->assertFalse($element->hasAttribute('xmlns:media'));
        $this->assertFalse($element->hasAttribute('xmlns:poco'));
        $this->assertFalse($element->hasAttribute('xmlns:ostatus'));
        $this->assertFalse($element->hasAttribute('xmlns:statusnet'));
    }

    public function testSourceFlag()
    {
        $notice = $this->_fakeNotice();

        // Test with no source

        $entry = $notice->asAtomEntry(false, false);

        $element = $this->_entryToElement($entry, true);

        $source = ActivityUtils::child($element, 'source');

        $this->assertNull($source);

        // Test with source

        $entry = $notice->asAtomEntry(false, true);

        $element = $this->_entryToElement($entry, true);

        $source = ActivityUtils::child($element, 'source');

        $this->assertNotNull($source);
    }

    public function testSourceContent()
    {
        $notice = $this->_fakeNotice();
        // make a time difference!
        sleep(2);
        $notice2 = $this->_fakeNotice();

        $entry = $notice->asAtomEntry(false, true);

        $element = $this->_entryToElement($entry, true);

        $source = ActivityUtils::child($element, 'source');

        $atomUrl = common_local_url('ApiTimelineUser', array('id' => $this->author1->id, 'format' => 'atom'));

        $profile = $this->author1->getProfile();

        $this->assertEquals($atomUrl, ActivityUtils::childContent($source, 'id'));
        $this->assertEquals($atomUrl, ActivityUtils::getLink($source, 'self', 'application/atom+xml'));
        $this->assertEquals($profile->profileurl, ActivityUtils::getPermalink($source));
        $this->assertEquals(strtotime($notice2->created), strtotime(ActivityUtils::childContent($source, 'updated')));
        // XXX: do we care here?
        $this->assertFalse(is_null(ActivityUtils::childContent($source, 'title')));
        $this->assertEquals(common_config('license', 'url'), ActivityUtils::getLink($source, 'license'));
    }

    public function testAuthorFlag()
    {
        $notice = $this->_fakeNotice();

        // Test with no author

        $entry = $notice->asAtomEntry(false, false, false);

        $element = $this->_entryToElement($entry, true);

        $this->assertNull(ActivityUtils::child($element, 'author'));
        $this->assertNull(ActivityUtils::child($element, 'actor', Activity::SPEC));

        // Test with source

        $entry = $notice->asAtomEntry(false, false, true);

        $element = $this->_entryToElement($entry, true);

        $author = ActivityUtils::child($element, 'author');
        $actor  = ActivityUtils::child($element, 'actor', Activity::SPEC);

        $this->assertFalse(is_null($author));
        $this->assertTrue(is_null($actor)); // <activity:actor> is obsolete, no longer added
    }

    public function testAuthorContent()
    {
        $notice = $this->_fakeNotice();

        // Test with author

        $entry = $notice->asAtomEntry(false, false, true);

        $element = $this->_entryToElement($entry, true);

        $author = ActivityUtils::child($element, 'author');

        $this->assertEquals($this->author1->getNickname(), ActivityUtils::childContent($author, 'name'));
        $this->assertEquals($this->author1->getUri(), ActivityUtils::childContent($author, 'uri'));
    }

    /**
     * We no longer create <activity:actor> entries, they have merged to <atom:author>
     */
    public function testActorContent()
    {
        $notice = $this->_fakeNotice();

        // Test with author

        $entry = $notice->asAtomEntry(false, false, true);

        $element = $this->_entryToElement($entry, true);

        $actor = ActivityUtils::child($element, 'actor', Activity::SPEC);

        $this->assertEquals($actor, null);
    }

    public function testReplyLink()
    {
        $orig = $this->_fakeNotice($this->targetUser1);

        $text = "@" . $this->targetUser1->nickname . " reply text " . common_random_hexstr(4);

        $reply = Notice::saveNew($this->author1->id, $text, 'test', array('uri' => null, 'reply_to' => $orig->id));

        $entry = $reply->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $irt = ActivityUtils::child($element, 'in-reply-to', 'http://purl.org/syndication/thread/1.0');

        $this->assertNotNull($irt);
        $this->assertEquals($orig->getUri(), $irt->getAttribute('ref'));
        $this->assertEquals($orig->getUrl(), $irt->getAttribute('href'));
    }

    public function testReplyAttention()
    {
        $orig = $this->_fakeNotice($this->targetUser1);

        $text = "@" . $this->targetUser1->nickname . " reply text " . common_random_hexstr(4);

        $reply = Notice::saveNew($this->author1->id, $text, 'test', array('uri' => null, 'reply_to' => $orig->id));

        $entry = $reply->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $this->assertEquals($this->targetUser1->getUri(), ActivityUtils::getLink($element, 'mentioned'));
    }

    public function testMultipleReplyAttention()
    {
        $orig = $this->_fakeNotice($this->targetUser1);

        $text = "@" . $this->targetUser1->nickname . " reply text " . common_random_hexstr(4);

        $reply = Notice::saveNew($this->targetUser2->id, $text, 'test', array('uri' => null, 'reply_to' => $orig->id));

        $text = "@" . $this->targetUser1->nickname . " @" . $this->targetUser2->nickname . " reply text " . common_random_hexstr(4);

        $reply2 = Notice::saveNew($this->author1->id, $text, 'test', array('uri' => null, 'reply_to' => $reply->id));

        $entry = $reply2->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $links = ActivityUtils::getLinks($element, 'mentioned');

        $this->assertEquals(2, count($links));

        $hrefs = array();

        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }

        $this->assertTrue(in_array($this->targetUser1->getUri(), $hrefs));
        $this->assertTrue(in_array($this->targetUser2->getUri(), $hrefs));

        $links = ActivityUtils::getLinks($element, 'mentioned');

        $this->assertEquals(2, count($links));

        $hrefs = array();

        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }

        $this->assertTrue(in_array($this->targetUser1->getUri(), $hrefs));
        $this->assertTrue(in_array($this->targetUser2->getUri(), $hrefs));
    }

    public function testGroupPostAttention()
    {
        $text = "!" . $this->targetGroup1->nickname . " reply text " . common_random_hexstr(4);

        $notice = Notice::saveNew($this->author1->id, $text, 'test', array('uri' => null));

        $entry = $notice->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $this->assertEquals($this->targetGroup1->getUri(), ActivityUtils::getLink($element, 'mentioned'));
    }

    public function testMultipleGroupPostAttention()
    {
        $text = "!" . $this->targetGroup1->nickname . " !" . $this->targetGroup2->nickname . " reply text " . common_random_hexstr(4);

        $notice = Notice::saveNew($this->author1->id, $text, 'test', array('uri' => null));

        $entry = $notice->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $links = ActivityUtils::getLinks($element, 'mentioned');

        $this->assertEquals(2, count($links));

        $hrefs = array();

        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }

        $this->assertTrue(in_array($this->targetGroup1->getUri(), $hrefs));
        $this->assertTrue(in_array($this->targetGroup2->getUri(), $hrefs));

        $links = ActivityUtils::getLinks($element, 'mentioned');

        $this->assertEquals(2, count($links));

        $hrefs = array();

        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }

        $this->assertTrue(in_array($this->targetGroup1->getUri(), $hrefs));
        $this->assertTrue(in_array($this->targetGroup2->getUri(), $hrefs));
    }

    public function testRepeatLink()
    {
        $notice = $this->_fakeNotice($this->author1);
        $repeat = $notice->repeat($this->author2->id, 'test');

        $entry = $repeat->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $forward = ActivityUtils::child($element, 'forward', "http://ostatus.org/schema/1.0");

        $this->assertNotNull($forward);
        $this->assertEquals($notice->getUri(), $forward->getAttribute('ref'));
        $this->assertEquals($notice->getUrl(), $forward->getAttribute('href'));
    }

    public function testTag()
    {
        $tag1 = common_random_hexstr(4);

        $notice = $this->_fakeNotice($this->author1, '#' . $tag1);

        $entry = $notice->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $category = ActivityUtils::child($element, 'category');

        $this->assertNotNull($category);
        $this->assertEquals($tag1, $category->getAttribute('term'));
    }

    public function testMultiTag()
    {
        $tag1 = common_random_hexstr(4);
        $tag2 = common_random_hexstr(4);

        $notice = $this->_fakeNotice($this->author1, '#' . $tag1 . ' #' . $tag2);

        $entry = $notice->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $categories = $element->getElementsByTagName('category');

        $this->assertNotNull($categories);
        $this->assertEquals(2, $categories->length);

        $terms = array();

        for ($i = 0; $i < $categories->length; $i++) {
            $cat = $categories->item($i);
            $terms[] = $cat->getAttribute('term');
        }

        $this->assertTrue(in_array($tag1, $terms));
        $this->assertTrue(in_array($tag2, $terms));
    }

    public function testGeotaggedActivity()
    {
        $notice = Notice::saveNew($this->author1->id, common_random_hexstr(4), 'test', array('uri' => null, 'lat' => 45.5, 'lon' => -73.6));

        $entry = $notice->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $this->assertEquals('45.5 -73.6', ActivityUtils::childContent($element, 'point', "http://www.georss.org/georss"));
    }

    public function testNoticeInfo()
    {
        $notice = $this->_fakeNotice();

        $entry = $notice->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $noticeInfo = ActivityUtils::child($element, 'notice_info', "http://status.net/schema/api/1/");

        $this->assertEquals($notice->id, $noticeInfo->getAttribute('local_id'));
        $this->assertEquals($notice->source, $noticeInfo->getAttribute('source'));
        $this->assertEquals('', $noticeInfo->getAttribute('repeat_of'));
        $this->assertEquals('', $noticeInfo->getAttribute('repeated'));
        $this->assertEquals('', $noticeInfo->getAttribute('favorite'));
        $this->assertEquals('', $noticeInfo->getAttribute('source_link'));
    }

    public function testNoticeInfoRepeatOf()
    {
        $notice = $this->_fakeNotice();

        $repeat = $notice->repeat($this->author2->id, 'test');

        $entry = $repeat->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $noticeInfo = ActivityUtils::child($element, 'notice_info', "http://status.net/schema/api/1/");

        $this->assertEquals($notice->id, $noticeInfo->getAttribute('repeat_of'));
    }

    public function testNoticeInfoRepeated()
    {
        $notice = $this->_fakeNotice();

        $repeat = $notice->repeat($this->author2->id, 'test');

        $entry = $notice->asAtomEntry(false, false, false, $this->author2);

        $element = $this->_entryToElement($entry, true);

        $noticeInfo = ActivityUtils::child($element, 'notice_info', "http://status.net/schema/api/1/");

        $this->assertEquals('true', $noticeInfo->getAttribute('repeated'));

        $entry = $notice->asAtomEntry(false, false, false, $this->targetUser1);

        $element = $this->_entryToElement($entry, true);

        $noticeInfo = ActivityUtils::child($element, 'notice_info', "http://status.net/schema/api/1/");

        $this->assertEquals('false', $noticeInfo->getAttribute('repeated'));
    }

    public function testNoticeInfoFave()
    {
        $notice = $this->_fakeNotice();

        $fave = Fave::addNew($this->author2->getProfile(), $notice);

        // Should be set if user has faved

        $entry = $notice->asAtomEntry(false, false, false, $this->author2);

        $element = $this->_entryToElement($entry, true);

        $noticeInfo = ActivityUtils::child($element, 'notice_info', "http://status.net/schema/api/1/");

        $this->assertEquals('true', $noticeInfo->getAttribute('favorite'));

        // Shouldn't be set if user has not faved

        $entry = $notice->asAtomEntry(false, false, false, $this->targetUser1);

        $element = $this->_entryToElement($entry, true);

        $noticeInfo = ActivityUtils::child($element, 'notice_info', "http://status.net/schema/api/1/");

        $this->assertEquals('false', $noticeInfo->getAttribute('favorite'));
    }

    public function testConversationLink()
    {
        $orig = $this->_fakeNotice($this->targetUser1);

        $text = "@" . $this->targetUser1->nickname . " reply text " . common_random_hexstr(4);

        $reply = Notice::saveNew($this->author1->id, $text, 'test', array('uri' => null, 'reply_to' => $orig->id));

        $conv = Conversation::getKV('id', $reply->conversation);

        $entry = $reply->asAtomEntry();

        $element = $this->_entryToElement($entry, true);

        $this->assertEquals($conv->uri, ActivityUtils::getLink($element, 'ostatus:conversation'));
    }

    function __destruct()
    {
        if (!is_null($this->author1)) {
            $this->author1->delete();
        }

        if (!is_null($this->author2)) {
            $this->author2->delete();
        }

        if (!is_null($this->targetUser1)) {
            $this->targetUser1->delete();
        }

        if (!is_null($this->targetUser2)) {
            $this->targetUser2->delete();
        }

        if (!is_null($this->targetGroup1)) {
            $this->targetGroup1->delete();
        }

        if (!is_null($this->targetGroup2)) {
            $this->targetGroup2->delete();
        }
    }

    private function _fakeNotice($user = null, $text = null)
    {
        if (empty($user)) {
            $user = $this->author1;
        }

        if (empty($text)) {
            $text = "fake-o text-o " . common_random_hexstr(32);
        }

        return Notice::saveNew($user->id, $text, 'test', array('uri' => null));
    }

    private function _entryToElement($entry, $namespace = false)
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>'."\n\n";
        $xml .= '<feed';
        if ($namespace) {
            $xml .= ' xmlns="http://www.w3.org/2005/Atom"';
            $xml .= ' xmlns:thr="http://purl.org/syndication/thread/1.0"';
            $xml .= ' xmlns:georss="http://www.georss.org/georss"';
            $xml .= ' xmlns:activity="http://activitystrea.ms/spec/1.0/"';
            $xml .= ' xmlns:media="http://purl.org/syndication/atommedia"';
            $xml .= ' xmlns:poco="http://portablecontacts.net/spec/1.0"';
            $xml .= ' xmlns:ostatus="http://ostatus.org/schema/1.0"';
            $xml .= ' xmlns:statusnet="http://status.net/schema/api/1/"';
        }
        $xml .= '>' . "\n" . $entry . "\n" . '</feed>' . "\n";
        $doc = DOMDocument::loadXML($xml);
        $feed = $doc->documentElement;
        $entries = $feed->getElementsByTagName('entry');

        return $entries->item(0);
    }
}
