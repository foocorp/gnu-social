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
fixup_utf8.php <maxdate> <maxid> <minid>

Fixup records in a database that stored the data incorrectly (pre-0.7.4 for StatusNet).

ENDOFHELP;

require_once INSTALLDIR.'/scripts/commandline.inc';
require_once 'DB.php';

class UTF8FixerUpper
{
    var $dbl = null;
    var $dbu = null;
    var $args = array();

    function __construct($args)
    {
        $this->args = $args;

        if (!empty($args['max_date'])) {
            $this->max_date = strftime('%Y-%m-%d %H:%M:%S', strtotime($args['max_date']));
        } else {
            $this->max_date = strftime('%Y-%m-%d %H:%M:%S', time());
        }

        $this->dbl = $this->doConnect('latin1');

        if (empty($this->dbl)) {
            return;
        }

        $this->dbu = $this->doConnect('utf8');

        if (empty($this->dbu)) {
            return;
        }
    }

    function doConnect($charset)
    {
        $db = DB::connect(common_config('db', 'database'),
                          array('persistent' => false));

        if (PEAR::isError($db)) {
            echo "ERROR: " . $db->getMessage() . "\n";
            return NULL;
        }

        $conn = $db->connection;

        $succ = mysqli_set_charset($conn, $charset);

        if (!$succ) {
            echo "ERROR: couldn't set charset\n";
            $db->disconnect();
            return NULL;
        }

        $result = $db->autoCommit(true);

        if (PEAR::isError($result)) {
            echo "ERROR: " . $result->getMessage() . "\n";
            $db->disconnect();
            return NULL;
        }

        return $db;
    }

    function fixup()
    {
        $this->fixupNotices($this->args['max_notice'],
                            $this->args['min_notice']);
        $this->fixupProfiles();
        $this->fixupGroups();
        $this->fixupMessages();
    }

    function fixupNotices($max_id, $min_id) {

        // Do a separate DB connection

        $sth = $this->dbu->prepare("UPDATE notice SET content = UNHEX(?), rendered = UNHEX(?) WHERE id = ?");

        if (PEAR::isError($sth)) {
            echo "ERROR: " . $sth->getMessage() . "\n";
            return;
        }

        $sql = 'SELECT id, content, rendered FROM notice ' .
          'WHERE LENGTH(content) != CHAR_LENGTH(content) '.
          'AND modified < "'.$this->max_date.'" ';

        if (!empty($max_id)) {
            $sql .= ' AND id <= ' . $max_id;
        }

        if (!empty($min_id)) {
            $sql .= ' AND id >= ' . $min_id;
        }

        $sql .= ' ORDER BY id DESC';

        $rn = $this->dbl->query($sql);

        if (PEAR::isError($rn)) {
            echo "ERROR: " . $rn->getMessage() . "\n";
            return;
        }

        echo "Number of rows: " . $rn->numRows() . "\n";

        $notice = array();

        while (DB_OK == $rn->fetchInto($notice)) {

            $id = ($notice[0])+0;
            $content = bin2hex($notice[1]);
            $rendered = bin2hex($notice[2]);

            echo "$id...";

            $result = $this->dbu->execute($sth, array($content, $rendered, $id));

            if (PEAR::isError($result)) {
                echo "ERROR: " . $result->getMessage() . "\n";
                continue;
            }

            $cnt = $this->dbu->affectedRows();

            if ($cnt != 1) {
                echo "ERROR: 0 rows affected\n";
                continue;
            }

            $notice = Notice::getKV('id', $id);
            $notice->decache();
            $notice->free();

            echo "OK\n";
        }
    }

    function fixupProfiles()
    {
        // Do a separate DB connection

        $sth = $this->dbu->prepare("UPDATE profile SET ".
                                   "fullname = UNHEX(?),".
                                   "location = UNHEX(?), ".
                                   "bio = UNHEX(?) ".
                                   "WHERE id = ?");

        if (PEAR::isError($sth)) {
            echo "ERROR: " . $sth->getMessage() . "\n";
            return;
        }

        $sql = 'SELECT id, fullname, location, bio FROM profile ' .
          'WHERE (LENGTH(fullname) != CHAR_LENGTH(fullname) '.
          'OR LENGTH(location) != CHAR_LENGTH(location) '.
          'OR LENGTH(bio) != CHAR_LENGTH(bio)) '.
          'AND modified < "'.$this->max_date.'" '.
          ' ORDER BY modified DESC';

        $rn = $this->dbl->query($sql);

        if (PEAR::isError($rn)) {
            echo "ERROR: " . $rn->getMessage() . "\n";
            return;
        }

        echo "Number of rows: " . $rn->numRows() . "\n";

        $profile = array();

        while (DB_OK == $rn->fetchInto($profile)) {

            $id = ($profile[0])+0;
            $fullname = bin2hex($profile[1]);
            $location = bin2hex($profile[2]);
            $bio = bin2hex($profile[3]);

            echo "$id...";

            $result = $this->dbu->execute($sth, array($fullname, $location, $bio, $id));

            if (PEAR::isError($result)) {
                echo "ERROR: " . $result->getMessage() . "\n";
                continue;
            }

            $cnt = $this->dbu->affectedRows();

            if ($cnt != 1) {
                echo "ERROR: 0 rows affected\n";
                continue;
            }

            $profile = Profile::getKV('id', $id);
            $profile->decache();
            $profile->free();

            echo "OK\n";
        }
    }

