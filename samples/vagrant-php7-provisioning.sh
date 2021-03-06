#!/bin/bash
#
export DEBIAN_FRONTEND=noninteractive

RED='\033[0;31m'
NC='\033[0m' # No Color

DBHOST="localhost"
DBNAME="roadiz"
DBUSER="roadiz"
DBPASSWD="roadiz"

echo -e "\n--- Okay, installing now... ---\n"
sudo apt-get -qq update;

echo -e "\n--- Install base packages ---\n"
sudo locale-gen fr_FR.utf8;

echo -e "\n--- Add some repos to update our distro ---\n"
LC_ALL=C.UTF-8 sudo add-apt-repository ppa:ondrej/php > /dev/null 2>&1;
if [ $? -eq 0 ]; then
   echo -e "\t--- OK\n"
else
   echo -e "${RED}\t!!! FAIL${NC}\n"
   echo -e "${RED}\t!!! Please destroy your vagrant and provision again.${NC}\n"
   exit 1;
fi


# Use latest nginx for HTTP/2
sudo cp -a /var/www/samples/vagrant/sources.list.d/nginx.list /etc/apt/sources.list.d/nginx.list;
wget -q -O- http://nginx.org/keys/nginx_signing.key | sudo apt-key add - > /dev/null 2>&1;
if [ $? -eq 0 ]; then
   echo -e "\t--- OK\n"
else
   echo -e "${RED}\t!!! FAIL nginx key signing ${NC}\n"
   echo -e "${RED}\t!!! Please destroy your vagrant and provision again.${NC}\n"
   exit 1;
fi

echo -e "\n--- Updating packages list ---\n"
sudo apt-get -qq update;
sudo apt-get -qq -y upgrade > /dev/null 2>&1;

echo -e "\n--- Install MySQL specific packages and settings ---\n"
sudo debconf-set-selections <<< "mariadb-server-10.0 mysql-server/root_password password $DBPASSWD"
sudo debconf-set-selections <<< "mariadb-server-10.0 mysql-server/root_password_again password $DBPASSWD"

echo -e "\n--- Install base servers and packages ---\n"
sudo apt-get -qq -f -y install git nano zip nginx mariadb-server mariadb-client curl > /dev/null 2>&1;
if [ $? -eq 0 ]; then
   echo -e "\t--- OK\n"
else
   echo -e "${RED}\t!!! FAIL${NC}\n"
   echo -e "${RED}\t!!! Please destroy your vagrant and provision again.${NC}\n"
   exit 1;
fi

echo -e "\n--- Install php7.2 and all extensions ---\n"
sudo apt-get -qq -f -y install php7.2 php7.2-cli php7.2-fpm php7.2-common php7.2-opcache php7.2-cli php7.2-mysql  \
                               php7.2-xml php7.2-gd php7.2-intl php7.2-imap php7.2-pspell \
                               php7.2-curl php7.2-recode php7.2-sqlite3 php7.2-mbstring php7.2-tidy \
                               php7.2-xsl php7.2-apcu php7.2-gd php7.2-apcu-bc php7.2-xdebug php7.2-zip > /dev/null 2>&1;
if [ $? -eq 0 ]; then
   echo -e "\t--- OK\n"
else
   echo -e "${RED}\t!!! FAIL${NC}\n"
   echo -e "${RED}\t!!! Please destroy your vagrant and provision again.${NC}\n"
   exit 1;
fi

echo -e "\n--- Setting up our MySQL user, DB and test DB ---\n"
sudo mysql -uroot -p$DBPASSWD <<EOF
create database ${DBNAME};
grant all privileges on ${DBNAME}.* to '${DBUSER}'@'localhost' identified by '${DBPASSWD}';
create database ${DBNAME}_test;
grant all privileges on ${DBNAME}_test.* to '${DBUSER}'@'localhost' identified by '${DBPASSWD}';
EOF
if [ $? -eq 0 ]; then
   echo -e "\t--- OK\n"
else
   echo -e "${RED}\t!!! FAIL creating databases${NC}\n"
   echo -e "${RED}\t!!! Please destroy your vagrant and provision again.${NC}\n"
   exit 1;
fi

echo -e "\n--- We definitly need to see the PHP errors, turning them on ---\n"
sudo sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/7.2/fpm/php.ini
sudo sed -i "s/display_errors = .*/display_errors = On/" /etc/php/7.2/fpm/php.ini
sudo sed -i "s/;realpath_cache_size = .*/realpath_cache_size = 4096k/" /etc/php/7.2/fpm/php.ini
sudo sed -i "s/;realpath_cache_ttl = .*/realpath_cache_ttl = 600/" /etc/php/7.2/fpm/php.ini

