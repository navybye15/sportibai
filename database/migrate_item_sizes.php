<?php
require_once __DIR__ . '/../includes/db.php';

// 1. Create item_sizes table
$sql1 = "CREATE TABLE IF NOT EXISTS item_sizes (
    size_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    size_label VARCHAR(30) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_item_size (item_id, size_label),
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE
)";
if (mysqli_query($conn, $sql1)) {
    echo "TABLE item_sizes ready.\n";
} else {
    echo "Error creating item_sizes: " . mysqli_error($conn) . "\n";
}

// 2. Add selected_size to order_items if not present
$chk = mysqli_query($conn, "SHOW COLUMNS FROM order_items LIKE 'selected_size'");
if (mysqli_num_rows($chk) == 0) {
    $alter = mysqli_query($conn, "ALTER TABLE order_items ADD COLUMN selected_size VARCHAR(50) NULL DEFAULT NULL AFTER item_id");
    if ($alter) {
        echo "COLUMN selected_size added to order_items.\n";
    } else {
        echo "Error adding selected_size: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "COLUMN selected_size already exists in order_items.\n";
}

// 3. Update view vw_order_details to include selected_size
$view_sql = "CREATE OR REPLACE VIEW vw_order_details AS
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
    oi.selected_size,
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
JOIN items i ON oi.item_id = i.item_id";
if (mysqli_query($conn, $view_sql)) {
    echo "VIEW vw_order_details updated.\n";
} else {
    echo "Error updating vw_order_details: " . mysqli_error($conn) . "\n";
}

// 4. Seed initial sizes for existing Apparel and Footwear items based on their current stock!
$res = mysqli_query($conn, "SELECT i.item_id, i.name, i.stock, c.name AS category 
                           FROM items i 
                           JOIN categories c ON i.category_id = c.category_id 
                           WHERE c.name IN ('Apparel', 'Footwear')");

while ($item = mysqli_fetch_assoc($res)) {
    $item_id = $item['item_id'];
    $cat = $item['category'];
    $total_stock = (int)$item['stock'];

    // Check if item already has sizes
    $s_chk = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM item_sizes WHERE item_id = $item_id");
    $cnt_row = mysqli_fetch_assoc($s_chk);
    if ((int)$cnt_row['cnt'] > 0) continue;

    if ($cat === 'Apparel') {
        $sizes = array('XS', 'S', 'M', 'L', 'XL', '2XL');
    } else {
        $sizes = array('EU 40', 'EU 41', 'EU 42', 'EU 43', 'EU 44', 'EU 45');
    }

    // Distribute current stock among sizes evenly
    $num_sizes = count($sizes);
    $base_qty = intdiv($total_stock, $num_sizes);
    $remainder = $total_stock % $num_sizes;

    foreach ($sizes as $idx => $s) {
        $qty = $base_qty + ($idx < $remainder ? 1 : 0);
        $stmt = mysqli_prepare($conn, "INSERT INTO item_sizes (item_id, size_label, stock) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isi", $item_id, $s, $qty);
        mysqli_stmt_execute($stmt);
    }
    echo "Seeded sizes for item #$item_id ({$item['name']}): $total_stock pcs distributed.\n";
}
echo "MIGRATION COMPLETE.\n";
