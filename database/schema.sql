-- Updated schema with all required tables for the complete system
CREATE DATABASE IF NOT EXISTS juakali_lend;
USE juakali_lend;

-- Users table
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  phone VARCHAR(20),
  role ENUM('retailer', 'lender', 'supplier', 'admin') NOT NULL,
  company_name VARCHAR(255),
  business_registration VARCHAR(255),
  status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  kyc_verified BOOLEAN DEFAULT FALSE,
  mfa_enabled BOOLEAN DEFAULT FALSE,
  last_login DATETIME,
  last_login_ip VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  loyalty_points INT DEFAULT 0,
  INDEX idx_email (email),
  INDEX idx_role (role),
  INDEX idx_status (status)
);

-- User Profiles table
CREATE TABLE user_profiles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNIQUE NOT NULL,
  bio TEXT,
  address VARCHAR(255),
  city VARCHAR(100),
  country VARCHAR(100),
  profile_image VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Credit Scores table
CREATE TABLE credit_scores (
  id INT PRIMARY KEY AUTO_INCREMENT,
  retailer_id INT UNIQUE NOT NULL,
  score INT DEFAULT 500,
  credit_limit DECIMAL(15, 2) DEFAULT 10000,
  total_borrowed DECIMAL(15, 2) DEFAULT 0,
  total_repaid DECIMAL(15, 2) DEFAULT 0,
  default_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (retailer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Products table
CREATE TABLE products (
  id INT PRIMARY KEY AUTO_INCREMENT,
  supplier_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  category VARCHAR(100),
  price DECIMAL(15, 2) NOT NULL,
  quantity_available INT DEFAULT 0,
  unit VARCHAR(50),
  image_url VARCHAR(255),
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES users(id),
  INDEX idx_supplier (supplier_id),
  INDEX idx_category (category)
);

-- Orders table
CREATE TABLE orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  retailer_id INT NOT NULL,
  supplier_id INT NOT NULL,
  order_number VARCHAR(50) UNIQUE NOT NULL,
  total_amount DECIMAL(15, 2) NOT NULL,
  status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
  delivery_date DATE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (retailer_id) REFERENCES users(id),
  FOREIGN KEY (supplier_id) REFERENCES users(id),
  INDEX idx_retailer (retailer_id),
  INDEX idx_supplier (supplier_id),
  INDEX idx_status (status)
);

-- Order Items table
CREATE TABLE order_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(15, 2) NOT NULL,
  subtotal DECIMAL(15, 2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Loans table
CREATE TABLE loans (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  retailer_id INT NOT NULL,
  lender_id INT NOT NULL,
  loan_amount DECIMAL(15, 2) NOT NULL,
  interest_rate DECIMAL(5, 2) NOT NULL,
  loan_term_days INT NOT NULL,
  status ENUM('pending', 'approved', 'disbursed', 'repaid', 'defaulted') DEFAULT 'pending',
  approval_date DATETIME,
  due_date DATE,
  blockchain_hash VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (retailer_id) REFERENCES users(id),
  FOREIGN KEY (lender_id) REFERENCES users(id),
  INDEX idx_retailer (retailer_id),
  INDEX idx_lender (lender_id),
  INDEX idx_status (status)
);

-- Repayment Schedule table
CREATE TABLE repayment_schedule (
  id INT PRIMARY KEY AUTO_INCREMENT,
  loan_id INT NOT NULL,
  amount_due DECIMAL(15, 2) NOT NULL,
  amount_paid DECIMAL(15, 2) DEFAULT 0,
  due_date DATE NOT NULL,
  status ENUM('pending', 'completed', 'overdue') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  INDEX idx_loan (loan_id),
  INDEX idx_status (status)
);

-- Payments table
CREATE TABLE payments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  loan_id INT NOT NULL,
  amount DECIMAL(15, 2) NOT NULL,
  payment_method ENUM('bank_transfer', 'mobile_money', 'cash', 'check') DEFAULT 'bank_transfer',
  transaction_id VARCHAR(100),
  status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
  payment_date DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (loan_id) REFERENCES loans(id),
  INDEX idx_loan (loan_id),
  INDEX idx_status (status)
);

-- Notifications table
CREATE TABLE notifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('order', 'loan', 'payment', 'system') DEFAULT 'system',
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_read (is_read)
);

