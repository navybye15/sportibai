<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

if ($conn == null) {
    die('Database is not connected.');
}

if (isset($_POST['add_expense'])) {
    if (!csrf_verify()) {
        header('Location: expenses.php');
        exit;
    }
    $expense_date = $_POST['expense_date'];
    $description = strip_tags(trim($_POST['description']));
    $category = strip_tags(trim($_POST['category']));
    $amount = (float) $_POST['amount'];

    if ($description == '' || $amount <= 0) {
        $_SESSION['message'] = 'Please enter a valid description and amount.';
    } else {
        $sql = "INSERT INTO expenses (expense_date, description, category, amount) VALUES (?, ?, ?, ?)";
        mysqli_execute_query($conn, $sql, array($expense_date, $description, $category, $amount));
        $_SESSION['success'] = 'Expense of ' . peso($amount) . ' added successfully.';
    }
    header('Location: expenses.php');
    exit;
}

if (isset($_POST['delete_expense'])) {
    if (!csrf_verify()) {
        header('Location: expenses.php');
        exit;
    }
    $expense_id = (int) $_POST['expense_id'];
    $sql = "DELETE FROM expenses WHERE expense_id = ?";
    mysqli_execute_query($conn, $sql, array($expense_id));
    $_SESSION['success'] = 'Expense entry deleted.';
    header('Location: expenses.php');
    exit;
}

// ── Metrics for Expenses ─────────────────────────────────────────────────────
$cur_m_start = date('Y-m-01');
$cur_m_end   = date('Y-m-t');

$res_all = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS total_all, COUNT(*) as cnt FROM expenses");
$all_data = mysqli_fetch_assoc($res_all);
$total_all_expenses = (float)($all_data['total_all'] ?? 0);
$total_expense_count = (int)($all_data['cnt'] ?? 0);

$res_m = mysqli_execute_query($conn, "SELECT COALESCE(SUM(amount), 0) AS total_m FROM expenses WHERE expense_date BETWEEN ? AND ?", array($cur_m_start, $cur_m_end));
$total_m_expenses = (float)(mysqli_fetch_assoc($res_m)['total_m'] ?? 0);

// Fetch all expenses
$expenses = array();
$result = mysqli_query($conn, "SELECT * FROM expenses ORDER BY expense_date DESC, expense_id DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $expenses[] = $row;
}

