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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Abort if called from a web server

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$helptext = <<<ENDOFHELP
console.php - provide an interactive PHP interpreter for testing

ENDOFHELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

// Assume we're on a terminal if on Windows, otherwise posix_isatty tells us.
define('CONSOLE_INTERACTIVE', !function_exists('posix_isatty') || posix_isatty(0));
define('CONSOLE_READLINE', CONSOLE_INTERACTIVE && function_exists('readline'));

if (CONSOLE_READLINE && CONSOLE_INTERACTIVE) {
    define('CONSOLE_HISTORY', getenv("HOME") . "/.statusnet_console_history");
    if (file_exists(CONSOLE_HISTORY)) {
        readline_read_history(CONSOLE_HISTORY);
    }
}

function read_input_line($prompt)
{
    if (CONSOLE_INTERACTIVE) {
        if (CONSOLE_READLINE) {
            $line = readline($prompt);
            if (trim($line) != '') {
                readline_add_history($line);
                if (defined('CONSOLE_HISTORY')) {
                    // Save often; it's easy to hit fatal errors.
                    readline_write_history(CONSOLE_HISTORY);
                }
            }
            return $line;
        } else {
            return readline_emulation($prompt);
        }
    } else {
        return fgets(STDIN);
    }
}

/**
 * On Unix-like systems where PHP readline extension isn't present,
 * -cough- Mac OS X -cough- we can shell out to bash to do it for us.
 * This lets us at least handle things like arrow keys, but we don't
 * get any entry history. :(
 *
 * Shamelessly ripped from when I wrote the same code for MediaWiki. :)
 * @author Brion Vibber <brion@status.net>
 *
 * @param string $prompt
 * @return mixed string on success, false on fail or EOF
 */
function readline_emulation($prompt)
{
    if(CONSOLE_INTERACTIVE && file_exists(trim(shell_exec('which bash')))) {
        $encPrompt = escapeshellarg($prompt);
        $command = "read -er -p $encPrompt && echo \"\$REPLY\"";
        $encCommand = escapeshellarg($command);
        $metaCommand = "bash -c $encCommand";

        // passthru passes our STDIN and TTY to the child...
        // We can pull the returned string via output buffering.
        ob_start();
        $retval = false;
        passthru($metaCommand, $retval);
        $line = ob_get_contents();
        ob_end_clean();

        if ($retval == 0) {
            return $line;
        } elseif ($retval == 127) {
            // Couldn't execute bash even though we thought we saw it.
            // Shell probably spit out an error message, sorry :(
            // Fall through to fgets()...
        } else {
            // EOF/ctrl+D
            return false;
        }
    }

    // Fallback... we'll have no editing controls, EWWW
    if (feof(STDIN)) {
        return false;
    }
    if (CONSOLE_INTERACTIVE) {
        print $prompt;
    }
    return fgets(STDIN);
}

function console_help()
{
    print "Welcome to GNU social's interactive PHP console!\n";
    print "Type some PHP code and it'll execute...\n";
    print "\n";
    print "Hint: return a value of any type to output it via var_export():\n";
    print "  \$profile = new Profile();\n";
    print "  \$profile->find();\n";
    print "  \$profile->fetch();\n";
    print "  return \$profile;\n";
    print "\n";
    print "Note that PHP is cranky and you can easily kill your session by mistyping.\n";
    print "\n";
    print "Type ctrl+D or enter 'exit' to exit.\n";
}

if (CONSOLE_INTERACTIVE) {
    print "GNU social interactive PHP console... type ctrl+D or enter 'exit' to exit.\n";
    $prompt = common_slugify(common_config('site', 'name')) . '> ';
} else {
    $prompt = '';
}
while (!feof(STDIN)) {
    $line = read_input_line($prompt);
    if ($line === false) {
        if (CONSOLE_INTERACTIVE) {
            print "\n";
        }
        break;
    } elseif ($line !== '') {
        try {
            if (trim($line) == 'exit') {
                break;
            } elseif (trim($line) == 'help') {
                console_help();
                continue;
            }
            
            // Let's do this!
            $result = eval($line);
            if ($result === false) {
                // parse error
            } elseif ($result === null) {
                // no return
            } else {
                // return value from eval'd code
                var_export($result);
            }
        } catch(Exception $e) {
            print get_class($e) . ": " . $e->getMessage() . "\n";
        }
    }
    if (CONSOLE_INTERACTIVE) {
        print "\n";
    }
}