-- Audit Log table
CREATE TABLE audit_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  action VARCHAR(255) NOT NULL,
  entity_type VARCHAR(100),
  entity_id INT,
  old_values JSON,
  new_values JSON,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_action (action)
);

-- OTP Logs table
CREATE TABLE otp_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  otp VARCHAR(6) NOT NULL,
  phone_number VARCHAR(20) NOT NULL,
  attempts INT DEFAULT 0,
  verified BOOLEAN DEFAULT FALSE,
  expires_at DATETIME NOT NULL,
  verified_at DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_expires (expires_at)
);

-- Password Resets table
CREATE TABLE password_resets (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  token VARCHAR(255) UNIQUE NOT NULL,
  expires_at DATETIME NOT NULL,
  used BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token (token),
  INDEX idx_expires (expires_at)
);

-- Rate Limits table
CREATE TABLE rate_limits (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ip_address VARCHAR(45) UNIQUE NOT NULL,
  attempts INT DEFAULT 0,
  last_attempt DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip (ip_address)
);

-- Sessions table
CREATE TABLE sessions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  session_token VARCHAR(255) UNIQUE NOT NULL,
  device_fingerprint VARCHAR(255),
  ip_address VARCHAR(45),
  user_agent TEXT,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_token (session_token),
  INDEX idx_expires (expires_at)
);

-- MFA Settings table
CREATE TABLE mfa_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNIQUE NOT NULL,
  mfa_enabled BOOLEAN DEFAULT FALSE,
  totp_secret VARCHAR(255),
  backup_codes JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Adding KYC Documents table
CREATE TABLE kyc_documents (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  document_type ENUM('national_id', 'passport', 'business_license', 'tax_certificate') NOT NULL,
  document_url VARCHAR(255) NOT NULL,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  rejection_reason TEXT,
  verified_by INT,
  verified_at DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES users(id),
  INDEX idx_user (user_id),
  INDEX idx_status (status)
);

-- Adding Categories table
CREATE TABLE categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  icon VARCHAR(255),
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status)
);

-- Adding Testimonials table
CREATE TABLE testimonials (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  rating INT DEFAULT 5,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_status (status)
);

-- Adding Blog/News table
CREATE TABLE blog_posts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  author_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) UNIQUE NOT NULL,
  content LONGTEXT NOT NULL,
  featured_image VARCHAR(255),
  status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
  views INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  published_at DATETIME,
  FOREIGN KEY (author_id) REFERENCES users(id),
  INDEX idx_status (status),
  INDEX idx_slug (slug)
);

-- Adding FAQ table
CREATE TABLE faqs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  question VARCHAR(255) NOT NULL,
  answer LONGTEXT NOT NULL,
  category VARCHAR(100),
  order_position INT DEFAULT 0,
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_category (category)
);

