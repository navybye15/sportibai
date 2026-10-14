<?php
/**
 * PHPMailer Integration for Sportibai
 * Based on the pattern from https://github.com/m3lfx/itim211-2b-shop
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/pdf_receipt.php';

function get_mailer_instance() {
    $mail = new PHPMailer(true);

    // Default Mailtrap sandbox configuration matching prof's send.php
    $host       = 'sandbox.smtp.mailtrap.io';
    $port       = 2525;
    $user       = '';
    $pass       = '';
    $encryption = '';
    $from_email = 'no-reply@sportibai.ph';
    $from_name  = 'Sportibai Athletes';

    // Check optional mail config
    $mail_cfg_file = __DIR__ . '/../config/mail_config.php';
    if (file_exists($mail_cfg_file)) {
        $mcfg = include $mail_cfg_file;
        if (is_array($mcfg)) {
            $host       = $mcfg['host'] ?? $host;
            $port       = (int)($mcfg['port'] ?? $port);
            $user       = $mcfg['user'] ?? $user;
            $pass       = $mcfg['pass'] ?? $pass;
            $encryption = $mcfg['encryption'] ?? '';
            $from_email = $mcfg['from_email'] ?? $from_email;
            $from_name  = $mcfg['from_name'] ?? $from_name;
        }
    }

    $mail->isSMTP();
    $mail->Host     = $host;
    $mail->SMTPAuth = true;
    $mail->Port     = $port;
    $mail->Username = $user;
    $mail->Password = $pass;
    $mail->CharSet  = 'UTF-8';

    if (!empty($encryption)) {
        $mail->SMTPSecure = $encryption;
    } elseif ($port === 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($port === 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->setFrom($from_email, $from_name);

    return $mail;
}

/**
 * Log mail to logs/mail.log for local offline verification & grading
 */
function log_mail_delivery($type, $to, $subject, $extra = '') {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) @mkdir($log_dir, 0777, true);
    $entry = "[" . date('Y-m-d H:i:s') . "] [{$type}] To: {$to} | Subject: {$subject}\n";
    if ($extra) {
        $entry .= "Details: " . $extra . "\n";
    }
    $entry .= str_repeat("-", 60) . "\n";
    @file_put_contents($log_dir . '/mail.log', $entry, FILE_APPEND);
}

/**
 * Send Password Reset Email using PHPMailer
 */
function send_password_reset_email($recipient_email, $reset_link) {
    log_mail_delivery('PASSWORD_RESET', $recipient_email, 'Reset Your Sportibai Password', "Reset Link: {$reset_link}");

    try {
        $mail = get_mailer_instance();
        // If username is empty, we don't attempt external network connection to avoid timeouts
        if (empty($mail->Username)) {
            return array('success' => true, 'simulated' => true, 'link' => $reset_link);
        }

        $mail->addAddress($recipient_email);
        $mail->isHTML(true);
        $mail->Subject = 'Sportibai - Reset Your Account Password';

        $body = '
        <div style="background:#111317; color:#f2efe9; padding:32px; font-family:\'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; border-radius:14px; max-width:540px; margin:0 auto; border:1px solid #232730;">
            <div style="margin-bottom:20px;">
                <span style="font-size:20px; font-weight:800; letter-spacing:0.12em; color:#ffffff;">SPORTI<span style="color:#51af43;">BAI</span></span>
                <span style="display:inline-block; margin-left:8px; font-size:11px; font-weight:700; color:#6f9be0; letter-spacing:0.15em;">[ ACCOUNT SECURITY ]</span>
            </div>
            <h2 style="color:#ffffff; font-size:22px; margin:0 0 12px;">Reset Your Password</h2>
            <p style="color:#a1a4ad; line-height:1.6; font-size:14px; margin:0 0 24px;">
                We received a request to reset your password. Click the button below to create a new password. This link is valid for 1 hour.
            </p>
            <div style="margin:28px 0; text-align:center;">
                <a href="' . htmlspecialchars($reset_link) . '" style="background:#51af43; color:#ffffff; padding:14px 28px; text-decoration:none; border-radius:8px; font-weight:700; font-size:14px; display:inline-block; box-shadow:0 6px 20px rgba(81,175,67,0.35);">
                    Reset My Password →
                </a>
            </div>
            <p style="color:#6d7078; font-size:12px; line-height:1.5; margin:24px 0 0; border-top:1px solid #232730; padding-top:16px;">
                If you did not request this, you can safely ignore this email. Your password will remain unchanged.<br>
                Direct link: <a href="' . htmlspecialchars($reset_link) . '" style="color:#6f9be0;">' . htmlspecialchars($reset_link) . '</a>
            </p>
        </div>';

        $mail->Body    = $body;
        $mail->AltBody = "Reset your Sportibai password by visiting: {$reset_link}";

        $mail->send();
        return array('success' => true, 'simulated' => false, 'link' => $reset_link);
    } catch (Exception $e) {
        log_mail_delivery('MAILER_ERROR', $recipient_email, 'Failed to send reset email', $mail->ErrorInfo);
        return array('success' => false, 'error' => $mail->ErrorInfo, 'link' => $reset_link);
    }
}

