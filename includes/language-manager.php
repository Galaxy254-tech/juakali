<?php
/**
 * Language Manager
 * Manages multi-language support with translations
 */
class LanguageManager {
    private $supported_languages = ['en' => 'English', 'sw' => 'Swahili', 'fr' => 'French'];
    private $current_language = 'en';
    private $translations = [];
    
    public function __construct() {
        $this->loadLanguage($_SESSION['language'] ?? 'en');
    }
    
    /**
     * Load language translations
     * @param string $language Language code
     */
    public function loadLanguage($language) {
        if (!in_array($language, array_keys($this->supported_languages))) {
            $language = 'en';
        }
        
        $this->current_language = $language;
        $_SESSION['language'] = $language;
        
        // Load translations from file
        $file = __DIR__ . "/../languages/{$language}.php";
        if (file_exists($file)) {
            $this->translations = require_once $file;
        }
    }
    
    /**
     * Get translation string
     * @param string $key Translation key
     * @param array $params Parameters for interpolation
     * @return string Translated string
     */
    public function translate($key, $params = []) {
        $text = $this->translations[$key] ?? $key;
        
        // Replace parameters
        foreach ($params as $param_key => $value) {
            $text = str_replace("{{$param_key}}", $value, $text);
        }
        
        return $text;
    }
    
    /**
     * Shorthand translation function
     */
    public function t($key, $params = []) {
        return $this->translate($key, $params);
    }
    
    /**
     * Get all supported languages
     * @return array
     */
    public function getSupportedLanguages() {
        return $this->supported_languages;
    }
    
    /**
     * Get current language
     * @return string
     */
    public function getCurrentLanguage() {
        return $this->current_language;
    }
}
?>
