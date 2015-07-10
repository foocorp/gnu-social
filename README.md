# GNU social 1.2.x
2015

(c) Free Software Foundation, Inc
(c) StatusNet, Inc

This is the README file for GNU social, the free
software social networking platform. It includes
general information about the software and the
project.

Some other files to review:

- INSTALL: instructions on how to install the software.
- UPGRADE: upgrading from earlier versions
- CONFIGURE: configuration options in gruesome detail.
- PLUGINS.txt: how to install and configure plugins.
- EVENTS.txt: events supported by the plugin system
- COPYING: full text of the software license

Information on using GNU social can be found in
the "doc" subdirectory or in the "help" section
on-line, or you can catch us on IRC in #social on
the freenode network.

## About

GNU social is a free social networking
platform. It helps people in a community, company
or group to exchange short status updates, do
polls, announce events, or other social activities
(and you can add more!). Users can choose which
people to "follow" and receive only their friends'
or colleagues' status messages. It provides a
similar service to sites like Twitter, Google+ or
Facebook, but is much more awesome.

With a little work, status messages can be sent to
mobile phones, instant messenger programs (using
XMPP), and specially-designed desktop clients that
support the Twitter API.

GNU social supports an open standard called
OStatus <https://www.w3.org/community/ostatus/> that lets users in
different networks follow each other. It enables a
distributed social network spread all across the
Web.

GNU social was originally developed as "StatusNet" by
StatusNet, Inc. with Evan Prodromou as lead developer.

It is shared with you in hope that you too make an
service available to your users. To learn more,
please see the Open Software Service Definition
1.1: <http://www.opendefinition.org/ossd>

### License

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public
License along with this program, in the file "COPYING".  If not, see
<http://www.gnu.org/licenses/>.

    IMPORTANT NOTE: The GNU Affero General Public License (AGPL) has
    *different requirements* from the "regular" GPL. In particular, if
    you make modifications to the GNU social source code on your server,
    you *MUST MAKE AVAILABLE* the modified version of the source code
    to your users under the same license. This is a legal requirement
    of using the software, and if you do not wish to share your
    modifications, *YOU MAY NOT INSTALL GNU SOCIAL*.

Documentation in the /doc-src/ directory is available under the
Creative Commons Attribution 3.0 Unported license, with attribution to
"GNU social". See <http://creativecommons.org/licenses/by/3.0/> for details.

CSS and images in the /theme/ directory are available under the
Creative Commons Attribution 3.0 Unported license, with attribution to
"GNU social". See <http://creativecommons.org/licenses/by/3.0/> for details.

Our understanding and intention is that if you add your own theme that
uses only CSS and images, those files are not subject to the copyleft
requirements of the Affero General Public License 3.0. See
<http://wordpress.org/news/2009/07/themes-are-gpl-too/>. This is not
legal advice; consult your lawyer.

Additional library software has been made available in the 'extlib'
directory. All of it is Free Software and can be distributed under
liberal terms, but those terms may differ in detail from the AGPL's
particulars. See each package's license file in the extlib directory
for additional terms.

## New this version

This is the development branch for the 1.2.x version of GNU social.
All daring 1.1.x admins should upgrade to this version.

So far it includes the following changes:

- Backing up a user's account is more and more complete.
- Emojis 😸 (utf8mb4 support)

The last release, 1.1.3, gave us these improvements:

- XSS security fix (thanks Simon Waters, <https://www.surevine.com/>)
- Many improvements to ease adoption of the Qvitter front-end <https://github.com/hannesmannerheim/qvitter>
- Protocol adaptions for improved performance and stability

Upgrades from _StatusNet_ 1.1.1 will also experience these improvements:

- Fixes for SQL injection errors in profile lists.
- Improved ActivityStreams JSON representation of activities and objects.
- Upgrade to the Twitter 1.1 API.
- More robust handling of errors in distribution.
- Fix error in OStatus subscription for remote groups.
- Fix error in XMPP distribution.
- Tracking of conversation URI metadata (more coherent convos)

### Troubleshooting

The primary output for GNU social is syslog,
unless you configured a separate logfile. This is
probably the first place to look if you're getting
weird behaviour from GNU social.

If you're tracking the unstable version of
GNU social in the git repository (see below), and you
get a compilation error ("unexpected T_STRING") in
the browser, check to see that you don't have any
conflicts in your code.

### Unstable version

If you're adventurous or impatient, you may want
to install the development version of GNU social.
To get it, use the git version control tool
<http://git-scm.com/> like so:

    git clone https://github.com/foocorp/gnu-social.git

In the current phase of development it is probably
recommended to use git as a means to stay up to date
with the source code. You can choose between these
branches:
- 1.2.x     "stable", few updates, well tested code
- master    "testing", more updates, usually working well
- nightly   "unstable", most updates, not always working

To keep it up-to-date, use 'git pull'. Watch for conflicts!

## Further information

There are several ways to get more information about GNU social.

* The #social IRC channel on freenode.net <https://www.freenode.net/>.
* The unofficial XMPP room linked to IRC on <xmpp:gnusocial@conference.bka.li>
* The GNU social website <https://gnu.io/social/>
* Following us on GNU social -- <https://quitter.se/gnusocial>

* GNU social has a bug tracker for any defects you may find, or ideas for
  making things better. <https://git.gnu.io/gnu/gnu-social/issues/>
* Patches are welcome, preferrably to our repository on git.gnu.io. <https://git.gnu.io/gnu/gnu-social>

Credits
=======

The following is an incomplete list of developers
who've worked on GNU social, or its predecessors
StatusNet and Free Social. Apologies for any
oversight; please let mattl@gnu.org know if
anyone's been overlooked in error.

## Project Founders

* Matt Lee (GNU social)
* Evan Prodromou (StatusNet)
* Mikael Nordfeldth (Free Social)

Thanks to all of the StatusNet developers:

* Zach Copley, StatusNet, Inc.
* Earle Martin, StatusNet, Inc.
* Marie-Claude Doyon, designer, StatusNet, Inc.
* Sarven Capadisli, StatusNet, Inc.
* Robin Millette, StatusNet, Inc.
* Ciaran Gultnieks
* Michael Landers
* Ori Avtalion
* Garret Buell
* Mike Cochrane
* Matthew Gregg
* Florian Biree
* Erik Stambaugh
* 'drry'
* Gina Haeussge
* Tryggvi Björgvinsson
* Adrian Lang
* Ori Avtalion
* Meitar Moscovitz
* Ken Sheppardson (Trac server, man-about-town)
* Tiago 'gouki' Faria (i18n manager)
* Sean Murphy
* Leslie Michael Orchard
* Eric Helgeson
* Ken Sedgwick
* Brian Hendrickson
* Tobias Diekershoff
* Dan Moore
* Fil
* Jeff Mitchell
* Brenda Wallace
* Jeffery To
* Federico Marani
* mEDI
* Brett Taylor
* Brigitte Schuster
* Siebrand Mazeland and the amazing volunteer translators at translatewiki.net
* Brion Vibber, StatusNet, Inc.
* James Walker, StatusNet, Inc.
* Samantha Doherty, designer, StatusNet, Inc.
* Simon Waters, Surevine
* Joshua Judson Rosen (rozzin)

### Extra special thanks to the GNU socialites

* Craig Andrews
* Donald Robertson
* Deb Nicholson
* Ian Denhart
* Steven DuBois
* Blaine Cook
* Henry Story
* Melvin Carvalho

Thanks also to the developers of our upstream
library code and to the thousands of people who
have tried out GNU social, told their friends, and
built the fediverse network to what it is today.

### License help from

* Bradley M. Kuhn

