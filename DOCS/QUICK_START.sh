#!/bin/bash

# Staffingly IT Asset Management - Quick Start Installation Script
# This script automates the basic setup process

set -e

echo "========================================"
echo "Staffingly Quick Start Installation"
echo "========================================"
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
    exit 1
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

# Step 1: Check prerequisites
echo "Checking prerequisites..."
echo ""

# Check PHP
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed"
fi
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
print_success "PHP $PHP_VERSION found"

# Check PHP version >= 8.2
PHP_MAJOR=$(echo $PHP_VERSION | cut -d. -f1)
PHP_MINOR=$(echo $PHP_VERSION | cut -d. -f2)
if [ "$PHP_MAJOR" -lt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 2 ]); then
    print_error "PHP 8.2 or higher is required (found: $PHP_VERSION)"
fi

# Check required PHP extensions
echo ""
echo "Checking PHP extensions..."
for ext in curl json pdo mbstring fileinfo iconv; do
    if php -m | grep -q $ext; then
        print_success "$ext extension found"
    else
        print_error "$ext extension is missing. Install it with: sudo apt install php-$ext"
    fi
done

# Check MySQL
if ! command -v mysql &> /dev/null; then
    print_error "MySQL/MariaDB is not installed"
fi
MYSQL_VERSION=$(mysql --version)
print_success "MySQL/MariaDB found: $MYSQL_VERSION"

# Check Composer
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed. Visit: https://getcomposer.org/"
fi
print_success "Composer found"

# Check Git
if ! command -v git &> /dev/null; then
    print_error "Git is not installed"
fi
print_success "Git found"

# Step 2: Get user input
echo ""
echo "========================================"
echo "Configuration"
echo "========================================"
echo ""

read -p "Database host [127.0.0.1]: " DB_HOST
DB_HOST=${DB_HOST:-127.0.0.1}

read -p "Database port [3306]: " DB_PORT
DB_PORT=${DB_PORT:-3306}

read -p "Database name [staffingly]: " DB_NAME
DB_NAME=${DB_NAME:-staffingly}

read -p "Database username [staffingly]: " DB_USER
DB_USER=${DB_USER:-staffingly}

read -sp "Database password: " DB_PASS
echo ""

read -p "Admin email: " ADMIN_EMAIL
read -p "Admin username [admin]: " ADMIN_USER
ADMIN_USER=${ADMIN_USER:-admin}

read -sp "Admin password: " ADMIN_PASS
echo ""

read -p "Application URL [http://localhost]: " APP_URL
APP_URL=${APP_URL:-http://localhost}

# Step 3: Test database connection
echo ""
echo "Testing database connection..."
if mysql -h "$DB_HOST" -P "$DB_PORT" -u "root" -e "SELECT 1;" > /dev/null 2>&1; then
    print_success "Connected to MySQL as root"
else
    print_error "Could not connect to MySQL. Is the service running?"
fi

# Step 4: Create database and user
echo ""
echo "Creating database and user..."
MYSQL_ROOT_PASS=${MYSQL_ROOT_PASS:-}
if [ -z "$MYSQL_ROOT_PASS" ]; then
    read -sp "MySQL root password: " MYSQL_ROOT_PASS
    echo ""
fi

mysql -h "$DB_HOST" -P "$DB_PORT" -u root -p"$MYSQL_ROOT_PASS" <<EOF
DROP DATABASE IF EXISTS $DB_NAME;
CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'$DB_HOST' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'$DB_HOST';
FLUSH PRIVILEGES;
EOF

if [ $? -eq 0 ]; then
    print_success "Database and user created"
else
    print_error "Failed to create database"
fi

# Step 5: Create .env file
echo ""
echo "Creating .env file..."
APP_KEY=$(php artisan key:generate --show 2>/dev/null || echo "base64:$(openssl rand -base64 32)")

cat > .env <<EOF
APP_ENV=production
APP_DEBUG=false
APP_KEY=$APP_KEY
APP_URL=$APP_URL
APP_TIMEZONE=UTC
APP_LOCALE=en-US

DB_CONNECTION=mysql
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDR=noreply@example.com
MAIL_FROM_NAME=Staffingly

CACHE_DRIVER=file
QUEUE_DRIVER=sync
SESSION_DRIVER=file

LOG_CHANNEL=single
LOG_LEVEL=debug
EOF

print_success ".env file created"

# Step 6: Install PHP dependencies
echo ""
echo "Installing PHP dependencies..."
echo "This may take several minutes..."
composer install --no-dev --prefer-dist --optimize-autoloader || print_error "Composer install failed"
print_success "PHP dependencies installed"

# Step 7: Set file permissions
echo ""
echo "Setting file permissions..."
WEB_USER=$(whoami)
chown -R "$WEB_USER:$WEB_USER" . || print_error "Could not change ownership"
chmod -R 755 .
chmod -R 775 storage bootstrap/cache || print_error "Could not set storage permissions"
chmod 644 .env
print_success "Permissions set"

# Step 8: Run migrations
echo ""
echo "Running database migrations..."
php artisan migrate --force || print_error "Migration failed"
print_success "Database migrations completed"

# Step 9: Create admin user
echo ""
echo "Creating admin user..."
php artisan tinker <<EOF
\$user = new App\Models\User();
\$user->username = '$ADMIN_USER';
\$user->email = '$ADMIN_EMAIL';
\$user->password = bcrypt('$ADMIN_PASS');
\$user->is_admin = true;
\$user->activated = true;
\$user->save();
exit
EOF

if [ $? -eq 0 ]; then
    print_success "Admin user created"
else
    print_error "Failed to create admin user"
fi

# Step 10: Clear cache
echo ""
echo "Optimizing application..."
php artisan config:cache || print_error "Config cache failed"
php artisan route:cache || print_error "Route cache failed"
php artisan view:cache || print_error "View cache failed"
print_success "Application optimized"

# Step 11: Health check
echo ""
echo "Running health check..."
if php artisan tinker <<EOF 2>/dev/null | grep -q "PDOStatement";
DB::connection()->getPDO();
exit
EOF
; then
    print_success "Database connection verified"
else
    print_error "Database connection failed"
fi

# Success!
echo ""
echo "========================================"
echo "Installation Complete!"
echo "========================================"
echo ""
print_success "Staffingly is ready to use"
echo ""
echo "Access your installation at:"
echo "  URL: $APP_URL"
echo "  Username: $ADMIN_USER"
echo ""
echo "Next steps:"
echo "  1. Log in with your admin credentials"
echo "  2. Configure settings in Admin > Settings"
echo "  3. Add users in Admin > Users"
echo "  4. Create asset categories"
echo ""
echo "Documentation: https://snipe-it.readme.io/"
echo "Support: https://discord.gg/yZFtShAcKk"
echo ""
