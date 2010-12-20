#!/bin/bash

# live fast! die young!

set -e

source /etc/statusnet/setup.cfg || (echo "Failed to read /etc/statusnet/setup.cfg"; exit -1)

export nickname=$1
if [ "x" == "x$nickname" ]
then
    echo "Usage: delete_status_network.sh <site-nickname>"
    exit 1
fi

export database=$nickname$DBBASE

# Pull the status_network record so we know which DB server to drop from...
TARGET_DBHOST=`mysql -h $DBHOST -u $ADMIN --password=$ADMINPASS $SITEDB --batch --skip-column-names -e \
  "select dbhost from status_network where nickname='$nickname'"`

if [ "x" == "x$TARGET_DBHOST" ]
then
    echo "Aborting: Could not find status_network record for site $nickname"
    exit 1
fi

# Drop the database
echo "Dropping $database from $TARGET_DBHOST..."
mysqladmin -h $TARGET_DBHOST -u $ADMIN --password=$ADMINPASS -f drop $database || exit 1

# Remove the status_network entry
echo "Removing status_network entry for $nickname..."
mysql -h $DBHOST -u $ADMIN --password=$ADMINPASS $SITEDB -e \
    "delete from status_network where nickname = '$nickname'" || exit 1

# Remove uploaded file areas
for top in $AVATARBASE $FILEBASE $BACKGROUNDBASE; do
    if [ "x" == "x$top" ]
    then
        echo "Skipping deletion due to broken config"
    else
        echo "Deleting $top/$nickname"
        rm -Rf "$top/$nickname"
    fi
done

echo "Done."
