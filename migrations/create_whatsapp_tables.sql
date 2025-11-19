-- WhatsApp Integration Tables Migration for JuaKali Lend
-- This script creates all necessary tables for WhatsApp functionality

-- WhatsApp message logs
CREATE TABLE IF NOT EXISTS whatsapp_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'template', 'interactive', 'media') DEFAULT 'text',
    template_name VARCHAR(100) NULL,
    response_data JSON NULL,
    error_message TEXT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_recipient_phone (recipient_phone),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_message_type (message_type)
);

-- WhatsApp incoming messages
CREATE TABLE IF NOT EXISTS whatsapp_incoming (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    message_id VARCHAR(100) NOT NULL UNIQUE,
    sender_phone VARCHAR(20) NOT NULL,
    message_type ENUM('text', 'interactive', 'button', 'media', 'location', 'contact') DEFAULT 'text',
    message_data JSON NOT NULL,
    timestamp BIGINT NULL,
    processed ENUM('yes', 'no') DEFAULT 'no',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_sender_phone (sender_phone),
    INDEX idx_message_id (message_id),
    INDEX idx_processed (processed),
    INDEX idx_created_at (created_at)
);

-- WhatsApp templates
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL UNIQUE,
    template_category ENUM('MARKETING', 'UTILITY', 'AUTHENTICATION') DEFAULT 'UTILITY',
    template_language VARCHAR(10) DEFAULT 'en',
    template_content JSON NOT NULL,
    status ENUM('active', 'inactive', 'pending_approval', 'rejected') DEFAULT 'pending_approval',
    whatsapp_template_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_template_name (template_name),
    INDEX idx_status (status),
    INDEX idx_category (template_category)
);

-- WhatsApp campaign management
CREATE TABLE IF NOT EXISTS whatsapp_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_name VARCHAR(200) NOT NULL,
    campaign_type ENUM('bulk_message', 'payment_reminders', 'loan_approvals', 'marketing', 'alerts') DEFAULT 'bulk_message',
    target_audience JSON NOT NULL, -- Stores filters for target users
    message_template TEXT NOT NULL,
    template_name VARCHAR(100) NULL,
    total_recipients INT DEFAULT 0,
    sent_messages INT DEFAULT 0,
    failed_messages INT DEFAULT 0,
    status ENUM('draft', 'scheduled', 'running', 'completed', 'paused', 'cancelled') DEFAULT 'draft',
    scheduled_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_campaign_name (campaign_name),
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_created_by (created_by)
);

-- WhatsApp campaign recipients
CREATE TABLE IF NOT EXISTS whatsapp_campaign_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    user_id INT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    message_content TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'delivered', 'read') DEFAULT 'pending',
    message_id VARCHAR(100) NULL,
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (campaign_id) REFERENCES whatsapp_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_campaign_id (campaign_id),
    INDEX idx_user_id (user_id),
    INDEX idx_recipient_phone (recipient_phone),
    INDEX idx_status (status),
    UNIQUE KEY unique_campaign_recipient (campaign_id, recipient_phone)
);

-- User notification preferences
CREATE TABLE IF NOT EXISTS user_notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    whatsapp_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT TRUE,
    email_notifications BOOLEAN DEFAULT TRUE,
    payment_reminders BOOLEAN DEFAULT TRUE,
    loan_updates BOOLEAN DEFAULT TRUE,
    promotional_messages BOOLEAN DEFAULT FALSE,
    fraud_alerts BOOLEAN DEFAULT TRUE,
    delivery_notifications BOOLEAN DEFAULT TRUE,
    quiet_hours_enabled BOOLEAN DEFAULT FALSE,
    quiet_hours_start TIME DEFAULT '22:00:00',
    quiet_hours_end TIME DEFAULT '08:00:00',
    preferred_language VARCHAR(10) DEFAULT 'en',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- WhatsApp configuration
CREATE TABLE IF NOT EXISTS whatsapp_configuration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value JSON NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_config_key (config_key),
    INDEX idx_is_active (is_active)
);

-- WhatsApp webhooks log
CREATE TABLE IF NOT EXISTS whatsapp_webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id VARCHAR(100) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    payload JSON NOT NULL,
    processed ENUM('yes', 'no', 'error') DEFAULT 'no',
    error_message TEXT NULL,
    processing_time INT NULL, -- in milliseconds
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_webhook_id (webhook_id),
    INDEX idx_event_type (event_type),
    INDEX idx_processed (processed),
    INDEX idx_created_at (created_at)
);

