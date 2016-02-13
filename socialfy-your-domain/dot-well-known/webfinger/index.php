<?php

/*
 * GNU social
 * Copyright (C) 2010, Free Software Foundation, Inc
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


// basename should make sure we can't escape this directory
$u = basename($_GET['resource']);

if (!strpos($u, '@')) {
    throw new Exception('Bad resource');
    exit(1);
}

if (mb_strpos($u, 'acct:')===0) {
    $u = substr($u, 5);
}

// Just to be a little bit safer, you know, with all the unicode stuff going on
$u = filter_var($u, FILTER_SANITIZE_EMAIL);

$f = $u . ".xml";

if (file_exists($f)) {
  header('Content-Disposition: attachment; filename="'.urlencode($f).'"');
  header('Content-type: application/xrd+xml');
  echo file_get_contents($f);
}