echo -e "\n--- Fix php-fpm startup PID path ---\n"
sudo sed -i "s/\/run\/php\/php7.2-fpm.pid/\/run\/php7.2-fpm.pid/" /etc/php/7.2/fpm/php-fpm.conf

echo -e "\n--- We definitly need to upload large files ---\n"
sed -i "s/server_tokens off;/server_tokens off;\\n\\tclient_max_body_size 256M;/" /etc/nginx/nginx.conf

echo -e "\n--- Configure Nginx virtual host for Roadiz and phpmyadmin ---\n"
sudo mkdir /etc/nginx/snippets;
sudo mkdir /etc/nginx/certs;
sudo mkdir /etc/nginx/sites-available;
sudo rm /etc/nginx/conf.d/default.conf;
sudo cp /var/www/samples/vagrant/nginx-conf.conf /etc/nginx/nginx.conf;
sudo cp /var/www/samples/vagrant/nginx-vhost.conf /etc/nginx/sites-available/default;
sudo cp /var/www/samples/vagrant/roadiz-nginx-include.conf /etc/nginx/snippets/roadiz.conf;

#
# Do not generate default DH param and certificate
# to speed up Vagrant provisioning
#

#echo -e "\n--- Generating a unique Diffie-Hellman Group ---\n"
#sudo openssl dhparam -out /etc/nginx/certs/default.dhparam.pem 2048 > /dev/null 2>&1;
#
#echo -e "\n--- Generating a self-signed SSL certificate ---\n"
#sudo openssl req -new -newkey rsa:2048 -days 365 -nodes \
#            -x509 -subj "/C=FR/ST=Rhonealpes/L=Lyon/O=ACME/CN=localhost" \
#            -keyout /etc/nginx/certs/default.key \
#            -out /etc/nginx/certs/default.crt > /dev/null 2>&1;

echo -e "\n--- Configure PHP-FPM default pool ---\n"
sudo rm /etc/php/7.2/fpm/pool.d/www.conf;
sudo cp /var/www/samples/vagrant/php-pool.conf /etc/php/7.2/fpm/pool.d/www.conf;
sudo cp /var/www/samples/vagrant/xdebug.ini /etc/php/7.2/mods-available/xdebug.ini;
sudo cp /var/www/samples/vagrant/logs.ini /etc/php/7.2/mods-available/logs.ini;
sudo cp /var/www/samples/vagrant/opcache-recommended.ini /etc/php/7.2/mods-available/opcache-recommended.ini;
sudo phpenmod -v ALL -s ALL opcache-recommended;
sudo phpenmod -v ALL -s ALL logs;
sudo phpenmod -v ALL -s ALL xdebug;

echo -e "\n--- Restarting Nginx and PHP servers ---\n"
sudo service nginx restart > /dev/null 2>&1;
sudo service php7.2-fpm restart > /dev/null 2>&1;

##### CLEAN UP #####
sudo dpkg --configure -a  > /dev/null 2>&1; # when upgrade or install doesn't run well (e.g. loss of connection) this may resolve quite a few issues
sudo apt-get autoremove -y  > /dev/null 2>&1; # remove obsolete packages
sudo apt-get clean; # remove obsolete packages

# Set envvars
export DB_HOST=$DBHOST
export DB_NAME=$DBNAME
export DB_USER=$DBUSER
export DB_PASS=$DBPASSWD

export PRIVATE_IP=`/sbin/ifconfig enp0s8 | grep 'inet addr:' | cut -d: -f2 | awk '{ print $1}'`

echo -e "\n-----------------------------------------------------------------"
echo -e "\n----------- Your Roadiz Vagrant is ready in /var/www ------------"
echo -e "\n-----------------------------------------------------------------"
echo -e "\nDo not forget to \"composer install\" and to add "
echo -e "\nyour host IP into install.php and dev.php"
echo -e "\nto get allowed in install and dev entry-points."
echo -e "\n* Type http://$PRIVATE_IP/install.php to proceed to install."
#echo -e "\n* Type https://$PRIVATE_IP/install.php to proceed using SSL (cert is not authenticated)."
echo -e "\n* MySQL User: $DBUSER"
echo -e "\n* MySQL Password: $DBPASSWD"
echo -e "\n* MySQL Database: $DBNAME"
echo -e "\n* MySQL Database for tests: ${DBNAME}_test"
echo -e "\n-----------------------------------------------------------------"
