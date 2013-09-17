#!/bin/sh

# StatusNet - a distributed open-source microblogging tool

# Copyright (C) 2008, 2009, StatusNet, Inc.
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

# This program tries to start the daemons for StatusNet.
# Note that the 'maildaemon' needs to run as a mail filter.

ARGSG=
ARGSD=

if [ $# -gt 0 ]; then
    ARGSG="$ARGSG -s$1"
    ID=`echo $1 | sed s/\\\\./_/g`
    ARGSD="$ARGSD -s$1 -i$ID"
fi

if [ $# -gt 1 ]; then
    ARGSD="$ARGSD -p$2"
    ARGSG="$ARGSG -p$2"
fi

DIR=`dirname $0`
DAEMONS=`php $DIR/getvaliddaemons.php $ARGSG`

for f in $DAEMONS; do

    echo "Starting $f..."
    php $f $ARGSD
    echo "DONE."

done
