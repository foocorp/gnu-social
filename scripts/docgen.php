#!/usr/bin/env php
<?php

$shortoptions = '';
$longoptions = array('plugin=');


$helptext = <<<ENDOFHELP
Build HTML documentation from doc comments in source.

Usage: docgen.php [options] output-directory
Options:

  --plugin=...     build docs for given plugin instead of core


ENDOFHELP;

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
set_include_path(INSTALLDIR . DIRECTORY_SEPARATOR . 'extlib' . PATH_SEPARATOR . get_include_path());

$pattern = "*.php *.inc";
$exclude = 'config.php */extlib/* */local/* */plugins/* */scripts/*';
$plugin = false;

require_once 'Console/Getopt.php';
$parser = new Console_Getopt();
$result = $parser->getopt($_SERVER['argv'], $shortoptions, $longoptions);
if (PEAR::isError($result)) {
    print $result->getMessage() . "\n";
    exit(1);
}
list($options, $args) = $result;

foreach ($options as $option) {
    $arg = $option[0];
    if ($arg == '--plugin') {
        $plugin = $options[1];
    } else if ($arg == 'h' || $arg == '--help') {
        print $helptext;
        exit(0);
    }
}

if (isset($args[0])) {
    $outdir = $args[0];
    if (!is_dir($outdir)) {
        echo "Output directory $outdir is not a directory.\n";
        exit(1);
    }
} else {
    print $helptext;
    exit(1);
}

if ($plugin) {
    $exclude = "*/extlib/*";
    $indir = INSTALLDIR . "/plugins/" . $plugin;
    if (!is_dir($indir)) {
        $indir = INSTALLDIR . "/plugins";
        $filename = "{$plugin}Plugin.php";
        if (!file_exists("$indir/$filename")) {
            echo "Can't find plugin $plugin.\n";
            exit(1);
        } else {
            $pattern = $filename;
        }
    }
} else {
    $indir = INSTALLDIR;
}

function getVersion()
{
    // define('GNUSOCIAL_VERSION', '0.9.1');
    $source = file_get_contents(INSTALLDIR . '/lib/common.php');
    if (preg_match('/^\s*define\s*\(\s*[\'"]GNUSOCIAL_VERSION[\'"]\s*,\s*[\'"](.*)[\'"]\s*\)\s*;/m', $source, $matches)) {
        return $matches[1];
    }
    return 'unknown';
}


$replacements = array(
    '%%version%%' => getVersion(),
    '%%indir%%' => $indir,
    '%%pattern%%' => $pattern,
    '%%outdir%%' => $outdir,
    '%%htmlout%%' => $outdir,
    '%%exclude%%' => $exclude,
);

var_dump($replacements);

$template = file_get_contents(dirname(__FILE__) . '/doxygen.tmpl');
$template = strtr($template, $replacements);

$templateFile = tempnam(sys_get_temp_dir(), 'statusnet-doxygen');
file_put_contents($templateFile, $template);

$cmd = "doxygen " . escapeshellarg($templateFile);

$retval = 0;
passthru($cmd, $retval);

if ($retval == 0) {
    echo "Done!\n";
    unlink($templateFile);
    exit(0);
} else {
    echo "Failed! Doxygen config left in $templateFile\n";
    exit($retval);
}

