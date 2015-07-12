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

// Abort if called from a web server

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

require_once INSTALLDIR.'/scripts/commandline.inc';

$base = INSTALLDIR;
$encBase = escapeshellarg($base);

$ver = GNUSOCIAL_VERSION;

// @fixme hack
if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $ver, $matches)) {
    list(, $a, $b, $c) = $matches;
    if ($c > '0') {
        $cprime = $c - 1;
        $prev = "$a.$b.$cprime";
    } else {
        die("This is a .0 release; you need to provide a thingy.\n");
    }
}

$tag = $ver;
$prefix = "statusnet-$tag";
$target = "$prefix.tar.gz";

$cmd = <<<END
(cd $encBase && git archive --prefix=$prefix/ $tag | gzip > /tmp/$target) && \
(cd /tmp && tar zxf $target && cd $prefix && make) && \
(cd $encBase && git log --oneline {$prev}..{$tag} > /tmp/$prefix/Changelog) && \
(cd /tmp && tar zcf $target $prefix) && \
(cd /tmp && rm -rf $prefix) && \
(mv /tmp/$target .)
END;

echo $cmd;
echo "\n";
