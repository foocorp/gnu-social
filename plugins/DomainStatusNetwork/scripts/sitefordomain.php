#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Script to print out current version of the software
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$helptext = <<<END_OF_SITEFORDOMAIN_HELP
sitefordomain.php [options] <email address|domain>
Prints site information for the domain given

END_OF_SITEFORDOMAIN_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$domain = DomainStatusNetworkPlugin::toDomain($args[0]);

$sn = DomainStatusNetworkPlugin::siteForDomain($domain);

if (empty($sn)) {
    exit(1);
}

print $sn->nickname."\n";
exit(0);