    function fixupGroups()
    {
        // Do a separate DB connection

        $sth = $this->dbu->prepare("UPDATE user_group SET ".
                                   "fullname = UNHEX(?),".
                                   "location = UNHEX(?), ".
                                   "description = UNHEX(?) ".
                                   "WHERE id = ?");

        if (PEAR::isError($sth)) {
            echo "ERROR: " . $sth->getMessage() . "\n";
            return;
        }

        $sql = 'SELECT id, fullname, location, description FROM user_group ' .
          'WHERE LENGTH(fullname) != CHAR_LENGTH(fullname) '.
          'OR LENGTH(location) != CHAR_LENGTH(location) '.
          'OR LENGTH(description) != CHAR_LENGTH(description) '.
          'AND modified < "'.$this->max_date.'" '.
          'ORDER BY modified DESC';

        $rn = $this->dbl->query($sql);

        if (PEAR::isError($rn)) {
            echo "ERROR: " . $rn->getMessage() . "\n";
            return;
        }

        echo "Number of rows: " . $rn->numRows() . "\n";

        $user_group = array();

        while (DB_OK == $rn->fetchInto($user_group)) {

            $id = ($user_group[0])+0;
            $fullname = bin2hex($user_group[1]);
            $location = bin2hex($user_group[2]);
            $description = bin2hex($user_group[3]);

            echo "$id...";

            $result = $this->dbu->execute($sth, array($fullname, $location, $description, $id));

            if (PEAR::isError($result)) {
                echo "ERROR: " . $result->getMessage() . "\n";
                continue;
            }

            $cnt = $this->dbu->affectedRows();

            if ($cnt != 1) {
                echo "ERROR: 0 rows affected\n";
                continue;
            }

            $user_group = User_group::getKV('id', $id);
            $user_group->decache();
            $user_group->free();

            echo "OK\n";
        }
    }

    function fixupMessages() {

        // Do a separate DB connection

        $sth = $this->dbu->prepare("UPDATE message SET content = UNHEX(?), rendered = UNHEX(?) WHERE id = ?");

        if (PEAR::isError($sth)) {
            echo "ERROR: " . $sth->getMessage() . "\n";
            return;
        }

        $sql = 'SELECT id, content, rendered FROM message ' .
          'WHERE LENGTH(content) != CHAR_LENGTH(content) '.
          'AND modified < "'.$this->max_date.'" '.
          'ORDER BY id DESC';

        $rn = $this->dbl->query($sql);

        if (PEAR::isError($rn)) {
            echo "ERROR: " . $rn->getMessage() . "\n";
            return;
        }

        echo "Number of rows: " . $rn->numRows() . "\n";

        $message = array();

        while (DB_OK == $rn->fetchInto($message)) {

            $id = ($message[0])+0;
            $content = bin2hex($message[1]);
            $rendered = bin2hex($message[2]);

            echo "$id...";

            $result = $this->dbu->execute($sth, array($content, $rendered, $id));

            if (PEAR::isError($result)) {
                echo "ERROR: " . $result->getMessage() . "\n";
                continue;
            }

            $cnt = $this->dbu->affectedRows();

            if ($cnt != 1) {
                echo "ERROR: 0 rows affected\n";
                continue;
            }

            $message = Message::getKV('id', $id);
            $message->decache();
            $message->free();

            echo "OK\n";
        }
    }
}

$max_date = (count($args) > 0) ? $args[0] : null;
$max_id = (count($args) > 1) ? $args[1] : null;
$min_id = (count($args) > 2) ? $args[2] : null;

$fixer = new UTF8FixerUpper(array('max_date' => $max_date,
                                  'max_notice' => $max_id,
                                  'min_notice' => $min_id));

$fixer->fixup();

