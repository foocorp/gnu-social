#!/bin/bash

# live fast! die young!

set -e

# move_status_network.sh nickname newserver

export nickname="$1"
export newdbhost="$2"

source /etc/statusnet/setup.cfg

function set_maintenance_mode()
{
    local nickname=$1;

    php $PHPBASE/scripts/settag.php $nickname maintenancemode;
}

function get_current_db_info()
{
    local nickname=$1;

    #FIXME I couldn't make this work better
    
    export dbhost=`mysql -NB -h $SITEDBHOSTNAME -u $ADMIN --password=$ADMINPASS -e "SELECT dbhost FROM status_network WHERE nickname = '$nickname'" $SITEDB`
    export dbuser=`mysql -NB -h $SITEDBHOSTNAME -u $ADMIN --password=$ADMINPASS -e "SELECT dbuser FROM status_network WHERE nickname = '$nickname'" $SITEDB`
    export dbpass=`mysql -NB -h $SITEDBHOSTNAME -u $ADMIN --password=$ADMINPASS -e "SELECT dbpass FROM status_network WHERE nickname = '$nickname'" $SITEDB`
    export dbname=`mysql -NB -h $SITEDBHOSTNAME -u $ADMIN --password=$ADMINPASS -e "SELECT dbname FROM status_network WHERE nickname = '$nickname'" $SITEDB`
}

function create_empty_db()
{
    local newdbhost=$1;
    local dbuser=$2;
    local dbpass=$3;
    local dbname=$4;
    
    mysqladmin -h $newdbhost -u $ADMIN --password=$ADMINPASS create $dbname;
    
    mysql -h $newdbhost -u $ADMIN --password=$ADMINPASS -e "GRANT ALL ON $dbname.* TO '$dbuser'@'localhost' IDENTIFIED BY '$dbpass';" $dbname;
    mysql -h $newdbhost -u $ADMIN --password=$ADMINPASS -e "GRANT ALL ON $dbname.* TO '$dbuser'@'%' IDENTIFIED BY '$dbpass';" $dbname;
}

function transfer_data()
{
    local dbhost=$1;
    local newdbhost=$2;
    local dbuser=$3;
    local dbpass=$4;
    local dbname=$5;
    local dumpfile=`mktemp`;

    mysqldump -h $dbhost -u $ADMIN --password=$ADMINPASS $dbname > $dumpfile;
    mysql -h $newdbhost -u $ADMIN --password=$ADMINPASS $dbname < $dumpfile;
    rm $dumpfile;
}

function update_routing_table()
{
    local nickname=$1;
    local newdbhost=$2;
    
    mysql -h $SITEDBHOSTNAME -u $ADMIN --password=$ADMINPASS -e "UPDATE status_network set dbhost = '$newdbhost' where nickname = '$nickname'" $SITEDB
}

function flush_site()
{
    local nickname=$1;
    
    php $PHPBASE/scripts/flushsite.php -s$nickname.$WILDCARD
}

function unset_maintenance_mode()
{
    local nickname=$1;
    
    php $PHPBASE/scripts/settag.php -d $nickname maintenancemode;
}

echo -n Setting maintenance mode on $nickname...
set_maintenance_mode $nickname
echo DONE.
echo -n Getting current database info...
get_current_db_info $nickname
echo DONE.
echo -n Creating empty $dbname database on server $newdbhost...
create_empty_db $newdbhost $dbuser $dbpass $dbname
echo DONE
echo -n Copying $dbname database from $dbhost to $newdbhost...
transfer_data $dbhost $newdbhost $dbuser $dbpass $dbname
echo DONE
echo -n Updating the routing table for $nickname to use $dbname on $newdbhost...
update_routing_table $nickname $newdbhost
echo DONE
echo -n Flushing $nickname site from cache...
flush_site $nickname
echo DONE
echo -n Turning off maintenance mode on $nickname...
unset_maintenance_mode $nickname
echo DONE.
