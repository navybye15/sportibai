<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

if ($conn == null) {
    die('Database is not connected.');
}

// Default range: the current month
$from = date('Y-m-01');
$to = date('Y-m-t');
if (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])) {
    $from = $_GET['from'];
}
if (isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])) {
    $to = $_GET['to'];
}

// Shortcut links. A "week" here means Monday to Sunday.
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
// completed_at is a datetime, so we include the whole "to" day up to 23:59:59
$to_datetime = $to . ' 23:59:59';
$from_datetime = $from . ' 00:00:00';

// Total sales: only Completed orders count as a sale, and we use
// completed_at (when it was actually finished), not date_ordered
$sql = "SELECT COALESCE(SUM(total), 0) AS total_sales, COUNT(*) AS order_count
        FROM orders
        WHERE status = 'Completed' AND completed_at BETWEEN ? AND ?";
$result = mysqli_execute_query($conn, $sql, array($from_datetime, $to_datetime));
$row = mysqli_fetch_assoc($result);
$total_sales = $row['total_sales'];
$order_count = $row['order_count'];

// Cost of Goods Sold (COGS) based on items.cost_price
$sql_cost = "SELECT COALESCE(SUM(oi.quantity * COALESCE(i.cost_price, 0)), 0) AS total_cogs
             FROM order_items oi
             JOIN orders o ON oi.order_id = o.order_id
             JOIN items i ON oi.item_id = i.item_id
             WHERE o.status = 'Completed' AND o.completed_at BETWEEN ? AND ?";
$res_cost = mysqli_execute_query($conn, $sql_cost, array($from_datetime, $to_datetime));
$row_cost = mysqli_fetch_assoc($res_cost);
$total_cogs = (float)($row_cost['total_cogs'] ?? 0);

// Total store expenses for the same period
$sql = "SELECT COALESCE(SUM(amount), 0) AS total_expenses
        FROM expenses
        WHERE expense_date BETWEEN ? AND ?";
$result = mysqli_execute_query($conn, $sql, array($from, $to));
$row = mysqli_fetch_assoc($result);
$total_expenses = $row['total_expenses'];

$gross_profit = $total_sales - $total_cogs;
$net_income   = $gross_profit - $total_expenses;

// Best-selling items, ranked by quantity sold
$sql = "SELECT i.name, SUM(oi.quantity) AS qty_sold, SUM(oi.quantity * oi.price) AS revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN items i ON oi.item_id = i.item_id
        WHERE o.status = 'Completed' AND o.completed_at BETWEEN ? AND ?
        GROUP BY oi.item_id, i.name
        ORDER BY qty_sold DESC
        LIMIT 5";
$result = mysqli_execute_query($conn, $sql, array($from_datetime, $to_datetime));
$best_sellers = array();
while ($row = mysqli_fetch_assoc($result)) {
    $best_sellers[] = $row;
}

// ── Daily Breakdown Computation ──────────────────────────────────────────────
$sql_daily_orders = "SELECT DATE(o.completed_at) AS day,
                            COUNT(DISTINCT o.order_id) AS orders_count,
                            COALESCE(SUM(oi.quantity * oi.price), 0) AS daily_sales,
                            COALESCE(SUM(oi.quantity * COALESCE(i.cost_price, 0)), 0) AS daily_cogs
                     FROM orders o
                     JOIN order_items oi ON o.order_id = oi.order_id
                     JOIN items i ON oi.item_id = i.item_id
                     WHERE o.status = 'Completed' AND o.completed_at BETWEEN ? AND ?
                     GROUP BY DATE(o.completed_at)";
$res_daily_orders = mysqli_execute_query($conn, $sql_daily_orders, array($from_datetime, $to_datetime));
$daily_data = array();
while ($r = mysqli_fetch_assoc($res_daily_orders)) {
    $d = $r['day'];
    $daily_data[$d] = array(
        'day' => $d,
        'orders_count' => (int)$r['orders_count'],
        'sales' => (float)$r['daily_sales'],
        'cogs' => (float)$r['daily_cogs'],
        'expenses' => 0.0,
        'profit' => (float)$r['daily_sales'] - (float)$r['daily_cogs']
    );
}

$sql_daily_exp = "SELECT expense_date AS day, COALESCE(SUM(amount), 0) AS daily_expenses
                  FROM expenses
                  WHERE expense_date BETWEEN ? AND ?
                  GROUP BY expense_date";
