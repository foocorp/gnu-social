#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$longoptions = array('base=', 'network');

$helptext = <<<END_OF_TRIM_HELP
Runs Sphinx search indexer.
    --rotate             Have Sphinx run index update in background and
                         rotate updated indexes into place as they finish.
    --base               Base dir to Sphinx install
                         (default /usr/local)
    --network            Use status_network global config table for site list
                         (non-functional at present)


END_OF_TRIM_HELP;

require_once INSTALLDIR . '/scripts/commandline.inc';
require dirname(__FILE__) . '/sphinx-utils.php';

sphinx_iterate_sites('sphinx_index_update');

function sphinx_index_update($sn)
{
    $base = sphinx_base();

    $baseIndexes = array('notice', 'profile');
    $params = array();

    if (have_option('rotate')) {
        $params[] = '--rotate';
    }
    foreach ($baseIndexes as $index) {
        $params[] = "{$sn->dbname}_{$index}";
    }

    $params = implode(' ', $params);
    $cmd = "$base/bin/indexer --config $base/etc/sphinx.conf $params";

    print "$cmd\n";
    system($cmd);
}
