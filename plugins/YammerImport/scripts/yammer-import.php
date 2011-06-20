<?php

if (php_sapi_name() != 'cli') {
    die('no');
}

define('INSTALLDIR', dirname(dirname(dirname(dirname(__FILE__)))));

$longoptions = array('verify=', 'reset');
require INSTALLDIR . "/scripts/commandline.inc";

echo "Checking current state...\n";
$runner = YammerRunner::init();

if (have_option('reset')) {
    echo "Resetting Yammer import state...\n";
    $runner->reset();
    echo "done.\n";
    exit(0);
}

switch ($runner->state())
{
    case 'init':
        echo "Requesting authentication to Yammer API...\n";
        $url = $runner->requestAuth();
        echo "Log in to Yammer at the following URL and confirm permissions:\n";
        echo "\n";
        echo "    $url\n";
        echo "\n";
        echo "Pass the resulting code back by running:\n";
        echo "\n";
        echo "    php yammer-import.php --verify=####\n";
        echo "\n";
        break;

    case 'requesting-auth':
        if (!have_option('verify')) {
            echo "Awaiting authentication...\n";
            echo "\n";
            echo "If you need to start over, reset the state:\n";
            echo "\n";
            echo "    php yammer-import.php --reset\n";
            echo "\n";
            exit(1);
        }
        echo "Saving final authentication token for Yammer API...\n";
        $runner->saveAuthToken(get_option_value('verify'));
        // Fall through...

    default:
        while ($runner->hasWork()) {
            echo "... {$runner->state()}\n";
            if (!$runner->iterate()) {
                echo "FAIL??!?!?!\n";
            }
        }
        if ($runner->isDone()) {
            echo "... done.\n";
        } else {
            echo "... no more import work scheduled.\n";
        }
        break;
}
