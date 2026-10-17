<?php
// cancel_order.php — Customer cancels an order (allowed while Pending or Prepared)
if (session_status() == PHP_SESSION_NONE) session_start();
$base = '';
require_once __DIR__ . '/includes/customer_guard.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (!csrf_verify()) {
    header('Location: orders.php');
    exit;
}
if ($conn == null) {
    header('Location: orders.php');
    exit;
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

try {
    mysqli_begin_transaction($conn);

    // Ownership check: the order must belong to the logged-in customer,
    // and it must still be Pending or Prepared.
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

    // Allow cancel only while the shop hasn't shipped it yet
    $cancellable = array('Pending', 'Prepared');
    if (!in_array($order['status'], $cancellable)) {
        throw new Exception('This order can no longer be cancelled (it has already been ' . $order['status'] . ').');
    }

    // Return each item's quantity back to stock (including specific size stock)
    $sql = "SELECT item_id, quantity, selected_size FROM order_items WHERE order_id = ?";
    $result = mysqli_execute_query($conn, $sql, array($order_id));
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['selected_size'])) {
            mysqli_execute_query($conn, "UPDATE item_sizes SET stock = stock + ? WHERE item_id = ? AND size_label = ?", array($row['quantity'], $row['item_id'], $row['selected_size']));
        }
        $sql2 = "UPDATE items SET stock = stock + ? WHERE item_id = ?";
        mysqli_execute_query($conn, $sql2, array($row['quantity'], $row['item_id']));
    }

    $sql = "UPDATE orders SET status = 'Cancelled' WHERE order_id = ?";
    mysqli_execute_query($conn, $sql, array($order_id));

    // Log the cancellation
    $sql = "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?, 'Cancelled', 'customer')";
    mysqli_execute_query($conn, $sql, array($order_id));

    mysqli_commit($conn);
    $_SESSION['success'] = 'Order #' . $order_id . ' has been cancelled.';
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    $_SESSION['message'] = 'Something went wrong. Please try again.';
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['message'] = $e->getMessage();
}

header('Location: orders.php?tab=cancelled');
exit;
