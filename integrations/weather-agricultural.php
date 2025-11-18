<?php
/**
 * Weather & Agricultural Data Integration
 * Weather-dependent loan terms and crop predictions
 */

class WeatherAgriculturalData {
    private $db;
    private $weather_api_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->weather_api_key = getenv('WEATHER_API_KEY');
    }
    
    /**
     * Get weather forecast for agricultural lending
     */
    public function getWeatherForecast($latitude, $longitude, $days = 30) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.openweathermap.org/data/2.5/forecast?" . http_build_query([
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'appid' => $this->weather_api_key,
                    'units' => 'metric'
                ]),
                CURLOPT_RETURNTRANSFER => true,
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            $forecast = json_decode($response, true);
            return $forecast;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Adjust loan terms based on weather
     */
    public function adjustLoanTermsForWeather($loan_id, $latitude, $longitude) {
        $forecast = $this->getWeatherForecast($latitude, $longitude);
        
        $base_interest_rate = 15; // Base rate
        $adjusted_rate = $base_interest_rate;
        
        // Check for adverse weather
        if (isset($forecast['list'])) {
            foreach ($forecast['list'] as $weather) {
                if (in_array($weather['weather'][0]['main'], ['Rain', 'Snow', 'Storm'])) {
                    $adjusted_rate += 2; // Increase rate for risky weather
                }
            }
        }
        
        // Update loan with adjusted rate
        $this->db->query("UPDATE loans SET interest_rate = ? WHERE id = ?");
        $this->db->bind(':rate', $adjusted_rate);
        $this->db->bind(':id', $loan_id);
        $this->db->execute();
        
        return ['adjusted_rate' => $adjusted_rate, 'forecast' => $forecast];
    }
}
?>
