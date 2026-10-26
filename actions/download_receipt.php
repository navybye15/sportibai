<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pdf_receipt.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    die('Invalid order ID.');
}

// Access check: User must be logged in as admin OR be the owner of the order
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if (!$is_admin) {
    // Verify customer owns this order
    $sql = "SELECT o.order_id 
            FROM orders o 
            JOIN customer c ON o.customer_id = c.customer_id 
            WHERE o.order_id = ? AND c.user_id = ? LIMIT 1";
    $res = mysqli_execute_query($conn, $sql, array($order_id, $_SESSION['user_id']));
    if (mysqli_num_rows($res) === 0) {
        die('Access denied: You do not have permission to view this receipt.');
    }
}

// Generate the PDF
$pdf_file = generate_order_receipt_pdf($order_id, $conn);
if (!$pdf_file || !file_exists($pdf_file)) {
    die('Could not generate receipt PDF.');
}

$ref_num = 'SB-' . str_pad($order_id, 7, '0', STR_PAD_LEFT);
$filename = "Sportibai_Receipt_{$ref_num}.pdf";

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($pdf_file));
readfile($pdf_file);

// Clean up
@unlink($pdf_file);
exit;
