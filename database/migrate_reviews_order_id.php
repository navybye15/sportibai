<?php
// database/migrate_reviews_order_id.php
// Migrates reviews table to associate each review with an order_id

require_once __DIR__ . '/../config/db_config.php';
$host = $cfg['host'] ?? 'localhost';
$user = $cfg['user'] ?? 'root';
$pass = $cfg['pass'] ?? '';
$name = $cfg['name'] ?? 'sportibai';

$conn = mysqli_connect($host, $user, $pass, $name);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

echo "Starting migration...\n";

// 1. Add order_id column if not exists
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM reviews LIKE 'order_id'");
if (mysqli_num_rows($col_check) === 0) {
    echo "Adding order_id column to reviews...\n";
    mysqli_query($conn, "ALTER TABLE reviews ADD COLUMN order_id INT(11) NULL AFTER review_id");
} else {
    echo "order_id column already exists.\n";
}

// 2. Backfill existing reviews with their corresponding completed orders
echo "Backfilling existing reviews...\n";
$backfill_map = array(
    1 => 8,   // Review 1 was Nike Stride T-shirt by User 1 (Order 8 on 2026-10-12)
    5 => 10,  // Review 5 by User 3 (Order 10)
    6 => 12,  // Review 6 by User 9 (Order 12)
    7 => 11,  // Review 7 was Nike Aeroswift Dri-fit by User 1 (Order 11 on 2026-10-17)
);

foreach ($backfill_map as $rev_id => $ord_id) {
    mysqli_execute_query($conn, "UPDATE reviews SET order_id = ? WHERE review_id = ? AND (order_id IS NULL OR order_id = 0)", array($ord_id, $rev_id));
}

// Also auto-backfill any remaining reviews without order_id to the earliest completed order matching user & item
$unmapped = mysqli_query($conn, "SELECT review_id, item_id, user_id FROM reviews WHERE order_id IS NULL OR order_id = 0");
while ($r = mysqli_fetch_assoc($unmapped)) {
    $f_ord = mysqli_execute_query($conn, "
        SELECT o.order_id 
        FROM orders o
        JOIN customer c ON o.customer_id = c.customer_id
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE c.user_id = ? AND oi.item_id = ? AND o.status = 'Completed'
        ORDER BY o.order_id ASC
        LIMIT 1
    ", array($r['user_id'], $r['item_id']));
    if ($ord_row = mysqli_fetch_assoc($f_ord)) {
        mysqli_execute_query($conn, "UPDATE reviews SET order_id = ? WHERE review_id = ?", array($ord_row['order_id'], $r['review_id']));
        echo "Auto-mapped review #{$r['review_id']} to order #{$ord_row['order_id']}.\n";
    }
}

// 3. Ensure item_id has an index before dropping uq_item_user_review (since FK needs an index)
$item_idx_check = mysqli_query($conn, "SHOW INDEX FROM reviews WHERE Key_name = 'idx_item_id' OR (Key_name != 'uq_item_user_review' AND Column_name = 'item_id' AND Seq_in_index = 1)");
if (mysqli_num_rows($item_idx_check) === 0) {
    echo "Adding index on item_id for foreign key...\n";
    mysqli_query($conn, "ALTER TABLE reviews ADD KEY idx_item_id (item_id)");
}

$idx_check = mysqli_query($conn, "SHOW INDEX FROM reviews WHERE Key_name = 'uq_item_user_review'");
if (mysqli_num_rows($idx_check) > 0) {
    echo "Dropping old index uq_item_user_review...\n";
    mysqli_query($conn, "ALTER TABLE reviews DROP INDEX uq_item_user_review");
}

// 4. Add index on order_id and unique key on (order_id, item_id)
$new_idx_check = mysqli_query($conn, "SHOW INDEX FROM reviews WHERE Key_name = 'uq_order_item_review'");
if (mysqli_num_rows($new_idx_check) === 0) {
    echo "Adding unique index uq_order_item_review (order_id, item_id)...\n";
    mysqli_query($conn, "ALTER TABLE reviews ADD UNIQUE KEY uq_order_item_review (order_id, item_id)");
}

$fk_check = mysqli_query($conn, "
    SELECT CONSTRAINT_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews' AND CONSTRAINT_NAME = 'reviews_ibfk_3'
");
if (mysqli_num_rows($fk_check) === 0) {
    echo "Adding foreign key constraint for order_id...\n";
    mysqli_query($conn, "ALTER TABLE reviews ADD CONSTRAINT reviews_ibfk_3 FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE");
}

// 5. Update view vw_item_reviews to include order_id
echo "Updating view vw_item_reviews...\n";
mysqli_query($conn, "DROP VIEW IF EXISTS vw_item_reviews");
mysqli_query($conn, "
    CREATE VIEW vw_item_reviews AS 
    SELECT r.review_id AS review_id,
           r.order_id AS order_id,
           r.item_id AS item_id,
           r.user_id AS user_id,
           r.rating AS rating,
           r.comment AS comment,
           r.review_image AS review_image,
           r.created_at AS created_at,
           r.updated_at AS updated_at,
           u.email AS user_email,
           u.profile_photo AS user_photo,
           COALESCE(NULLIF(TRIM(c.first_name),''), 'Athlete') AS reviewer_name
    FROM reviews r
    JOIN users u ON r.user_id = u.user_id
    LEFT JOIN customer c ON u.user_id = c.user_id
");

echo "Migration completed successfully!\n";
