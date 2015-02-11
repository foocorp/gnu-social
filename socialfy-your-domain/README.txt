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

$s = "johnny5";

or 

$s = "12345";

It really doesn't matter too much.


Step 3
======

Edit the .well-known/host-meta file and replace all occurrences of
"example.com" with your domain name.

Step 4
======

For each user on your site, and this might only be you...

In the xrd directory, make a copy of the example@example.com.xml file
so that it's called...

     yoursecretusername@domain.com.xml

So, if your secret from step 2 is 'johnny5' and your name is 'ben' and
your domain is 'titanictoycorp.biz', your file should be called
johnny5ben@titanictoycorp.biz.xml

Then edit the file, replacing "social.example.com" with your GNU
social instance's base path, and change the user ID number (and
nickname for the FOAF link) to that of your account on your social
site. If you don't know your user ID number, you can see this on your
GNU social profile page by looking at the destination URLs in the
Feeds links.

Finally
=======

Using this method, though fiddly, you can now be @user@domain without
the need for any prefixes for subdomains, etc.
