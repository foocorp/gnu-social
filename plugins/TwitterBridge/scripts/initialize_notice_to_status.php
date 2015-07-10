#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

$helptext = <<<ENDOFHELP
USAGE: initialize_notice_to_status.php

Initializes the notice_to_status table with existing Twitter synch
data. Only necessary if you've had the Twitter bridge enabled before
version 0.9.5.

ENDOFHELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

// We update any notices that may have come in from
// Twitter that we don't have a status_id for. Note that
// this won't catch notices that originated at this StatusNet site.

$n = new Notice();

$n->query('SELECT notice.id, notice.uri ' .
          'FROM notice LEFT JOIN notice_to_status ' .
          'ON notice.id = notice_to_status.notice_id ' .
          'WHERE notice.source = "twitter"' .
          'AND notice_to_status.status_id IS NULL');

while ($n->fetch()) {
    if (preg_match('/^http://twitter.com(/#!)?/[\w_.]+/status/(\d+)$/', $n->uri, $match)) {
        $status_id = $match[1];
        Notice_to_status::saveNew($n->id, $status_id);
    }
}