$res_daily_exp = mysqli_execute_query($conn, $sql_daily_exp, array($from, $to));
while ($r = mysqli_fetch_assoc($res_daily_exp)) {
    $d = $r['day'];
    if (!isset($daily_data[$d])) {
        $daily_data[$d] = array(
            'day' => $d,
            'orders_count' => 0,
            'sales' => 0.0,
            'cogs' => 0.0,
            'expenses' => (float)$r['daily_expenses'],
            'profit' => -(float)$r['daily_expenses']
        );
    } else {
        $daily_data[$d]['expenses'] = (float)$r['daily_expenses'];
        $daily_data[$d]['profit'] = $daily_data[$d]['sales'] - $daily_data[$d]['cogs'] - (float)$r['daily_expenses'];
    }
}
krsort($daily_data);

// ── CSV Export Handler ───────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sportibai_sales_' . $from . '_to_' . $to . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($out, array('SPORTIBAI SALES & REVENUE REPORT'));
    fputcsv($out, array('Report Period', $from . ' to ' . $to));
    fputcsv($out, array('Generated On', date('Y-m-d H:i:s')));
    fputcsv($out, array(''));
    fputcsv($out, array('FINANCIAL SUMMARY'));
    fputcsv($out, array('Gross Revenue (PHP)', number_format($total_sales, 2, '.', '')));
    fputcsv($out, array('Cost of Goods Sold (PHP)', number_format($total_cogs, 2, '.', '')));
    fputcsv($out, array('Store Expenses Deducted (PHP)', number_format($total_expenses, 2, '.', '')));
    fputcsv($out, array('Net Store Profit (PHP)', number_format($net_income, 2, '.', '')));
    fputcsv($out, array('Completed Orders', $order_count));
    fputcsv($out, array(''));
    fputcsv($out, array('DAILY BREAKDOWN'));
    fputcsv($out, array('Date', 'Completed Orders', 'Gross Sales (PHP)', 'Cost of Goods (PHP)', 'Expenses (PHP)', 'Net Profit (PHP)'));
    foreach ($daily_data as $row) {
        fputcsv($out, array(
            $row['day'],
            $row['orders_count'],
            number_format($row['sales'], 2, '.', ''),
            number_format($row['cogs'], 2, '.', ''),
            number_format($row['expenses'], 2, '.', ''),
            number_format($row['profit'], 2, '.', '')
        ));
    }
    fputcsv($out, array(''));
    fputcsv($out, array('TOP 5 BEST SELLING PRODUCTS'));
    fputcsv($out, array('Product Name', 'Quantity Sold', 'Revenue (PHP)'));
    foreach ($best_sellers as $b) {
        fputcsv($out, array($b['name'], $b['qty_sold'], number_format($b['revenue'], 2, '.', '')));
    }
    fclose($out);
    exit;
}

