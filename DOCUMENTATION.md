# JuaKali Lend - Complete System Documentation

## Overview

JuaKali Lend is a comprehensive e-commerce microfinance lending platform built with PHP 8.1+, MySQL, Bootstrap 5, and modern web technologies. It enables retailers to access credit-based goods and products from suppliers while providing lenders with investment opportunities.

## System Architecture

### Technology Stack

- **Backend**: PHP 8.1+ (PDO, OOP, PSR standards)
- **Database**: MySQL 8.0+ / MariaDB 10.6+
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript
- **APIs**: RESTful JSON APIs with 50+ endpoints
- **Security**: JWT, RBAC, MFA, CSRF protection
- **Payment Gateways**: PesaPal, M-Pesa, Airtel Money, Stripe

### Key Features

#### 1. Role-Based Access Control (4 Roles)
- **Retailers**: Browse products, request credit, make repayments
- **Suppliers**: Manage products, track orders, handle delivery
- **Lenders**: Approve loans, track investments, analyze portfolio
- **Admins**: System management, KYC verification, dispute resolution

#### 2. Complete CRUD Operations
- **45+ API Endpoints** with full CRUD support
- Products, Orders, Loans, Payments, Users management
- Wishlist, Shopping Cart, Inventory management
- All endpoints include validation, error handling, security

#### 3. Advanced Features
- Credit scoring system with machine learning
- Automated penalty calculation
- Multi-channel notifications (SMS, Email, WhatsApp, Push)
- QR code generation and delivery verification
- Blockchain transaction recording
- Advanced analytics and reporting
- A/B testing framework
- Gamification system with achievements
- Multi-language support (English, Swahili, French)
- Multi-currency support (KES, USD, EUR, GBP, UGX, TZS)

#### 4. Security Features
- Password hashing (BCrypt)
- Multi-factor authentication (OTP)
- Session management with timeouts
- Rate limiting on authentication
- Device fingerprinting
- Audit logging on all actions
- SQL injection prevention (prepared statements)
- CSRF token protection
- Data encryption

#### 5. Payment Integration
- **PesaPal**: OAuth-based payment gateway
- **M-Pesa**: STK Push integration
- **Airtel Money**: Mobile money integration
- **Stripe**: Card payments
- **Bank Transfers**: Direct bank deposits
- All with IPN callbacks and reconciliation

## Installation & Setup

### Requirements
- PHP 8.1 or higher
- MySQL 8.0 / MariaDB 10.6 or higher
- Apache/Nginx web server
- OpenSSL for HTTPS
- 500MB disk space minimum
- Composer (optional for additional packages)

### Installation Steps

1. **Download/Clone the Repository**
\`\`\`bash
git clone https://github.com/yourusername/juakali-lend.git
cd juakali-lend
\`\`\`

2. **Run Installation Wizard**
- Navigate to `http://localhost/juakali-lend/install/`
- Follow the 5-step installation process:
  - Welcome & pre-flight checks
  - Database configuration
  - Database initialization
  - Admin user creation
  - Completion confirmation

3. **Post-Installation**
- Delete the `/install/` directory for security
- Set proper file permissions (755 for directories, 644 for files)
- Configure email credentials (optional)
- Set up cron jobs for automated tasks

### Configuration

Edit `config/config.php` to customize:
- Database credentials
- Application URL
- Session timeout
- Password requirements
- File upload limits
- Email settings
- Timezone (default: Africa/Nairobi)

## Database Schema

### Core Tables (40+)
- **users**: User accounts with roles
- **user_profiles**: Extended user information
- **products**: Product catalog
- **orders**: Order management
- **order_items**: Order line items
- **loans**: Loan records
- **repayment_schedule**: Payment schedules
- **payments**: Payment transactions
- **credit_scores**: Credit rating system
- **kyc_documents**: KYC verification
- **delivery_tracking**: Shipment tracking
- **penalties**: Late payment penalties
- **disputes**: Dispute resolution
- **notifications**: User notifications
- **audit_logs**: Activity logging
- **sessions**: Session management
- **mfa_settings**: Two-factor auth
- **payment_methods**: Saved payment options
- **wishlist**: Favorite products
- **shopping_cart**: Shopping cart items
- **pesapal_transactions**: Payment transactions
- Plus 20+ additional tables for advanced features

