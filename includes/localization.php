<?php
/**
 * Comprehensive Multi-language Support System
 * Supports Swahili, English, and other languages for East African markets
 */

class LocalizationManager {
    private $db;
    private $currentLanguage;
    private $translations = [];
    private $supportedLanguages = [
        'en' => [
            'name' => 'English',
            'native_name' => 'English',
            'code' => 'en',
            'flag' => '🇺🇸',
            'rtl' => false
        ],
        'sw' => [
            'name' => 'Swahili',
            'native_name' => 'Kiswahili',
            'code' => 'sw',
            'flag' => '🇰🇪',
            'rtl' => false
        ],
        'fr' => [
            'name' => 'French',
            'native_name' => 'Français',
            'code' => 'fr',
            'flag' => '🇫🇷',
            'rtl' => false
        ],
        'ar' => [
            'name' => 'Arabic',
            'native_name' => 'العربية',
            'code' => 'ar',
            'flag' => '🇸🇦',
            'rtl' => true
        ]
    ];

    public function __construct($database) {
        $this->db = $database;
        $this->initializeLanguage();
        $this->loadTranslations();
    }

    /**
     * Initialize language based on user preference or browser
     */
    private function initializeLanguage() {
        // Priority: Session > User Preference > Browser > Default
        if (isset($_SESSION['user_language'])) {
            $this->currentLanguage = $_SESSION['user_language'];
        } elseif (isset($_COOKIE['language_preference'])) {
            $this->currentLanguage = $_COOKIE['language_preference'];
        } else {
            $this->currentLanguage = $this->detectBrowserLanguage();
        }

        // Ensure language is supported
        if (!isset($this->supportedLanguages[$this->currentLanguage])) {
            $this->currentLanguage = 'en'; // Default to English
        }
    }

    /**
     * Detect browser language preference
     */
    private function detectBrowserLanguage() {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLanguages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

            foreach ($browserLanguages as $lang) {
                $lang = substr(trim($lang), 0, 2);
                if (isset($this->supportedLanguages[$lang])) {
                    return $lang;
                }
            }
        }

