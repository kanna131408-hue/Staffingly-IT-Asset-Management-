# Installation Checklist

## Prerequisites

### System Requirements
- [ ] PHP 8.2 or higher installed
- [ ] MySQL 5.7+ or MariaDB 10.2+ installed
- [ ] Web server (Apache, Nginx, or built-in PHP server)
- [ ] Composer installed
- [ ] Git installed (for cloning repository)
- [ ] At least 1GB RAM available
- [ ] At least 2GB disk space

### Check Requirements
```bash
# Check PHP version
php -v

# Check required PHP extensions
php -m | grep -E '(curl|json|pdo|mbstring|fileinfo|iconv)'

# Check Composer version
composer --version

# Check MySQL version
mysql --version
```

---

## Step 1: Clone Repository

```bash
# Clone the repository
git clone https://github.com/grokability/snipe-it.git staffingly
cd staffingly

# Checkout latest stable release
git fetch --tags
git checkout $(git describe --tags --abbrev=0)
```

- [ ] Repository cloned successfully
- [ ] In correct directory

---

## Step 2: Install Dependencies

```bash
# Install PHP dependencies
composer install --no-dev --prefer-dist

# This may take 2-5 minutes
```

- [ ] Composer install completed without errors
- [ ] `vendor/` directory created

---

## Step 3: Create Database

```bash
# Log into MySQL
mysql -u root -p

# Create database
CREATE DATABASE staffingly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'staffingly'@'localhost' IDENTIFIED BY 'your_password_here';
GRANT ALL PRIVILEGES ON staffingly.* TO 'staffingly'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

- [ ] Database created
- [ ] Database user created with password
- [ ] User has all privileges
- [ ] Test login: `mysql -u staffingly -p staffingly`

---

## Step 4: Configure Environment

```bash
# Copy example .env
cp .env.example .env

# Generate application key
php artisan key:generate

# Edit .env with your settings
nano .env
```

### Critical .env Settings
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=staffingly
DB_USERNAME=staffingly
DB_PASSWORD=your_password_here
```

- [ ] `.env` file created
- [ ] APP_KEY generated (not "ChangeMe")
- [ ] Database credentials configured
- [ ] APP_URL set to your domain

---

## Step 5: Set File Permissions

```bash
# For Ubuntu/Debian with www-data user
chown -R www-data:www-data /path/to/staffingly
chmod -R 755 /path/to/staffingly
chmod -R 775 /path/to/staffingly/storage
chmod -R 775 /path/to/staffingly/bootstrap/cache
chmod 644 /path/to/staffingly/.env

# For CentOS/RHEL with apache user
chown -R apache:apache /path/to/staffingly
chmod -R 755 /path/to/staffingly
chmod -R 775 /path/to/staffingly/storage
chmod -R 775 /path/to/staffingly/bootstrap/cache
chmod 644 /path/to/staffingly/.env
```

- [ ] Directory permissions set correctly
- [ ] Storage and cache directories are writable
- [ ] .env file permissions are restrictive

---

## Step 6: Run Migrations

```bash
# Run database migrations
php artisan migrate --force

# Seed example data (optional)
php artisan db:seed --class=UsersTableSeeder

# Check migration status
php artisan migrate:status
```

- [ ] Migrations completed successfully
- [ ] No errors in output
- [ ] Database tables created

---

## Step 7: Configure Web Server

### For Apache (with mod_rewrite)

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/staffingly/public

    <Directory /path/to/staffingly/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/staffingly-error.log
    CustomLog ${APACHE_LOG_DIR}/staffingly-access.log combined
</VirtualHost>
```

Then enable:
```bash
a2enmod rewrite
a2ensite staffingly
sudo systemctl restart apache2
```

### For Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/staffingly/public;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    index index.html index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).*$ {
        deny all;
    }
}
```

Then enable:
```bash
sudo ln -s /etc/nginx/sites-available/staffingly /etc/nginx/sites-enabled/
sudo systemctl restart nginx
```

- [ ] Web server configuration created
- [ ] Virtual host enabled
- [ ] Web server restarted without errors

---

## Step 8: SSL/TLS Certificate

### Using Let's Encrypt with Certbot

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Get certificate
sudo certbot certonly --apache -d your-domain.com

# Or for Nginx
sudo apt install certbot python3-certbot-nginx
sudo certbot certonly --nginx -d your-domain.com

# Auto-renewal
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
```

### Update Web Server Config
Update your VirtualHost or Nginx config to use SSL:
```
SSL Certificate: /etc/letsencrypt/live/your-domain.com/fullchain.pem
SSL Key: /etc/letsencrypt/live/your-domain.com/privkey.pem
```

Also update `.env`:
```
APP_URL=https://your-domain.com
```

- [ ] SSL certificate obtained
- [ ] Web server configured for HTTPS
- [ ] Certificate auto-renewal configured

---

## Step 9: Clear Cache & Compile Assets

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Compile assets (if npm installed)
npm install
npm run production

# Or just optimize
php artisan optimize
php artisan config:cache
```