-- Adding Lender Performance table
CREATE TABLE lender_performance (
  id INT PRIMARY KEY AUTO_INCREMENT,
  lender_id INT UNIQUE NOT NULL,
  total_invested DECIMAL(15, 2) DEFAULT 0,
  total_returned DECIMAL(15, 2) DEFAULT 0,
  active_investments INT DEFAULT 0,
  default_rate DECIMAL(5, 2) DEFAULT 0,
  roi DECIMAL(5, 2) DEFAULT 0,
  rating DECIMAL(3, 2) DEFAULT 5,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (lender_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_lender (lender_id)
);

-- Adding Risk Assessment table
CREATE TABLE risk_assessments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  retailer_id INT NOT NULL,
  risk_score INT DEFAULT 50,
  risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
  fraud_indicators JSON,
  assessment_date DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (retailer_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_retailer (retailer_id),
  INDEX idx_risk_level (risk_level)
);

-- Adding Fraud Detection table
CREATE TABLE fraud_detection (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  description TEXT,
  severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
  status ENUM('pending', 'investigated', 'resolved') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_severity (severity)
);

-- Adding Payment Methods table
CREATE TABLE payment_methods (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  method_type ENUM('bank_account', 'mobile_money', 'card') NOT NULL,
  provider VARCHAR(100),
  account_identifier VARCHAR(255),
  is_default BOOLEAN DEFAULT FALSE,
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_status (status)
);

-- Adding Notification Settings table
CREATE TABLE notification_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNIQUE NOT NULL,
  email_notifications BOOLEAN DEFAULT TRUE,
  sms_notifications BOOLEAN DEFAULT TRUE,
  push_notifications BOOLEAN DEFAULT TRUE,
  whatsapp_notifications BOOLEAN DEFAULT FALSE,
  order_alerts BOOLEAN DEFAULT TRUE,
  payment_alerts BOOLEAN DEFAULT TRUE,
  loan_alerts BOOLEAN DEFAULT TRUE,
  marketing_emails BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Adding Analytics Events table
CREATE TABLE analytics_events (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  event_type VARCHAR(100) NOT NULL,
  event_data JSON,
  page_url VARCHAR(255),
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_event_type (event_type),
  INDEX idx_created (created_at)
);

-- Adding Product Reviews table
CREATE TABLE product_reviews (
  id INT PRIMARY KEY AUTO_INCREMENT,
  product_id INT NOT NULL,
  retailer_id INT NOT NULL,
  rating INT DEFAULT 5,
  review_text TEXT,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (retailer_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_product (product_id),
  INDEX idx_status (status)
);

-- Adding Supplier Ratings table
CREATE TABLE supplier_ratings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  supplier_id INT UNIQUE NOT NULL,
  average_rating DECIMAL(3, 2) DEFAULT 5,
  total_reviews INT DEFAULT 0,
  on_time_delivery_rate DECIMAL(5, 2) DEFAULT 100,
  quality_score DECIMAL(5, 2) DEFAULT 100,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Adding Delivery Tracking table
CREATE TABLE delivery_tracking (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  status ENUM('pending', 'in_transit', 'out_for_delivery', 'delivered', 'failed') DEFAULT 'pending',
  current_location VARCHAR(255),
  estimated_delivery DATE,
  actual_delivery_date DATE,
  tracking_number VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_order (order_id),
  INDEX idx_status (status)
);

-- Adding Return Requests table
CREATE TABLE return_requests (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  retailer_id INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  description TEXT,
  status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
  refund_amount DECIMAL(15, 2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (retailer_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_order (order_id),
  INDEX idx_status (status)
);

-- Adding Disputes table
CREATE TABLE disputes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  initiator_id INT NOT NULL,
  respondent_id INT NOT NULL,
  subject VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  status ENUM('open', 'in_review', 'resolved', 'closed') DEFAULT 'open',
  resolution TEXT,
  resolved_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (initiator_id) REFERENCES users(id),
  FOREIGN KEY (respondent_id) REFERENCES users(id),
  FOREIGN KEY (resolved_by) REFERENCES users(id),
  INDEX idx_order (order_id),
  INDEX idx_status (status)
);

-- Adding System Settings table
CREATE TABLE system_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value LONGTEXT,
  setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_key (setting_key)
);

-- Adding Inventory Alerts table
CREATE TABLE inventory_alerts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  product_id INT NOT NULL,
  supplier_id INT NOT NULL,
  alert_type ENUM('low_stock', 'out_of_stock', 'overstock') DEFAULT 'low_stock',
  threshold_quantity INT,
  current_quantity INT,
  status ENUM('active', 'resolved') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_product (product_id),
  INDEX idx_status (status)
);

-- Adding tables for new features: banners, promotions, educational content, user achievements, ab tests, user devices

-- Banners table for dynamic content
CREATE TABLE IF NOT EXISTS banners (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  content TEXT,
  image_url VARCHAR(255),
  link_url VARCHAR(255),
  display_order INT DEFAULT 0,
  status ENUM('active', 'inactive') DEFAULT 'active',
  start_date DATETIME,
  end_date DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status)
);

