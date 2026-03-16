<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();
$purchase = new Purchase($db);
$supplier = new Supplier($db);

$suppliers = $supplier->read()->fetchAll(PDO::FETCH_ASSOC);
$supplierId = (int)($_GET['supplier_id'] ?? 0);

$aging = $purchase->getSupplierPayableAging();
$bills = $purchase->getOutstandingBills($supplierId, 300);

$totalDue = 0;
$total0_30 = 0;
$total31_60 = 0;
$total61_90 = 0;
$total90Plus = 0;
foreach ($aging as $a) {
    $totalDue += (float)$a['total_due'];
    $total0_30 += (float)$a['bucket_0_30'];
    $total31_60 += (float)$a['bucket_31_60'];
    $total61_90 += (float)$a['bucket_61_90'];
    $total90Plus += (float)$a['bucket_90_plus'];
}

$exportAging = '../api/export_csv.php?type=supplier_payables';
$exportBills = '../api/export_csv.php?type=purchases&payment_status=Pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Payable Aging - PharmaFlow Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-primary">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php"><i class="bi bi-heart-pulse-fill me-2"></i>PharmaFlow Pro</a>
        <div class="d-flex gap-2">
            <a href="purchase-management.php" class="btn btn-sm btn-light"><i class="bi bi-truck me-1"></i>New Purchase</a>
            <a href="purchase-history.php" class="btn btn-sm btn-outline-light"><i class="bi bi-clock-history me-1"></i>Purchase History</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="fw-bold text-primary mb-1"><i class="bi bi-hourglass-split me-2"></i>Supplier Payable Aging</h2>
            <p class="text-muted mb-0">Track overdue dues by supplier and age bucket.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo $exportAging; ?>" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Aging</a>
            <a href="<?php echo $exportBills; ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-text me-1"></i>Export Open Bills</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Total Due</small><h5 class="text-danger mb-0">₹<?php echo number_format($totalDue, 2); ?></h5></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">0-30 Days</small><h5 class="mb-0">₹<?php echo number_format($total0_30, 2); ?></h5></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">31-60</small><h6 class="mb-0">₹<?php echo number_format($total31_60, 2); ?></h6></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">61-90</small><h6 class="mb-0">₹<?php echo number_format($total61_90, 2); ?></h6></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">90+ Days</small><h6 class="text-danger mb-0">₹<?php echo number_format($total90Plus, 2); ?></h6></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><h6 class="mb-0 text-primary">Supplier Aging Summary</h6></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="px-3">Supplier</th><th class="text-end">0-30</th><th class="text-end">31-60</th><th class="text-end">61-90</th><th class="text-end">90+</th><th class="text-end">Total Due</th><th class="text-center px-3">Bills</th></tr></thead>
                <tbody>
                    <?php if (empty($aging)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No supplier dues pending.</td></tr>
                    <?php else: ?>
                        <?php foreach ($aging as $a): ?>
                            <tr>
                                <td class="px-3 fw-semibold"><?php echo htmlspecialchars($a['supplier_name']); ?></td>
                                <td class="text-end">₹<?php echo number_format((float)$a['bucket_0_30'], 2); ?></td>
                                <td class="text-end">₹<?php echo number_format((float)$a['bucket_31_60'], 2); ?></td>
                                <td class="text-end">₹<?php echo number_format((float)$a['bucket_61_90'], 2); ?></td>
                                <td class="text-end text-danger">₹<?php echo number_format((float)$a['bucket_90_plus'], 2); ?></td>
                                <td class="text-end fw-bold text-danger">₹<?php echo number_format((float)$a['total_due'], 2); ?></td>
                                <td class="text-center px-3"><span class="badge bg-light text-dark border"><?php echo (int)$a['due_bills']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-primary">Outstanding Bills</h6>
            <form method="GET" class="d-flex gap-2">
                <select class="form-select form-select-sm" name="supplier_id">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>" <?php echo $supplierId === (int)$s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary">Filter</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
                <thead class="table-light"><tr><th class="px-3">Invoice</th><th>Supplier</th><th>Date</th><th class="text-center">Age</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end px-3">Due</th></tr></thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No outstanding bills.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bills as $b): ?>
                            <tr>
                                <td class="px-3 fw-semibold"><?php echo htmlspecialchars($b['invoice_number']); ?></td>
                                <td><?php echo htmlspecialchars($b['supplier_name']); ?></td>
                                <td><?php echo date('d M Y', strtotime($b['purchase_date'])); ?></td>
                                <td class="text-center"><?php echo max(0, (int)$b['bill_age_days']); ?>d</td>
                                <td class="text-end">₹<?php echo number_format((float)$b['total_amount'], 2); ?></td>
                                <td class="text-end">₹<?php echo number_format((float)$b['amount_paid'], 2); ?></td>
                                <td class="text-end px-3 fw-bold text-danger">₹<?php echo number_format((float)$b['due_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>

