<?php
function get_all_items($conn) {
    if ($conn == null) {
        include __DIR__ . '/sample_data.php';
        return $sample_items;
    }
    $items = array();
    $sql = "SELECT i.item_id, i.name, i.brand, i.description, i.price, i.stock, i.image, c.name AS category
            FROM items i JOIN categories c ON i.category_id = c.category_id
            WHERE i.is_active = 1 AND c.is_active = 1";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    return $items;
}

function find_item($conn, $id) {
    // No database yet: look in the sample items instead
    if ($conn == null) {
        $all = get_all_items($conn);
        foreach ($all as $item) {
            if ($item['item_id'] == $id) {
                return $item;
            }
        }
        return null;
    }
    // Ask the database for just this one item (prepared statement)
    $sql = "SELECT i.item_id, i.name, i.brand, i.description, i.price, i.stock, i.image, c.name AS category
            FROM items i JOIN categories c ON i.category_id = c.category_id
            WHERE i.item_id = ? AND i.is_active = 1 AND c.is_active = 1
            LIMIT 1";
    $result = mysqli_execute_query($conn, $sql, array($id));
    $item = mysqli_fetch_assoc($result);
    return $item;
}

function peso($n) {
    return '₱' . number_format($n, 2);
}

function tile_hue($id) {
    return ($id * 47) % 360;
}

function handle_item_image($file) {
    // No file chosen is fine, just means "keep using no image / existing image"
    if ($file['error'] == UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] != UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed.');
    }

    // 1. Limit file size (2 MB)
    $max_size = 2 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception('Image is too large. Max size is 2MB.');
    }

    // 2. Only allow a small whitelist of extensions
    $allowed_ext = array('jpg', 'jpeg', 'png', 'webp');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        throw new Exception('Only JPG, PNG, or WEBP images are allowed.');
    }

    // 3. Check the REAL file content, not just the filename.
    // Someone could rename malware.php to malware.jpg, so we don't trust
    // the extension alone, finfo looks at the actual file bytes.
    $allowed_mime = array('image/jpeg', 'image/png', 'image/webp');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mime)) {
        throw new Exception('That file is not a valid image.');
    }

    // 4. Never use the uploader's original filename.
    // A new, random name prevents overwriting other files and prevents
    // tricks like "../../something.php".
    $new_name = uniqid('item_', true) . '.' . $ext;
    $destination = __DIR__ . '/../images/uploads/' . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Could not save the uploaded image.');
    }

    return $new_name;
}

// ── Handle User Profile Photo Upload (MP2) ──────────────────────────────────
function handle_user_photo($file) {
    if (!isset($file) || $file['error'] == UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] != UPLOAD_ERR_OK) {
        throw new Exception('Profile photo upload failed.');
    }

    // 1. Limit file size (2 MB)
    $max_size = 2 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception('Profile photo is too large. Max size is 2MB.');
    }

    // 2. Allowed extensions
    $allowed_ext = array('jpg', 'jpeg', 'png', 'webp');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        throw new Exception('Only JPG, PNG, or WEBP photos are allowed.');
    }

    // 3. Check actual MIME type
    $allowed_mime = array('image/jpeg', 'image/png', 'image/webp');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mime)) {
        throw new Exception('Uploaded file is not a valid image.');
    }

    $new_name = uniqid('user_', true) . '.' . $ext;
    $destination = __DIR__ . '/../images/uploads/' . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Could not save uploaded profile photo.');
    }

    return $new_name;
}

// ── Parse Review Images (Supports both single image string and JSON array) ────
function parse_review_images($raw) {
    if (empty($raw)) {
        return array();
    }
    if (is_array($raw)) {
        return array_values(array_filter($raw));
    }
    $trimmed = trim($raw);
    if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded));
        }
    }
    return array($trimmed);
}