## API Endpoints (50+)

### Authentication
- POST `/api/users/register` - Register new user
- POST `/api/users/login` - User login
- GET `/api/users/get` - Get user profile
- PUT `/api/users/update` - Update profile
- DELETE `/api/users/delete` - Delete account

### Products
- GET `/api/products/list` - List all products
- POST `/api/products/create` - Create product
- GET `/api/products/get` - Get single product
- PUT `/api/products/update` - Update product
- DELETE `/api/products/delete` - Delete product

### Orders
- GET `/api/orders/list` - List orders
- POST `/api/orders/create` - Create order
- GET `/api/orders/get` - Get order details
- PUT `/api/orders/update` - Update order
- DELETE `/api/orders/delete` - Cancel order

### Loans
- GET `/api/loans/list` - List loans
- POST `/api/loans/create` - Request loan
- GET `/api/loans/get` - Get loan details
- POST `/api/loans/approve` - Approve loan
- POST `/api/loans/disburse` - Disburse funds
- POST `/api/loans/repay` - Make repayment

### Payments
- GET `/api/payments/list` - Payment history
- POST `/api/payments/create` - Process payment
- GET `/api/payments/get` - Payment details
- POST `/api/payments/pesapal-callback` - PesaPal IPN
- POST `/api/payments/mpesa-callback` - M-Pesa callback

### Cart & Wishlist
- POST `/api/cart/add` - Add to cart
- PUT `/api/cart/update` - Update quantity
- DELETE `/api/cart/remove` - Remove item
- POST `/api/cart/checkout` - Checkout
- POST `/api/wishlist/add` - Add to wishlist
- GET `/api/wishlist/list` - List wishlist

### Reports & Analytics
- GET `/api/reports/financial` - Financial reports
- GET `/api/reports/user-activity` - User activity
- GET `/api/reports/loan-performance` - Loan analytics
- GET `/api/analytics/dashboard` - Dashboard metrics

### Admin Functions
- POST `/api/penalties/apply` - Apply penalty
- POST `/api/penalties/waive` - Waive penalty
- GET `/api/kyc/list` - KYC applications
- POST `/api/disputes/create` - Create dispute
- GET `/api/system/settings` - System configuration

## Dashboard Pages (30+)

### Retailer Dashboard
- `/dashboard/retailer/` - Overview
- `/dashboard/retailer/products.php` - Browse products
- `/dashboard/retailer/cart.php` - Shopping cart
- `/dashboard/retailer/orders.php` - Order history
- `/dashboard/retailer/credit-score.php` - Credit rating
- `/dashboard/retailer/repayments.php` - Repayment schedule
- `/dashboard/retailer/notifications.php` - Notifications
- `/dashboard/retailer/wishlist.php` - Saved items
- `/dashboard/retailer/profile.php` - Account settings

### Supplier Dashboard
- `/dashboard/supplier/` - Overview
- `/dashboard/supplier/products-management.php` - Manage catalog
- `/dashboard/supplier/orders.php` - Orders received
- `/dashboard/supplier/delivery-tracking.php` - Shipments
- `/dashboard/supplier/delivery-verification.php` - QR verification
- `/dashboard/supplier/inventory.php` - Stock management
- `/dashboard/supplier/reviews.php` - Customer feedback

### Lender Dashboard
- `/dashboard/lender/` - Overview
- `/dashboard/lender/portfolio-analysis.php` - Investment analysis
- `/dashboard/lender/loan-applications.php` - Loan queue
- `/dashboard/lender/investor-dashboard.php` - Performance metrics
- `/dashboard/lender/index.php` - Portfolio summary

### Admin Dashboard
- `/dashboard/admin/` - Overview
- `/dashboard/admin/users-management.php` - User management
- `/dashboard/admin/kyc-management.php` - KYC verification
- `/dashboard/admin/disputes-management.php` - Dispute resolution
- `/dashboard/admin/penalties.php` - Penalty management
- `/dashboard/admin/reports.php` - Financial reports
- `/dashboard/admin/settings.php` - System configuration

