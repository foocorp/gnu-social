#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008-2011 StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'x::';
$longoptions = array('extensions=');

$helptext = <<<END_OF_CHECKSCHEMA_HELP
php checkschema.php [options]
Gives plugins a chance to update the database schema.

    -x --extensions=     Comma-separated list of plugins to load before checking


END_OF_CHECKSCHEMA_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

function tableDefs()
{
	$schema = array();
	require INSTALLDIR.'/db/core.php';
	return $schema;
}

$schema = Schema::get();
$schemaUpdater = new SchemaUpdater($schema);
foreach (tableDefs() as $table => $def) {
	$schemaUpdater->register($table, $def);
}
$schemaUpdater->checkSchema();

if (have_option('x', 'extensions')) {
    $ext = trim(get_option_value('x', 'extensions'));
    $exts = explode(',', $ext);
    foreach ($exts as $plugin) {
        try {
            addPlugin($plugin);
        } catch (Exception $e) {
            print $e->getMessage()."\n";
            exit(1);
        }
    }
}

Event::handle('BeforePluginCheckSchema');
Event::handle('CheckSchema');