// ── Handle Customer Review Image Upload (MP4 single fallback) ───────────────
function handle_review_image($file) {
    if (!isset($file) || $file['error'] == UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] != UPLOAD_ERR_OK) {
        throw new Exception('Review image upload failed.');
    }

    $max_size = 3 * 1024 * 1024; // 3 MB
    if ($file['size'] > $max_size) {
        throw new Exception('Review photo is too large. Max size is 3MB.');
    }

    $allowed_ext = array('jpg', 'jpeg', 'png', 'webp');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        throw new Exception('Only JPG, PNG, or WEBP photos are allowed for reviews.');
    }

    $allowed_mime = array('image/jpeg', 'image/png', 'image/webp');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mime)) {
        throw new Exception('Uploaded file is not a valid image.');
    }

    $new_name = uniqid('review_', true) . '.' . $ext;
    $destination = __DIR__ . '/../images/uploads/' . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Could not save uploaded review photo.');
    }

    return $new_name;
}

// ── Handle Multiple Customer Review Image Uploads (Up to 5 photos) ──────────
function handle_multiple_review_images($files, $max_files = 5) {
    if (!isset($files) || !isset($files['name'])) {
        return '';
    }

    // Convert single-file upload format to array format if needed
    if (!is_array($files['name'])) {
        if ($files['error'] == UPLOAD_ERR_NO_FILE) {
            return '';
        }
        $single_img = handle_review_image($files);
        return $single_img !== '' ? json_encode(array($single_img)) : '';
    }

    $uploaded_images = array();
    $allowed_ext = array('jpg', 'jpeg', 'png', 'webp');
    $allowed_mime = array('image/jpeg', 'image/png', 'image/webp');
    $max_size = 3 * 1024 * 1024; // 3 MB per image

    $count = count($files['name']);
    $valid_files = 0;

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] == UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $valid_files++;
    }

    if ($valid_files > $max_files) {
        throw new Exception("You can upload a maximum of {$max_files} photos for your review.");
    }

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] == UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($files['error'][$i] != UPLOAD_ERR_OK) {
            throw new Exception("Upload failed for photo: " . htmlspecialchars($files['name'][$i]));
        }
        if ($files['size'][$i] > $max_size) {
            throw new Exception("Photo " . htmlspecialchars($files['name'][$i]) . " is too large. Max size is 3MB per photo.");
        }

        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            throw new Exception("Photo " . htmlspecialchars($files['name'][$i]) . ": Only JPG, PNG, or WEBP photos are allowed.");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $files['tmp_name'][$i]);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_mime)) {
            throw new Exception("Photo " . htmlspecialchars($files['name'][$i]) . " is not a valid image.");
        }

        $new_name = uniqid('review_', true) . '.' . $ext;
        $destination = __DIR__ . '/../images/uploads/' . $new_name;

        if (!move_uploaded_file($files['tmp_name'][$i], $destination)) {
            throw new Exception("Could not save uploaded photo: " . htmlspecialchars($files['name'][$i]));
        }

        $uploaded_images[] = $new_name;
    }

    if (empty($uploaded_images)) {
        return '';
    }

    return json_encode($uploaded_images);
}

// ── Handle Multiple Item Images (MP1) ────────────────────────────────────────
function handle_multiple_item_images($files, $item_id, $conn) {
    if (!isset($files['name']) || !is_array($files['name'])) {
        return 0;
    }

    $uploaded_count = 0;
    $count = count($files['name']);
    $allowed_ext = array('jpg', 'jpeg', 'png', 'webp');
    $allowed_mime = array('image/jpeg', 'image/png', 'image/webp');
    $max_size = 3 * 1024 * 1024; // 3MB

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] == UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($files['error'][$i] != UPLOAD_ERR_OK) {
            continue;
        }
        if ($files['size'][$i] > $max_size) {
            continue;
        }

        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            continue;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $files['tmp_name'][$i]);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_mime)) {
            continue;
        }

        $new_name = uniqid('item_gallery_', true) . '.' . $ext;
        $destination = __DIR__ . '/../images/uploads/' . $new_name;

        if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
            $sql = "INSERT INTO item_images (item_id, image_path, sort_order) VALUES (?, ?, ?)";
            mysqli_execute_query($conn, $sql, array($item_id, $new_name, $uploaded_count));
            $uploaded_count++;
        }
    }
    return $uploaded_count;
}

