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

$helptext = <<<END_OF_UPGRADE_HELP
php upgrade.php [options]
Upgrade database schema and data to latest software

END_OF_UPGRADE_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

function main()
{
    updateSchemaCore();
    updateSchemaPlugins();

    // These replace old "fixup_*" scripts

    fixupNoticesRendered();
}

function tableDefs()
{
	$schema = array();
	require INSTALLDIR.'/db/core.php';
	return $schema;
}

function updateSchemaCore()
{
    printfnq("Upgrading core schema");

    $schema = Schema::get();
    $schemaUpdater = new SchemaUpdater($schema);
    foreach (tableDefs() as $table => $def) {
        $schemaUpdater->register($table, $def);
    }
    $schemaUpdater->checkSchema();
}

function updateSchemaPlugins()
{
    printfnq("Upgrading plugin schema");

    Event::handle('CheckSchema');
}

function fixupNoticesRendered()
{
    printfnq("Ensuring all notices have rendered HTML");

    $notice = new Notice();

    $notice->whereAdd('rendered IS NULL');
    $notice->find();

    while ($notice->fetch()) {
        $original = clone($notice);
        $notice->rendered = common_render_content($notice->content, $notice);
        $notice->update($original);
    }
}

main();
