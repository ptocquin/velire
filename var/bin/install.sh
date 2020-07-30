# Init sur SD-card
Ajouter netplan.yaml au bon endroit
autoriser ssh
sudo sed -i 's/#PermitRootLogin prohibit-password/PermitRootLogin yes/' /etc/ssh/sshd_config

# set time zone
sudo dpkg-reconfigure tzdata

sudo apt update && sudo apt upgrade
sudo apt install nginx sqlite3 cmdtest apache2-utils composer php7.4-cli php7.4-fpm php7.4-sqlite3 php7.4-xml php7.4-curl php7.4-mbstring

sudo nano /etc/nginx/sites-available/lumiatec

	server {
	    server_name domain.tld www.domain.tld;
	    root /var/www/lumiatec/public;

	    location / {
	        # try to serve file directly, fallback to index.php
	        try_files $uri /index.php$is_args$args;
	    }

	    # optionally disable falling back to PHP script for the asset directories;
	    # nginx will return a 404 error when files are not found instead of passing the
	    # request to Symfony (improves performance but Symfony's 404 page is not displayed)
	    # location /bundles {
	    #     try_files $uri =404;
	    # }

	    location ~ ^/index\.php(/|$) {
	        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
	        fastcgi_split_path_info ^(.+\.php)(/.*)$;
	        include fastcgi_params;

	        # optionally set the value of the environment variables used in the application
	        # fastcgi_param APP_ENV prod;
	        # fastcgi_param APP_SECRET <app-secret-id>;
	        # fastcgi_param DATABASE_URL "mysql://db_user:db_pass@host:3306/db_name";

	        # When you are using symlinks to link the document root to the
	        # current version of your application, you should pass the real
	        # application path instead of the path to the symlink to PHP
	        # FPM.
	        # Otherwise, PHP's OPcache may not properly detect changes to
	        # your PHP files (see https://github.com/zendtech/ZendOptimizerPlus/issues/126
	        # for more information).
	        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
	        fastcgi_param DOCUMENT_ROOT $realpath_root;
	        # Prevents URIs that include the front controller. This will 404:
	        # http://domain.tld/index.php/some-path
	        # Remove the internal directive to allow URIs like this
	        internal;
	    }

	    # return 404 for all other php files not matching the front controller
	    # this prevents access to other php files you don't want to be accessible.
	    location ~ \.php$ {
	        return 404;
	    }

	    error_log /var/log/nginx/lumiatec_error.log;
	    access_log /var/log/nginx/lumiatec_access.log;
	}

cd /etc/nginx/sites-enabled/
sudo ln -sf /etc/nginx/sites-available/lumiatec default

sudo nginx -s reload

cd /var/www/
sudo git clone https://github.com/ptocquin/velire.git lumiatec
cd lumiatec
sudo cp .env.div .env
#sudo nano .env

	# This file is a "template" of which env vars need to be defined for your application
	# Copy this file to .env file for development, create environment variables when deploying to production
	# https://symfony.com/doc/current/best_practices/configuration.html#infrastructure-related-configuration

	###> symfony/framework-bundle ###
	APP_ENV=prod
	APP_SECRET=6c21298c15c9d4fed36955a6d2d030b5
	###< symfony/framework-bundle ###

	###> doctrine/doctrine-bundle ###
	# Format described at http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url
	# For an SQLite database, use: "sqlite:///%kernel.project_dir%/var/data.db"
	# Configure your db driver and server_version in config/packages/doctrine.yaml
	# DATABASE_URL=mysql://db_user:db_password@127.0.0.1:3306/db_name
	# DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db
	###< doctrine/doctrine-bundle ###

	###> symfony/swiftmailer-bundle ###
	# For Gmail as a transport, use: "gmail://username:password@localhost"
	# For a generic SMTP server, use: "smtp://localhost:25?encryption=&auth_mode="
	# Delivery is disabled by default via "null://localhost"
	MAILER_URL=null://localhost
	###< symfony/swiftmailer-bundle ###

	###> nelmio/cors-bundle ###
	CORS_ALLOW_ORIGIN=^https?://localhost(:[0-9]+)?$
	###< nelmio/cors-bundle ###

	SHARED_DIR=$HOME/.lumiatec
	BIN_DIR=$SHARED_DIR/bin
	TMP_DIR=$SHARED_DIR/tmp
	DATABASE_URL=sqlite:///$SHARED_DIR/data.db
	PYTHON_CMD="python3 $BIN_DIR/lumiatec-cmd.py --port /dev/ttyUSB0 --model lumiatec-phs16"
	BASH_CMD="sh $BIN_DIR/velire.sh"


sudo composer install --no-dev --optimize-autoloader
./var/bin/init.sh

#sudo nano /usr/local/bin/init-lumiatec.sh

	#!/bin/bash

	SHARED_DIR=/home/ubuntu/.lumiatec
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

	echo "* * * * * /usr/bin/php /var/www/lumiatec/bin/console app:check-run > /dev/null" | crontab -

#sudo chmod a+x /usr/local/bin/init-lumiatec.sh
#init-lumiatec.sh

sudo chown -R www-data ~/.lumiatec
sudo chmod -R 777 ~/.lumiatec
sudo chown -R www-data /var/www/lumiatec/public/
sudo usermod -a -G dialout www-data 
sudo chmod -R 777 var/cache var/log

sudo nano /var/www/lumiatec/var/netplan.yaml # éditer les info

network:
  version: 2
  ethernets:
    eth0:
      addresses: [139.165.112.145/24]
      gateway4: 139.165.112.1
      nameservers:
          addresses: [139.165.214.214, 8.8.8.8, 8.8.4.4]
      dhcp4: yes
      dhcp6: false
  wifis:
    wlan0:
      access-points:
        ULiege:
          auth:
            key-management: eap
            method: peap
            identity: "f054745"
            password: "avtK5772"
      dhcp4: yes

cd /etc/netplan
sudo ln -s /var/www/lumiatec/var/netplan.yaml lumiatec-network.yaml

sudo reboot

sudo apt install openvpn
# sudo mkdir -p /etc/openvpn
sudo chmod 700 /etc/openvn
sudo chown ubuntu:root /etc/openvpn
sudo chmod 770 /etc/openvpn
sudo cp /var/www/lumiatec/var/bin/lumiatecvpn.conf /etc/openvpn/lumiatecvpn.conf
echo raspitest /etc/openvpn/lumiatec.txt
sudo openvpn /etc/openvpn/lumiatecvpn.conf



# https://serverfault.com/a/918441
# permettre l'accès ssh sur l'adresse IP publique quand VPN actif
sudo ip rule add from $(ip route get 1 | grep -Po '(?<=src )(\S+)') table 128
sudo ip route add table 128 to $(ip route get 1 | grep -Po '(?<=src )(\S+)')/32 dev $(ip -4 route ls | grep default | grep -Po '(?<=dev )(\S+)')
sudo ip route add table 128 default via $(ip -4 route ls | grep default | grep -Po '(?<=via )(\S+)')


#https://obrienlabs.net/setup-kiosk-ubuntu-chromium/
sudo apt-get install -y chromium-browser unclutter xdotool
sudo apt install lightdm
sudo adduser kiosk
sudo nano /etc/lightdm/lightdm.conf

	[SeatDefaults]
	autologin-user=kiosk
	autologin-user-timeout=0
	user-session=ubuntu
	greeter-session=unity-greeter

sudo nano /etc/lightdm/lightdm.conf.d/50-myconfig.conf

	[SeatDefaults]
	autologin-user=kiosk

# rotation de l'écran
sudo nano /boot/firmware/usercfg.txt

	display_rotate=2