// ── Mask Foul Words via Regex (MP4) ─────────────────────────────────────────
function filter_bad_words($text) {
    $bad_words = array('puta', 'gago', 'tangina', 'tarantado', 'bobo', 'inutil', 'ulol', 'pakshet', 'fuck', 'shit', 'damn', 'ass', 'bitch', 'cunt', 'dick', 'pussy', 'bastard');
    $pattern = '/\b(' . implode('|', array_map('preg_quote', $bad_words)) . ')\b/i';
    return preg_replace_callback($pattern, function($m) {
        return str_repeat('*', strlen($m[0]));
    }, $text ?? '');
}

// Generate a human-readable reference number from an order_id
// Called AFTER INSERT to get the new order_id, then UPDATE.
function generate_ref($order_id) {
    return 'SB-' . str_pad($order_id, 7, '0', STR_PAD_LEFT);
}

// Return all status log entries for a given order, oldest first
function get_status_log($conn, $order_id) {
    $log = array();
    if ($conn == null) return $log;
    $sql = "SELECT status, changed_at, changed_by FROM order_status_log
            WHERE order_id = ? ORDER BY log_id ASC";
    $result = mysqli_execute_query($conn, $sql, array($order_id));
    while ($row = mysqli_fetch_assoc($result)) {
        $log[] = $row;
    }
    return $log;
}

// Map a status string to which customer tab it belongs to
function status_to_tab($status) {
    $topay    = array('Pending', 'Prepared', 'Ready to Ship');
    $toreceive = array('Ready for Pick Up', 'Shipped', 'Out for Delivery', 'Delivered');
    if (in_array($status, $topay))     return 'topay';
    if (in_array($status, $toreceive)) return 'toreceive';
    if ($status === 'Completed')       return 'completed';
    if ($status === 'Cancelled')       return 'cancelled';
    return 'all';
}

// Return inline style string for a status badge (color-coded)
function status_badge_style($status) {
    switch ($status) {
        case 'Pending':
            return 'background:rgba(200,150,0,0.15);color:#f0c040;border-color:rgba(200,150,0,0.3);';
        case 'Prepared':
            return 'background:rgba(100,170,255,0.12);color:#6f9be0;border-color:rgba(111,155,224,0.3);';
        case 'Ready to Ship':
        case 'Ready for Pick Up':
            return 'background:rgba(80,160,255,0.15);color:#7ab5ff;border-color:rgba(80,160,255,0.35);';
        case 'Shipped':
        case 'Out for Delivery':
            return 'background:rgba(140,100,255,0.15);color:#b388ff;border-color:rgba(140,100,255,0.3);';
        case 'Delivered':
            return 'background:rgba(50,200,120,0.15);color:#4dd898;border-color:rgba(50,200,120,0.3);';
        case 'Completed':
            return 'background:rgba(81,175,67,0.18);color:#51af43;border-color:rgba(81,175,67,0.35);';
        case 'Cancelled':
            return 'background:rgba(200,60,50,0.15);color:#ff9f96;border-color:rgba(255,100,80,0.3);';
        default:
            return 'background:rgba(111,155,224,0.12);color:#6f9be0;border-color:rgba(111,155,224,0.25);';
    }
}

