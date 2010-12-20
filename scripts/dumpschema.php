#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

$helptext = <<<END_OF_CHECKSCHEMA_HELP
Attempt to pull a schema definition for a given table.

  --all     run over all defined core tables
  --diff    show differences between the expected and live table defs
  --raw     skip compatibility filtering for diffs
  --create  dump SQL that would be run to update or create this table
  --build   dump SQL that would be run to create this table fresh
  --checksum just output checksums from the source schema defs


END_OF_CHECKSCHEMA_HELP;

$longoptions = array('diff', 'all', 'create', 'update', 'raw', 'checksum');
require_once INSTALLDIR.'/scripts/commandline.inc';

function indentOptions($indent)
{
    $cutoff = 3;
    if ($indent < $cutoff) {
        $space = $indent ? str_repeat(' ', $indent * 4) : '';
        $sep = ",";
        $lf = "\n";
        $endspace = "$lf" . ($indent ? str_repeat(' ', ($indent - 1) * 4) : '');
    } else {
        $space = '';
        $sep = ", ";
        $lf = '';
        $endspace = '';
    }
    if ($indent - 1 < $cutoff) {
    }
    return array($space, $sep, $lf, $endspace);
}

function prettyDumpArray($arr, $key=null, $indent=0)
{
    // hack
    if ($key == 'primary key') {
        $subIndent = $indent + 2;
    } else {
        $subIndent = $indent + 1;
    }

    list($space, $sep, $lf, $endspace) = indentOptions($indent);
    list($inspace, $insep, $inlf, $inendspace) = indentOptions($subIndent);

    print "{$space}";
    if (!is_numeric($key)) {
        print "'$key' => ";
    }
    if (is_array($arr)) {
        print "array({$inlf}";
        $n = 0;
        foreach ($arr as $key => $row) {
            $n++;
            prettyDumpArray($row, $key, $subIndent);
            if ($n < count($arr)) {
                print "$insep$inlf";
            }
        }
        // hack!
        print "{$inendspace})";
    } else {
        print var_export($arr, true);
    }
}

function getCoreSchema($tableName)
{
    $schema = array();
    include INSTALLDIR . '/db/core.php';
    return $schema[$tableName];
}

function getCoreTables()
{
    $schema = array();
    include INSTALLDIR . '/db/core.php';
    return array_keys($schema);
}

function dumpTable($tableName, $live)
{
    if ($live) {
        $schema = Schema::get();
        $def = $schema->getTableDef($tableName);
    } else {
        // hack
        $def = getCoreSchema($tableName);
    }
    prettyDumpArray($def, $tableName);
    print "\n";
}

function dumpBuildTable($tableName)
{
    echo "-- \n";
    echo "-- $tableName\n";
    echo "-- \n";

    $schema = Schema::get();
    $def = getCoreSchema($tableName);
    $sql = $schema->buildCreateTable($tableName, $def);
    $sql[] = '';

    echo implode(";\n", $sql);
    echo "\n";
}

function dumpEnsureTable($tableName)
{
    $schema = Schema::get();
    $def = getCoreSchema($tableName);
    $sql = $schema->buildEnsureTable($tableName, $def);

    if ($sql) {
        echo "-- \n";
        echo "-- $tableName\n";
        echo "-- \n";

        $sql[] = '';
        echo implode(";\n", $sql);
        echo "\n";
    }
}

function dumpDiff($tableName, $filter)
{
    $schema = Schema::get();
    $def = getCoreSchema($tableName);
    try {
        $old = $schema->getTableDef($tableName);
    } catch (Exception $e) {
        // @fixme this is a terrible check :D
        if (preg_match('/no such table/i', $e->getMessage())) {
            return dumpTable($tableName, false);
        } else {
            throw $e;
        }
    }

    if ($filter) {
        //$old = $schema->filterDef($old);
        $def = $schema->filterDef($def);
    }

    // @hack
    $old = tweakPrimaryKey($old);
    $def = tweakPrimaryKey($def);

    $sections = array_unique(array_merge(array_keys($old), array_keys($def)));
    $final = array();
    foreach ($sections as $section) {
        if ($section == 'fields') {
            // this shouldn't be needed maybe... wait what?
        }
        $diff = $schema->diffArrays($old, $def, $section);
        $chunks = array('del', 'mod', 'add');
        foreach ($chunks as $chunk) {
            if ($diff[$chunk]) {
                foreach ($diff[$chunk] as $key) {
                    if ($chunk == 'del') {
                        $final[$section]["DEL $key"] = $old[$section][$key];
                    } else if ($chunk == 'add') {
                        $final[$section]["ADD $key"] = $def[$section][$key];
                    } else if ($chunk == 'mod') {
                        $final[$section]["OLD $key"] = $old[$section][$key];
                        $final[$section]["NEW $key"] = $def[$section][$key];
                    }
                }
            }
        }
    }

    prettyDumpArray($final, $tableName);
    print "\n";
}

function tweakPrimaryKey($def)
{
    if (isset($def['primary key'])) {
        $def['primary keys'] = array('primary key' => $def['primary key']);
        unset($def['primary key']);
    }
    if (isset($def['description'])) {
        $def['descriptions'] = array('description' => $def['description']);
        unset($def['description']);
    }
    return $def;
}

function dumpChecksum($tableName)
{
    $schema = Schema::get();
    $def = getCoreSchema($tableName);

    $updater = new SchemaUpdater($schema);
    $checksum = $updater->checksum($def);
    $old = @$updater->checksums[$tableName];

    if ($old == $checksum) {
        echo "OK  $checksum $tableName\n";
    } else if (!$old) {
        echo "NEW $checksum $tableName\n";
    } else {
        echo "MOD $checksum $tableName (was $old)\n";
    }
}

if (have_option('all')) {
    $args = getCoreTables();
}

if (count($args)) {
    foreach ($args as $tableName) {
        if (have_option('diff')) {
            dumpDiff($tableName, !have_option('raw'));
        } else if (have_option('create')) {
            dumpBuildTable($tableName);
        } else if (have_option('update')) {
            dumpEnsureTable($tableName);
        } else if (have_option('checksum')) {
            dumpChecksum($tableName);
        } else {
            dumpTable($tableName, true);
        }
    }
} else {
    show_help($helptext);
}