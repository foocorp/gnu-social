<?php

if (php_sapi_name() != 'cli') {
    die('not for web');
}

define('HTTP_TIMEOUT', 60); // ssslllloowwwww salmon if queues are off

define('INSTALLDIR', dirname(dirname(dirname(dirname(__FILE__)))));
set_include_path(INSTALLDIR . '/extlib' . PATH_SEPARATOR . get_include_path());

require_once 'PEAR.php';
require_once 'Net/URL2.php';
require_once 'HTTP/Request2.php';


// ostatus test script, client-side :)

class TestBase
{
    function log($str)
    {
        $args = func_get_args();
        array_shift($args);

        $msg = vsprintf($str, $args);
        print $msg . "\n";
    }

    function assertEqual($a, $b)
    {
        if ($a != $b) {
            throw new Exception("Failed to assert equality: expected $a, got $b");
        }
        return true;
    }

    function assertNotEqual($a, $b)
    {
        if ($a == $b) {
            throw new Exception("Failed to assert inequality: expected not $a, got $b");
        }
        return true;
    }

    function assertTrue($a)
    {
        if (!$a) {
            throw new Exception("Failed to assert true: got false");
        }
    }

    function assertFalse($a)
    {
        if ($a) {
            throw new Exception("Failed to assert false: got true");
        }
    }
}

class OStatusTester extends TestBase
{
    /**
     * @param string $a base URL of test site A (eg http://localhost/mublog)
     * @param string $b base URL of test site B (eg http://localhost/mublog2)
     * @param int $timeout HTTP timeout value (needs to be long if Salmon is slow)
     */
    function __construct($a, $b, $timeout=60) {
        $this->a = $a;
        $this->b = $b;

        $base = 'test' . mt_rand(1, 1000000);
        $this->pub = new SNTestClient($this->a, 'pub' . $base, 'pw-' . mt_rand(1, 1000000), $timeout);
        $this->sub = new SNTestClient($this->b, 'sub' . $base, 'pw-' . mt_rand(1, 1000000), $timeout);

        $this->group = 'group' . $base;
    }

    function run()
    {
        $this->setup();

        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (strtolower(substr($method, 0, 4)) == 'test') {
                print "\n";
                print "== $method ==\n";
                call_user_func(array($this, $method));
            }
        }

        print "\n";
        $this->log("DONE!");
    }

    function setup()
    {
        $this->pub->register();
        $this->pub->assertRegistered();

        $this->sub->register();
        $this->sub->assertRegistered();
    }

    function testLocalPost()
    {
        $post = $this->pub->post("Local post, no subscribers yet.");
        $this->assertNotEqual('', $post);

        $post = $this->sub->post("Local post, no subscriptions yet.");
        $this->assertNotEqual('', $post);
    }

    /**
     * pub posts: @b/sub
     */
    function testMentionUrl()
    {
        $bits = parse_url($this->b);
        $base = $bits['host'];
        if (isset($bits['path'])) {
            $base .= $bits['path'];
        }
        $name = $this->sub->username;

        $post = $this->pub->post("@$base/$name should have this in home and replies");
        $this->sub->assertReceived($post);
    }

    function testSubscribe()
    {
        $this->assertFalse($this->sub->hasSubscription($this->pub->getProfileUri()));
        $this->assertFalse($this->pub->hasSubscriber($this->sub->getProfileUri()));
        $this->sub->subscribe($this->pub->getProfileLink());
        $this->assertTrue($this->sub->hasSubscription($this->pub->getProfileUri()));
        $this->assertTrue($this->pub->hasSubscriber($this->sub->getProfileUri()));
    }

    function testPush()
    {
        $this->assertTrue($this->sub->hasSubscription($this->pub->getProfileUri()));
        $this->assertTrue($this->pub->hasSubscriber($this->sub->getProfileUri()));

        $name = $this->sub->username;
        $post = $this->pub->post("Regular post, which $name should get via PuSH");
        $this->sub->assertReceived($post);
    }

    function testMentionSubscribee()
    {
        $this->assertTrue($this->sub->hasSubscription($this->pub->getProfileUri()));
        $this->assertFalse($this->pub->hasSubscription($this->sub->getProfileUri()));

        $name = $this->pub->username;
        $post = $this->sub->post("Just a quick note back to my remote subscribee @$name");
        $this->pub->assertReceived($post);
    }

    function testUnsubscribe()
    {
        $this->assertTrue($this->sub->hasSubscription($this->pub->getProfileUri()));
        $this->assertTrue($this->pub->hasSubscriber($this->sub->getProfileUri()));
        $this->sub->unsubscribe($this->pub->getProfileLink());
        $this->assertFalse($this->sub->hasSubscription($this->pub->getProfileUri()));
        $this->assertFalse($this->pub->hasSubscriber($this->sub->getProfileUri()));
    }

    function testCreateGroup()
    {
        $this->groupUrl = $this->pub->createGroup($this->group);
        $this->assertTrue(!empty($this->groupUrl));
    }

    function testJoinGroup()
    {
        #$this->assertFalse($this->sub->inGroup($this->groupUrl));
        $this->sub->joinGroup($this->groupUrl);
        #$this->assertTrue($this->sub->inGroup($this->groupUrl));
    }

    function testLocalGroupPost()
    {
        $post = $this->pub->post("Group post from local to !{$this->group}, should go out over push.");
        $this->assertNotEqual('', $post);
        $this->sub->assertReceived($post);
    }

    function testRemoteGroupPost()
    {
        $post = $this->sub->post("Group post from remote to !{$this->group}, should come in over salmon.");
        $this->assertNotEqual('', $post);
        $this->pub->assertReceived($post);
    }

    function testLeaveGroup()
    {
        #$this->assertTrue($this->sub->inGroup($this->groupUrl));
        $this->sub->leaveGroup($this->groupUrl);
        #$this->assertFalse($this->sub->inGroup($this->groupUrl));
    }
}

