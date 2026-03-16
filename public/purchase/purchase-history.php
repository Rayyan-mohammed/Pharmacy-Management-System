<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();
$purchase = new Purchase($db);
$supplier = new Supplier($db);

$suppliers = $supplier->read()->fetchAll(PDO::FETCH_ASSOC);

$filters = [
    'supplier_id' => (int)($_GET['supplier_id'] ?? 0),
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
    'payment_status' => $_GET['payment_status'] ?? ''
];

$allowedStatuses = ['Paid', 'Partial', 'Pending'];
if (!in_array($filters['payment_status'], $allowedStatuses, true)) {
    $filters['payment_status'] = '';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$result = $purchase->getPurchaseHistory($filters, $page, $perPage);
$rows = $result['rows'];
$totalRecords = (int)$result['total'];
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$exportQuery = http_build_query([
    'type' => 'purchases',
    'supplier_id' => $filters['supplier_id'] ?: null,
    'start_date' => $filters['start_date'] ?: null,
    'end_date' => $filters['end_date'] ?: null,
    'payment_status' => $filters['payment_status'] ?: null
]);

$detailsPurchaseId = (int)($_GET['details'] ?? 0);
$detailItems = $detailsPurchaseId > 0 ? $purchase->getPurchaseItems($detailsPurchaseId) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History - PharmaFlow Pro</title>
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
            <a href="supplier-payables.php" class="btn btn-sm btn-outline-light"><i class="bi bi-hourglass-split me-1"></i>Payable Aging</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="fw-bold text-primary mb-1"><i class="bi bi-clock-history me-2"></i>Purchase History</h2>
            <p class="text-muted mb-0">Filter and audit supplier purchases with due aging visibility.</p>
        </div>
        <a href="../api/export_csv.php?<?php echo htmlspecialchars($exportQuery); ?>" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small">Supplier</label>
                    <select class="form-select" name="supplier_id">
                        <option value="">All suppliers</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo $filters['supplier_id'] === (int)$s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small">From</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small">To</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($filters['end_date']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small">Status</label>
                    <select class="form-select" name="payment_status">
                        <option value="">All</option>
                        <option value="Paid" <?php echo $filters['payment_status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Partial" <?php echo $filters['payment_status'] === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="Pending" <?php echo $filters['payment_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
                    <a href="purchase-history.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-3">Date</th>
                        <th>Invoice</th>
                        <th>Supplier</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Due</th>
                        <th class="text-center">Age</th>
                        <th class="text-center">Status</th>
                        <th class="text-end px-3">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No purchase records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="px-3"><?php echo date('d M Y', strtotime($r['purchase_date'])); ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($r['invoice_number']); ?></td>
                                <td><?php echo htmlspecialchars($r['supplier_name']); ?></td>
                                <td class="text-end">₹<?php echo number_format((float)$r['total_amount'], 2); ?></td>
                                <td class="text-end">₹<?php echo number_format((float)$r['amount_paid'], 2); ?></td>
                                <td class="text-end fw-bold <?php echo ((float)$r['due_amount'] > 0) ? 'text-danger' : 'text-success'; ?>">₹<?php echo number_format((float)$r['due_amount'], 2); ?></td>
                                <td class="text-center"><?php echo max(0, (int)$r['bill_age_days']); ?>d</td>
                                <td class="text-center">
                                    <?php
                                    $cls = $r['payment_status'] === 'Paid' ? 'success' : ($r['payment_status'] === 'Partial' ? 'warning text-dark' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $cls; ?>"><?php echo htmlspecialchars($r['payment_status']); ?></span>
                                </td>
                                <td class="text-end px-3">
                                    <a class="btn btn-sm btn-outline-primary" href="?<?php echo http_build_query(array_merge($_GET, ['details' => (int)$r['id'])); ?>#details"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <small class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?></small>
                <div class="btn-group btn-group-sm">
                    <?php
                    $prev = max(1, $page - 1);
                    $next = min($totalPages, $page + 1);
                    $base = $_GET;
                    $base['page'] = $prev;
                    ?>
                    <a class="btn btn-outline-secondary <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo http_build_query($base); ?>">Prev</a>
                    <button type="button" class="btn btn-outline-secondary disabled">Page <?php echo $page; ?> / <?php echo $totalPages; ?></button>
                    <?php $base['page'] = $next; ?>
                    <a class="btn btn-outline-secondary <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="?<?php echo http_build_query($base); ?>">Next</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm mt-4" id="details">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-primary">Purchase Line Items<?php echo $detailsPurchaseId > 0 ? ' - GRN #' . $detailsPurchaseId : ''; ?></h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-light"><tr><th class="px-3">Medicine</th><th>Batch</th><th>Expiry</th><th class="text-end">Qty</th><th class="text-end">Cost</th><th class="text-end">Sale</th><th class="text-end px-3">Line Total</th></tr></thead>
                    <tbody>
                        <?php if ($detailsPurchaseId <= 0): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">Select any purchase row to inspect items.</td></tr>
                        <?php elseif (empty($detailItems)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">No line items found for this purchase.</td></tr>
                        <?php else: ?>
                            <?php foreach ($detailItems as $i): ?>
                                <tr>
                                    <td class="px-3"><?php echo htmlspecialchars($i['medicine_name']); ?></td>
                                    <td><?php echo htmlspecialchars($i['batch_number']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($i['expiration_date'])); ?></td>
                                    <td class="text-end"><?php echo (int)$i['quantity']; ?></td>
                                    <td class="text-end">₹<?php echo number_format((float)$i['cost_price'], 2); ?></td>
                                    <td class="text-end">₹<?php echo number_format((float)$i['sale_price'], 2); ?></td>
                                    <td class="text-end px-3">₹<?php echo number_format((float)$i['line_total'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>

