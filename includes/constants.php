<?php
// User Roles
define('ROLE_RETAILER', 'retailer');
define('ROLE_SUPPLIER', 'supplier');
define('ROLE_LENDER', 'lender');
define('ROLE_ADMIN', 'admin');

// Order Status
define('ORDER_PENDING', 'pending');
define('ORDER_CONFIRMED', 'confirmed');
define('ORDER_SHIPPED', 'shipped');
define('ORDER_DELIVERED', 'delivered');
define('ORDER_CANCELLED', 'cancelled');

// Loan Status
define('LOAN_PENDING', 'pending');
define('LOAN_APPROVED', 'approved');
define('LOAN_REJECTED', 'rejected');
define('LOAN_ACTIVE', 'active');
define('LOAN_COMPLETED', 'completed');
define('LOAN_DEFAULTED', 'defaulted');

// Payment Status
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_COMPLETED', 'completed');
define('PAYMENT_FAILED', 'failed');
define('PAYMENT_REFUNDED', 'refunded');

// KYC Status
define('KYC_PENDING', 'pending');
define('KYC_APPROVED', 'approved');
define('KYC_REJECTED', 'rejected');

// Credit Score Ranges
define('CREDIT_EXCELLENT', 'excellent');
define('CREDIT_GOOD', 'good');
define('CREDIT_FAIR', 'fair');
define('CREDIT_POOR', 'poor');

// API Response Codes
define('API_SUCCESS', 200);
define('API_CREATED', 201);
define('API_BAD_REQUEST', 400);
define('API_UNAUTHORIZED', 401);
define('API_FORBIDDEN', 403);
define('API_NOT_FOUND', 404);
define('API_SERVER_ERROR', 500);

// Email Templates
define('EMAIL_WELCOME', 'welcome');
define('EMAIL_LOAN_APPROVED', 'loan_approved');
define('EMAIL_PAYMENT_REMINDER', 'payment_reminder');
define('EMAIL_VERIFICATION', 'verification');

// SMS Templates
define('SMS_OTP', 'otp');
define('SMS_LOAN_STATUS', 'loan_status');
define('SMS_PAYMENT_CONFIRMATION', 'payment_confirmation');
?>