$base = '../';
$page_title = 'Expenses | Sportibai Admin';
require '../includes/header.php';
?>
<section class="reveal in">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div class="hero-eyebrow" style="color:var(--color-brand-red);">[ FINANCIAL DISBURSEMENTS ]</div>
            <h1 style="font-size: var(--text-display-l); line-height:1.15; margin:2px 0 0;">Store Expenses</h1>
        </div>
        <button type="button" class="btn sm" onclick="toggleExpenseForm()" style="box-shadow:0 0 16px rgba(10,132,255,0.35);">
            + Log New Expense
        </button>
    </div>

    <?php require '../includes/alert.php'; ?>

    <!-- KPI Summary Cards -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:2rem;">
        <div class="card in" style="padding:1.25rem 1.5rem;">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--color-fg-secondary); letter-spacing:0.8px;">
                This Month's Outflow
            </div>
            <div style="font-size:26px; font-weight:800; color:#ff9f96; margin:6px 0 2px;">
                <?= peso($total_m_expenses) ?>
            </div>
            <div class="muted" style="font-size:12px;">Disbursed in <?= date('F Y') ?></div>
        </div>

        <div class="card in" style="padding:1.25rem 1.5rem;">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--color-fg-secondary); letter-spacing:0.8px;">
                All-Time Expenses
            </div>
            <div style="font-size:26px; font-weight:800; color:#fff; margin:6px 0 2px;">
                <?= peso($total_all_expenses) ?>
            </div>
            <div class="muted" style="font-size:12px;"><?= $total_expense_count ?> total expense records logged</div>
        </div>
    </div>

    <!-- Collapsible Add Expense Form -->
    <form id="expense-form" class="form" method="POST" action="expenses.php" novalidate style="margin: 0 0 2.5rem; max-width: 100%; display: <?= isset($_GET['log']) ? 'block' : 'none' ?>;">
        <?= csrf_field() ?>
        <div>
            <div class="hero-eyebrow" style="color:var(--color-brand-red);">[ RECORD DISBURSEMENT ]</div>
            <h2 style="font-size:1.35rem; font-weight:700; color:#fff; margin-top:2px;">Add Expense Entry</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-top:1rem;">
            <div>
                <label class="form-label">Expense Date *</label>
                <input type="date" name="expense_date" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div>
                <label class="form-label">Description *</label>
                <input type="text" name="description" placeholder="e.g. Warehouse Rent, Lalamove Freight" required>
            </div>
            <div>
                <label class="form-label">Expense Category *</label>
                <input type="text" id="expense_cat_input" name="category" list="expense_categories" placeholder="Freight, Packaging, Utilities..." required>
                <datalist id="expense_categories">
                    <option value="Freight & Courier">
                    <option value="Warehouse Rent">
                    <option value="Packaging Materials">
                    <option value="Inventory Purchases">
                    <option value="Utilities & Power">
                    <option value="Marketing & Promotion">
                    <option value="Maintenance & Gear Repair">
                </datalist>
            </div>
            <div>
                <label class="form-label">Amount (₱) *</label>
                <input type="number" step="0.01" name="amount" placeholder="0.00" required>
            </div>
        </div>
        <div style="margin-top:1.25rem; display:flex; gap:10px;">
            <button class="btn" type="submit" name="add_expense">+ Save Expense Record</button>
            <button class="btn ghost" type="button" onclick="toggleExpenseForm()">Cancel</button>
        </div>
    </form>

    <!-- Expense Log Lines -->
    <div class="lines" style="padding-top:0.5rem;">
        <div class="rail-section-title" style="margin-bottom:0.75rem;">Expense Log (<?php echo count($expenses); ?> entries)</div>
        <?php if (count($expenses) === 0) { ?>
            <div class="card in" style="text-align:center; padding:3rem;">
                <p class="muted">No expenses recorded yet. Click "+ Log New Expense" above to add one.</p>
            </div>
        <?php } ?>
        <?php foreach ($expenses as $e) { ?>
            <div class="line" style="gap:1rem;">
                <div class="tile" style="--h: <?php echo ($e['expense_id'] * 47) % 360; ?>; aspect-ratio:1; max-height:56px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                    💸
                </div>
                <div style="flex-grow:1;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="brand-tag" style="font-size:11px; text-transform:uppercase; color:var(--color-accent-on-dark); font-weight:700;">
                            <?php echo htmlspecialchars($e['category']); ?>
                        </span>
                        <span class="muted" style="font-size:11px;">• <?= date('M d, Y', strtotime($e['expense_date'])) ?></span>
                    </div>
                    <h2 style="font-size:1.15rem; font-weight:700; margin-top:2px;"><?php echo htmlspecialchars($e['description']); ?></h2>
                    <span class="muted" style="font-size:12px;">Amount: <strong style="color:#ff9f96; font-size:14px;"><?php echo peso($e['amount']); ?></strong></span>
                </div>
                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <a class="btn ghost sm" href="expense_edit.php?id=<?php echo $e['expense_id']; ?>">Edit</a>
                    <form method="POST" action="expenses.php" style="display:inline" onsubmit="return confirm('Delete this expense?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="expense_id" value="<?php echo $e['expense_id']; ?>">
                        <button class="btn ghost sm" type="submit" name="delete_expense" style="color:#ff9f96; border-color:rgba(255,100,100,0.25);">Delete</button>
                    </form>
                </div>
            </div>
        <?php } ?>
    </div>
</section>

<script>
function toggleExpenseForm() {
    var form = document.getElementById('expense-form');
    if (!form) return;
    if (form.style.display === 'none') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth' });
    } else {
        form.style.display = 'none';
    }
}
</script>

<?php require '../includes/footer.php'; ?>
