GNU Social Profile Extensions
=============================

Allows administrators to define additional profile fields for the
users of a GNU Social installation.


Installation
------------

To install, copy this directory into your plugins directory and add
the following lines to your config.php file:

addPlugin('GNUsocialProfileExtensions');
$config['admin']['panels'][] = 'profilefields';

