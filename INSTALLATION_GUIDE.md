# Installation Guide

## Quick Start (5 Minutes)

1. Download JuaKali Lend
2. Extract to web root (e.g., `/var/www/html/juakali-lend`)
3. Visit `http://localhost/juakali-lend/install/`
4. Follow the 5-step wizard
5. Delete `/install/` directory
6. Login with admin credentials

## Detailed Setup

### Step 1: Database Setup

Ensure MySQL/MariaDB is running:

\`\`\`bash
# Linux/Mac
sudo service mysql start

# Windows (using XAMPP/WAMP)
# Start from control panel
\`\`\`

### Step 2: Upload Files

Extract to web root:
\`\`\`bash
cd /var/www/html
unzip juakali-lend.zip
cd juakali-lend
\`\`\`

### Step 3: Run Installation

1. Open `http://localhost/juakali-lend/install/`
2. **Step 1 - Welcome**: Review pre-flight checks
3. **Step 2 - Database**: Enter credentials
   - Host: localhost
   - User: root
   - Password: (leave empty if none)
   - Database: juakali_lend
4. **Step 3 - Initialize**: Click to create tables
5. **Step 4 - Admin**: Create admin account
6. **Step 5 - Complete**: Setup complete!

### Step 4: Post-Installation

\`\`\`bash
# Remove install directory
rm -rf install/

# Set permissions
chmod 755 uploads/
chmod 755 logs/
chmod 755 cache/

# Optional: Create SSL certificate
sudo certbot certonly --standalone -d yourdomain.com
\`\`\`

### Step 5: Verify Installation

Visit `http://localhost/juakali-lend/` and login with admin credentials.

Check System Status in Admin Dashboard:
- All Systems: Operational
- Database: Connected
- File permissions: Correct
- Payment gateways: Configured (optional)

## Configuration

### Email Setup

Edit `config/config.php`:

\`\`\`php
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'your-email@gmail.com');
define('MAIL_PASS', 'your-app-password');
define('MAIL_FROM', 'noreply@yourdomain.com');
define('MAIL_FROM_NAME', 'JuaKali Lend');
\`\`\`

### Payment Gateway Setup

1. **PesaPal**
   - Get API credentials from pesapal.com
   - Update in admin settings
   - Enable in payment methods

2. **M-Pesa**
   - Safaricom Business API
   - Consumer key & secret
   - Shortcode & credentials

3. **Stripe** (optional)
   - Get API keys from stripe.com
   - Update in config
   - Enable card payments

### HTTPS Setup

\`\`\`bash
# Get SSL certificate
sudo certbot certonly --webroot -w /var/www/html/juakali-lend -d yourdomain.com

# Update .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Update config
define('APP_URL', 'https://yourdomain.com');
\`\`\`

## Troubleshooting

### "Cannot connect to database"
- Verify MySQL is running
- Check credentials in config/config.php
- Ensure database user has proper privileges

### "Permission denied" errors
- Check file permissions: `sudo chmod -R 755 /path/to/juakali-lend`
- Web server must own files: `sudo chown -R www-data:www-data /path/to/juakali-lend`

### Blank page on homepage
- Check error logs: `tail -f logs/error.log`
- Verify config.php exists and is readable
- Enable error display in config.php (development only)

### Email not sending
- Test SMTP credentials separately
- Check firewall allows SMTP port (587 or 465)
- Verify "Less secure app access" enabled (Gmail)

### Payment gateway errors
- Verify API credentials in admin settings
- Check network connectivity
- Review payment gateway documentation
- Test with sandbox credentials first

## Next Steps

1. **Configure System Settings**
   - Admin Dashboard → Settings
   - Set company name, logo, contact info
   - Configure notification preferences

2. **Add Initial Data**
   - Create supplier accounts
   - Add product categories
   - Import product catalog
   - Set up lender pool

3. **Customize Appearance**
   - Upload company logo
   - Customize color scheme
   - Add custom footer/header
   - Configure email templates

4. **Enable Features**
   - Set up payment methods
   - Configure SMS gateway
   - Enable push notifications
   - Set up analytics

5. **Test Workflows**
   - Register test users
   - Create test orders
   - Process test loans
   - Make test payments

## Performance Optimization

1. **Database**
   - Create indexes on frequently queried fields
   - Archive old records regularly
   - Run OPTIMIZE TABLE monthly

2. **Caching**
   - Enable Redis if available
   - Cache expensive queries
   - Use browser caching for static files

3. **Files**
   - Compress images (max 200KB)
   - Minify CSS/JS
   - Use CDN for static content

4. **Monitoring**
   - Set up error log monitoring
   - Monitor response times
   - Track user activity
   - Alert on system errors

## Backup Strategy

### Daily Backups
\`\`\`bash
#!/bin/bash
# backup.sh
DATE=$(date +%Y%m%d)
mysqldump -u root -p juakali_lend > /backups/db_$DATE.sql
tar -czf /backups/files_$DATE.tar.gz /var/www/html/juakali-lend/uploads
\`\`\`

### Schedule with Cron
\`\`\`bash
# Daily at 2 AM
0 2 * * * /scripts/backup.sh
\`\`\`

### Restore Process
\`\`\`bash
# Restore database
mysql -u root -p juakali_lend < /backups/db_20250115.sql

# Restore files
tar -xzf /backups/files_20250115.tar.gz -C /
