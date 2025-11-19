<?php
/**
 * Language Switching API Endpoint
 * Handles dynamic language changes for multilingual support
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/localization.php';

// Check if user is authenticated
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required',
        'error_code' => 'AUTHENTICATION_REQUIRED'
    ]);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $languageCode = $input['language'] ?? '';

    if (empty($languageCode)) {
        throw new Exception('Language code is required');
    }

    $db = Database::getInstance();
    $localizationManager = new LocalizationManager($db);
    $userId = $_SESSION['user_id'];

    // Validate language code
    $supportedLanguages = $localizationManager->getSupportedLanguages();
    if (!isset($supportedLanguages[$languageCode])) {
        throw new Exception('Unsupported language code');
    }

    // Update user language preference
    $result = $db->execute("
        UPDATE users
        SET preferred_language = ?, updated_at = NOW()
        WHERE id = ?
    ", [$languageCode, $userId]);

    if (!$result) {
        throw new Exception('Failed to update language preference');
    }

    // Log language change
    $db->execute("
        INSERT INTO user_language_changes (user_id, previous_language, new_language, changed_at, ip_address)
        VALUES (?, ?, ?, NOW(), ?)
    ", [
        $userId,
        $_SESSION['user_language'] ?? 'en',
        $languageCode,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    // Update session
    $_SESSION['user_language'] = $languageCode;

    // Set cookie for persistence
    setcookie('language_preference', $languageCode, time() + (86400 * 30), '/');

    // Get user data with new language preference
    $user = $db->fetchOne("
        SELECT
            id, name, email, phone, role, preferred_language,
            created_at, last_login
        FROM users
        WHERE id = ?
    ", [$userId]);

    // Get localized messages
    $successMessage = $localizationManager->translate('update_success');
    $welcomeMessage = $localizationManager->translate('welcome', ['name' => $user['name']]);

    echo json_encode([
        'success' => true,
        'message' => $successMessage,
        'language' => [
            'code' => $languageCode,
            'name' => $supportedLanguages[$languageCode]['name'],
            'native_name' => $supportedLanguages[$languageCode]['native_name'],
            'flag' => $supportedLanguages[$languageCode]['flag'],
            'rtl' => $supportedLanguages[$languageCode]['rtl']
        ],
        'user' => [
            'preferred_language' => $languageCode,
            'welcome_message' => $welcomeMessage
        ],
        'translations' => [
            'dashboard' => $localizationManager->translate('dashboard'),
            'my_loans' => $localizationManager->translate('my_loans'),
            'profile' => $localizationManager->translate('profile'),
            'settings' => $localizationManager->translate('settings'),
            'logout' => $localizationManager->translate('logout'),
            'loan_amount' => $localizationManager->translate('loan_amount'),
            'make_payment' => $localizationManager->translate('make_payment'),
            'contact_support' => $localizationManager->translate('contact_support')
        ],
        'session_id' => session_id(),
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'LANGUAGE_SWITCH_ERROR'
    ]);
}
?>