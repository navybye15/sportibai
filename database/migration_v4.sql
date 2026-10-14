-- =====================================================
-- Sportibai DB Migration v4
-- Run this in phpMyAdmin on the `sportibai` database.
-- SAFE: does NOT drop or recreate existing tables.
-- =====================================================

-- 1. Add cost_price to items table for true profit calculations
ALTER TABLE items 
  ADD COLUMN cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price;

-- 2. Create stock_log table for manual adjustment audit trail
CREATE TABLE IF NOT EXISTS stock_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    change_qty INT NOT NULL,
    new_stock INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE
);
