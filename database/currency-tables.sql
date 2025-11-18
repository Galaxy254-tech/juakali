-- Exchange Rates table
CREATE TABLE IF NOT EXISTS exchange_rates (
  id INT PRIMARY KEY AUTO_INCREMENT,
  from_currency VARCHAR(3) NOT NULL,
  to_currency VARCHAR(3) NOT NULL,
  rate DECIMAL(18, 6) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_pair (from_currency, to_currency),
  INDEX idx_currencies (from_currency, to_currency),
  INDEX idx_updated (updated_at)
);

-- Ledger table for tracking all financial transactions in base currency
CREATE TABLE IF NOT EXISTS ledger (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  transaction_type ENUM('order', 'loan', 'payment', 'refund', 'penalty') NOT NULL,
  reference_id INT,
  amount_original DECIMAL(15, 2) NOT NULL,
  original_currency VARCHAR(3) NOT NULL,
  amount_kes DECIMAL(15, 2) NOT NULL,
  exchange_rate DECIMAL(18, 6) NOT NULL,
  balance DECIMAL(15, 2),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_type (transaction_type),
  INDEX idx_created (created_at)
);

-- User Currency Preferences
ALTER TABLE users ADD COLUMN currency VARCHAR(3) DEFAULT 'KES' AFTER loyalty_points;