-- Promotions table
CREATE TABLE IF NOT EXISTS promotions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  discount_percentage DECIMAL(5, 2),
  target_city VARCHAR(100),
  status ENUM('active', 'inactive') DEFAULT 'active',
  start_date DATETIME,
  end_date DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status)
);

-- Educational Content table
CREATE TABLE IF NOT EXISTS educational_content (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  content LONGTEXT,
  category VARCHAR(100),
  content_type ENUM('article', 'video', 'tutorial') DEFAULT 'article',
  status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_category (category)
);

-- User Achievements table
CREATE TABLE IF NOT EXISTS user_achievements (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  achievement_type VARCHAR(100) NOT NULL,
  badge_name VARCHAR(100),
  points_awarded INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id)
);

-- A/B Tests table
CREATE TABLE IF NOT EXISTS ab_tests (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  test_name VARCHAR(100) NOT NULL,
  variant ENUM('A', 'B') NOT NULL,
  conversions INT DEFAULT 0,
  conversion_value DECIMAL(15, 2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_test (test_name)
);

-- User Devices table for push notifications
CREATE TABLE IF NOT EXISTS user_devices (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  device_token VARCHAR(255) UNIQUE NOT NULL,
  device_type ENUM('ios', 'android', 'web') DEFAULT 'web',
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id)
);

-- Shopping Cart table
CREATE TABLE IF NOT EXISTS shopping_cart (
  id INT PRIMARY KEY AUTO_INCREMENT,
  retailer_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(15, 2) NOT NULL,
  subtotal DECIMAL(15, 2) NOT NULL,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (retailer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY unique_cart_item (retailer_id, product_id),
  INDEX idx_retailer (retailer_id)
);

-- Wishlist table for saved items
CREATE TABLE IF NOT EXISTS wishlist (
  id INT PRIMARY KEY AUTO_INCREMENT,
  retailer_id INT NOT NULL,
  product_id INT NOT NULL,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (retailer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY unique_wishlist_item (retailer_id, product_id),
  INDEX idx_retailer (retailer_id)
);

-- Saved for later cart items
CREATE TABLE IF NOT EXISTS cart_saved_for_later (
  id INT PRIMARY KEY AUTO_INCREMENT,
  cart_id INT NOT NULL,
  saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cart_id) REFERENCES shopping_cart(id) ON DELETE CASCADE
);

-- Penalty Records table
CREATE TABLE IF NOT EXISTS penalty_records (
  id INT PRIMARY KEY AUTO_INCREMENT,
  repayment_id INT NOT NULL,
  loan_id INT NOT NULL,
  penalty_amount DECIMAL(15, 2) NOT NULL,
  days_overdue INT NOT NULL,
  reason TEXT,
  waived BOOLEAN DEFAULT FALSE,
  waived_reason TEXT,
  waived_by INT,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  waived_at DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (repayment_id) REFERENCES repayment_schedule(id) ON DELETE CASCADE,
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  FOREIGN KEY (waived_by) REFERENCES users(id),
  INDEX idx_loan (loan_id),
  INDEX idx_applied (applied_at)
);

-- Adding PesaPal transaction tracking table
CREATE TABLE IF NOT EXISTS pesapal_transactions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  txn_id BIGINT UNIQUE NOT NULL,
  loan_id INT NOT NULL,
  invoice_id INT NOT NULL,
  amount DECIMAL(15, 2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'KES',
  reference_code VARCHAR(50) NOT NULL UNIQUE,
  tracking_id VARCHAR(5000),
  notification_type VARCHAR(50),
  txn_status ENUM('PENDING', 'PAID', 'FAILED', 'INVALID') DEFAULT 'PENDING',
  txn_date DATETIME,
  ipn_date DATETIME,
  flag BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  INDEX idx_reference (reference_code),
  INDEX idx_tracking (tracking_id(100)),
  INDEX idx_status (txn_status),
  INDEX idx_loan (loan_id)
);
