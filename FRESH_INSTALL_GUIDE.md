# Fresh Installation Guide (Ubuntu 22.04/24.04 WSL)

This guide assumes you have a fresh WSL Ubuntu instance.

## 1. Install Prerequisites (LAMP Stack)

```bash
sudo apt update
sudo apt upgrade -y

# Install Apache
sudo apt install apache2 -y

# Install MySQL/MariaDB
sudo apt install mariadb-server mariadb-client -y

# Start Services
sudo service apache2 start
sudo service mysql start
```

## 2. Install PHP 8.4 (Repository Required)

Ubuntu default repositories might not have PHP 8.4 yet.

```bash
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.4 and extensions
sudo apt install php8.4 php8.4-common php8.4-mysql php8.4-xml php8.4-xmlrpc \
php8.4-curl php8.4-gd php8.4-imagick php8.4-cli php8.4-dev php8.4-imap \
php8.4-mbstring php8.4-opcache php8.4-soap php8.4-zip php8.4-intl \
libapache2-mod-php8.4 -y

# Enable Apache modules
sudo a2enmod rewrite
sudo a2enmod headers
sudo service apache2 restart
```

## 3. Configure Database

```bash
# Secure installation (optional for dev)
# sudo mysql_secure_installation

# Create Database and User
sudo mysql -u root
```

In the MySQL shell:
```sql
CREATE DATABASE travium CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'travium'@'localhost' IDENTIFIED BY 'travium';
GRANT ALL PRIVILEGES ON travium.* TO 'travium'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 4. Install the Project

```bash
# Clone repository
cd /var/www/html
sudo git clone https://github.com/mo7amedabdulahad-bit/travium.git
sudo mv travium public_html
cd public_html

# Set Permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 777 src/config.php  # Temporary for installer to write
```

## 5. Configure Apache

Create a new config file:
```bash
sudo nano /etc/apache2/sites-available/travium.conf
```

Add this content:
```apache
<VirtualHost *:80>
    ServerName travium.local
    DocumentRoot /var/www/html/public_html

    <Directory /var/www/html/public_html>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/travium_error.log
    CustomLog ${APACHE_LOG_DIR}/travium_access.log combined
</VirtualHost>
```

Enable site:
```bash
sudo a2dissite 000-default.conf
sudo a2ensite travium.conf
sudo service apache2 restart
```

## 6. Access the Installer

1.  **Host File (Windows)**:
    Open `C:\Windows\System32\drivers\etc\hosts` as Administrator and add:
    ```
    127.0.0.1 travium.local
    ```

2.  **Run Installer**:
    Open your browser to `http://travium.local/install/`

3.  **Follow Installer Steps**:
    - Database Host: `localhost`
    - Database User: `travium`
    - Database Password: `travium`
    - Database Name: `travium`

## 7. Post-Install

After installation is complete:
```bash
# Secure config
sudo chmod 644 src/config.php
```

## Troubleshooting

-   **"File not found"**: Ensure `.htaccess` is working (`AllowOverride All` in Apache config) and `mod_rewrite` is enabled.
-   **PHP Errors**: Check logs: `tail -f /var/log/apache2/travium_error.log`
-   **Missing Columns/Tables**: If installer fails, you might need to import `maindb.sql` manually:
    ```bash
    sudo mysql -u travium -ptravium travium < maindb.sql
    ```
