Initial simple way to Webfinger enable your domain -- needs PHP.
================================================================

Step 1
======

First, put the folders 'xrd' and 'dot-well-known' on your website, so
they load at:

     http://yourname.com/xrd/

     and

     http://yourname.com/.well-known/

     (Remember the . at the beginning of this one)

Step 2
======

Next, edit xrd/index.php and enter a secret in this line:

$s = "";

This can be anything you like...

$s = "johnny-five";

or 

$s = "12345";

It really doesn't matter too much.

Step 3
======

For each user on your site, and this might only be you...

Make a copy of the example@example.com.xml file so that it's called...

     yoursecretusername@domain.com.xml

     So, if your secret is 'johnny5' and your name is ben and your
     domain is titanictoycorp.biz, your file should be called
     johnny5ben@titanictoycorp.biz.xml

Finally, edit the file to point at your account on your social
site. If you are the only user, then you probably don't need to worry
about user/1 as this will be you. For multi user sites, the user ID is
on the profile page.

Finally
=======

Using this method, though fiddly, you can now be @user@domain without
the need for any prefixes for subdomains, etc.
