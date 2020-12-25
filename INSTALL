NexusPHP 1.5 beta 4 20100517 Installation
This guide is intended for experienced webmasters and takes Ubuntu Server 10.04 as the example. Check out http://www.nexusphp.com for more tutorials.
1.Environment
This project should work on most operation systems where PHP is supported, such as Linux, Unix and Microsoft Windows.
1.1.Required:
A web server, Apache HTTP Server (v2.2.X tested) preferred. IIS HTTP Server (6.0 tested) should work as well but not recommended.
PHP 5 (v5.2.X and v5.3.X tested). Multibyte String(mbstring), MySQL(mysql), Memcache(memcache), GD extensions are required.
MySQL Server (v5.0.X tested).
memcached

1.2.Optional:
PEAR with HTTP_Request2 package. For the feature of IMDb information scraping.
A SMTP server, Postfix preferred. IIS SMTP Server (6.0 tested) should work, too. For sending email. 

2.INSTALL
2.1.Apache web server
2.1.1.install the web server.
on Ubuntu Server 10.04: 
# OS commandline starts
sudo apt-get install apache2
# OS commandline ends
2.1.2.edit the configuration file (usually named 'Apache2.conf' or 'httpd.conf')
on Ubuntu Server 10.04: 
# OS commandline starts
sudo nano /etc/apache2/sites-enabled/000-default
# OS commandline ends

# configuration starts
<IfModule dir_module>
    DirectoryIndex index.php index.html
</IfModule>
<VirtualHost *:80>
        DocumentRoot "/your/http/document/root"
        <Directory "/your/http/document/root">
                Options FollowSymLinks
                AllowOverride None
                Order allow,deny
                Allow from all
        </Directory>
        <DirectoryMatch /\.svn/>
                AllowOverride None
                Order allow,deny
                Deny from all
        </DirectoryMatch>
        <Directory "/your/http/document/root/_db">
                AllowOverride None
                Order allow,deny
                Deny from all
        </Directory>
        <Directory "/your/http/document/root/config">
                AllowOverride None
                Order allow,deny
                Deny from all
        </Directory>
        <Directory "/your/http/document/root/_doc">
                Options +Indexes
                Order allow,deny
                Allow from all
        </Directory>
        <Directory "/your/http/document/root/lang">
                AllowOverride None
                Order allow,deny
                Deny from all
        </Directory>
</VirtualHost>
# configuration ends

Note: replace '/your/http/document/root' with your own path, e.g. '/var/www/nexusphp'

2.2.PHP
2.2.1.install PHP with all required extensions.
on Ubuntu Server 10.04: 
# OS commandline starts
sudo apt-get install php5 php5-gd php5-memcache php5-mysql
# OS commandline ends
2.2.2.edit the configuration file (usually named 'php.ini')
on Ubuntu Server 10.04: 
# OS commandline starts
sudo nano /etc/php5/apache2/php.ini
# OS commandline ends
IMPORTANT: You must turn off the 'magic quotes' feature in PHP. It is unfortunately turned on by default with PHP 5.2

; configuration starts
magic_quotes_gpc = Off
magic_quotes_runtime = Off
magic_quotes_sybase = Off
; Optional. Increase it if memory-limit-reached error occurs when uploading large torrent files.
memory_limit = 128M
; configuration ends

2.3.MySQL server
2.3.1.install it.
on Ubuntu Server 10.04: 
# OS commandline starts
sudo apt-get install mysql-server
# OS commandline ends
2.3.2.edit the configuration file (usually named 'my.cnf' or 'my.ini')
on Ubuntu Server 10.04: 
# OS commandline starts
sudo nano /etc/mysql/my.cnf
# OS commandline ends

IMPORTANT: Do not set any SQL Modes. This project is not tested to work with them.
# configuration starts
sql-mode=""
; Optional. Increase it if mysql connection-failure occurs under heavy traffic load.
max_connections = 1000
# configuration ends

