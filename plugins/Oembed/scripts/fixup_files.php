#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2010 StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$longoptions = array('dry-run');

$helptext = <<<END_OF_USERROLE_HELP
fixup_files.php [options]
Patches up file entries with corrupted types and titles (the "h bug").

     --dry-run  look but don't touch

END_OF_USERROLE_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$dry = have_option('dry-run');

$f = new File();
$f->title = 'h';
$f->mimetype = 'h';
$f->size = 0;
$f->protected = 0;
$f->find();
echo "Found $f->N bad items:\n";

while ($f->fetch()) {
    echo "$f->id $f->url";

    $data = File_redirection::lookupWhere($f->url);
    if ($dry) {
        if (is_array($data)) {
            echo " (unchanged)\n";
        } else {
            echo " (unchanged, but embedding lookup failed)\n";
        }
    } else {
        // NULL out the mime/title/size/protected fields
        $sql = sprintf("UPDATE file " .
                       "SET mimetype=null,title=null,size=null,protected=null " .
                       "WHERE id=%d",
                       $f->id);
        $f->query($sql);
        $f->decache();
        
        if (is_array($data)) {
            Event::handle('EndFileSaveNew', array($f, $data, $f->url));
            echo " (ok)\n";
        } else {
            echo " (ok, but embedding lookup failed)\n";
        }
    }
}

echo "done.\n";

