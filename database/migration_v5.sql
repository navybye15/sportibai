-- =====================================================
-- Sportibai DB Migration v5 (ITIM211 Requirements)
-- Safe migration: adds profile_photo, item_images, reviews, and VIEWS
-- =====================================================

-- 1. MP2: Profile photo support in users table
ALTER TABLE users 
  ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) NULL AFTER status;

-- 2. MP1: Multi-photo support for items
CREATE TABLE IF NOT EXISTS item_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE
);

-- 3. MP4: Review system (1-5 rating, foul word filtering in PHP, updateable)
CREATE TABLE IF NOT EXISTS reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT NOT NULL,
    review_image TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_item_user_review (item_id, user_id),
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 4. Unit 1: MySQL VIEW for order transaction details
CREATE OR REPLACE VIEW vw_order_details AS
SELECT 
    o.order_id,
    o.reference_number,
    o.date_ordered,
    o.status AS order_status,
    o.order_type,
    o.payment_method,
    o.total AS grand_total,
    o.customer_id,
    o.snapshot_name AS customer_name,
    c.user_id,
    u.email AS customer_email,
    oi.order_item_id,
    oi.item_id,
    i.name AS item_name,
    i.brand AS item_brand,
    i.image AS item_image,
    oi.quantity,
    oi.price AS unit_price,
    (oi.quantity * oi.price) AS subtotal
FROM orders o
LEFT JOIN customer c ON o.customer_id = c.customer_id
LEFT JOIN users u ON c.user_id = u.user_id
JOIN order_items oi ON o.order_id = oi.order_id
JOIN items i ON oi.item_id = i.item_id;

-- 5. Unit 1: MySQL VIEW for item reviews (Display first name only, not recipient)
CREATE OR REPLACE VIEW vw_item_reviews AS
SELECT 
    r.review_id,
    r.item_id,
    r.user_id,
    r.rating,
    r.comment,
    r.review_image,
    r.created_at,
    r.updated_at,
    u.email AS user_email,
    u.profile_photo AS user_photo,
    COALESCE(NULLIF(TRIM(c.first_name), ''), 'Athlete') AS reviewer_name
FROM reviews r
JOIN users u ON r.user_id = u.user_id
LEFT JOIN customer c ON u.user_id = c.user_id;