class SNTestClient extends TestBase
{
    function __construct($base, $username, $password, $timeout=60)
    {
        $this->basepath = $base;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;

        $this->fullname = ucfirst($username) . ' Smith';
        $this->homepage = 'http://example.org/' . $username;
        $this->bio = 'Stub account for OStatus tests.';
        $this->location = 'Montreal, QC';
    }

    /**
     * Make a low-level web hit to this site, with authentication.
     * @param string $path URL fragment for something under the base path
     * @param array $params POST parameters to send
     * @param boolean $auth whether to include auth data
     * @return string
     * @throws Exception on low-level error conditions
     */
    protected function hit($path, $params=array(), $auth=false, $cookies=array())
    {
        $url = $this->basepath . '/' . $path;

        $http = new HTTP_Request2($url, 'POST', array('timeout' => $this->timeout));
        if ($auth) {
            $http->setAuth($this->username, $this->password, HTTP_Request2::AUTH_BASIC);
        }
        foreach ($cookies as $name => $val) {
            $http->addCookie($name, $val);
        }
        $http->addPostParameter($params);
        $response = $http->send();

        $code = $response->getStatus();
        if ($code < '200' || $code >= '400') {
            throw new Exception("Failed API hit to $url: $code\n" . $response->getBody());
        }

        return $response;
    }

    /**
     * Make a hit to a web form, without authentication but with a session.
     * @param string $path URL fragment relative to site base
     * @param string $form id of web form to pull initial parameters from
     * @param array $params POST parameters, will be merged with defaults in form
     */
    protected function web($path, $form, $params=array())
    {
        $url = $this->basepath . '/' . $path;
        $http = new HTTP_Request2($url, 'GET', array('timeout' => $this->timeout));
        $response = $http->send();

        $dom = $this->checkWeb($url, 'GET', $response);
        $cookies = array();
        foreach ($response->getCookies() as $cookie) {
            // @fixme check for expirations etc
            $cookies[$cookie['name']] = $cookie['value'];
        }

        $form = $dom->getElementById($form);
        if (!$form) {
            throw new Exception("Form $form not found on $url");
        }
        $inputs = $form->getElementsByTagName('input');
        foreach ($inputs as $item) {
            $type = $item->getAttribute('type');
            if ($type != 'check') {
                $name = $item->getAttribute('name');
                $val = $item->getAttribute('value');
                if ($name && $val && !isset($params[$name])) {
                    $params[$name] = $val;
                }
            }
        }

        $response = $this->hit($path, $params, false, $cookies);
        $dom = $this->checkWeb($url, 'POST', $response);

        return $dom;
    }

    protected function checkWeb($url, $method, $response)
    {
        $dom = new DOMDocument();
        if (!$dom->loadHTML($response->getBody())) {
            throw new Exception("Invalid HTML from $method to $url");
        }

        $xpath = new DOMXPath($dom);
        $error = $xpath->query('//p[@class="error"]');
        if ($error && $error->length) {
            throw new Exception("Error on $method to $url: " .
                                $error->item(0)->textContent);
        }

        return $dom;
    }

    protected function parseXml($path, $body)
    {
        $dom = new DOMDocument();
        if ($dom->loadXML($body)) {
            return $dom;
        } else {
            throw new Exception("Bogus XML data from $path:\n$body");
        }
    }