$base = '../';
$page_title = 'Sales Report | Sportibai';
require '../includes/header.php';
?>
<section class="reveal">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div class="hero-eyebrow">[ PERFORMANCE & FINANCIAL LEDGER ]</div>
            <h1 style="font-size: var(--text-display-l); margin:0;">Sales & Revenue Report</h1>
            <p class="muted" style="font-size:var(--text-body-sm); margin-top:0.3rem;">Audit store earnings, completed order revenue, expenses, and net profit.</p>
        </div>
        <div style="display:flex; gap:8px;" class="no-print">
            <a class="btn ghost sm" href="sales_report.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=csv">
                📥 Export CSV
            </a>
            <button class="btn ghost sm" type="button" onclick="window.print()">
                🖨️ Print Report
            </button>
        </div>
    </div>

    <!-- Date Shortcut Filters -->
    <div class="filter-search-bar no-print" style="margin-bottom:1.25rem;">
        <span class="rail-section-title" style="margin:0 0.5rem 0 0;">Range:</span>
        <a class="btn ghost sm" href="sales_report.php?from=<?php echo $today; ?>&to=<?php echo $today; ?>">Today</a>
        <a class="btn ghost sm" href="sales_report.php?from=<?php echo $week_start; ?>&to=<?php echo $week_end; ?>">This Week</a>
        <a class="btn ghost sm" href="sales_report.php?from=<?php echo $month_start; ?>&to=<?php echo $month_end; ?>">This Month</a>
        
        <form method="GET" action="sales_report.php" style="display:flex; gap:0.5rem; align-items:center; margin-left:auto; flex-wrap:wrap;">
            <input class="search" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" style="min-width:9rem; padding:0.45rem 0.8rem;">
            <span class="muted" style="font-size:12px;">to</span>
            <input class="search" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" style="min-width:9rem; padding:0.45rem 0.8rem;">
            <button class="btn sm" type="submit">Filter Range</button>
        </form>
    </div>

    <!-- Metric Cards (BrewedOps stats cards) -->
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 2rem;">
        <div class="card in">
            <div class="info">
                <span class="brand-tag">GROSS REVENUE</span>
                <h2 style="font-size:1.85rem; color:var(--color-accent-on-dark);"><?php echo peso($total_sales); ?></h2>
                <p class="muted" style="font-size:var(--text-body-sm);"><?php echo $order_count; ?> completed order(s)</p>
            </div>
        </div>
        <div class="card in">
            <div class="info">
                <span class="brand-tag">COST OF GOODS</span>
                <h2 style="font-size:1.85rem; color:#ffc069;"><?php echo peso($total_cogs); ?></h2>
                <p class="muted" style="font-size:var(--text-body-sm);">Inventory capital sold</p>
            </div>
        </div>
        <div class="card in">
            <div class="info">
                <span class="brand-tag">EXPENSES DEDUCTED</span>
                <h2 style="font-size:1.85rem; color:#ff9f96;"><?php echo peso($total_expenses); ?></h2>
                <p class="muted" style="font-size:var(--text-body-sm);">Operating & logistics disbursements</p>
            </div>
        </div>
        <div class="card in" style="border-color: <?php echo $net_income >= 0 ? 'rgba(81,175,67,0.3)' : 'rgba(255,100,100,0.3)'; ?>;">
            <div class="info">
                <span class="brand-tag">NET STORE PROFIT</span>
                <h2 style="font-size:1.85rem; color: <?php echo $net_income >= 0 ? 'var(--color-brand-green)' : '#ff9f96'; ?>;">
                    <?php echo peso($net_income); ?>
                </h2>
                <p class="muted" style="font-size:var(--text-body-sm);">Sales minus COGS & expenses</p>
            </div>
        </div>
    </div>

    <!-- Daily Breakdown Table -->
    <div style="margin-bottom: 2.5rem;">
        <div class="rail-section-title" style="margin-bottom:0.75rem;">Daily Breakdown (<?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?>)</div>
        <?php if (count($daily_data) == 0) { ?>
            <div class="card in" style="text-align:center; padding: 2rem;">
                <p class="muted">No sales or expenses recorded in this date range.</p>
            </div>
        <?php } else { ?>
            <div class="card in" style="padding:0; overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:var(--text-body-sm); text-align:left;">
                    <thead>
                        <tr style="border-bottom:var(--hairline) solid var(--color-border); background:rgba(255,255,255,0.03);">
                            <th style="padding:0.85rem 1rem;">Date</th>
                            <th style="padding:0.85rem 1rem;">Orders</th>
                            <th style="padding:0.85rem 1rem;">Gross Sales</th>
                            <th style="padding:0.85rem 1rem;">Cost of Goods</th>
                            <th style="padding:0.85rem 1rem;">Expenses</th>
                            <th style="padding:0.85rem 1rem; text-align:right;">Net Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_data as $row) { ?>
                            <tr style="border-bottom:var(--hairline) solid rgba(255,255,255,0.05);">
                                <td style="padding:0.75rem 1rem; font-weight:600;"><?= date('M d, Y', strtotime($row['day'])) ?></td>
                                <td style="padding:0.75rem 1rem; color:var(--color-fg-muted);"><?= $row['orders_count'] ?></td>
                                <td style="padding:0.75rem 1rem; color:var(--color-accent-on-dark); font-weight:600;"><?= peso($row['sales']) ?></td>
                                <td style="padding:0.75rem 1rem; color:#ffc069;"><?= peso($row['cogs']) ?></td>
                                <td style="padding:0.75rem 1rem; color:#ff9f96;"><?= peso($row['expenses']) ?></td>
                                <td style="padding:0.75rem 1rem; text-align:right; font-weight:700; color:<?= $row['profit'] >= 0 ? 'var(--color-brand-green)' : '#ff9f96' ?>;">
                                    <?= peso($row['profit']) ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>

    <div>
        <div class="rail-section-title" style="margin-bottom:0.75rem;">Top 5 Best-Selling Products</div>
        <?php if (count($best_sellers) == 0) { ?>
            <div class="card in" style="text-align:center; padding: 2.5rem;">
                <p class="muted">No completed order volume recorded for this date range.</p>
            </div>
        <?php } else { ?>
            <div class="lines" style="padding:0;">
                <?php foreach ($best_sellers as $b) { ?>
                    <div class="line">
                        <div class="tile" style="--h: 150; aspect-ratio:1; max-height:56px;">★</div>
                        <div>
                            <h2 style="font-size:1.15rem;"><?php echo htmlspecialchars($b['name']); ?></h2>
                            <span class="muted" style="font-size:var(--text-body-sm);">Quantity sold: <strong style="color:var(--color-fg);"><?php echo $b['qty_sold']; ?></strong> &nbsp;•&nbsp; Revenue: <strong style="color:var(--color-accent-on-dark);"><?php echo peso($b['revenue']); ?></strong></span>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</section>
<?php require '../includes/footer.php'; ?>
