# Init sur SD-card pour Ubuntu-Server
Ajouter netplan.yaml au bon endroit
autoriser ssh
sudo sed -i 's/#PermitRootLogin prohibit-password/PermitRootLogin yes/' /etc/ssh/sshd_config

# Init sur SD-card pour Raspbian
touch ssh
### Raspbian ?
# https://learn.sparkfun.com/tutorials/headless-raspberry-pi-setup/ethernet-with-static-ip-address
# https://www.raspberrypi.org/documentation/configuration/tcpip/
cd /media/<USERNAME>/rootfs
sudo nano /etc/dhcpcd.conf

interface eth0

static ip_address=139.165.112.145/24
static routers=139.165.112.1
static domain_name_servers=139.165.214.214

sudo raspi-config # set time-zone

# Netplan on raspbian ?
# https://snapcraft.io/install/netplan/raspbian
# sudo apt install python3-netifaces
apt install netplan # j'ai un doute sur le fait que ça marche


# set time zone
sudo dpkg-reconfigure tzdata

sudo apt update && sudo apt upgrade

#php7.4 sur Ubuntu 20.04 / php7.2 sur raspbian 07-2020
sudo apt install nginx sqlite3 cmdtest apache2-utils composer php7.2-cli php7.2-fpm php7.2-sqlite3 php7.2-xml php7.2-curl php7.2-mbstring
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
sudo cp .env.dist .env
# sudo sed  -i 's/$HOME/\/home\/ubuntu/' .env
sudo chown -R :ubuntu .
sudo chown -R www-data public/
sudo chmod -R g+w .
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

sudo chmod -R 777 var/cache var/log
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
	echo -e "$(crontab -l)\n*/5 * * * * /usr/bin/php /var/www/lumiatec/bin/console app:log > /dev/null" | crontab -

#sudo chmod a+x /usr/local/bin/init-lumiatec.sh
#init-lumiatec.sh

	sudo chown -R www-data ~/.lumiatec
	sudo chmod -R 777 ~/.lumiatec
	sudo chown -R www-data /var/www/lumiatec/public/
	sudo usermod -a -G dialout www-data 


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
      optional: true
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
      optional: true

cd /etc/netplan
sudo ln -s /var/www/lumiatec/var/netplan.yaml lumiatec-network.yaml

sudo reboot

sudo apt install openvpn
# sudo mkdir -p /etc/openvpn
# sudo chmod 700 /etc/openvn
sudo chown ubuntu:root /etc/openvpn
sudo chmod 770 /etc/openvpn
sudo cp /var/www/lumiatec/var/bin/lumiatecvpn.conf /etc/openvpn/lumiatecvpn.conf
echo raspitest > /etc/openvpn/lumiatec.txt
sudo openvpn /etc/openvpn/lumiatecvpn.conf

# Pour utiliser des liens symboliques vers certificats: https://www.linuxquestions.org/questions/linux-software-2/openvpn-won%27t-work-with-keys-in-different-directory-4175637168/
# sudo nano /lib/systemd/system/openvpn@.service
# ProtectHome=true to ProtectHome=read-only
sudo sed -i 's/ProtectHome=true/ProtectHome=read-only/' /lib/systemd/system/openvpn@.service



# https://serverfault.com/a/918441
# permettre l'accès ssh sur l'adresse IP publique quand VPN actif
sudo ip rule add from $(ip route get 1 | grep -Po '(?<=src )(\S+)') table 128
sudo ip route add table 128 to $(ip route get 1 | grep -Po '(?<=src )(\S+)')/32 dev $(ip -4 route ls | grep default | grep -Po '(?<=dev )(\S+)')
sudo ip route add table 128 default via $(ip -4 route ls | grep default | grep -Po '(?<=via )(\S+)')


#https://obrienlabs.net/setup-kiosk-ubuntu-chromium/
#https://levelup.gitconnected.com/how-to-create-interactive-kiosk-with-chromium-ubuntu-c249834dd0cc
sudo apt install lightdm gnome-core xfonts-base xserver-xorg
sudo apt-get install -y chromium-browser unclutter #xdotool

sudo adduser kiosk
sudo nano /etc/lightdm/lightdm.conf.d/60-myconf.conf

[Seat:*]
autologin-user=kiosk
autologin-user-timeout=0
user-session=ubuntu
greeter-session=unity-greeter

# sudo nano /etc/lightdm/lightdm.conf.d/50-myconfig.conf

# [SeatDefaults]
# autologin-user=kiosk

sudo mkdir -p /home/kiosk/.config/autostart && sudo nano /home/kiosk/.config/autostart/kiosk.desktop

[Desktop Entry]
Type=Application
Name=Kiosk
Exec=/home/kiosk/kiosk.sh
X-GNOME-Autostart-enabled=true

sudo nano /home/kiosk/kiosk.sh

#!/bin/bash

# Run this script in display 0 - the monitor
export DISPLAY=:0

# Hide the mouse from the display
unclutter &

# If Chromium crashes (usually due to rebooting), clear the crash flag so we don't have the annoying warning bar
sed -i 's/"exited_cleanly":false/"exited_cleanly":true/' /home/kiosk/.config/chromium/Default/Preferences
sed -i 's/"exit_type":"Crashed"/"exit_type":"Normal"/' /home/kiosk/.config/chromium/Default/Preferences

# Run Chromium and open tabs
/usr/bin/chromium-browser --window-size=1920,1080 --kiosk --window-position=0,0 http://127.0.0.1 &

# Start the kiosk loop. This keystroke changes the Chromium tab
# To have just anti-idle, use this line instead:
# xdotool keydown ctrl; xdotool keyup ctrl;
# Otherwise, the ctrl+Tab is designed to switch tabs in Chrome
# #
# while (true)
#   do
#     xdotool keydown ctrl+Tab; xdotool keyup ctrl+Tab;
#     sleep 15
# done


sudo chmod a+x /home/kiosk/kiosk.sh

# rotation de l'écran
sudo nano /boot/firmware/usercfg.txt

	lcd_rotate=2

# Raspbian https://pimylifeup.com/raspberry-pi-kiosk/

#https://www.unixtutorial.org/disable-sleep-on-ubuntu-server/
sudo systemctl mask sleep.target suspend.target hibernate.target hybrid-sleep.target
#sudo systemctl unmask sleep.target suspend.target hibernate.target hybrid-sleep.target
#systemctl status sleep.target
gsettings set org.gnome.settings-daemon.plugins.power sleep-inactive-ac-type 'nothing'


