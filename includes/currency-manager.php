<?php
/**
 * Currency Manager
 * Manages multi-currency support with real-time exchange rates
 */
class CurrencyManager {
    private $db;
    private $supported_currencies = [
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KES'],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
        'UGX' => ['name' => 'Ugandan Shilling', 'symbol' => 'UGX'],
        'TZS' => ['name' => 'Tanzanian Shilling', 'symbol' => 'TZS'],
    ];
    private $default_currency = 'KES';
    private $base_currency = 'KES';
    
    public function __construct($database) {
        $this->db = $database;
        $this->loadUserCurrency();
    }
    
    /**
     * Load user's preferred currency
     */
    private function loadUserCurrency() {
        if (isset($_SESSION['user_id'])) {
            $this->db->query('SELECT currency FROM users WHERE id = ?');
            $this->db->bind('i', $_SESSION['user_id']);
            $user = $this->db->single();
            
            if ($user && isset($user['currency'])) {
                $_SESSION['currency'] = $user['currency'];
            }
        }
    }
    
    /**
     * Set user currency
     * @param string $currency Currency code
     */
    public function setUserCurrency($currency) {
        if (!in_array($currency, array_keys($this->supported_currencies))) {
            return false;
        }
        
        $_SESSION['currency'] = $currency;
        
        // Update user preference
        if (isset($_SESSION['user_id'])) {
            $this->db->query('UPDATE users SET currency = ? WHERE id = ?');
            $this->db->bind('s', $currency);
            $this->db->bind('i', $_SESSION['user_id']);
            $this->db->execute();
        }
        
        return true;
    }
    
    /**
     * Get current user currency
     * @return string Currency code
     */
    public function getUserCurrency() {
        return $_SESSION['currency'] ?? $this->default_currency;
    }
    
    /**
     * Get exchange rate between two currencies
     * @param string $from_currency From currency
     * @param string $to_currency To currency
     * @return float Exchange rate
     */
    public function getExchangeRate($from_currency, $to_currency) {
        if ($from_currency === $to_currency) {
            return 1.0;
        }
        
        // Check database for cached rates
        $this->db->query('SELECT rate FROM exchange_rates 
                         WHERE from_currency = ? AND to_currency = ? 
                         AND DATE(updated_at) = CURDATE()');
        $this->db->bind('s', $from_currency);
        $this->db->bind('s', $to_currency);
        $cached_rate = $this->db->single();
        
        if ($cached_rate) {
            return floatval($cached_rate['rate']);
        }
        
        // Fetch rate from external API (e.g., Open Exchange Rates)
        $rate = $this->fetchExchangeRate($from_currency, $to_currency);
        
        // Cache the rate
        if ($rate) {
            $this->db->query('INSERT INTO exchange_rates (from_currency, to_currency, rate, updated_at)
                             VALUES (?, ?, ?, NOW())
                             ON DUPLICATE KEY UPDATE rate = ?, updated_at = NOW()');
            $this->db->bind('s', $from_currency);
            $this->db->bind('s', $to_currency);
            $this->db->bind('d', $rate);
            $this->db->bind('d', $rate);
            $this->db->execute();
        }
        
        return $rate ?? 1.0;
    }
    
    /**
     * Fetch exchange rate from external API
     * @param string $from_currency From currency
     * @param string $to_currency To currency
     * @return float|null Exchange rate
     */
    private function fetchExchangeRate($from_currency, $to_currency) {
        // Using Open Exchange Rates API
        $api_key = getenv('EXCHANGE_RATE_API_KEY');
        if (!$api_key) {
            return null;
        }
        
        $url = "https://openexchangerates.org/api/latest.json?app_id=$api_key&base=$from_currency&symbols=$to_currency";
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        
        if (isset($response['rates'][$to_currency])) {
            return floatval($response['rates'][$to_currency]);
        }
        
        return null;
    }
    
    /**
     * Convert amount between currencies
     * @param float $amount Amount to convert
     * @param string $from_currency From currency
     * @param string $to_currency To currency
     * @return float Converted amount
     */
    public function convertAmount($amount, $from_currency, $to_currency) {
        $rate = $this->getExchangeRate($from_currency, $to_currency);
        return round($amount * $rate, 2);
    }
    
    /**
     * Format currency value
     * @param float $amount Amount
     * @param string $currency Currency code
     * @return string Formatted currency string
     */
    public function formatCurrency($amount, $currency = null) {
        $currency = $currency ?? $this->getUserCurrency();
        
        if (!isset($this->supported_currencies[$currency])) {
            $currency = $this->default_currency;
        }
        
        $symbol = $this->supported_currencies[$currency]['symbol'];
        return $symbol . ' ' . number_format($amount, 2);
    }
    
    /**
     * Get all supported currencies
     * @return array
     */
    public function getSupportedCurrencies() {
        return $this->supported_currencies;
    }
    
    /**
     * Get currency details
     * @param string $currency Currency code
     * @return array|null Currency details
     */
    public function getCurrencyDetails($currency) {
        return $this->supported_currencies[$currency] ?? null;
    }
}
?>
