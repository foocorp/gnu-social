#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
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

// Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

function update_core($dir, $domain)
{
    $old = getcwd();
    chdir($dir);
    passthru(<<<END
xgettext \
    --from-code=UTF-8 \
    --default-domain=$domain \
    --output=locale/$domain.pot \
    --language=PHP \
    --add-comments=TRANS \
    --keyword="_m:1,1t" \
    --keyword="_m:1c,2,2t" \
    --keyword="_m:1,2,3t" \
    --keyword="_m:1c,2,3,4t" \
    --keyword="pgettext:1c,2" \
    --keyword="npgettext:1c,2,3" \
    actions/*.php \
    classes/*.php \
    lib/*.php \
    scripts/*.php
END
);
    chdir($old);
}

function do_update_plugin($dir, $domain)
{
    $old = getcwd();
    chdir($dir);
    if (!file_exists('locale')) {
        mkdir('locale');
    }
    $files = get_plugin_sources(".");
    $cmd = <<<END
xgettext \
    --from-code=UTF-8 \
    --default-domain=$domain \
    --output=locale/$domain.pot \
    --language=PHP \
    --add-comments=TRANS \
    --keyword='' \
    --keyword="_m:1,1t" \
    --keyword="_m:1c,2,2t" \
    --keyword="_m:1,2,3t" \
    --keyword="_m:1c,2,3,4t" \

END;
    foreach ($files as $file) {
      $cmd .= ' ' . escapeshellarg($file);
    }
    passthru($cmd);
    chdir($old);
}

function get_plugins($dir)
{
    $plugins = array();
    $dirs = new DirectoryIterator("$dir/plugins");
    foreach ($dirs as $item) {
        if ($item->isDir() && !$item->isDot()) {
            $name = $item->getBasename();
            if (file_exists("$dir/plugins/$name/{$name}Plugin.php")) {
                $plugins[] = $name;
            }
        }
    }
    return $plugins;
}

function get_plugin_sources($dir)
{
    $files = array();

    $dirs = new RecursiveDirectoryIterator($dir);
    $iter = new RecursiveIteratorIterator($dirs);
    foreach ($iter as $pathname => $item) {
        if ($item->isFile() && preg_match('/\.php$/', $item->getBaseName())) {
            $files[] = $pathname;
        }
    }
    return $files;
}

function plugin_using_gettext($dir)
{
    $files = get_plugin_sources($dir);
    foreach ($files as $pathname) {
        // Check if the file is using our _m gettext wrapper
        $code = file_get_contents($pathname);
        if (preg_match('/\b_m\(/', $code)) {
            return true;
        }
    }

    return false;
}

function update_plugin($basedir, $name)
{
    $dir = "$basedir/plugins/$name";
    if (plugin_using_gettext($dir)) {
        do_update_plugin($dir, $name);
        return true;
    } else {
        return false;
    }
}

$args = $_SERVER['argv'];
array_shift($args);

$all = false;
$core = false;
$allplugins = false;
$plugins = array();
if (count($args) == 0) {
    $all = true;
}
foreach ($args as $arg) {
    if ($arg == '--all') {
        $all = true;
    } elseif ($arg == "--core") {
        $core = true;
    } elseif ($arg == "--plugins") {
        $allplugins = true;
    } elseif (substr($arg, 0, 9) == "--plugin=") {
        $plugins[] = substr($arg, 9);
    } elseif ($arg == '--help') {
        echo "options: --all --core --plugins --plugin=Foo\n\n";
        exit(0);
    }
}

if ($all || $core) {
    echo "core...";
    update_core(INSTALLDIR, 'statusnet');
    echo " ok\n";
}
if ($all || $allplugins) {
    $plugins = get_plugins(INSTALLDIR);
}
if ($plugins) {
    foreach ($plugins as $plugin) {
        echo "$plugin...";
        if (update_plugin(INSTALLDIR, $plugin)) {
            echo " ok\n";
        } else {
            echo " not localized\n";
        }
    }
}