    /**
     * Make a hit to a REST-y XML page on the site, without authentication.
     * @param string $path URL fragment for something relative to base
     * @param array $params POST parameters to send
     * @return DOMDocument
     * @throws Exception on low-level error conditions
     */
    protected function xml($path, $params=array())
    {
        $response = $this->hit($path, $params, true);
        $body = $response->getBody();
        return $this->parseXml($path, $body);
    }

    protected function parseJson($path, $body)
    {
        $data = json_decode($body, true);
        if ($data !== null) {
            if (!empty($data['error'])) {
                throw new Exception("JSON API returned error: " . $data['error']);
            }
            return $data;
        } else {
            throw new Exception("Bogus JSON data from $path:\n$body");
        }
    }

    /**
     * Make an API hit to this site, with authentication.
     * @param string $path URL fragment for something under 'api' folder
     * @param string $style one of 'json', 'xml', or 'atom'
     * @param array $params POST parameters to send
     * @return mixed associative array for JSON, DOMDocument for XML/Atom
     * @throws Exception on low-level error conditions
     */
    protected function api($path, $style, $params=array())
    {
        $response = $this->hit("api/$path.$style", $params, true);
        $body = $response->getBody();
        if ($style == 'json') {
            return $this->parseJson($path, $body);
        } else if ($style == 'xml' || $style == 'atom') {
            return $this->parseXml($path, $body);
        } else {
            throw new Exception("API needs to be JSON, XML, or Atom");
        }
    }

    /**
     * Register the account.
     *
     * Unfortunately there's not an API method for registering, so we fake it.
     */
    function register()
    {
        $this->log("Registering user %s on %s",
                   $this->username,
                   $this->basepath);
        $ret = $this->web('main/register', 'form_register',
            array('nickname' => $this->username,
                  'password' => $this->password,
                  'confirm' => $this->password,
                  'fullname' => $this->fullname,
                  'homepage' => $this->homepage,
                  'bio' => $this->bio,
                  'license' => 1,
                  'submit' => 'Register'));
    }

    /**
     * @return string canonical URI/URL to profile page
     */
    function getProfileUri()
    {
        $data = $this->api('account/verify_credentials', 'json');
        $id = $data['id'];
        return $this->basepath . '/user/' . $id;
    }

    /**
     * @return string human-friendly URL to profile page
     */
    function getProfileLink()
    {
        return $this->basepath . '/' . $this->username;
    }

    /**
     * Check that the account has been registered and can be used.
     * On failure, throws a test failure exception.
     */
    function assertRegistered()
    {
        $this->log("Confirming %s is registered on %s",
                   $this->username,
                   $this->basepath);
        $data = $this->api('account/verify_credentials', 'json');
        $this->assertEqual($this->username, $data['screen_name']);
        $this->assertEqual($this->fullname, $data['name']);
        $this->assertEqual($this->homepage, $data['url']);
        $this->assertEqual($this->bio, $data['description']);
        $this->log("  looks good!");
    }

    /**
     * Post a given message from this account
     * @param string $message
     * @return string URL/URI of notice
     * @todo reply, location options
     */
    function post($message)
    {
        $this->log("Posting notice as %s on %s: %s",
                   $this->username,
                   $this->basepath,
                   $message);
        $data = $this->api('statuses/update', 'json',
            array('status' => $message));

        $url = $this->basepath . '/notice/' . $data['id'];
        return $url;
    }

    /**
     * Check that this account has received the notice.
     * @param string $notice_uri URI for the notice to check for
     */
    function assertReceived($notice_uri)
    {
        $timeout = 5;
        $tries = 6;
        while ($tries) {
            $ok = $this->checkReceived($notice_uri);
            if ($ok) {
                return true;
            }
            $tries--;
            if ($tries) {
                $this->log("  didn't see it yet, waiting $timeout seconds");
                sleep($timeout);
            }
        }
        throw new Exception("  message $notice_uri not received by $this->username");
    }