-- Insert default WhatsApp configuration
INSERT INTO whatsapp_configuration (config_key, config_value, description) VALUES
('whatsapp_config', JSON_OBJECT(
    'access_token', '',
    'phone_number_id', '',
    'business_account_id', '',
    'webhook_verify_token', 'juakali_whatsapp_webhook_token_2024',
    'base_url', 'https://graph.facebook.com',
    'environment', 'sandbox'
), 'Main WhatsApp Business API configuration'),
('rate_limits', JSON_OBJECT(
    'messages_per_second', 10,
    'messages_per_hour', 1000,
    'messages_per_day', 10000,
    'bulk_message_delay', 100
), 'Rate limiting configuration for WhatsApp messages'),
('message_templates', JSON_OBJECT(
    'payment_reminder', JSON_OBJECT(
        'name', 'payment_reminder',
        'category', 'UTILITY',
        'language', 'en',
        'components', JSON_ARRAY(
            JSON_OBJECT('type', 'header', 'format', 'TEXT', 'text', 'Payment Reminder'),
            JSON_OBJECT('type', 'body', 'text', 'Hello {{1}}, this is a reminder that your payment of KES {{2}} is due on {{3}}. Please ensure timely payment.'),
            JSON_OBJECT('type', 'footer', 'text', 'JuaKali Lend')
        )
    ),
    'loan_approved', JSON_OBJECT(
        'name', 'loan_approved',
        'category', 'UTILITY',
        'language', 'en',
        'components', JSON_ARRAY(
            JSON_OBJECT('type', 'header', 'format', 'TEXT', 'text', 'Loan Approved!'),
            JSON_OBJECT('type', 'body', 'text', 'Congratulations {{1}}! Your loan application for KES {{2}} has been approved. {{3}}'),
            JSON_OBJECT('type', 'footer', 'text', 'JuaKali Lend')
        )
    ),
    'otp_verification', JSON_OBJECT(
        'name', 'otp_verification',
        'category', 'AUTHENTICATION',
        'language', 'en',
        'components', JSON_ARRAY(
            JSON_OBJECT('type', 'body', 'text', 'Your OTP for {{1}} is {{2}}. Valid for 10 minutes. Do not share this code.'),
            JSON_OBJECT('type', 'footer', 'text', 'JuaKali Lend')
        )
    )
), 'Pre-defined message templates for WhatsApp'),
('business_profile', JSON_OBJECT(
    'about', 'JuaKali Lend - Your trusted microfinance partner',
    'address', 'Nairobi, Kenya',
    'description', 'Providing quick and affordable loans to small businesses',
    'email', 'support@juakali-lend.com',
    'websites', JSON_ARRAY('https://juakali-lend.com'),
    'vertical', 'Financial Services'
), 'WhatsApp Business Profile information')
ON DUPLICATE KEY UPDATE
config_value = VALUES(config_value),
updated_at = CURRENT_TIMESTAMP;

-- Insert default notification preferences for existing users
INSERT IGNORE INTO user_notification_preferences (user_id)
SELECT id FROM users;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_whatsapp_logs_composite ON whatsapp_logs(status, created_at);
CREATE INDEX IF NOT EXISTS idx_whatsapp_incoming_composite ON whatsapp_incoming(processed, created_at);
CREATE INDEX IF NOT EXISTS idx_campaign_recipients_composite ON whatsapp_campaign_recipients(campaign_id, status);

-- Add foreign key constraints if they don't exist
ALTER TABLE whatsapp_campaigns
ADD CONSTRAINT fk_campaigns_created_by
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE whatsapp_campaign_recipients
ADD CONSTRAINT fk_campaign_recipients_campaign
FOREIGN KEY (campaign_id) REFERENCES whatsapp_campaigns(id) ON DELETE CASCADE;

ALTER TABLE whatsapp_campaign_recipients
ADD CONSTRAINT fk_campaign_recipients_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE user_notification_preferences
ADD CONSTRAINT fk_notification_preferences_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Create view for WhatsApp statistics
CREATE OR REPLACE VIEW whatsapp_statistics AS
SELECT
    DATE(created_at) as date,
    COUNT(*) as total_messages,
    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_messages,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_messages,
    COUNT(CASE WHEN message_type = 'text' THEN 1 END) as text_messages,
    COUNT(CASE WHEN message_type = 'template' THEN 1 END) as template_messages,
    COUNT(CASE WHEN message_type = 'interactive' THEN 1 END) as interactive_messages,
    COUNT(CASE WHEN message_type = 'media' THEN 1 END) as media_messages,
    COUNT(CASE WHEN template_name = 'payment_reminder' THEN 1 END) as payment_reminders,
    COUNT(CASE WHEN template_name = 'loan_approved' THEN 1 END) as loan_approvals,
    COUNT(CASE WHEN template_name = 'otp_verification' THEN 1 END) as otp_verifications,
    COUNT(CASE WHEN template_name = 'fraud_alert' THEN 1 END) as fraud_alerts,
    ROUND(AVG(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) * 100, 2) as success_rate
FROM whatsapp_logs
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Create view for user engagement statistics
CREATE OR REPLACE VIEW whatsapp_user_engagement AS
SELECT
    u.id as user_id,
    u.name,
    u.phone,
    COUNT(DISTINCT wi.id) as total_incoming_messages,
    COUNT(DISTINCT wl.id) as total_outgoing_messages,
    MAX(wi.created_at) as last_incoming_message,
    MAX(wl.created_at) as last_outgoing_message,
    CASE
        WHEN MAX(wi.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'active'
        WHEN MAX(wi.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'moderate'
        ELSE 'inactive'
    END as engagement_level
FROM users u
LEFT JOIN whatsapp_incoming wi ON u.id = wi.user_id
LEFT JOIN whatsapp_logs wl ON u.phone = wl.recipient_phone
GROUP BY u.id, u.name, u.phone
ORDER BY total_incoming_messages DESC, total_outgoing_messages DESC;

-- Create stored procedure for cleaning old logs
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CleanOldWhatsAppLogs()
BEGIN
    -- Delete logs older than 90 days
    DELETE FROM whatsapp_logs
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

    -- Delete webhook logs older than 30 days
    DELETE FROM whatsapp_webhook_logs
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

    -- Delete incoming messages older than 60 days
    DELETE FROM whatsapp_incoming
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY);

    SELECT ROW_COUNT() as total_cleaned;
END //
DELIMITER ;

-- Create event for automatic cleanup (runs weekly)
CREATE EVENT IF NOT EXISTS whatsapp_cleanup_event
ON SCHEDULE EVERY 1 WEEK
STARTS TIMESTAMP(CURRENT_DATE, '23:00:00')
DO CALL CleanOldWhatsAppLogs();

-- Set the event scheduler to be active
SET GLOBAL event_scheduler = ON;