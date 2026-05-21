# Login Troubleshooting Guide

## Quick Diagnosis (2-5 minutes)

If you're getting a **500 error when trying to login**, follow these steps:

### Step 1: Enable Debugging
Edit `.env` and set:
```
APP_DEBUG=true
LOG_CHANNEL=single
```

### Step 2: Check the Error Log
```bash
tail -f storage/logs/laravel.log
```

Attempt login again and check what error appears in the log.

### Step 3: Common Errors

#### **Database Connection Error**
```
PDOException: could not find driver
PDOException: Connection refused
PDOException: Access denied for user
```

**Fix:**
- Verify MySQL/MariaDB is running: `systemctl status mysql`
- Check database credentials in `.env`
- Test connection: `php artisan tinker` then `DB::connection()->getPDO()`

#### **Table Not Found Error**
```
PDOException: Table 'staffingly.settings' doesn't exist
```

**Fix:**
- Run migrations: `php artisan migrate`
- Check migration status: `php artisan migrate:status`

#### **LDAP Connection Error**
```
Exception: Could not bind to LDAP
Exception: Your app key has changed
```

**Fix:**
- Disable LDAP in admin settings or `.env`: `LDAP_ENABLED=0`
- If needed, reconfigure LDAP in Admin > Settings > LDAP/AD

#### **Missing APP_KEY**
```
Exception: No application encryption key has been specified
```

**Fix:**
```bash
php artisan key:generate
```

---

## Complete Troubleshooting Checklist

### Database Setup
- [ ] MySQL/MariaDB is running
- [ ] Database exists: `CREATE DATABASE staffingly;`
- [ ] Database user has proper permissions
- [ ] Database credentials match `.env`
- [ ] Can connect via MySQL CLI: `mysql -h 127.0.0.1 -u username -p staffingly`
- [ ] Migrations have run: `php artisan migrate:status` shows all "yes"
- [ ] Settings table exists: `php artisan tinker` > `DB::table('settings')->count()`

### Application Configuration
- [ ] `.env` exists and is readable
- [ ] `APP_KEY` is generated and not "ChangeMe"
- [ ] `APP_URL` matches your domain
- [ ] `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` are set
- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` in production

### File Permissions
- [ ] `storage/` directory is writable: `chmod -R 775 storage`
- [ ] `bootstrap/cache/` is writable: `chmod -R 775 bootstrap/cache`
- [ ] Owned by web server: `chown -R www-data:www-data storage bootstrap/cache`

### PHP & Server
- [ ] PHP version >= 8.2: `php -v`
- [ ] Required extensions installed:
  ```bash
  php -m | grep -E '(curl|json|pdo|mbstring|fileinfo|iconv)'
  ```
- [ ] Memory limit >= 256MB: Check `php.ini` for `memory_limit`
- [ ] Execution timeout >= 30 seconds: Check `php.ini` for `max_execution_time`

### Cache & Sessions
- [ ] Clear cache: `php artisan cache:clear`
- [ ] Clear config: `php artisan config:clear`
- [ ] Clear views: `php artisan view:clear`
- [ ] Session directory is writable: `storage/framework/sessions/`

### Authentication
- [ ] Admin user exists: `php artisan tinker` > `App\Models\User::where('is_admin',1)->count()`
- [ ] User is activated: Check `users.activated = 1`
- [ ] User password is set
- [ ] LDAP/SAML not forcing login: Check admin settings

---

## Advanced Debugging

### Enable Query Logging
In `.env`:
```
DB_DEBUG=true
LOG_LEVEL=debug
```

This will log all database queries to `storage/logs/laravel.log`

### Test Database Connection Directly
```bash
php artisan tinker

# Test connection
> DB::connection()->getPDO()

# Test settings table
> DB::table('settings')->first()

# Test users table
> App\Models\User::first()

# Exit
> exit
```

### Check Middleware Issues
The login route uses the 'web' middleware group. If there's an issue with middleware:
```bash
php artisan route:list | grep login
```

### Monitor Real-Time Logs
```bash
# Watch logs as they happen
tail -f storage/logs/laravel.log