function item_tile($item, $base) {
    // Show the uploaded picture ONLY if the file really exists in images/uploads.
    // If the name is in the database but the file is missing, we fall back to
    // the gradient placeholder instead of a broken-image icon.
    $has_image = false;
    if (isset($item['image']) && $item['image'] != '') {
        $file_path = __DIR__ . '/../images/uploads/' . basename($item['image']);
        if (file_exists($file_path)) {
            $has_image = true;
        }
    }

    if ($has_image) {
        $src = $base . 'images/uploads/' . basename($item['image']);
        echo '<img class="tile" src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($item['name']) . '">';
    } else {
        $hue = tile_hue($item['item_id']);
        echo '<div class="tile" style="--h: ' . $hue . '">' . htmlspecialchars($item['category']) . '</div>';
    }
}

// ── CSRF Protection Helpers ──────────────────────────────────────────────────
function csrf_token() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['message'] = 'Security validation failed (invalid CSRF token). Please try again.';
        return false;
    }
    return true;
}

// ── Session Lifetime & Active User Check ─────────────────────────────────────
function check_user_session($conn) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) return;

    // Inactivity timeout: 30 minutes
    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['message'] = 'Session timed out due to inactivity. Please log in again.';
        header('Location: ' . (file_exists('login.php') ? 'login.php' : '../login.php'));
        exit;
    }
    $_SESSION['last_activity'] = time();

    // Check user active status from database
    if ($conn != null) {
        $sql = "SELECT status FROM users WHERE user_id = ? LIMIT 1";
        $res = @mysqli_execute_query($conn, $sql, array($_SESSION['user_id']));
        $user = @mysqli_fetch_assoc($res);
        if (!$user || $user['status'] !== 'active') {
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['message'] = 'Your account has been deactivated. Please contact support.';
            header('Location: ' . (file_exists('login.php') ? 'login.php' : '../login.php'));
            exit;
        }
    }
}

// ── Item Size & Variant Management (Apparel & Footwear) ─────────────────────
function get_item_sizes($conn, $item_id) {
    if (!$conn || !(int)$item_id) return array();
    $sql = "SELECT size_id, item_id, size_label, stock FROM item_sizes WHERE item_id = ? ORDER BY size_id ASC";
    $res = mysqli_execute_query($conn, $sql, array((int)$item_id));
    $sizes = array();
    while ($row = mysqli_fetch_assoc($res)) {
        $sizes[] = $row;
    }
    return $sizes;
}

function get_item_size_stock($conn, $item_id, $size_label) {
    if (!$conn) return 0;
    $sql = "SELECT stock FROM item_sizes WHERE item_id = ? AND size_label = ? LIMIT 1";
    $res = mysqli_execute_query($conn, $sql, array((int)$item_id, trim($size_label)));
    if ($row = mysqli_fetch_assoc($res)) {
        return (int)$row['stock'];
    }
    return 0;
}

function category_supports_sizes($category_name) {
    $cat = strtolower(trim($category_name));
    if (in_array($cat, array('apparel', 'apparels', 'clothing', 'shirts', 'jerseys', 'shorts', 'wear'))) {
        return 'apparel';
    }
    if (in_array($cat, array('footwear', 'shoes', 'kicks', 'sneakers'))) {
        return 'footwear';
    }
    return false;
}

function get_default_sizes_for_type($type) {
    if ($type === 'apparel') {
        return array('XS', 'S', 'M', 'L', 'XL', '2XL');
    }
    if ($type === 'footwear') {
        return array('EU 39', 'EU 40', 'EU 41', 'EU 42', 'EU 43', 'EU 44', 'EU 45');
    }
    return array();
}

function sync_item_total_stock($conn, $item_id) {
    if (!$conn || !(int)$item_id) return;
    $chk = mysqli_execute_query($conn, "SELECT COUNT(*) AS cnt, SUM(stock) AS total_stock FROM item_sizes WHERE item_id = ?", array((int)$item_id));
    $row = mysqli_fetch_assoc($chk);
    if ((int)$row['cnt'] > 0) {
        $tot = (int)$row['total_stock'];
        mysqli_execute_query($conn, "UPDATE items SET stock = ? WHERE item_id = ?", array($tot, (int)$item_id));
    }
}

