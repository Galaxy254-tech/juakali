<?php
session_start();
require_once '../config/config.php';
require_once '../includes/language-manager.php';

$language_manager = new LanguageManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $language = $_POST['language'] ?? 'en';
    $language_manager->loadLanguage($language);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'language' => $language]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get-languages') {
        header('Content-Type: application/json');
        echo json_encode($language_manager->getSupportedLanguages());
        exit;
    }
}
?>
