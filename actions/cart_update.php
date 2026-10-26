<?php
// Guests can use the cart. No login needed here.
session_start();
require_once __DIR__ . '/../includes/customer_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}
$action = '';
if (isset($_POST['action'])) {
    $action = $_POST['action'];
}
$id = 0;
if (isset($_POST['item_id'])) {
    $id = (int) $_POST['item_id'];
}
$size = trim($_POST['size'] ?? '');
$cart_key = isset($_POST['cart_key']) && $_POST['cart_key'] !== '' ? trim($_POST['cart_key']) : '';
if ($cart_key === '') {
    $cart_key = ($size !== '') ? ($id . '_' . $size) : (string)$id;
} else {
    $parts = explode('_', $cart_key, 2);
    $id = (int)$parts[0];
    if (empty($size) && isset($parts[1])) {
        $size = $parts[1];
    }
}

$item = find_item($conn, $id);
$item_sizes = ($item != null) ? get_item_sizes($conn, $id) : array();
$has_sizes = count($item_sizes) > 0;

if ($item != null) {
    // Determine effective stock for item or specific size
    $max_stock = (int)$item['stock'];
    if ($has_sizes) {
        if ($size === '' && $action == 'add') {
            $_SESSION['message'] = 'Please select a size for "' . $item['name'] . '" before adding to cart.';
            $redirect_url = !empty($_POST['return_url']) ? $_POST['return_url'] : ('item/detail.php?id=' . $id);
            if (!preg_match('#^(https?://|/|\.\./)#', $redirect_url)) {
                $redirect_url = '../' . $redirect_url;
            }
            header('Location: ' . $redirect_url);
            exit;
        }
        $max_stock = get_item_size_stock($conn, $id, $size);
    }

    $size_tag = ($size !== '') ? (' (Size ' . $size . ')') : '';

    if ($action == 'add') {
        $current = 0;
        if (isset($_SESSION['cart'][$cart_key])) {
            $current = (int)$_SESSION['cart'][$cart_key];
        }

        if ($max_stock <= 0) {
            $_SESSION['message'] = $item['name'] . $size_tag . ' is out of stock.';
        } elseif ($current + 1 > $max_stock) {
            $_SESSION['message'] = 'Only ' . $max_stock . ' available for ' . $item['name'] . $size_tag . ', and you already have them in your cart.';
        } else {
            $_SESSION['cart'][$cart_key] = $current + 1;
            $_SESSION['success'] = 'Added "' . $item['name'] . $size_tag . '" to your cart!';
        }
    }
    if ($action == 'update') {
        $qty = (int) $_POST['qty'];
        if ($qty > $max_stock) {
            $qty = $max_stock;
        }
        if ($qty <= 0) {
            unset($_SESSION['cart'][$cart_key]);
            $_SESSION['message'] = 'Removed item from cart.';
        } else {
            $_SESSION['cart'][$cart_key] = $qty;
            $_SESSION['success'] = 'Cart updated.';
        }
    }
    if ($action == 'remove') {
        unset($_SESSION['cart'][$cart_key]);
        $_SESSION['message'] = 'Removed item from cart.';
    }
} else {
    unset($_SESSION['cart'][$cart_key]);
}

// Redirect back
if (isset($_POST['back']) && $_POST['back'] == 'cart') {
    header('Location: ../view_cart.php');
} elseif (!empty($_POST['return_url'])) {
    $return_target = $_POST['return_url'];
    if (!preg_match('#^(https?://|/|\.\./)#', $return_target)) {
        $return_target = '../' . $return_target;
    }
    header('Location: ' . $return_target);
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    if ($action == 'add' && !str_contains($ref, '#')) {
        $ref .= '#item-' . $id;
    }
    header('Location: ' . $ref);
} else {
    header('Location: ../shop.php');
}
exit;