### Field Agent Dashboard
- `/dashboard/field-agent/` - Overview
- `/dashboard/field-agent/retailers.php` - Assigned retailers
- `/dashboard/field-agent/deliveries.php` - Delivery tracking
- `/dashboard/field-agent/verification.php` - PIN verification
- `/dashboard/field-agent/earnings.php` - Commission tracking
- `/dashboard/field-agent/profile.php` - Agent profile

## Security Best Practices

1. **Password Security**
   - Minimum 8 characters required
   - BCrypt hashing (2^10 rounds)
   - Password strength validation
   - Secure password reset mechanism

2. **Session Management**
   - 1-hour default timeout
   - Secure session cookies (HttpOnly, Secure flags)
   - CSRF token on all forms
   - Session regeneration on login

3. **Authentication**
   - Optional multi-factor authentication (OTP via SMS)
   - Rate limiting (5 attempts, 15-minute lockout)
   - Device fingerprinting
   - Login IP tracking

4. **Data Protection**
   - Prepared statements for all queries (SQL injection prevention)
   - Input validation and sanitization
   - Output escaping
   - File upload validation
   - JSON Web Tokens for API authentication

5. **Audit & Logging**
   - All user actions logged
   - System event logging
   - Failed login attempts tracked
   - Sensitive data access audited

## Email Configuration (Optional)

To enable email notifications:

1. Edit `config/config.php`:
\`\`\`php
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'your-email@gmail.com');
define('MAIL_PASS', 'your-app-password');
define('MAIL_FROM', 'noreply@juakali-lend.com');
\`\`\`

2. Install PHPMailer (optional):
\`\`\`bash
composer require phpmailer/phpmailer
\`\`\`

## Cron Jobs (Automated Tasks)

Add to crontab for automated operations:

\`\`\`bash
# Run every hour
0 * * * * php /path/to/juakali-lend/cron/penalty-calculation.php

# Run daily at 2 AM
0 2 * * * php /path/to/juakali-lend/cron/daily-reports.php

# Run every 15 minutes
*/15 * * * * php /path/to/juakali-lend/cron/notification-queue.php

# Run weekly on Monday at 6 AM
0 6 * * 1 php /path/to/juakali-lend/cron/weekly-summary.php
\`\`\`

## API Usage Examples

### Register New User
\`\`\`bash
curl -X POST http://localhost/juakali-lend/api/users/register.php \\
  -H "Content-Type: application/json" \\
  -d '{
    "email": "user@example.com",
    "password": "SecurePass123",
    "first_name": "John",
    "last_name": "Doe",
    "role": "retailer"
  }'
\`\`\`

### Create Order
\`\`\`bash
curl -X POST http://localhost/juakali-lend/api/orders/create.php \\
  -H "Content-Type: application/json" \\
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \\
  -d '{
    "products": [
      {"product_id": 1, "quantity": 5},
      {"product_id": 2, "quantity": 3}
    ]
  }'
\`\`\`

### Request Loan
\`\`\`bash
curl -X POST http://localhost/juakali-lend/api/loans/create.php \\
  -H "Content-Type: application/json" \\
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \\
  -d '{
    "order_id": 123,
    "loan_term_days": 30
  }'
\`\`\`

## Troubleshooting

### Database Connection Error
- Verify MySQL is running
- Check DB_HOST, DB_USER, DB_PASS in config/config.php
- Ensure database exists or installer will create it

### File Upload Issues
- Check `/uploads` directory permissions (755)
- Verify MAX_FILE_SIZE in config
- Ensure file extensions are allowed

### Payment Gateway Errors
- Verify API credentials in config
- Check network connectivity
- Review integration logs

### Email Not Sending
- Enable MAIL_* settings in config
- Verify SMTP credentials
- Check server firewall allows SMTP port

## Support & Maintenance

- Regular backups recommended (weekly minimum)
- Update dependencies quarterly
- Monitor error logs in `/logs/`
- Review audit logs monthly
- Test disaster recovery procedures

## License

JuaKali Lend is proprietary software. All rights reserved.

## Version

Version 1.0.0 - Released January 2025
