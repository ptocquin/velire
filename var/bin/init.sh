#!/bin/bash

SHARED_DIR=$HOME/.lumiatec
BIN_DIR=$SHARED_DIR/bin
TMP_DIR=$SHARED_DIR/tmp
DATABASE_URL=$SHARED_DIR/data.db
#PYTHON_CMD="python3 $BIN_DIR/velire-cmd.py --config $BIN_DIR/config.yaml"
 
mkdir -p $SHARED_DIR $BIN_DIR $TMP_DIR
cp -r var/bin/* $BIN_DIR

php bin/console doctrine:database:create
php bin/console doctrine:schema:update --force

PASSWD=`htpasswd -bnBC 10 "" password | tr -d ":\n"`

sqlite3 $DATABASE_URL "insert into user (email, password, roles, api_token) values ('admin@lumiatec', '$PASSWD', '[\"ROLE_ADMIN\"]', 'b785d01cd4bdef3d8172c0ba1aaf08d7');"
sqlite3 $DATABASE_URL "insert into luminaire_status (code,message) values (0, 'OK');"
sqlite3 $DATABASE_URL "insert into luminaire_status (code,message) values (1, 'Warning');"
sqlite3 $DATABASE_URL "insert into luminaire_status (code,message) values (2, 'Error');"
sqlite3 $DATABASE_URL "insert into luminaire_status (code,message) values (99, 'Not detected');"

echo "* * * * * /usr/bin/php /var/www/lumiatec/bin/console app:check-run > /dev/null") | crontab -