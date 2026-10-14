<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

if ($conn == null) {
    die('Database is not connected.');
}

if (isset($_POST['add_expense'])) {
    $expense_date = $_POST['expense_date'];
    $description = strip_tags(trim($_POST['description']));
    $category = strip_tags(trim($_POST['category']));
    $amount = (float) $_POST['amount'];

    if ($description == '' || $amount <= 0) {
        $_SESSION['message'] = 'Please enter a valid description and amount.';
    } else {
        $sql = "INSERT INTO expenses (expense_date, description, category, amount) VALUES (?, ?, ?, ?)";
        mysqli_execute_query($conn, $sql, array($expense_date, $description, $category, $amount));
        $_SESSION['success'] = 'Expense added.';
    }
    header('Location: expenses.php');
    exit;
}

if (isset($_POST['delete_expense'])) {
    $expense_id = (int) $_POST['expense_id'];
    $sql = "DELETE FROM expenses WHERE expense_id = ?";
    mysqli_execute_query($conn, $sql, array($expense_id));
    $_SESSION['success'] = 'Expense deleted.';
    header('Location: expenses.php');
    exit;
}

$expenses = array();
$result = mysqli_query($conn, "SELECT * FROM expenses ORDER BY expense_date DESC, expense_id DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $expenses[] = $row;
}

$base = '../';
$page_title = 'Expenses | Sportibai';
require '../includes/header.php';
?>
<section class="reveal">
    <div style="margin-bottom:1rem;">
        <div class="hero-eyebrow">[ FINANCIAL AUDIT ]</div>
        <h1 style="font-size: var(--text-display-l)">Store Expenses</h1>
    </div>
    <?php require '../includes/alert.php'; ?>

    <form class="form" method="POST" action="expenses.php" style="margin: 2rem 0; max-width: 100%;">
        <div>
            <div class="hero-eyebrow">[ RECORD DISBURSEMENT ]</div>
            <h2 style="font-size:1.35rem; margin-top:4px;">Add Expense Entry</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
            <div>
                <label class="form-label">Expense Date</label>
                <input type="date" name="expense_date" required value="<?php echo date('Y-m-d'); ?>" style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Description</label>
                <input type="text" name="description" placeholder="e.g. Courier Shipping, Warehouse Rent" required style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Expense Category</label>
                <input type="text" name="category" placeholder="Supplies, Freight, Marketing" required style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Amount (₱)</label>
                <input type="number" step="0.01" name="amount" placeholder="0.00" required style="margin-top:0.6rem;">
            </div>
        </div>
        <div>
            <button class="btn" type="submit" name="add_expense">+ Record Expense</button>
        </div>
    </form>

    <div class="lines" style="padding-top:1rem;">
        <div class="rail-section-title" style="margin-bottom:0.5rem;">Expense Log (<?php echo count($expenses); ?> entries)</div>
        <?php foreach ($expenses as $e) { ?>
            <div class="line">
                <div class="tile" style="--h: <?php echo ($e['expense_id'] * 47) % 360; ?>; aspect-ratio:1; max-height:64px;"></div>
                <div>
                    <span class="brand-tag" style="font-size:var(--text-label); letter-spacing:var(--tracking-label); text-transform:uppercase; color:var(--color-accent-on-dark); font-weight:600;">
                        <?php echo htmlspecialchars($e['category']); ?> &nbsp;•&nbsp; Date: <?php echo $e['expense_date']; ?>
                    </span>
                    <h2 style="font-size:1.15rem;"><?php echo htmlspecialchars($e['description']); ?></h2>
                    <span class="muted" style="font-size:var(--text-body-sm);">Disbursed: <strong style="color:#ff9f96;"><?php echo peso($e['amount']); ?></strong></span>
                </div>
                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <a class="btn ghost sm" href="expense_edit.php?id=<?php echo $e['expense_id']; ?>">Edit</a>
                    <form method="POST" action="expenses.php" style="display:inline" onsubmit="return confirm('Delete this expense?');">
                        <input type="hidden" name="expense_id" value="<?php echo $e['expense_id']; ?>">
                        <button class="btn ghost sm" type="submit" name="delete_expense" style="color:#ff9f96; border-color:rgba(255,100,100,0.25);">Delete</button>
                    </form>
                </div>
            </div>
        <?php } ?>
    </div>
</section>
<?php require '../includes/footer.php'; ?>
