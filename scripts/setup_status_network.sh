#!/bin/bash

# live fast! die young!

set -e

source /etc/statusnet/setup.cfg

# setup_status_network.sh mysite 'My Site' '1user' 'owner@example.com' 'Firsty McLastname'

export nickname="$1"
export sitename="$2"
export tags="$3"
export email="$4"
export fullname="$5"
export siteplan="$6"

if [ "$siteplan" == '' ]; then
    siteplan='single-user'
fi

# Fixme: if this is changed later we need to update profile URLs
# for the created user.
export server="$nickname.$WILDCARD"

# End-user info
export userpass=`$PWDGEN`
export roles="administrator moderator owner"

# DB info
export password=`$PWDGEN`
export database=$nickname$DBBASE
export username=$nickname$USERBASE

# Create the db

mysqladmin -h $DBHOST -u $ADMIN --password=$ADMINPASS create $database

for f in statusnet.sql innodb.sql sms_carrier.sql foreign_services.sql notice_source.sql; do
    mysql -h $DBHOST -u $ADMIN --password=$ADMINPASS $database < ../db/$f;
done

mysql -h $DBHOST -u $ADMIN --password=$ADMINPASS $SITEDB << ENDOFCOMMANDS

GRANT ALL ON $database.* TO '$username'@'localhost' IDENTIFIED BY '$password';
GRANT ALL ON $database.* TO '$username'@'%' IDENTIFIED BY '$password';
INSERT INTO status_network (nickname, dbhost, dbuser, dbpass, dbname, sitename, created)
VALUES ('$nickname', '$DBHOSTNAME', '$username', '$password', '$database', '$sitename', now());

ENDOFCOMMANDS

for top in $AVATARBASE $FILEBASE $BACKGROUNDBASE; do
    mkdir $top/$nickname
    chmod a+w $top/$nickname
done

php $PHPBASE/scripts/checkschema.php -s"$server"

php $PHPBASE/scripts/settag.php -s"$server" "$nickname" "$tags"

php $PHPBASE/scripts/registeruser.php \
  -s"$server" \
  -n"$nickname" \
  -f"$fullname" \
  -w"$userpass" \
  -e"$email"

for role in $roles
do
  php $PHPBASE/scripts/userrole.php \
    -s"$server" \
    -n"$nickname" \
    -r"$role"
done

if [ -f "$MAILTEMPLATE" ]
then
    # fixme how safe is this? are sitenames sanitized?
    cat $MAILTEMPLATE | \
      sed "s/\$nickname/$nickname/" | \
      sed "s/\$sitename/$sitename/" | \
      sed "s/\$userpass/$userpass/" | \
      sed "s/\$siteplan/$siteplan/" | \
      php $PHPBASE/scripts/sendemail.php \
        -s"$server" \
        -n"$nickname" \
        --subject="$MAILSUBJECT"
else
    echo "No mail template, not sending email."
fi

if [ -f "$POSTINSTALL" ]
then
    echo "Running $POSTINSTALL ..."
    source "$POSTINSTALL"
fi
