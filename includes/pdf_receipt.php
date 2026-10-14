<?php
// =====================================================
// Sportibai PDF Receipt Generator (Term Test Lab Requirement)
// Uses FPDF to produce an official transaction receipt PDF
// =====================================================

require_once __DIR__ . '/../fpdf/fpdf.php';

function generate_order_receipt_pdf($order_id, $conn) {
    if (!$conn) {
        return null;
    }

    // 1. Fetch from Unit 1 MySQL View: vw_order_details
    $sql = "SELECT * FROM vw_order_details WHERE order_id = ?";
    $res = mysqli_execute_query($conn, $sql, array($order_id));
    $items = array();
    $order_info = null;

    while ($row = mysqli_fetch_assoc($res)) {
        if ($order_info === null) {
            $order_info = $row;
        }
        $items[] = $row;
    }

    // Fallback if view had no rows (e.g. order without items)
    if ($order_info === null) {
        $o_res = mysqli_execute_query($conn, "SELECT * FROM orders WHERE order_id = ? LIMIT 1", array($order_id));
        $order_info = mysqli_fetch_assoc($o_res);
        if (!$order_info) {
            return null;
        }
    }

    $ref = $order_info['reference_number'] ?? ('SB-' . str_pad($order_id, 7, '0', STR_PAD_LEFT));
    $cust_name = $order_info['customer_name'] ?? 'Sportibai Athlete';
    $cust_email = $order_info['customer_email'] ?? 'N/A';
    $status = $order_info['order_status'] ?? ($order_info['status'] ?? 'Pending');
    $date = date('F d, Y h:i A', strtotime($order_info['date_ordered']));
    $payment = $order_info['payment_method'] ?? 'Cash on Delivery (COD)';
    $total = (float)($order_info['grand_total'] ?? ($order_info['total'] ?? 0));

    // 2. Build PDF Document
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    // Header Background / Title
    $pdf->SetFillColor(17, 19, 23); // Sportibai deep dark
    $pdf->Rect(0, 0, 210, 42, 'F');

    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 22);
    $pdf->SetXY(15, 12);
    $pdf->Write(10, 'SPORTIBAI');

    $pdf->SetTextColor(81, 175, 67); // Sportibai green
    $pdf->Write(10, ' GEAR');

    $pdf->SetTextColor(170, 175, 185);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetXY(15, 24);
    $pdf->Cell(0, 8, 'Official Customer Transaction Receipt', 0, 1);

    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetXY(120, 14);
    $pdf->Cell(75, 6, 'RECEIPT #: ' . $ref, 0, 1, 'R');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(170, 175, 185);
    $pdf->SetX(120);
    $pdf->Cell(75, 5, 'Date: ' . $date, 0, 1, 'R');
    $pdf->SetX(120);
    $pdf->Cell(75, 5, 'Status: ' . strtoupper($status), 0, 1, 'R');

    // Customer / Fulfillment Info Box
    $pdf->SetY(48);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(40, 44, 52);
    $pdf->Cell(95, 7, 'BILLED / DELIVERED TO:', 0, 0);
    $pdf->Cell(95, 7, 'PAYMENT & FULFILLMENT:', 0, 1);

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(70, 75, 85);
    $pdf->Cell(95, 5, 'Customer: ' . iconv('UTF-8', 'windows-1252//TRANSLIT', $cust_name), 0, 0);
    $pdf->Cell(95, 5, 'Payment Method: ' . $payment, 0, 1);

    $pdf->Cell(95, 5, 'Email: ' . $cust_email, 0, 0);
    $pdf->Cell(95, 5, 'Order Type: ' . strtoupper($order_info['order_type'] ?? 'Delivery'), 0, 1);

    $pdf->Ln(6);

    // Items Table Header
    $pdf->SetFillColor(240, 243, 248);
    $pdf->SetTextColor(30, 35, 45);
    $pdf->SetFont('Arial', 'B', 9);

    $pdf->Cell(85, 8, '  ITEM DESCRIPTION', 1, 0, 'L', true);
    $pdf->Cell(30, 8, 'BRAND', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'QTY', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'PRICE (PHP)', 1, 0, 'R', true);
    $pdf->Cell(25, 8, 'SUBTOTAL', 1, 1, 'R', true);

    // Items Table Rows
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(50, 55, 65);

    foreach ($items as $it) {
        $name = iconv('UTF-8', 'windows-1252//TRANSLIT', $it['item_name'] ?? 'Sport Item');
        $brand = iconv('UTF-8', 'windows-1252//TRANSLIT', $it['item_brand'] ?? '—');
        $qty = $it['quantity'] ?? 1;
        $price = number_format((float)($it['unit_price'] ?? 0), 2);
        $subtotal = number_format((float)($it['subtotal'] ?? 0), 2);

        $pdf->Cell(85, 7, '  ' . substr($name, 0, 45), 'LR', 0, 'L');
        $pdf->Cell(30, 7, substr($brand, 0, 18), 'LR', 0, 'C');
        $pdf->Cell(20, 7, (string)$qty, 'LR', 0, 'C');
        $pdf->Cell(25, 7, $price . ' ', 'LR', 0, 'R');
        $pdf->Cell(25, 7, $subtotal . ' ', 'LR', 1, 'R');
    }

    // Table bottom border
    $pdf->Cell(185, 0, '', 'T', 1);

    // Grand Total Box
    $pdf->Ln(4);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(80, 85, 95);
    $pdf->Cell(135, 6, '', 0, 0);
    $pdf->Cell(25, 6, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(25, 6, 'PHP ' . number_format($total, 2), 0, 1, 'R');

    $pdf->Cell(135, 6, '', 0, 0);
    $pdf->Cell(25, 6, 'Shipping:', 0, 0, 'R');
    $pdf->Cell(25, 6, 'FREE', 0, 1, 'R');

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(30, 35, 45);
    $pdf->Cell(135, 8, '', 0, 0);
    $pdf->SetFillColor(230, 245, 230);
    $pdf->Cell(25, 8, 'TOTAL:', 0, 0, 'R', true);
    $pdf->SetTextColor(40, 130, 30);
    $pdf->Cell(25, 8, 'PHP ' . number_format($total, 2), 0, 1, 'R', true);

    // Notice & Security
    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(120, 125, 135);
    $pdf->MultiCell(185, 4.5, "This is an official system-generated transaction receipt for your Sportibai order ($ref). For inquiries, warranty, or returns, please present this receipt or reply directly to your order notification email.\nSportibai Athletics Shop - ITIM211 Information Management 2026", 0, 'C');

    // Output to temp file
    $tmp_dir = sys_get_temp_dir();
    $pdf_filename = $tmp_dir . DIRECTORY_SEPARATOR . 'Sportibai_Receipt_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $ref) . '.pdf';
    $pdf->Output('F', $pdf_filename);

    return $pdf_filename;
}
