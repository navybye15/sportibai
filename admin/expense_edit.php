<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

if ($conn == null) {
    die('Database is not connected.');
}

$expense_id = 0;
if (isset($_GET['id'])) {
    $expense_id = (int) $_GET['id'];
}

if (isset($_POST['save'])) {
    $expense_date = $_POST['expense_date'];
    $description = strip_tags(trim($_POST['description']));
    $category = strip_tags(trim($_POST['category']));
    $amount = (float) $_POST['amount'];

    if ($description == '' || $category == '' || $amount <= 0) {
        $_SESSION['message'] = 'Please enter a valid description, category, and amount.';
        header('Location: expense_edit.php?id=' . $expense_id);
        exit;
    }

    try {
        $sql = "UPDATE expenses SET expense_date = ?, description = ?, category = ?, amount = ? WHERE expense_id = ?";
        mysqli_execute_query($conn, $sql, array($expense_date, $description, $category, $amount, $expense_id));
        $_SESSION['success'] = 'Expense updated.';
        header('Location: expenses.php');
        exit;
    } catch (mysqli_sql_exception $e) {
        $_SESSION['message'] = 'Could not save the expense. Please check the date and try again.';
        header('Location: expense_edit.php?id=' . $expense_id);
        exit;
    }
}

$sql = "SELECT * FROM expenses WHERE expense_id = ? LIMIT 1";
$result = mysqli_execute_query($conn, $sql, array($expense_id));
$expense = mysqli_fetch_assoc($result);
if ($expense == null) {
    header('Location: expenses.php');
    exit;
}

$base = '../';
$page_title = 'Edit Expense | Sportibai';
require '../includes/header.php';
?>
<div class="reveal in">
    <form class="form" method="POST" action="expense_edit.php?id=<?php echo $expense_id; ?>">
        <div>
            <div class="hero-eyebrow">[ EDIT DISBURSEMENT ]</div>
            <h1 style="font-size: var(--text-display-m); margin-top:6px;">Edit Expense #<?php echo $expense_id; ?></h1>
        </div>
        <?php require '../includes/alert.php'; ?>

        <div>
            <label class="form-label">Expense Date</label>
            <input type="date" name="expense_date" value="<?php echo htmlspecialchars($expense['expense_date']); ?>" required style="margin-top:0.75rem;">
        </div>

        <div>
            <label class="form-label">Description</label>
            <input type="text" name="description" value="<?php echo htmlspecialchars($expense['description']); ?>" required style="margin-top:0.75rem;">
        </div>

        <div>
            <label class="form-label">Category</label>
            <input type="text" name="category" value="<?php echo htmlspecialchars($expense['category']); ?>" required style="margin-top:0.75rem;">
        </div>

        <div>
            <label class="form-label">Amount (₱)</label>
            <input type="number" step="0.01" name="amount" value="<?php echo htmlspecialchars($expense['amount']); ?>" required style="margin-top:0.75rem;">
        </div>

        <div style="display:flex; gap:1rem; margin-top:0.5rem;">
            <button class="btn" style="flex:1;" type="submit" name="save">Save Changes</button>
            <a class="btn ghost" href="expenses.php">Cancel</a>
        </div>
    </form>
</div>
<?php require '../includes/footer.php'; ?>
