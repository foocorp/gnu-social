<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

// All the fun stuff to actually initialize StatusNet's framework code,
// without loading up a site configuration.
require_once INSTALLDIR . '/lib/framework.php';

try {
    GNUsocial::init(@$server, @$path, @$conffile);
} catch (NoConfigException $e) {
    // XXX: Throw a conniption if database not installed
    // XXX: Find a way to use htmlwriter for this instead of handcoded markup
    // TRANS: Error message displayed when no configuration file was found for a StatusNet installation.
    echo '<p>'. _('No configuration file found.') .'</p>';
    // TRANS: Error message displayed when no configuration file was found for a StatusNet installation.
    // TRANS: Is followed by a list of directories (separated by HTML breaks).
    echo '<p>'. _('I looked for configuration files in the following places:') .'<br /> ';
    echo implode($e->configFiles, '<br />');
    // TRANS: Error message displayed when no configuration file was found for a StatusNet installation.
    echo '<p>'. _('You may wish to run the installer to fix this.') .'</p>';
    // @todo FIXME Link should be in a para?
    // TRANS: Error message displayed when no configuration file was found for a StatusNet installation.
    // TRANS: The text is link text that leads to the installer page.
    echo '<a href="install.php">'. _('Go to the installer.') .'</a>';
    exit;
}
