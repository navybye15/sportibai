-- =====================================================
-- Sportibai DB Migration  v2 → v3
-- Run this in phpMyAdmin on the `sportibai` database.
-- SAFE: does NOT drop or recreate any existing table.
-- =====================================================

-- 1. Widen status column to hold longer values
ALTER TABLE orders
  MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Pending';

-- 2. Add reference number column (e.g. SB-0000001)
ALTER TABLE orders
  ADD COLUMN reference_number VARCHAR(20) NULL AFTER order_id;

-- 3. Add customer name snapshot (name at time of order)
ALTER TABLE orders
  ADD COLUMN snapshot_name VARCHAR(150) NULL AFTER contact_number;

-- 4. Add unique index on reference_number (skip if already exists)
ALTER TABLE orders
  ADD UNIQUE KEY uq_ref (reference_number);

-- 5. Expand customer profile with province, postal code, landmark
ALTER TABLE customer
  ADD COLUMN province   VARCHAR(100) NOT NULL DEFAULT '' AFTER address,
  ADD COLUMN postal_code VARCHAR(10) NOT NULL DEFAULT '' AFTER province,
  ADD COLUMN landmark   TEXT         NOT NULL DEFAULT '' AFTER postal_code;

-- 6. Create order status log table (stores every status change with timestamp)
CREATE TABLE IF NOT EXISTS order_status_log (
    log_id     INT AUTO_INCREMENT PRIMARY KEY,
    order_id   INT         NOT NULL,
    status     VARCHAR(30) NOT NULL,
    changed_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by VARCHAR(10) NOT NULL DEFAULT 'system',  -- 'admin', 'customer', or 'system'
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

-- 7. Backfill reference numbers for existing orders
UPDATE orders
  SET reference_number = CONCAT('SB-', LPAD(order_id, 7, '0'))
  WHERE reference_number IS NULL;

-- 8. Backfill status log: every existing order gets a 'Pending' entry
--    using its original date_ordered as the timestamp
INSERT INTO order_status_log (order_id, status, changed_at, changed_by)
SELECT o.order_id, 'Pending', o.date_ordered, 'system'
FROM orders o
WHERE o.order_id NOT IN (
    SELECT DISTINCT order_id FROM order_status_log WHERE status = 'Pending'
);

-- 9. Backfill final status log entry for already-Completed or Cancelled orders
INSERT INTO order_status_log (order_id, status, changed_at, changed_by)
SELECT o.order_id, o.status, IFNULL(o.completed_at, o.date_ordered), 'system'
FROM orders o
WHERE o.status IN ('Completed', 'Cancelled')
  AND o.order_id NOT IN (
      SELECT DISTINCT order_id FROM order_status_log WHERE status IN ('Completed','Cancelled')
  );

-- 10. Add password reset token columns
ALTER TABLE users
  ADD COLUMN reset_token VARCHAR(64) NULL AFTER status,
  ADD COLUMN reset_expires DATETIME NULL AFTER reset_token;

-- Done! You can now run the updated application.
