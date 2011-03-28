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

$shortoptions = 'da';
$longoptions = array('delete', 'all');

$helptext = <<<END_OF_SETCONFIG_HELP
setconfig.php [options] [section] [setting] <value>
With three args, set the setting to the value.
With two args, just show the setting.
With -d, delete the setting.
With no args, lists all currently set values.

  [section]   section to use (required)
  [setting]   setting to use (required)
  <value>     value to set (optional)

  -d --delete delete the setting (no value)
  -a --all    list all configuration, not just the database values

END_OF_SETCONFIG_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (empty($args)) {
    if (have_option('a', 'all')) {
        foreach ($config as $section => $section_value) {
            foreach ($section_value as $setting => $value) {
                if (have_option('v', 'verbose') || !is_array($value)) {
                    // Don't print array's without the verbose flag
                    printf("%-20s %-20s %s\n", $section, $setting, var_export($value, true));
                }
            }
        }
    } else {
        $count = 0;
        $config = new Config();
        $config->find();
        while ($config->fetch()) {
            $count++;
            printf("%-20s %-20s %s\n", $config->section, $config->setting,
                   var_export($config->value, true));
        }
        if ($count == 0) {
            print "No configuration set in database for this site.\n";
        }
    }
    exit(0);
}

if (count($args) < 2 || count($args) > 3) {
    show_help();
    exit(1);
}

$section = $args[0];
$setting = $args[1];

if (count($args) == 3) {
    $value = $args[2];
} else {
    $value = null;
}

try {

    if (have_option('d', 'delete')) { // Delete
        if (count($args) != 2) {
            show_help();
            exit(1);
        }

        if (have_option('v', 'verbose')) {
            print "Deleting setting $section/$setting...";
        }

        $setting = Config::pkeyGet(array('section' => $section,
                                         'setting' => $setting));

        if (empty($setting)) {
            print "Not found.\n";
        } else {
            $result = $setting->delete();
            if ($result) {
                print "DONE.\n";
            } else {
                print "ERROR.\n";
            }
        }
    } else if (count($args) == 2) { // show
        if (have_option('v', 'verbose')) {
            print "$section/$setting = ";
        }
        $value = common_config($section, $setting);
        print "$value\n";
    } else { // set
        if (have_option('v', 'verbose')) {
            print "Setting $section/$setting...";
        }
        Config::save($section, $setting, $value);
        print "DONE.\n";
    }

} catch (Exception $e) {
    print $e->getMessage() . "\n";
    exit(1);
}
