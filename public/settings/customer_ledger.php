<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();
$customerModel = new Customer($db);

$search = trim($_GET['search'] ?? '');
$selectedCustomerId = (int)($_GET['customer_id'] ?? 0);

$customers = $customerModel->getSummary($search);
$history = $selectedCustomerId > 0 ? $customerModel->getPurchaseHistory($selectedCustomerId) : [];

$selectedCustomer = null;
if ($selectedCustomerId > 0) {
    foreach ($customers as $c) {
        if ((int)$c['id'] === $selectedCustomerId) {
            $selectedCustomer = $c;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Ledger - PharmaFlow Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>PharmaFlow Pro
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1"><i class="bi bi-people me-2"></i>Customer Ledger</h2>
                <p class="text-muted mb-0">Purchase history, total spend, and last visit by customer mobile.</p>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-9">
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by customer name or mobile">
                    </div>
                    <div class="col-md-3 d-grid">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-primary">Customers</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-3">Customer</th>
                                    <th>Mobile</th>
                                    <th class="text-end">Spent</th>
                                    <th class="text-end px-3">View</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($customers)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No customers found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($customers as $c): ?>
                                        <tr>
                                            <td class="px-3">
                                                <div class="fw-semibold"><?php echo htmlspecialchars($c['customer_name']); ?></div>
                                                <small class="text-muted">Orders: <?php echo (int)$c['total_orders']; ?> | Last: <?php echo !empty($c['last_visit']) ? date('d M Y', strtotime($c['last_visit'])) : '-'; ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($c['mobile']); ?></td>
                                            <td class="text-end fw-bold">₹<?php echo number_format((float)$c['total_spent'], 2); ?></td>
                                            <td class="text-end px-3">
                                                <a class="btn btn-sm btn-outline-primary" href="?search=<?php echo urlencode($search); ?>&customer_id=<?php echo (int)$c['id']; ?>">Open</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-primary">Purchase History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$selectedCustomer): ?>
                            <div class="text-muted">Select a customer to view history.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <div class="fw-semibold"><?php echo htmlspecialchars($selectedCustomer['customer_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($selectedCustomer['mobile']); ?> | Total spent ₹<?php echo number_format((float)$selectedCustomer['total_spent'], 2); ?></small>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Invoice</th>
                                            <th>Medicine</th>
                                            <th>Qty</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($history)): ?>
                                            <tr><td colspan="5" class="text-center text-muted py-3">No purchases found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($history as $h): ?>
                                                <tr>
                                                    <td><?php echo date('d M Y', strtotime($h['sale_date'])); ?></td>
                                                    <td>
                                                        <?php if (!empty($h['invoice_number'])): ?>
                                                            <a href="../sales/invoice.php?invoice=<?php echo urlencode($h['invoice_number']); ?>" target="_blank"><?php echo htmlspecialchars($h['invoice_number']); ?></a>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($h['medicine_name']); ?></td>
                                                    <td><?php echo (int)$h['quantity']; ?></td>
                                                    <td class="text-end">₹<?php echo number_format((float)$h['line_total'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