# Or use Laravel commands
php artisan log:tail
```

---

## LDAP-Specific Issues

If LDAP is configured, login failures might be:

### Check LDAP Server
```bash
# Test LDAP connectivity
ldapsearch -x -H ldap://ldap-server:389 -D "cn=admin,dc=example,dc=com" -w password
```

### Common LDAP Problems

| Error | Solution |
|-------|----------|
| "Could not bind to LDAP" | Check LDAP server address and port in settings |
| "Your app key has changed" | LDAP password was encrypted with old key. Reconfigure LDAP. |
| "User not found in LDAP" | User doesn't exist in LDAP directory or username format is wrong |
| "Could not create local user" | Check `users` table has enough space or quota issues |

### Disable LDAP Temporarily
In Admin > Settings > LDAP/AD Settings, set:
- LDAP Enabled: Off
- Login common disabled: Off

Then try login with local account.

---

## SAML-Specific Issues

Similar to LDAP, SAML authentication failures:

### Check SAML Logs
```bash
grep -i saml storage/logs/laravel.log | tail -20
```

### Common SAML Issues

| Error | Solution |
|-------|----------|
| "Assertion has already been used" | SAML nonce validation failed. Check `saml_nonces` table. |
| "Expired SAML Assertion" | IdP assertion is outdated. Check time sync between servers. |
| "SAML user could not be found" | User doesn't exist in Staffingly. Check username mapping. |

### Disable SAML Temporarily
In Admin > Settings > SAML Settings:
- SAML Enabled: Off
- Require SAML: Off

Add `?nosaml` to login URL to bypass SAML:
```
https://your-site.com/login?nosaml
```

---

## Network & Hosting Issues

### Database is on Remote Server
If database is hosted remotely:
```bash
# Test connectivity
telnet db-server.example.com 3306

# Or use mysql client
mysql -h db-server.example.com -u username -p
```

### Firewall Blocking Database
Whitelist your app server IP on database firewall:
```
FROM: app-server-ip
TO: db-server:3306
PROTOCOL: TCP
```

### SSL/TLS Certificate Issues
If using SSL for database:
```bash
DB_SSL=true
DB_SSL_CA_PATH=/path/to/ca.pem
DB_SSL_VERIFY_SERVER=false  # For self-signed certs
```

---

## Production-Safe Debugging

**IMPORTANT**: Don't enable `APP_DEBUG=true` in production for long!

### Temporary Debug Mode
```bash
# Enable debug for one request
php artisan tinker
> config('app.debug')
> config(['app.debug' => true])
> exit

# Make login attempt
# Check logs

# Disable debug
php artisan tinker
> config(['app.debug' => false])
> exit
```

### Use Error Monitoring Service
Set up error tracking (Rollbar, Sentry, etc.):
```
ROLLBAR_ACCESS_TOKEN=your_token
```

---

## Recovery

### Reset to Factory Settings
If configuration is severely broken:

```bash
# Backup current .env
cp .env .env.backup

# Copy from example
cp .env.example .env

# Re-enter your database credentials
# nano .env

# Clear cache
php artisan config:clear
php artisan cache:clear

# Verify
php artisan tinker
> DB::connection()->getPDO()
```

### Reset Database
If database is corrupted:

```bash
# BACKUP FIRST!
mysqldump -u root -p staffingly > backup.sql

# Drop and recreate
mysql -u root -p -e "DROP DATABASE staffingly; CREATE DATABASE staffingly;"

# Re-migrate
php artisan migrate --seed
```

---

## Getting Help

If you're still stuck:

1. **Check official docs**: https://snipe-it.readme.io/docs/common-issues
2. **Discord community**: https://discord.gg/yZFtShAcKk
3. **GitHub Issues**: https://github.com/grokability/snipe-it/issues

**When asking for help, include:**
- Full error message from `storage/logs/laravel.log`
- Output of `php -v`
- Output of `php artisan migrate:status`
- Output of `php artisan tinker` > `DB::connection()->getPDO()`
- Your `.env` configuration (with secrets masked)
