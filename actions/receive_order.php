<?php
// receive_order.php — Customer marks a Delivered order as Completed
if (session_status() == PHP_SESSION_NONE) session_start();
$base = '../';
require_once __DIR__ . '/../includes/customer_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}
if (!csrf_verify()) {
    header('Location: ../orders.php');
    exit;
}
if ($conn == null) {
    header('Location: ../orders.php');
    exit;
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

try {
    mysqli_begin_transaction($conn);

    // Lock the row and verify ownership + status
    $sql = "SELECT o.order_id, o.status
            FROM orders o
            JOIN customer c ON o.customer_id = c.customer_id
            WHERE o.order_id = ? AND c.user_id = ?
            LIMIT 1 FOR UPDATE";
    $result = mysqli_execute_query($conn, $sql, array($order_id, $_SESSION['user_id']));
    $order  = mysqli_fetch_assoc($result);

    if ($order === null) {
        throw new Exception('Order not found.');
    }
    if ($order['status'] !== 'Delivered') {
        throw new Exception('This order cannot be marked as received yet.');
    }

    // Move to Completed
    $sql = "UPDATE orders SET status = 'Completed', completed_at = NOW() WHERE order_id = ?";
    mysqli_execute_query($conn, $sql, array($order_id));

    // Log the status change
    $sql = "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?, 'Completed', 'customer')";
    mysqli_execute_query($conn, $sql, array($order_id));

    mysqli_commit($conn);
    $_SESSION['success'] = 'Order #' . $order_id . ' marked as received! You can now write a review for your gear below.';
    header('Location: ../orders.php?tab=completed&open_review=' . $order_id);
    exit;
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    $_SESSION['message'] = 'Something went wrong. Please try again.';
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['message'] = $e->getMessage();
}

header('Location: ../orders.php?tab=completed');
exit;