2.3.3.with the 1.5 beta 4 release, no installation script comes with the project. So you have to do everything yourself, such as creating a database:
2.3.3.1.connect to MySQL server
# OS commandline starts
mysql --user=yourdbusername --password=yourdbpassword --host=yourdbhostname
# OS commandline ends
Note: replace 'yourdbusername' with your own MySQL username e.g. 'root', 'yourdbpassword' with your MySQL user password, and 'yourdbhostname' with your MySQL hostname e.g. 'localhost'
2.3.3.2.create a database.
-- MySQL commandline starts
CREATE DATABASE yourdbname;
USE yourdbname;
-- MySQL commandline end
Note: replace 'yourdbname' with your own mysql database name, e.g. 'nexusphp'.
2.3.3.3.import database structure from this project.
-- MySQL commandline starts
SET NAMES utf8;
SOURCE /path/to/project/source/_db/dbstructure.sql;
-- MySQL commandline ends
Note: replace '/path/to/project/source' with your own path where you save files from this project.
2.3.3.4.quit MySQL
-- MySQL commandline starts
quit;
-- MySQL commandline ends

2.4.Memcached
2.4.1.install it.
on Ubuntu Server 10.04: 
# OS commandline starts
sudo apt-get install memcached
# OS commandline ends
2.4.2.run it as a daemon.
on Ubuntu Server 10.04: 
# OS commandline starts
memcached -d -u nobody
# OS commandline ends

2.5.PEAR and HTTP_Request2 package
To save the trouble, a package named 'Required.Files.From.PEAR' is available from the website http://www.nexusphp.com. You may skipped the following procedure if you have downloaded that package.
2.5.1.install PEAR basic package
on Ubuntu Server 10.04: 
# OS commandline starts
sudo apt-get install php-pear
# OS commandline ends
2.5.2.set preferred package state of PEAR to 'alpha'
on Ubuntu Server 10.04: 
# OS commandline starts
sudo pear config-set preferred_state alpha
# OS commandline ends
2.5.3.install HTTP_Request2 package.
# OS commandline starts
sudo pear install HTTP_Request2
# OS commandline ends

2.6.Postfix
2.6.1.install it.
on Ubuntu Server 10.04: 
# OS commandline starts
sudo apt-get install postfix
# OS commandline ends

2.7.Restart MySQL and Apache HTTP Server services
For edits of configuration to take effect, services need to be restarted. 
on Ubuntu Server 10.04: 
# OS commandline starts
sudo /etc/init.d/apache2 restart
sudo /etc/init.d/mysql restart
# OS commandline ends

3.Set up this project
3.1.put all files from this project into the document root of your http server, e.g. '/var/www/nexusphp'
3.2.on *nix OS, change files' access permission to 777
on Ubuntu Server 10.04: 
# OS commandline starts
sudo chmod -Rf 777 /your/http/document/root
# OS commandline ends
Note: replace '/your/http/document/root' with your own path, e.g. '/var/www/nexusphp'
3.3.edit the configuration file (named 'config/allconfig.php') of this project

// configuration starts
$BASIC=array(
	'SITENAME' => 'yoursitename',
	'BASEURL' => 'yoursiteurl',
	'announce_url' => 'yoursiteurl/announce.php',
	'mysql_host' => 'yourdbhostname',
	'mysql_user' => 'yourdbusername',
	'mysql_pass' => 'yourdbpassword',
	'mysql_db' => 'yourdbname',
);
// configuration ends
Note: replace 'yoursitename' with your own name of the website e.g. 'MyTracker', 'yoursiteurl' with your site base URL (without prefixing 'http://') e.g. 'www.nexusphp.com', 'mysql_host' with your MySQL hostname e.g. 'localhost', 'yourdbhostname' with your MySQL username e.g. 'root', 'yourdbusername' with your MySQL user password, and 'mysql_db' with your MySQL database name e.g. 'nexusphp'.
3.4.visit your site. Register a new user.
3.5.set yourself the Staff Leader.
Again, you have to do it the dirty way, namely running MySQL query manually.
3.5.1.connect to mysql server
# OS commandline starts
mysql --user=yourdbusername --password=yourdbpassword --host=yourdbhostname yourdbname
# OS commandline ends
Note: replace 'yourdbusername' with your own MySQL username e.g. 'root', 'yourdbpassword' with your MySQL user password, 'yourdbhostname' with your MySQL hostname e.g. 'localhost', and 'yourdbname' with your MySQL database name e.g. 'nexusphp'.
3.5.2.set your class to Staff Leader
-- MySQL commandline starts
UPDATE users SET class=16 WHERE username='yourwebsiteusername';
-- MySQL commandline ends
NOTE: replace 'yourwebsiteusername' with the username you have just registered on the website.
3.5.3.quit MySQL
-- MySQL commandline starts
quit;
-- MySQL commandline ends
