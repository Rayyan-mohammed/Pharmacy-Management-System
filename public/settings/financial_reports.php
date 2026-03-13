<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);
checkPermission('settings.financial');

$database = new Database();
$db = $database->getConnection();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    verify_csrf_token();
    try {
        $date = $_POST['expense_date'] ?? date('Y-m-d');
        $category = trim($_POST['category'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($category === '' || $amount <= 0) {
            throw new Exception('Expense category and amount are required.');
        }

        $ins = $db->prepare("INSERT INTO expense_entries (expense_date, category, amount, notes, created_by) VALUES (:expense_date, :category, :amount, :notes, :created_by)");
        $ins->bindValue(':expense_date', $date);
        $ins->bindValue(':category', $category);
        $ins->bindValue(':amount', $amount);
        $ins->bindValue(':notes', $notes ?: null);
        $ins->bindValue(':created_by', (int)($_SESSION['currentUser']['user_id'] ?? 0), PDO::PARAM_INT);
        $ins->execute();

        $message = 'Expense entry added.';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$salesStmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(net_total, total_price)),0) as revenue,
                                 COALESCE(SUM(COALESCE(net_total, total_price) - (m.inventory_price * s.quantity)),0) as gross_profit
                          FROM sales s
                          JOIN medicines m ON m.id = s.medicine_id
                          WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date");
$salesStmt->bindValue(':start_date', $startDate);
$salesStmt->bindValue(':end_date', $endDate);
$salesStmt->execute();
$salesAgg = $salesStmt->fetch(PDO::FETCH_ASSOC);

$expenseStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total_expense FROM expense_entries WHERE expense_date BETWEEN :start_date AND :end_date");
$expenseStmt->bindValue(':start_date', $startDate);
$expenseStmt->bindValue(':end_date', $endDate);
$expenseStmt->execute();
$totalExpense = (float)$expenseStmt->fetchColumn();

$revenue = (float)($salesAgg['revenue'] ?? 0);
$grossProfit = (float)($salesAgg['gross_profit'] ?? 0);
$netProfit = $grossProfit - $totalExpense;

$supplierStmt = $db->prepare("SELECT s.name as supplier_name, SUM(p.total_amount) as purchased, SUM(p.amount_paid) as paid, SUM(p.due_amount) as due
                              FROM purchases p
                              JOIN suppliers s ON s.id = p.supplier_id
                              WHERE p.purchase_date BETWEEN :start_date AND :end_date
                              GROUP BY s.id, s.name
                              ORDER BY due DESC, purchased DESC");
$supplierStmt->bindValue(':start_date', $startDate);
$supplierStmt->bindValue(':end_date', $endDate);
$supplierStmt->execute();
$supplierStatement = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);

$customerStmt = $db->prepare("SELECT COALESCE(customer_name,'Walk-in') as customer_name,
                                     COUNT(*) as txn_count,
                                     SUM(COALESCE(net_total, total_price)) as spending
                              FROM sales
                              WHERE DATE(sale_date) BETWEEN :start_date AND :end_date
                              GROUP BY customer_name
                              ORDER BY spending DESC
                              LIMIT 100");
$customerStmt->bindValue(':start_date', $startDate);
$customerStmt->bindValue(':end_date', $endDate);
$customerStmt->execute();
$customerStatement = $customerStmt->fetchAll(PDO::FETCH_ASSOC);

$recentExpenses = $db->query("SELECT * FROM expense_entries ORDER BY expense_date DESC, id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-primary"><div class="container"><a class="navbar-brand fw-bold" href="../dashboard/dashboard.php"><i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro</a></div></nav>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold text-primary mb-0"><i class="bi bi-cash-coin me-2"></i>Financial Reports</h2>
        <form method="GET" class="d-flex gap-2">
            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            <button class="btn btn-outline-primary">Apply</button>
        </form>
    </div>

    <?php if ($message): ?><div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Revenue</small><h5>₹<?php echo number_format($revenue, 2); ?></h5></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Gross Profit</small><h5>₹<?php echo number_format($grossProfit, 2); ?></h5></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Expenses</small><h5>₹<?php echo number_format($totalExpense, 2); ?></h5></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Net Profit (Lite)</small><h5 class="<?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>">₹<?php echo number_format($netProfit, 2); ?></h5></div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0 text-primary">Add Expense</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="add_expense" value="1">
                        <div class="mb-2"><label class="form-label">Date</label><input type="date" class="form-control" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                        <div class="mb-2"><label class="form-label">Category</label><input type="text" class="form-control" name="category" placeholder="Rent/Utility/Salary" required></div>
                        <div class="mb-2"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" class="form-control" name="amount" required></div>
                        <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
                        <button class="btn btn-primary w-100">Save Expense</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0 text-primary">Recent Expenses</h6></div>
                <div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead class="table-light"><tr><th class="px-3">Date</th><th>Category</th><th class="text-end px-3">Amount</th></tr></thead><tbody>
                <?php if (empty($recentExpenses)): ?><tr><td colspan="3" class="text-center text-muted py-3">No expense entries.</td></tr><?php else: foreach ($recentExpenses as $ex): ?>
                    <tr><td class="px-3"><?php echo date('d M Y', strtotime($ex['expense_date'])); ?></td><td><?php echo htmlspecialchars($ex['category']); ?></td><td class="text-end px-3">₹<?php echo number_format((float)$ex['amount'], 2); ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody></table></div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0 text-primary">Supplier Statement</h6></div>
                <div class="table-responsive"><table class="table table-sm table-striped mb-0 align-middle"><thead class="table-light"><tr><th class="px-3">Supplier</th><th class="text-end">Purchased</th><th class="text-end">Paid</th><th class="text-end px-3">Due</th></tr></thead><tbody>
                <?php if (empty($supplierStatement)): ?><tr><td colspan="4" class="text-center text-muted py-3">No supplier data for period.</td></tr><?php else: foreach ($supplierStatement as $s): ?>
                    <tr><td class="px-3"><?php echo htmlspecialchars($s['supplier_name']); ?></td><td class="text-end">₹<?php echo number_format((float)$s['purchased'], 2); ?></td><td class="text-end">₹<?php echo number_format((float)$s['paid'], 2); ?></td><td class="text-end px-3 fw-bold <?php echo (float)$s['due'] > 0 ? 'text-danger' : 'text-success'; ?>">₹<?php echo number_format((float)$s['due'], 2); ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody></table></div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0 text-primary">Customer Statement (Top Spending)</h6></div>
                <div class="table-responsive"><table class="table table-sm table-striped mb-0 align-middle"><thead class="table-light"><tr><th class="px-3">Customer</th><th class="text-center">Transactions</th><th class="text-end px-3">Spend</th></tr></thead><tbody>
                <?php if (empty($customerStatement)): ?><tr><td colspan="3" class="text-center text-muted py-3">No customer sales for period.</td></tr><?php else: foreach ($customerStatement as $c): ?>
                    <tr><td class="px-3"><?php echo htmlspecialchars($c['customer_name'] ?: 'Walk-in'); ?></td><td class="text-center"><?php echo (int)$c['txn_count']; ?></td><td class="text-end px-3">₹<?php echo number_format((float)$c['spending'], 2); ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody></table></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