        return 'en'; // Default fallback
    }

    /**
     * Load translations from database and files
     */
    private function loadTranslations() {
        // Load common translations from files
        $this->loadFileTranslations();

        // Load custom translations from database
        $this->loadDatabaseTranslations();
    }

    /**
     * Load translations from language files
     */
    private function loadFileTranslations() {
        $langDir = __DIR__ . '/../languages/';

        // Default English translations
        $this->translations['en'] = [
            // General
            'welcome' => 'Welcome',
            'login' => 'Login',
            'logout' => 'Logout',
            'register' => 'Register',
            'dashboard' => 'Dashboard',
            'profile' => 'Profile',
            'settings' => 'Settings',
            'help' => 'Help',
            'about' => 'About',
            'contact' => 'Contact',
            'support' => 'Support',
            'search' => 'Search',
            'save' => 'Save',
            'cancel' => 'Cancel',
            'submit' => 'Submit',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'view' => 'View',
            'close' => 'Close',
            'back' => 'Back',
            'next' => 'Next',
            'previous' => 'Previous',
            'yes' => 'Yes',
            'no' => 'No',
            'ok' => 'OK',
            'error' => 'Error',
            'success' => 'Success',
            'warning' => 'Warning',
            'info' => 'Information',
            'loading' => 'Loading...',
            'please_wait' => 'Please wait...',
            'no_data' => 'No data available',

            // Authentication
            'email' => 'Email',
            'password' => 'Password',
            'confirm_password' => 'Confirm Password',
            'forgot_password' => 'Forgot Password?',
            'remember_me' => 'Remember Me',
            'login_success' => 'Login successful',
            'login_failed' => 'Login failed. Please check your credentials.',
            'invalid_credentials' => 'Invalid email or password',
            'account_locked' => 'Account temporarily locked. Please try again later.',

            // User Roles
            'retailer' => 'Retailer',
            'supplier' => 'Supplier',
            'lender' => 'Lender',
            'admin' => 'Administrator',
            'field_agent' => 'Field Agent',

            // Loan System
            'loan' => 'Loan',
            'loans' => 'Loans',
            'apply_for_loan' => 'Apply for Loan',
            'loan_amount' => 'Loan Amount',
            'loan_purpose' => 'Loan Purpose',
            'interest_rate' => 'Interest Rate',
            'repayment_period' => 'Repayment Period',
            'loan_status' => 'Loan Status',
            'active_loan' => 'Active Loan',
            'completed_loan' => 'Completed Loan',
            'pending_loan' => 'Pending Loan',
            'rejected_loan' => 'Rejected Loan',
            'loan_application' => 'Loan Application',
            'loan_disbursement' => 'Loan Disbursement',
            'loan_repayment' => 'Loan Repayment',

            // Status
            'active' => 'Active',
            'pending' => 'Pending',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'approved' => 'Approved',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'overdue' => 'Overdue',
            'defaulted' => 'Defaulted',

            // Financial
            'amount' => 'Amount',
            'total_amount' => 'Total Amount',
            'paid_amount' => 'Paid Amount',
            'remaining_amount' => 'Remaining Amount',
            'due_amount' => 'Due Amount',
            'currency' => 'KES',
            'payment' => 'Payment',
            'payment_method' => 'Payment Method',
            'transaction' => 'Transaction',
            'receipt' => 'Receipt',

            // Dates
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This Week',
            'last_week' => 'Last Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
            'due_date' => 'Due Date',
            'paid_at' => 'Paid',

            // Messages
            'operation_successful' => 'Operation completed successfully',
            'operation_failed' => 'Operation failed',
            'invalid_input' => 'Invalid input provided',
            'insufficient_permissions' => 'Insufficient permissions',
            'session_expired' => 'Your session has expired. Please login again.',
            'file_upload_success' => 'File uploaded successfully',
            'file_upload_failed' => 'File upload failed',
            'data_saved' => 'Data saved successfully',

            // Business Terms
            'credit_score' => 'Credit Score',
            'risk_assessment' => 'Risk Assessment',
            'interest_earned' => 'Interest Earned',
            'penalty_applied' => 'Penalty Applied',
            'early_repayment' => 'Early Repayment',
            'loan_extension' => 'Loan Extension',

            // Navigation
            'home' => 'Home',
            'my_account' => 'My Account',
            'my_loans' => 'My Loans',
            'apply_now' => 'Apply Now',
            'make_payment' => 'Make Payment',
            'contact_support' => 'Contact Support',

            // Numbers
            'one' => 'One',
            'two' => 'Two',
            'three' => 'Three',
            'four' => 'Four',
            'five' => 'Five',
            'six' => 'Six',
            'seven' => 'Seven',
            'eight' => 'Eight',
            'nine' => 'Nine',
            'ten' => 'Ten',

            // Time
            'minute' => 'minute',
            'minutes' => 'minutes',
            'hour' => 'hour',
            'hours' => 'hours',
            'day' => 'day',
            'days' => 'days',
            'week' => 'week',
            'weeks' => 'weeks',
            'month' => 'month',
            'months' => 'months',
            'year' => 'year',
            'years' => 'years',

            // Validation
            'required_field' => 'This field is required',
            'invalid_email' => 'Please enter a valid email address',
            'invalid_phone' => 'Please enter a valid phone number',
            'password_too_short' => 'Password must be at least 8 characters',
            'passwords_not_match' => 'Passwords do not match',
            'amount_too_low' => 'Amount is too low',
            'amount_too_high' => 'Amount exceeds maximum limit',

            // Business Messages
            'loan_approved' => 'Congratulations! Your loan has been approved.',
            'loan_rejected' => 'Your loan application could not be approved at this time.',
            'payment_received' => 'Thank you! Your payment has been received.',
            'payment_overdue' => 'Your payment is overdue. Please pay immediately to avoid penalties.',
            'account_verified' => 'Your account has been successfully verified.',

            // System
            'system_maintenance' => 'System under maintenance. Please try again later.',
            'feature_unavailable' => 'This feature is currently unavailable.',
            'access_denied' => 'Access denied. You do not have permission to perform this action.',

            // Contact
            'phone_number' => 'Phone Number',
            'email_address' => 'Email Address',
            'physical_address' => 'Physical Address',
            'postal_code' => 'Postal Code',
            'city' => 'City',
            'country' => 'Country'
        ];

        // Load Swahili translations
        $this->translations['sw'] = [
            // General
            'welcome' => 'Karibu',
            'login' => 'Ingia',
            'logout' => 'Toka',
            'register' => 'Jiandikishe',
            'dashboard' => 'Dashibodi',
            'profile' => 'Wasifu',
            'settings' => 'Mipangilio',
            'help' => 'Msaada',
            'about' => 'Kuhusu',
            'contact' => 'Wasiliana',
            'support' => 'Msaada',
            'search' => 'Tafuta',
            'save' => 'Hifadhi',
            'cancel' => 'Ghairi',
            'submit' => 'Tuma',
            'edit' => 'Hariri',
            'delete' => 'Futa',
            'view' => 'Ona',
            'close' => 'Funga',
            'back' => 'Rudi',
            'next' => 'Ifuatayo',
            'previous' => 'Iliyopita',
            'yes' => 'Ndio',
            'no' => 'Hapana',
            'ok' => 'Sawa',
            'error' => 'Hitilafu',
            'success' => 'Mafanikio',
            'warning' => 'Onyo',
            'info' => 'Maelezo',
            'loading' => 'Inapakia...',
            'please_wait' => 'Tafadhali subiri...',
            'no_data' => 'Hakuna data inapatikana',

            // Authentication
            'email' => 'Barua pepe',
            'password' => 'Nenosiri',
            'confirm_password' => 'Thibitisha Nenosiri',
            'forgot_password' => 'Umesahau Nenosiri?',
            'remember_me' => 'Nikumbuke',
            'login_success' => 'Umeingia kwa mafanikio',
            'login_failed' => 'Imeingia kwa hitilafu. Tafadhali angalia siri zako.',
            'invalid_credentials' => 'Barua pepe au nenosiri sio sahihi',
            'account_locked' => 'Akaunti imefungwa kwa muda. Tafadhali jaribu tena baadaye.',

            // User Roles
            'retailer' => 'Muuzaji',
            'supplier' => 'Muzaji',
            'lender' => 'Mkopesha',
            'admin' => 'Msimamizi',
            'field_agent' => 'Mwakili wa Uga',

            // Loan System
            'loan' => 'Mkopo',
            'loans' => 'Mikopo',
            'apply_for_loan' => 'Omba Mkopo',
            'loan_amount' => 'Kiasi ya Mkopo',
            'loan_purpose' => 'Madhumuni ya Mkopo',
            'interest_rate' => 'Kiwango cha Riba',
            'repayment_period' => 'Kipindi cha Malipo',
            'loan_status' => 'Hali ya Mkopo',
            'active_loan' => 'Mkopo Amilivyofye',
            'completed_loan' => 'Mkopo Umekamilikwa',
            'pending_loan' => 'Mkopo Unaendelea',
            'rejected_loan' => 'Mkopo Ulikataliwa',
            'loan_application' => 'Omba la Mkopo',
            'loan_disbursement' => 'utoaji wa Mkopo',
            'loan_repayment' => 'Malipo ya Mkopo',

            // Status
            'active' => 'Amilivyofye',
            'pending' => 'Unaendelea',
            'completed' => 'Umekamilikwa',
            'rejected' => 'Ulikataliwa',
            'cancelled' => 'Ulifutwa',
            'approved' => 'Imeidhinishwa',
            'in_transit' => 'Njiani',
            'delivered' => 'Imewasilishwa',
            'overdue' => 'Umekuchelewa',
            'defaulted' => 'Mmilikiwa',

            // Financial
            'amount' => 'Kiasi',
            'total_amount' => 'Kiasi Jumla',
            'paid_amount' => 'Kiasi Lililopeshwa',
            'remaining_amount' => 'Kiasi Baki',
            'due_amount' => 'Kiasi Cha Kulipa',
            'currency' => 'KES',
            'payment' => 'Malipo',
            'payment_method' => 'Njia ya Malipo',
            'transaction' => 'Muamala',
            'receipt' => 'Risiti',

            // Dates
            'today' => 'Leo',
            'yesterday' => 'Jana',
            'this_week' => 'Wiki Hii',
            'last_week' => 'Wiki Iliyopita',
            'this_month' => 'Mwezi Hii',
            'last_month' => 'Mwezi Uliyopita',
            'created_at' => 'Umeundishwa',
            'updated_at' => 'Usasishwa',
            'due_date' => 'Tarehe ya Kulipa',
            'paid_at' => 'Ilipolishwa',

            // Messages
            'operation_successful' => 'Opereshia imemalizwa kwa mafanikio',
            'operation_failed' => 'Opereshia imeshindikwa',
            'invalid_input' => 'Ingizo lililo sio sahihi',
            'insufficient_permissions' => 'Hakuna ruhusa ya kutosha',
            'session_expired' => 'Kipindi chako kimekucha. Tafadhali ingia tena.',
            'file_upload_success' => 'Faili imepakuliwa kwa mafanikio',
            'file_upload_failed' => 'Imeshindikwa kupakua faili',
            'data_saved' => 'Data imehifadhiwa kwa mafanikio',

            // Business Terms
            'credit_score' => 'Alama ya Mkopo',
            'risk_assessment' => 'Tathmini ya Hatari',
            'interest_earned' => 'Riba Imelipwa',
            'penalty_aded' => 'Onyo Limejitwa',
            'early_repayment' => 'Malipo ya Mapema',
            'loan_extension' => 'Udhibishaji wa Mkopo',

            // Navigation
            'home' => 'Nyumbani',
            'my_account' => 'Akaunti Yangu',
            'my_loans' => 'Mikopo Yangu',
            'apply_now' => 'Omba Sasa',
            'make_payment' => 'Futa Malipo',
            'contact_support' => 'Wasiliana na Msaada',

            // Numbers
            'one' => 'Moja',
            'two' => 'Mbili',
            'three' => 'Tatu',
            'four' => 'Nne',
            'five' => 'Tano',
            'six' => 'Sita',
            'seven' => 'Saba',
            'eight' => 'Nane',
            'nine' => 'Tisa',
            'ten' => 'Kumi',

            // Time
            'minute' => 'Dakika',
            'minutes' => 'Dakika',
            'hour' => 'Saa',
            'hours' => 'Masaa',
            'day' => 'Siku',
            'days' => 'Masiku',
            'week' => 'Wiki',
            'weeks' => 'Wiki',
            'month' => 'Mwezi',
            'months' => 'Miezi',
            'year' => 'Mwaka',
            'years' => 'Miaka',

            // Validation
            'required_field' => 'Uga huu unahitajika',
            'invalid_email' => 'Tafadhali weka barua pepe halali',
            'invalid_phone' => 'Tafadhali weka namba ya simu halali',
            'password_too_short' => 'Nenosiri lazima kuwa na herufi 8',
            'passwords_not_match' => 'Nenosiri hazilingani',
            'amount_too_low' => 'Kiasi ni chache mno',
            'amount_too_high' => 'Kiasi zaidi kikomo',

            // Business Messages
            'loan_approved' => 'Hongera! Ombo lako limeidhinishwa.',
            'loan_rejected' => 'Ombo lako haliwekukataliwa kwa wakati huu.',
            'payment_received' => 'Asante! Malipo lako imepokelewa.',
            'payment_overdue' => 'Malipo lako limechelewa. Tafadhili lipa mara moja kuepuka onyo.',
            'account_verified' => 'Akaunti yako imeidhinishwa kwa mafanikio.',

            // System
            'system_maintenance' => 'Mfumo uko chini ya matengenezo. Tafadhali jaribu baadaye.',
            'feature_unavailable' => 'Huduma hii haipatikani kwa sasa.',
            'access_denied' => 'Ruhusi umekataliwa. Huwezi kufanya kitendo hiki.',

            // Contact
            'phone_number' => 'Namba ya Simu',
            'email_address' => 'Anwani ya Barua pepe',
            'physical_address' => 'Anwani ya Kimwili',
            'postal_code' => 'Namba ya Posta',
            'city' => 'Mji',
            'country' => 'Nchi'
        ];
    }

    /**
     * Load custom translations from database
     */
    private function loadDatabaseTranslations() {
        $dbTranslations = $this->db->fetchAll("
            SELECT language_code, translation_key, translation_value
            FROM translations
            WHERE is_active = 1
        ");

        foreach ($dbTranslations as $translation) {
            if (!isset($this->translations[$translation['language_code']])) {
                $this->translations[$translation['language_code']] = [];
            }
            $this->translations[$translation['language_code']][$translation['translation_key']] = $translation['translation_value'];
        }
    }

    /**
     * Translate text
     */
    public function translate($key, $parameters = [], $language = null) {
        $lang = $language ?: $this->currentLanguage;

        if (!isset($this->translations[$lang][$key])) {
            // Fallback to English if translation not found
            $key = $this->translations['en'][$key] ?? $key;
        } else {
            $key = $this->translations[$lang][$key];
        }

        // Replace parameters
        if (!empty($parameters)) {
            foreach ($parameters as $placeholder => $value) {
                $key = str_replace('{' . $placeholder . '}', $value, $key);
            }
        }

        return $key;
    }

    /**
     * Get current language
     */
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }

    /**
     * Set current language
     */
    public function setLanguage($language) {
        if (isset($this->supportedLanguages[$language])) {
            $this->currentLanguage = $language;
            $_SESSION['user_language'] = $language;

            // Set cookie for persistence
            setcookie('language_preference', $language, time() + (86400 * 30), '/');

            return true;
        }
        return false;
    }

    /**
     * Get supported languages
     */
    public function getSupportedLanguages() {
        return $this->supportedLanguages;
    }

    /**
     * Format date/time according to locale
     */
    public function formatDate($date, $format = 'full') {
        $lang = $this->currentLanguage;
        $timestamp = is_numeric($date) ? $date : strtotime($date);

        switch ($format) {
            case 'short':
                if ($lang === 'sw') {
                    return date('d/m/Y', $timestamp);
                }
                return date('M j, Y', $timestamp);

            case 'long':
                if ($lang === 'sw') {
                    $days = ['Jumapili', 'Jumatatu', 'Jumamosi', 'Jumapili', 'Alhamisi', 'Ijumaa', 'Jumamosi'];
                    $months = ['Januari', 'Februari', 'Machi', 'Aprili', 'Mei', 'Juni', 'Julai', 'Agosti', 'Septemba', 'Oktoba', 'Novemba', 'Desemba'];
                    return $days[date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' ' . $months[date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
                }
                return date('l, F j, Y', $timestamp);

            case 'time':
                if ($lang === 'sw') {
                    return date('H:i', $timestamp);
                }
                return date('g:i A', $timestamp);

            default:
                if ($lang === 'sw') {
                    return date('d/m/Y H:i', $timestamp);
                }
                return date('Y-m-d H:i:s', $timestamp);
        }
    }

    /**
     * Format currency according to locale
     */
    public function formatCurrency($amount, $currency = null) {
        $currency = $currency ?: 'KES';
        $lang = $this->currentLanguage;

        $formattedAmount = number_format($amount, 2, '.', ',');

        if ($lang === 'sw') {
            return $currency . ' ' . $formattedAmount;
        }

        return $formattedAmount . ' ' . $currency;
    }

    /**
     * Format number according to locale
     */
    public function formatNumber($number, $decimals = 0) {
        return number_format($number, $decimals, '.', ',');
    }

    /**
     * Get localized direction (RTL/LTR)
     */
    public function getTextDirection() {
        return $this->supportedLanguages[$this->currentLanguage]['rtl'] ? 'rtl' : 'ltr';
    }

    /**
     * Check if current language is RTL
     */
    public function isRTL() {
        return $this->supportedLanguages[$this->currentLanguage]['rtl'];
    }

    /**
     * Get language flag emoji
     */
    public function getLanguageFlag($language = null) {
        $lang = $language ?: $this->currentLanguage;
        return $this->supportedLanguages[$lang]['flag'] ?? '🌐';
    }

    /**
     * Save translation to database
     */
    public function saveTranslation($languageCode, $key, $value, $description = '') {
        return $this->db->execute("
            INSERT INTO translations (language_code, translation_key, translation_value, description, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            translation_value = VALUES(translation_value), description = VALUES(description), is_active = 1, updated_at = NOW()
        ", [$languageCode, $key, $value, $description]);
    }

    /**
     * Get localized validation messages
     */
    public function getValidationMessages() {
        $lang = $this->currentLanguage;

        $messages = [
            'en' => [
                'required' => 'This field is required',
                'email' => 'Please enter a valid email address',
                'minlength' => 'Minimum {min} characters required',
                'maxlength' => 'Maximum {max} characters allowed',
                'numeric' => 'Please enter a valid number',
                'positive' => 'Please enter a positive number',
                'phone' => 'Please enter a valid phone number',
                'date' => 'Please enter a valid date',
                'unique' => 'This value already exists',
                'confirmed' => 'Confirmation does not match'
            ],
            'sw' => [
                'required' => 'Uga huu unahitajika',
                'email' => 'Tafadhali weka barua pepe halali',
                'minlength' => 'Unahitaji herufi {min} chini',
                'maxlength' => 'Ruhusiwa herufi {max} juu',
                'numeric' => 'Tafadhali weka namba halali',
                'positive' => 'Tafadhili weka namba chanya',
                'phone' => 'Tafadhili weka namba ya simu halali',
                'date' => 'Tafadhili weka tarehe halali',
                'unique' => 'Thamani hii tayari ipo',
                'confirmed' => 'Thibitisho hailingani'
            ]
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Translate validation message with parameters
     */
    public function translateValidationMessage($rule, $parameters = []) {
        $messages = $this->getValidationMessages();
        $message = $messages[$rule] ?? $messages['required'];

        foreach ($parameters as $placeholder => $value) {
            $message = str_replace('{' . $placeholder . '}', $value, $message);
        }

        return $message;
    }

    /**
     * Get localized error messages
     */
    public function getErrorMessages() {
        $lang = $this->currentLanguage;

        $messages = [
            'en' => [
                'generic_error' => 'An error occurred. Please try again.',
                'network_error' => 'Network error. Please check your connection.',
                'server_error' => 'Server error. Please try again later.',
                'unauthorized' => 'You are not authorized to perform this action.',
                'forbidden' => 'Access denied. You do not have permission to perform this action.',
                'not_found' => 'The requested resource was not found.',
                'validation_failed' => 'Validation failed. Please check your input.',
                'file_upload_error' => 'Failed to upload file. Please try again.',
                'session_expired' => 'Your session has expired. Please login again.',
                'rate_limit_exceeded' => 'Too many requests. Please try again later.',
                'maintenance_mode' => 'System is under maintenance. Please try again later.'
            ],
            'sw' => [
                'generic_error' => 'Hitilafu imetokea. Tafadhali jaribu tena.',
                'network_error' => 'Hitilafu ya mtandao. Tafadhili angalia muunganisho wako.',
                'server_error' => 'Hitilafu ya seva. Tafadhali jaribu tena baadaye.',
                'unauthorized' => 'Hauruhusiwa kufanya kitendo hiki.',
                'forbidden' => 'Ruhusi umekataliwa. Huwezi kufanya kitendo hiki.',
                'not_found' => 'Rasilisi uliotafutuliwa.',
                'validation_failed' => 'Uthibitisho umeshindikwa. Tafadhili angalia ingizo lako.',
                'file_upload_error' => 'Imeshindikwa kupakua faili. Tafadhili jaribu tena.',
                'session_expired' => 'Kipindi chako kimekucha. Tafadhali ingia tena.',
                'rate_limit_exceeded' => 'ombiombigi nyingi. Tafadhali jaribu tena baadaye.',
                'maintenance_mode' => 'Mfumo uko chini ya matengenezo. Tafadhali jaribu tena baadaye.'
            ]
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Get localized success messages
     */
    public function getSuccessMessages() {
        $lang = $this->currentLanguage;

        $messages = [
            'en' => [
                'generic_success' => 'Operation completed successfully.',
                'save_success' => 'Data saved successfully.',
                'delete_success' => 'Item deleted successfully.',
                'upload_success' => 'File uploaded successfully.',
                'login_success' => 'Login successful.',
                'register_success' => 'Registration successful.',
                'payment_success' => 'Payment completed successfully.',
                'update_success' => 'Update successful.',
                'create_success' => 'Created successfully.',
                'send_success' => 'Sent successfully.',
                'approved_success' => 'Approved successfully.'
            ],
            'sw' => [
                'generic_success' => 'Opereshia imemalizwa kwa mafanikio.',
                'save_success' => 'Data imehifadhiwa kwa mafanikio.',
                'delete_success' => 'Kituimefutwa kwa mafanikio.',
                'upload_success' => 'Faili imepakuliwa kwa mafanikio.',
                'login_success' => 'Umeingia kwa mafanikio.',
                'register_success' => 'Usajili umemalizwa kwa mafanikio.',
                'payment_success' => 'Malipo imemalizwa kwa mafanikio.',
                'update_success' => 'Usasishaji umemalizwa kwa mafanikio.',
                'create_success' => 'Imeundishwa kwa mafanikio.',
                'send_success' => 'Imetumwa kwa mafanikio.',
                'approved_success' => 'Imeidhinishwa kwa mafanikio.'
            ]
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Generate localized HTML language selector
     */
    public function generateLanguageSelector() {
        $currentLang = $this->currentLanguage;
        $html = '<div class="language-selector">';
        $html .= '<button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">';
        $html .= $this->getLanguageFlag($currentLang) . ' ' . $this->supportedLanguages[$currentLang]['native_name'];
        $html .= '</button>';
        $html .= '<ul class="dropdown-menu">';

        foreach ($this->supportedLanguages as $code => $lang) {
            $active = $code === $currentLang ? 'active' : '';
            $html .= '<li>';
            $html .= '<a class="dropdown-item ' . $active . '" href="#" onclick="changeLanguage(\'' . $code . '\')">';
            $html .= $lang['flag'] . ' ' . $lang['native_name'];
            $html .= '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate JavaScript for language switching
     */
    public function generateLanguageSwitcherJS() {
        return "
        <script>
        function changeLanguage(language) {
            fetch('/api/localization/switch-language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ language: language })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to change language');
                }
            })
            .catch(error => {
                console.error('Error changing language:', error);
                alert('Error changing language');
            });
        }
        </script>";
    }
}

/**
 * Global translation function
 */
function t($key, $parameters = [], $language = null) {
    global $localizationManager;
    if (!$localizationManager) {
        $localizationManager = new LocalizationManager(Database::getInstance());
    }
    return $localizationManager->translate($key, $parameters, $language);
}

/**
 * Currency formatting function
 */
function format_currency($amount, $currency = null) {
    global $localizationManager;
    if (!$localizationManager) {
        $localizationManager = new LocalizationManager(Database::getInstance());
    }
    return $localizationManager->formatCurrency($amount, $currency);
}

/**
 * Date formatting function
 */
function format_date($date, $format = 'full') {
    global $localizationManager;
    if (!$localizationManager) {
        $localizationManager = new LocalizationManager(Database::getInstance());
    }
    return $localizationManager->formatDate($date, $format);
}
?>