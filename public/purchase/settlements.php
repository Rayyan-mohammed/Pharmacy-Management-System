<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);
checkPermission('purchase.create');

$database = new Database();
$db = $database->getConnection();
$purchase = new Purchase($db);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    try {
        $purchaseId = (int)($_POST['purchase_id'] ?? 0);
        $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
        $amount = (float)($_POST['amount'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'Cash';
        $referenceNo = trim($_POST['reference_no'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $purchase->addSettlement($purchaseId, $paymentDate, $amount, $paymentMethod, $referenceNo, $notes, (int)($_SESSION['currentUser']['user_id'] ?? 0));
        $message = 'Settlement recorded successfully.';
        $messageType = 'success';

        try {
            $al = new ActivityLog($db);
            $al->log('PURCHASE_PAYMENT', 'Recorded supplier settlement for purchase #' . $purchaseId . ' amount ₹' . number_format($amount, 2), 'purchase', $purchaseId);
        } catch (Exception $e) {}
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

$outstanding = $purchase->getOutstandingBills(0, 300);
$history = $purchase->getSettlementHistory(0, 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Settlements - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-primary">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php"><i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro</a>
        <div class="d-flex gap-2">
            <a href="purchase-history.php" class="btn btn-sm btn-light">Purchase History</a>
            <a href="supplier-payables.php" class="btn btn-sm btn-outline-light">Payable Aging</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <h2 class="fw-bold text-primary mb-3"><i class="bi bi-wallet2 me-2"></i>Supplier Due Settlement Ledger</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0 text-primary">Record Settlement</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div class="mb-3">
                            <label class="form-label">Bill</label>
                            <select class="form-select" name="purchase_id" required>
                                <option value="">Select outstanding bill</option>
                                <?php foreach ($outstanding as $b): ?>
                                    <option value="<?php echo (int)$b['id']; ?>"><?php echo htmlspecialchars($b['supplier_name'] . ' | ' . $b['invoice_number'] . ' | Due ₹' . number_format((float)$b['due_amount'], 2)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Payment Date</label>
                                <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Method</label>
                                <select class="form-select" name="payment_method">
                                    <option>Cash</option>
                                    <option>Card</option>
                                    <option>UPI</option>
                                    <option>Bank</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reference</label>
                                <input type="text" class="form-control" name="reference_no" placeholder="Optional">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        <button class="btn btn-success w-100"><i class="bi bi-check-circle me-1"></i>Post Settlement</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0 text-primary">Outstanding Bills</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 align-middle">
                        <thead class="table-light"><tr><th class="px-3">Invoice</th><th>Supplier</th><th class="text-end">Due</th><th class="text-center">Age</th></tr></thead>
                        <tbody>
                            <?php if (empty($outstanding)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No outstanding bills.</td></tr>
                            <?php else: foreach ($outstanding as $b): ?>
                                <tr>
                                    <td class="px-3"><?php echo htmlspecialchars($b['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($b['supplier_name']); ?></td>
                                    <td class="text-end fw-bold text-danger">₹<?php echo number_format((float)$b['due_amount'], 2); ?></td>
                                    <td class="text-center"><?php echo max(0, (int)$b['bill_age_days']); ?>d</td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0 text-primary">Recent Settlement Entries</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 align-middle">
                        <thead class="table-light"><tr><th class="px-3">Date</th><th>Invoice</th><th>Supplier</th><th>Method</th><th class="text-end px-3">Amount</th></tr></thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No settlements recorded yet.</td></tr>
                            <?php else: foreach ($history as $h): ?>
                                <tr>
                                    <td class="px-3"><?php echo date('d M Y', strtotime($h['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($h['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($h['supplier_name']); ?></td>
                                    <td><?php echo htmlspecialchars($h['payment_method']); ?></td>
                                    <td class="text-end px-3">₹<?php echo number_format((float)$h['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