- [ ] Cache cleared
- [ ] Assets compiled
- [ ] Configuration cached

---

## Step 10: Create Admin User

```bash
php artisan tinker

> $user = new App\Models\User();
> $user->username = 'admin';
> $user->email = 'admin@example.com';
> $user->password = bcrypt('your_password_here');
> $user->is_admin = true;
> $user->activated = true;
> $user->save();
> exit
```

- [ ] Admin user created
- [ ] Credentials noted somewhere secure

---

## Step 11: Backup Configuration

```bash
# Create backup directory
mkdir -p /backups/staffingly

# Backup .env
cp .env /backups/staffingly/.env.backup

# Backup database
mysqldump -u root -p staffingly > /backups/staffingly/database-$(date +%Y%m%d).sql

# Set backup permissions
chown -R root:root /backups/staffingly
chmod 600 /backups/staffingly/*
```

- [ ] Backup directory created
- [ ] .env backed up securely
- [ ] Database backed up
- [ ] Backup location documented

---

## Step 12: Post-Installation Configuration

Access Staffingly:
1. Navigate to `https://your-domain.com`
2. Log in with your admin credentials
3. Go to Admin > Settings
4. Configure:
   - [ ] Site name
   - [ ] Company name
   - [ ] Email settings
   - [ ] LDAP (if using)
   - [ ] SAML (if using)
   - [ ] Two-factor authentication

---

## Step 13: Security Hardening

```bash
# Set secure file permissions
find /path/to/staffingly -type f -exec chmod 644 {} \;
find /path/to/staffingly -type d -exec chmod 755 {} \;
chmod -R 775 /path/to/staffingly/storage
chmod -R 775 /path/to/staffingly/bootstrap/cache
chmod 600 /path/to/staffingly/.env

# Disable directory listing
echo "<FilesMatch \"^\">
  Deny from all
</FilesMatch>" >> /path/to/staffingly/public/.htaccess

# Configure firewall (example for UFW)
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# Keep system updated
sudo apt update && sudo apt upgrade -y
sudo apt install unattended-upgrades
```

- [ ] File permissions hardened
- [ ] Directory listing disabled
- [ ] Firewall configured
- [ ] System updated
- [ ] SSH key-only authentication enabled

---

## Step 14: Monitoring & Maintenance

### Set up Log Rotation
```bash
sudo nano /etc/logrotate.d/staffingly

# Add:
/path/to/staffingly/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### Set up Automated Backups
```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * mysqldump -u staffingly -pYOUR_PASSWORD staffingly | gzip > /backups/staffingly/db-$(date +\%Y\%m\%d).sql.gz
```

### Monitor Application
```bash
# Check error logs regularly
tail -f /path/to/staffingly/storage/logs/laravel.log

# Monitor disk space
df -h

# Monitor database
mysql -u root -p -e "SELECT table_name, ROUND(data_length + index_length) as size FROM information_schema.TABLES WHERE table_schema = 'staffingly' ORDER BY size DESC;"
```

- [ ] Log rotation configured
- [ ] Automated backups scheduled
- [ ] Monitoring tools in place

---

## Troubleshooting

### Issue: 500 Error on Login
**Solution**: Check `storage/logs/laravel.log` for database connection errors. See `DOCS/LOGIN_TROUBLESHOOTING.md`

### Issue: Permission Denied on Storage
**Solution**: Fix permissions:
```bash
chown -R www-data:www-data /path/to/staffingly/storage
chmod -R 775 /path/to/staffingly/storage
```

### Issue: Database Migration Failed
**Solution**:
```bash
# Check migration status
php artisan migrate:status

# Roll back and retry
php artisan migrate:rollback
php artisan migrate
```

### Issue: Can't Connect to Database
**Solution**:
```bash
# Test MySQL connection
mysql -h localhost -u staffingly -p staffingly -e "SELECT 1;"

# Check .env credentials
cat .env | grep DB_
```

---

## Verification Checklist

Before going live:

- [ ] Can access site via HTTPS
- [ ] Can login with admin account
- [ ] Can create and view assets
- [ ] Can create and view users
- [ ] Email notifications work
- [ ] Database backups working
- [ ] Error logs accessible
- [ ] SSL certificate valid
- [ ] Firewall rules in place
- [ ] System fully updated

---

## Next Steps

1. **Invite users**: Admin > Users > Create
2. **Configure asset categories**: Admin > Categories
3. **Set up locations**: Admin > Locations
4. **Add manufacturers**: Admin > Manufacturers
5. **Import assets**: Tools > Import
6. **Set up backups**: Admin > Settings > Backups
7. **Configure 2FA**: Admin > Settings > Security

---

## Support

- **Documentation**: https://snipe-it.readme.io/
- **Discord**: https://discord.gg/yZFtShAcKk
- **GitHub Issues**: https://github.com/grokability/snipe-it/issues
- **Security Issues**: security@snipeitapp.com
