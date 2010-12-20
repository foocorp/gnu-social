This is a plugin to automatically load notices in the browser no
matter who creates them -- the kind of thing we see with
search.twitter.com, rejaw.com, or FriendFeed's "real time" news.

NOTE: this is an insecure version; don't roll it out on a production
server.

It requires a cometd server. I've only had the cometd-java server work
correctly; something's wiggy with the Twisted-based server. See here
for help setting up a comet server:

    http://cometd.org/

After you have a cometd server installed, just add this code to your
config.php:

    require_once(INSTALLDIR.'/plugins/Comet/CometPlugin.php');
    $cp = new CometPlugin('http://example.com:8080/cometd/');

Change 'example.com:8080' to the name and port of the server you
installed cometd on.

TODO:

* Needs to be tested with Ajax submission. Probably messes everything
  up.
* Add more timelines: personal inbox and tags would be great.
* Add security. In particular, only let the PHP code publish notices
  to the cometd server. Currently, it doesn't try to authenticate.