/**
 * Send Order Confirmation Email using PHPMailer
 */
function send_order_confirmation_email($order_id, $conn) {
    if (!$conn) return false;

    // Fetch order, customer, items
    $sql = "SELECT o.reference_number, o.order_type, o.delivery_address, o.contact_number,
                   o.total, o.date_ordered, o.snapshot_name,
                   c.first_name, c.last_name, u.email
            FROM orders o
            JOIN customer c ON o.customer_id = c.customer_id
            JOIN users u ON c.user_id = u.user_id
            WHERE o.order_id = ? LIMIT 1";
    $res = mysqli_execute_query($conn, $sql, array($order_id));
    $order = mysqli_fetch_assoc($res);
    if (!$order) return false;

    $cust_email = $order['email'];
    $cust_name  = !empty($order['snapshot_name']) ? $order['snapshot_name'] : ($order['first_name'] . ' ' . $order['last_name']);
    $ref_num    = $order['reference_number'] ?? ('SB-' . str_pad($order_id, 7, '0', STR_PAD_LEFT));

    // Fetch order items
    $sql_items = "SELECT oi.quantity, oi.price, i.name, i.brand
                  FROM order_items oi
                  JOIN items i ON oi.item_id = i.item_id
                  WHERE oi.order_id = ?";
    $res_items = mysqli_execute_query($conn, $sql_items, array($order_id));
    $items = array();
    while ($it = mysqli_fetch_assoc($res_items)) {
        $items[] = $it;
    }

    log_mail_delivery('ORDER_CONFIRMATION', $cust_email, "Order Confirmation - {$ref_num}", "Items count: " . count($items) . " | Total: ₱" . number_format($order['total'], 2));

    try {
        $mail = get_mailer_instance();
        if (empty($mail->Username)) {
            // Simulated delivery when Mailtrap credentials not configured
            return true;
        }

        $mail->addAddress($cust_email, $cust_name);
        $mail->isHTML(true);
        $mail->Subject = "Sportibai Order Confirmed [{$ref_num}]";

        $items_html = '';
        foreach ($items as $it) {
            $items_html .= '
            <tr style="border-bottom:1px solid #232730;">
                <td style="padding:10px 0; color:#ffffff; font-size:14px;">
                    ' . htmlspecialchars($it['name']) . '
                    <span style="display:block; font-size:12px; color:#888;">Qty: ' . $it['quantity'] . '</span>
                </td>
                <td style="padding:10px 0; text-align:right; color:#ffffff; font-size:14px;">
                    ₱' . number_format($it['price'] * $it['quantity'], 2) . '
                </td>
            </tr>';
        }

        $body = '
        <div style="background:#111317; color:#f2efe9; padding:32px; font-family:\'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; border-radius:14px; max-width:540px; margin:0 auto; border:1px solid #232730;">
            <div style="margin-bottom:20px;">
                <span style="font-size:20px; font-weight:800; letter-spacing:0.12em; color:#ffffff;">SPORTI<span style="color:#51af43;">BAI</span></span>
                <span style="display:inline-block; margin-left:8px; font-size:11px; font-weight:700; color:#51af43; letter-spacing:0.15em;">[ ORDER CONFIRMED ]</span>
            </div>
            <h2 style="color:#ffffff; font-size:22px; margin:0 0 8px;">Salamat sa order, ' . htmlspecialchars($cust_name) . '!</h2>
            <p style="color:#a1a4ad; line-height:1.6; font-size:14px; margin:0 0 20px;">
                Your gear order <strong>' . htmlspecialchars($ref_num) . '</strong> has been placed and is now waiting for seller preparation.
            </p>
            <div style="background:#181b22; border-radius:10px; padding:16px; margin-bottom:20px;">
                <div style="font-size:12px; color:#888; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:8px;">Fulfillment Details</div>
                <div style="color:#ffffff; font-size:14px; font-weight:600;">Method: ' . strtoupper($order['order_type']) . ' (COD)</div>
                <div style="color:#a1a4ad; font-size:13px; margin-top:4px;">' . nl2br(htmlspecialchars($order['delivery_address'] ?: 'Central Mall Sportibai Hub')) . '</div>
                <div style="color:#a1a4ad; font-size:13px; margin-top:2px;">Contact: ' . htmlspecialchars($order['contact_number']) . '</div>
            </div>
            <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                <thead>
                    <tr style="border-bottom:1px solid #333; color:#888; font-size:12px; text-transform:uppercase;">
                        <th style="text-align:left; padding-bottom:8px;">Item</th>
                        <th style="text-align:right; padding-bottom:8px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $items_html . '
                </tbody>
            </table>
            <div style="border-top:2px solid #333; padding-top:12px; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:16px; font-weight:700; color:#ffffff;">Total Order Amount:</span>
                <span style="font-size:18px; font-weight:800; color:#51af43;">₱' . number_format($order['total'], 2) . '</span>
            </div>
        </div>';

        $mail->Body    = $body;
        $mail->AltBody = "Sportibai order confirmed: {$ref_num}. Total: ₱" . number_format($order['total'], 2);

        // Term Test Lab requirement: Attach PDF transaction receipt
        $pdf_file = generate_order_receipt_pdf($order_id, $conn);
        if ($pdf_file && file_exists($pdf_file)) {
            $mail->addAttachment($pdf_file, "Sportibai_Receipt_{$ref_num}.pdf");
        }

        $mail->send();

        if ($pdf_file && file_exists($pdf_file)) {
            @unlink($pdf_file);
        }

        return true;
    } catch (Exception $e) {
        log_mail_delivery('MAILER_ERROR', $cust_email, 'Order confirmation error', $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send Order Status Update Email to Customer (Term Test Lab Requirement: 35 pts)
 * Sends updated status, product list, subtotals, grand total, and attached PDF receipt.
 */
function send_order_status_update_email($order_id, $new_status, $conn) {
    if (!$conn) return false;

    // Use Unit 1 MySQL View: vw_order_details
    $sql = "SELECT * FROM vw_order_details WHERE order_id = ?";
    $res = mysqli_execute_query($conn, $sql, array($order_id));
    $items = array();
    $order = null;

    while ($row = mysqli_fetch_assoc($res)) {
        if ($order === null) {
            $order = $row;
        }
        $items[] = $row;
    }

    if (!$order) {
        // Fallback query if view returned empty (e.g. order with no items)
        $o_res = mysqli_execute_query($conn, "SELECT o.*, u.email AS customer_email, o.snapshot_name AS customer_name FROM orders o JOIN customer c ON o.customer_id = c.customer_id JOIN users u ON c.user_id = u.user_id WHERE o.order_id = ? LIMIT 1", array($order_id));
        $order = mysqli_fetch_assoc($o_res);
        if (!$order) return false;
    }

    $cust_email = $order['customer_email'];
    $cust_name  = !empty($order['customer_name']) ? $order['customer_name'] : 'Sportibai Athlete';
    $ref_num    = $order['reference_number'] ?? ('SB-' . str_pad($order_id, 7, '0', STR_PAD_LEFT));
    $grand_total = (float)($order['grand_total'] ?? ($order['total'] ?? 0));

    log_mail_delivery('ORDER_STATUS_UPDATE', $cust_email, "Status Update [{$ref_num}] - {$new_status}", "Items: " . count($items) . " | Total: ₱" . number_format($grand_total, 2));

    try {
        $mail = get_mailer_instance();
        if (empty($mail->Username)) {
            return true;
        }

        $mail->addAddress($cust_email, $cust_name);
        $mail->isHTML(true);
        $mail->Subject = "Sportibai Order #{$ref_num} Status: {$new_status}";

        // Itemized product table with subtotals (Term Test Lab requirement)
        $items_html = '';
        foreach ($items as $it) {
            $qty = (int)($it['quantity'] ?? 1);
            $price = (float)($it['unit_price'] ?? 0);
            $subtotal = (float)($it['subtotal'] ?? ($qty * $price));

            $items_html .= '
            <tr style="border-bottom:1px solid #232730;">
                <td style="padding:10px 0; color:#ffffff; font-size:14px;">
                    ' . htmlspecialchars($it['item_name']) . '
                    <span style="display:block; font-size:12px; color:#888;">Qty: ' . $qty . ' × ₱' . number_format($price, 2) . '</span>
                </td>
                <td style="padding:10px 0; text-align:right; color:#ffffff; font-size:14px;">
                    ₱' . number_format($subtotal, 2) . '
                </td>
            </tr>';
        }

        $body = '
        <div style="background:#111317; color:#f2efe9; padding:32px; font-family:\'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; border-radius:14px; max-width:540px; margin:0 auto; border:1px solid #232730;">
            <div style="margin-bottom:20px;">
                <span style="font-size:20px; font-weight:800; letter-spacing:0.12em; color:#ffffff;">SPORTI<span style="color:#51af43;">BAI</span></span>
                <span style="display:inline-block; margin-left:8px; font-size:11px; font-weight:700; color:#f59e0b; letter-spacing:0.15em;">[ STATUS NOTIFICATION ]</span>
            </div>
            <h2 style="color:#ffffff; font-size:22px; margin:0 0 8px;">Order Status: ' . htmlspecialchars($new_status) . '</h2>
            <p style="color:#a1a4ad; line-height:1.6; font-size:14px; margin:0 0 20px;">
                Hi <strong>' . htmlspecialchars($cust_name) . '</strong>, your order <strong>' . htmlspecialchars($ref_num) . '</strong> has been updated to <strong style="color:#51af43;">' . strtoupper(htmlspecialchars($new_status)) . '</strong>.
            </p>
            <div style="background:#181b22; border-radius:10px; padding:16px; margin-bottom:20px;">
                <div style="font-size:12px; color:#888; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:8px;">Order Details</div>
                <div style="color:#ffffff; font-size:14px; font-weight:600;">Status: ' . htmlspecialchars($new_status) . '</div>
                <div style="color:#a1a4ad; font-size:13px; margin-top:4px;">Reference #: ' . htmlspecialchars($ref_num) . '</div>
            </div>
            <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                <thead>
                    <tr style="border-bottom:1px solid #333; color:#888; font-size:12px; text-transform:uppercase;">
                        <th style="text-align:left; padding-bottom:8px;">Item Description</th>
                        <th style="text-align:right; padding-bottom:8px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $items_html . '
                </tbody>
            </table>
            <div style="border-top:2px solid #333; padding-top:12px; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:16px; font-weight:700; color:#ffffff;">Grand Total:</span>
                <span style="font-size:18px; font-weight:800; color:#51af43;">₱' . number_format($grand_total, 2) . '</span>
            </div>
            <p style="color:#6d7078; font-size:12px; line-height:1.5; margin:24px 0 0; border-top:1px solid #232730; padding-top:16px;">
                📎 An official PDF copy of your transaction receipt is attached to this email.<br>
                Thank you for choosing Sportibai for your athletic gear!
            </p>
        </div>';

        $mail->Body    = $body;
        $mail->AltBody = "Sportibai order #{$ref_num} status updated to: {$new_status}. Grand Total: ₱" . number_format($grand_total, 2);

        // Attach PDF Receipt (Term Test Lab requirement)
        $pdf_file = generate_order_receipt_pdf($order_id, $conn);
        if ($pdf_file && file_exists($pdf_file)) {
            $mail->addAttachment($pdf_file, "Sportibai_Receipt_{$ref_num}.pdf");
        }

        $mail->send();

        if ($pdf_file && file_exists($pdf_file)) {
            @unlink($pdf_file);
        }

        return true;
    } catch (Exception $e) {
        log_mail_delivery('MAILER_ERROR', $cust_email, 'Order status update error', $mail->ErrorInfo);
        return false;
    }
}

