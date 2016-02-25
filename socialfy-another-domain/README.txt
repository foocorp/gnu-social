Initial simple way to Webfinger enable your domain -- needs PHP.
================================================================

This guide needs some updating, since it will only guide you to present
XML data (while the curl command likely gives you JSON). The workaround
is to simply make curl get 'webfinger.xml' instead, and/or have another
file that contains JSON, but that requires editing the PHP file as well.

Step 1
======

Put the 'dot-well-known' on your website, so it loads at:

     https://example.com/.well-known/

(Remember the . at the beginning of this one, which is common practice
for "hidden" files and why we have renamed it "dot-")

Step 2
======

Edit the .well-known/host-meta file and replace "example.com" with the
domain name you're hosting the .well-known directory on.

Using vim you can do this as a quick method:
    $ vim .well-known/host-meta [ENTER]
    :%s/example.com/domain.com/    [ENTER]
    :wq [ENTER]

Step 3
======

For each user on your site, and this might only be you...

In the webfinger directory, make a copy of the example@example.com.xml file
so that it's called (replace username and example.com with appropriate
values, the domain name should be the same as you're "socialifying"):

     username@example.com.xml

Then edit the file contents, replacing "social.example.com" with your
GNU social instance's base path, and change the user ID number (and
nickname for the FOAF link) to that of your account on your social
site. If you don't know your user ID number, you can see this on your
GNU social profile page by looking at the destination URLs in the
Feeds links.

PROTIP: You can get the bulk of the contents (note the <Subject> element though)
        from curling down your real webfinger data:
$ curl https://social.example.com/.well-known/webfinger?resource=acct:username@social.example.com

Finally
=======

Using this method, though fiddly, you can now be @user@domain without
the need for any prefixes for subdomains, etc.