    /**
     * Pull the user's home timeline to check if a notice with the given
     * source URL has been received recently.
     * If we don't see it, we'll try a couple more times up to 10 seconds.
     *
     * @param string $notice_uri
     */
    function checkReceived($notice_uri)
    {
        $this->log("Checking if %s on %s received notice %s",
                   $this->username,
                   $this->basepath,
                   $notice_uri);
        $params = array();
        $dom = $this->api('statuses/home_timeline', 'atom', $params);

        $xml = simplexml_import_dom($dom);
        if (!$xml->entry) {
            return false;
        }
        if (is_array($xml->entry)) {
            $entries = $xml->entry;
        } else {
            $entries = array($xml->entry);
        }
        foreach ($entries as $entry) {
            if ($entry->id == $notice_uri) {
                $this->log("  found it $notice_uri");
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $profile user page link or webfinger
     */
    function subscribe($profile)
    {
        // This uses the command interface, since there's not currently
        // a friendly Twit-API way to do a fresh remote subscription and
        // the web form's a pain to use.
        $this->post('follow ' . $profile);
    }

    /**
     * @param string $profile user page link or webfinger
     */
    function unsubscribe($profile)
    {
        // This uses the command interface, since there's not currently
        // a friendly Twit-API way to do a fresh remote subscription and
        // the web form's a pain to use.
        $this->post('leave ' . $profile);
    }

    /**
     * Check that this account is subscribed to the given profile.
     * @param string $profile_uri URI for the profile to check for
     * @return boolean
     */
    function hasSubscription($profile_uri)
    {
        $this->log("Checking if $this->username has a subscription to $profile_uri");

        $me = $this->getProfileUri();
        return $this->checkSubscription($me, $profile_uri);
    }

    /**
     * Check that this account is subscribed to by the given profile.
     * @param string $profile_uri URI for the profile to check for
     * @return boolean
     */
    function hasSubscriber($profile_uri)
    {
        $this->log("Checking if $this->username is subscribed to by $profile_uri");

        $me = $this->getProfileUri();
        return $this->checkSubscription($profile_uri, $me);
    }

    protected function checkSubscription($subscriber, $subscribed)
    {
        // Using FOAF as the API methods for checking the social graph
        // currently are unfriendly to remote profiles
        $ns_foaf = 'http://xmlns.com/foaf/0.1/';
        $ns_sioc = 'http://rdfs.org/sioc/ns#';
        $ns_rdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

        $dom = $this->xml($this->username . '/foaf');
        $agents = $dom->getElementsByTagNameNS($ns_foaf, 'Agent');
        foreach ($agents as $agent) {
            $agent_uri = $agent->getAttributeNS($ns_rdf, 'about');
            if ($agent_uri == $subscriber) {
                $follows = $agent->getElementsByTagNameNS($ns_sioc, 'follows');
                foreach ($follows as $follow) {
                    $target = $follow->getAttributeNS($ns_rdf, 'resource');
                    if ($target == ($subscribed . '#acct')) {
                        $this->log("  confirmed $subscriber subscribed to $subscribed");
                        return true;
                    }
                }
                $this->log("  we found $subscriber but they don't follow $subscribed");
                return false;
            }
        }
        $this->log("  can't find $subscriber in {$this->username}'s social graph.");
        return false;
    }

    /**
     * Create a group on this site.
     *
     * @param string $nickname
     * @param array $options
     * @return string: profile URL for the group
     */
    function createGroup($nickname, $options=array()) {
        $this->log("Creating group as %s on %s: %s",
                   $this->username,
                   $this->basepath,
                   $nickname);

        $data = $this->api('statusnet/groups/create', 'json',
            array_merge(array('nickname' => $nickname), $options));
        $url = $data['url'];

        if ($url) {
            $this->log('  created as %s', $url);
        } else {
            $this->log('  failed? %s', var_export($data, true));
        }
        return $url;
    }

    function groupInfo($nickname) {
        $data = $this->api('statusnet/groups/show', 'json', array(
            'id' => $nickname
        ));
    }

    /**
     * Join a group.
     *
     * @param string $group nickname or URL
     */
    function joinGroup($group) {
        $this->post('join ' . $group);
    }

    /**
     * Leave a group.
     *
     * @param string $group nickname or URL
     */
    function leaveGroup($group) {
        $this->post('drop ' . $group);
    }

    /**
     *
     * @param string $nickname
     * @return
     */
    function inGroup($nickname) {
        // @todo
    }
}

// @fixme switch to commandline.inc?
$timeout = HTTP_TIMEOUT;

$args = array();
$options = array();
foreach (array_slice($_SERVER['argv'], 1) as $arg) {
    if (substr($arg, 0, 2) == '--') {
        $bits = explode('=', substr($arg, 2), 2);
        if (count($bits == 2)) {
            list($key, $val) = $bits;
            $options[$key] = $val;
        } else {
            list($key) = $bits;
            $options[$key] = true;
        }
    } else {
        $args[] = $arg;
    }
}
if (count($args) < 2) {
    print <<<END_HELP
remote-tests.php [options] <url1> <url2>
  --timeout=## change HTTP timeout from default {$timeout}s
  url1: base URL of a StatusNet instance
  url2: base URL of another StatusNet instance

This will register user accounts on the two given StatusNet instances
and run some tests to confirm that OStatus subscription and posting
between the two sites works correctly.

END_HELP;
exit(1);
}

$a = $args[0];
$b = $args[1];
if (isset($options['timeout'])) {
    $timeout = intval($options['timeout']);
}

$tester = new OStatusTester($a, $b, $timeout);
$tester->run();
