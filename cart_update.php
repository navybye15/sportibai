<?php
// Guests can use the cart. No login needed here.
session_start();
require 'includes/customer_guard.php';
require 'includes/db.php';
require 'includes/functions.php';

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
$item = find_item($conn, $id);

if ($item != null) {
    if ($action == 'add') {
        $current = 0;
        if (isset($_SESSION['cart'][$id])) {
            $current = $_SESSION['cart'][$id];
        }

        if ($item['stock'] <= 0) {
            // Out of stock: never put it in the cart
            $_SESSION['message'] = $item['name'] . ' is out of stock.';
        } elseif ($current + 1 > $item['stock']) {
            $_SESSION['message'] = 'Only ' . $item['stock'] . ' of ' . $item['name'] . ' in stock, and you already have them all in your cart.';
        } else {
            $_SESSION['cart'][$id] = $current + 1;
            $_SESSION['success'] = 'Added "' . $item['name'] . '" to your cart!';
        }
    }
    if ($action == 'update') {
        $qty = (int) $_POST['qty'];
        if ($qty > $item['stock']) {
            $qty = $item['stock'];
        }
        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
            $_SESSION['message'] = 'Removed item from cart.';
        } else {
            $_SESSION['cart'][$id] = $qty;
            $_SESSION['success'] = 'Cart updated.';
        }
    }
    if ($action == 'remove') {
        unset($_SESSION['cart'][$id]);
        $_SESSION['message'] = 'Removed item from cart.';
    }
} else {
    // The item is gone (removed by the admin), so drop it from the cart too
    unset($_SESSION['cart'][$id]);
}

// Redirect back
if (isset($_POST['back']) && $_POST['back'] == 'cart') {
    header('Location: view_cart.php');
} elseif (!empty($_POST['return_url'])) {
    header('Location: ' . $_POST['return_url']);
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    if ($action == 'add' && !str_contains($ref, '#')) {
        $ref .= '#item-' . $id;
    }
    header('Location: ' . $ref);
} else {
    header('Location: shop.php');
}
exit;
