<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist', 'Staff']);

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$userRole = $_SESSION['currentUser']['role'] ?? 'Staff';

function sales_column_exists($db, $column) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = :column");
        $stmt->bindValue(':column', $column);
        $stmt->execute();
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Exception $e) {
        return false;
    }
}

$hasPaymentMethod = sales_column_exists($db, 'payment_method');
$hasCustomerPhone = sales_column_exists($db, 'customer_phone');
$hasNetTotal = sales_column_exists($db, 'net_total');
$hasTaxAmount = sales_column_exists($db, 'tax_amount');
$hasDiscountAmount = sales_column_exists($db, 'discount_amount');
$hasPaymentReference = sales_column_exists($db, 'payment_reference');
$revenueExpr = $hasNetTotal ? 'COALESCE(s.net_total, s.total_price)' : 's.total_price';

// Filters
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';
$allowedMethods = ['Cash', 'Card', 'UPI'];
if (!in_array($paymentMethod, $allowedMethods, true)) {
    $paymentMethod = '';
}
$filterError = '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

if ($startDate && $endDate && $startDate > $endDate) {
    $filterError = 'Start date cannot be after end date.';
}

// Build filtered sales query
$conditions = [];
$params = [];
if ($startDate) {
    $conditions[] = "DATE(s.sale_date) >= :start_date";
    $params[':start_date'] = $startDate;
}
if ($endDate) {
    $conditions[] = "DATE(s.sale_date) <= :end_date";
    $params[':end_date'] = $endDate;
}
if ($hasPaymentMethod && $paymentMethod !== '') {
    $conditions[] = "s.payment_method = :payment_method";
    $params[':payment_method'] = $paymentMethod;
}

$whereSql = count($conditions) ? (' WHERE ' . implode(' AND ', $conditions)) : '';

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM sales s" . $whereSql;
$countStmt = $db->prepare($countQuery);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$totalRecords = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = max(1, ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Fetch all matching rows for aggregate stats (lightweight - no JOIN needed for totals)
$aggQuery = "SELECT SUM({$revenueExpr}) as totalRevenue, 
             SUM({$revenueExpr} - (m.inventory_price * s.quantity)) as totalProfit,
             SUM(s.quantity) as totalQty,
             COUNT(DISTINCT s.customer_name) as uniqueCustomers
             FROM sales s JOIN medicines m ON s.medicine_id = m.id" . $whereSql;
$aggStmt = $db->prepare($aggQuery);
foreach ($params as $key => $val) {
    $aggStmt->bindValue($key, $val);
}
$aggStmt->execute();
$agg = $aggStmt->fetch(PDO::FETCH_ASSOC);
$totalRevenue = (float)($agg['totalRevenue'] ?? 0);
$totalProfit = (float)($agg['totalProfit'] ?? 0);
$totalQty = (int)($agg['totalQty'] ?? 0);
$uniqueCustomers = (int)($agg['uniqueCustomers'] ?? 0);
$avgOrderValue = $totalRecords > 0 ? $totalRevenue / $totalRecords : 0;
$profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

// Paginated query for table display
$selectCustomerPhone = $hasCustomerPhone ? ', s.customer_phone' : ', NULL AS customer_phone';
$selectPaymentMethod = $hasPaymentMethod ? ', s.payment_method' : ', NULL AS payment_method';
$selectNetTotal = $hasNetTotal ? ', s.net_total' : ', s.total_price as net_total';
$selectTaxAmount = $hasTaxAmount ? ', s.tax_amount' : ', 0 as tax_amount';
$selectDiscountAmount = $hasDiscountAmount ? ', s.discount_amount' : ', 0 as discount_amount';
$selectPaymentReference = $hasPaymentReference ? ', s.payment_reference' : ', NULL as payment_reference';
$query = "SELECT s.*, m.name as medicine_name, m.inventory_price" . $selectCustomerPhone . $selectPaymentMethod . $selectNetTotal . $selectTaxAmount . $selectDiscountAmount . $selectPaymentReference . "
          FROM sales s 
          JOIN medicines m ON s.medicine_id = m.id" .
          $whereSql .
          " ORDER BY s.sale_date DESC LIMIT $perPage OFFSET $offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For charts: daily and medicine aggregation (across all filtered data, not just current page)
$chartQuery = "SELECT DATE(s.sale_date) as day, SUM({$revenueExpr}) as revenue, 
               SUM({$revenueExpr} - (m.inventory_price * s.quantity)) as profit, COUNT(*) as cnt
               FROM sales s JOIN medicines m ON s.medicine_id = m.id" . $whereSql . 
               " GROUP BY DATE(s.sale_date) ORDER BY day ASC";
$chartStmt = $db->prepare($chartQuery);
foreach ($params as $key => $val) {
    $chartStmt->bindValue($key, $val);
}
$chartStmt->execute();
$dailyAgg = [];
while ($row = $chartStmt->fetch(PDO::FETCH_ASSOC)) {
    $dailyAgg[$row['day']] = ['revenue' => (float)$row['revenue'], 'profit' => (float)$row['profit'], 'count' => (int)$row['cnt']];
}

$medQuery = "SELECT m.name, SUM({$revenueExpr}) as revenue, SUM(s.quantity) as qty
             FROM sales s JOIN medicines m ON s.medicine_id = m.id" . $whereSql . 
             " GROUP BY m.id, m.name ORDER BY revenue DESC LIMIT 8";
$medStmt = $db->prepare($medQuery);
foreach ($params as $key => $val) {
    $medStmt->bindValue($key, $val);
}
$medStmt->execute();
$topMedByRevenue = [];
while ($row = $medStmt->fetch(PDO::FETCH_ASSOC)) {
    $topMedByRevenue[$row['name']] = ['revenue' => (float)$row['revenue'], 'qty' => (int)$row['qty']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Records - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="../styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Header & Stats -->
        <div class="row align-items-center mb-3">
            <div class="col">
                <h2 class="fw-bold text-primary mb-1">Sales Records</h2>
                <p class="text-secondary mb-0">Overview of transactions and revenue.</p>
            </div>
            <div class="col-auto d-flex gap-2 align-items-center">
                <a href="../api/export_csv.php?type=sales<?php echo $startDate ? '&start_date=' . urlencode($startDate) : ''; ?><?php echo $endDate ? '&end_date=' . urlencode($endDate) : ''; ?><?php echo ($hasPaymentMethod && $paymentMethod) ? '&payment_method=' . urlencode($paymentMethod) : ''; ?>" class="btn btn-success">
                    <i class="bi bi-file-earmark-spreadsheet me-2"></i>Export CSV
                </a>
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-2"></i>Print Report
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <?php if($filterError): ?>
                    <div class="alert alert-danger py-2 mb-3"><?php echo htmlspecialchars($filterError); ?></div>
                <?php endif; ?>
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label text-muted small">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    <?php if ($hasPaymentMethod): ?>
                    <div class="col-md-4">
                        <label class="form-label text-muted small">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="">All Methods</option>
                            <option value="Cash" <?php echo $paymentMethod === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="Card" <?php echo $paymentMethod === 'Card' ? 'selected' : ''; ?>>Card</option>
                            <option value="UPI" <?php echo $paymentMethod === 'UPI' ? 'selected' : ''; ?>>UPI</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-2"></i>Apply Filter</button>
                        <?php if($startDate || $endDate || $paymentMethod): ?>
                            <a href="sales_records.php" class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 bg-primary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-currency-dollar text-primary fs-3"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Revenue</h6>
                                <h3 class="mb-0 fw-bold">₹<?php echo number_format($totalRevenue, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($userRole !== 'Staff'): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 bg-success bg-opacity-10 p-3 rounded">
                                <i class="bi bi-graph-up-arrow text-success fs-3"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Profit</h6>
                                <h3 class="mb-0 fw-bold text-success">₹<?php echo number_format($totalProfit, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 bg-info bg-opacity-10 p-3 rounded">
                                <i class="bi bi-receipt text-info fs-3"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="text-muted text-uppercase small fw-bold mb-1">Transactions</h6>
                                <h3 class="mb-0 fw-bold"><?php echo $totalRecords; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="card border-0 shadow">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 text-primary"><i class="bi bi-table me-2"></i>Transaction History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="py-3">Medicine</th>
                                <th class="py-3 text-center">Qty</th>
                                <th class="py-3 text-end">Price</th>
                                <th class="py-3 text-end">Subtotal</th>
                                <th class="py-3 text-end">Net</th>
                                <th class="py-3">Customer</th>
                                <th class="py-3">Payment</th>
                                <?php if ($userRole !== 'Staff'): ?>
                                    <th class="py-3 text-end px-4">Profit</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): 
                                $unitPrice = isset($sale['unit_price']) && $sale['unit_price'] > 0 
                                            ? $sale['unit_price'] 
                                            : ($sale['quantity'] > 0 ? $sale['total_price'] / $sale['quantity'] : 0);
                                
                                $profit = isset($sale['profit']) && $sale['profit'] != 0 
                                          ? $sale['profit'] 
                                          : (($sale['net_total'] ?? $sale['total_price']) - ($sale['inventory_price'] * $sale['quantity']));
                            ?>
                                <tr>
                                    <td class="px-4">
                                        <div class="fw-bold text-dark"><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></div>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-primary"><?php echo htmlspecialchars($sale['medicine_name']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border"><?php echo $sale['quantity']; ?></span>
                                    </td>
                                    <td class="text-end text-muted">₹<?php echo number_format($unitPrice, 2); ?></td>
                                    <td class="text-end fw-bold">₹<?php echo number_format($sale['total_price'], 2); ?></td>
                                    <td class="text-end fw-bold text-primary">₹<?php echo number_format($sale['net_total'] ?? $sale['total_price'], 2); ?></td>
                                    <td>
                                        <?php if(!empty($sale['customer_name'])): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-light rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">
                                                    <i class="bi bi-person text-secondary" style="font-size: 0.8rem;"></i>
                                                </div>
                                                <div>
                                                    <div><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                                                    <?php if (!empty($sale['customer_phone'])): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($sale['customer_phone']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($sale['payment_method'])): ?>
                                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($sale['payment_method']); ?></span>
                                            <?php if (!empty($sale['payment_reference'])): ?>
                                                <div><small class="text-muted"><?php echo htmlspecialchars($sale['payment_reference']); ?></small></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($userRole !== 'Staff'): ?>
                                    <?php 
                                        $profitClass = $profit >= 0 ? 'bg-success text-white' : 'bg-danger text-white';
                                    ?>
                                    <td class="text-end px-4">
                                        <span class="badge rounded-pill <?php echo $profitClass; ?>">
                                            <?php echo $profit >= 0 ? '+' : ''; ?>₹<?php echo number_format($profit, 2); ?>
                                        </span>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="5" class="text-end fw-bold py-3">Totals:</td>
                                <td class="text-end fw-bold py-3 text-primary">₹<?php echo number_format($totalRevenue, 2); ?></td>
                                <td></td>
                                <td></td>
                                <?php if ($userRole !== 'Staff'): ?>
                                    <td class="text-end fw-bold py-3 text-success px-4">₹<?php echo number_format($totalProfit, 2); ?></td>
                                <?php endif; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> records</small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            $queryParams = [];
                            if ($startDate) $queryParams['start_date'] = $startDate;
                            if ($endDate) $queryParams['end_date'] = $endDate;
                            if ($paymentMethod) $queryParams['payment_method'] = $paymentMethod;
                            
                            $buildUrl = function($p) use ($queryParams) {
                                $queryParams['page'] = $p;
                                return 'sales_records.php?' . http_build_query($queryParams);
                            };
                            ?>
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $buildUrl($page - 1); ?>">&laquo;</a>
                            </li>
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            if ($startPage > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?php echo $buildUrl(1); ?>">1</a></li>
                                <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                            <?php endif;
                            for ($p = $startPage; $p <= $endPage; $p++): ?>
                                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $buildUrl($p); ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor;
                            if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                <li class="page-item"><a class="page-link" href="<?php echo $buildUrl($totalPages); ?>"><?php echo $totalPages; ?></a></li>
                            <?php endif; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $buildUrl($page + 1); ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Analytics Section -->
        <?php if ($totalRecords > 0): ?>
        <hr class="my-5">
        <h4 class="fw-bold text-primary mb-4"><i class="bi bi-bar-chart-line me-2"></i>Sales Analytics</h4>

        <!-- Extra Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="card-body py-2">
                        <i class="bi bi-cart-check text-primary fs-4"></i>
                        <h5 class="fw-bold mb-0 mt-1"><?php echo number_format($totalQty); ?></h5>
                        <small class="text-muted">Units Sold</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="card-body py-2">
                        <i class="bi bi-cash text-success fs-4"></i>
                        <h5 class="fw-bold mb-0 mt-1">₹<?php echo number_format($avgOrderValue, 2); ?></h5>
                        <small class="text-muted">Avg Order Value</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="card-body py-2">
                        <i class="bi bi-people text-info fs-4"></i>
                        <h5 class="fw-bold mb-0 mt-1"><?php echo $uniqueCustomers; ?></h5>
                        <small class="text-muted">Unique Customers</small>
                    </div>
                </div>
            </div>
            <?php if ($userRole !== 'Staff'): ?>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="card-body py-2">
                        <i class="bi bi-percent text-warning fs-4"></i>
                        <h5 class="fw-bold mb-0 mt-1"><?php echo round($profitMargin, 1); ?>%</h5>
                        <small class="text-muted">Profit Margin</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Charts -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Daily Revenue & Profit</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyChart" height="260"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-pie-chart me-2 text-success"></i>Revenue by Medicine</h6>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <canvas id="medicinePieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (count($sales) > 0): ?>
    <script>
        // Daily Revenue & Profit line chart
        new Chart(document.getElementById('dailyChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($d){ return date('d M', strtotime($d)); }, array_keys($dailyAgg))); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_values(array_map(function($d){ return round($d['revenue'], 2); }, $dailyAgg))); ?>,
                    borderColor: '#4e79a7',
                    backgroundColor: 'rgba(78,121,167,0.1)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#4e79a7'
                }<?php if ($userRole !== 'Staff'): ?>, {
                    label: 'Profit',
                    data: <?php echo json_encode(array_values(array_map(function($d){ return round($d['profit'], 2); }, $dailyAgg))); ?>,
                    borderColor: '#59a14f',
                    backgroundColor: 'rgba(89,161,79,0.1)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#59a14f'
                }<?php endif; ?>]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ₹' + ctx.parsed.y.toLocaleString() } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2,4] }, ticks: { callback: v => '₹' + v.toLocaleString() } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Medicine revenue doughnut
        const pieColors = ['#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f','#edc948','#b07aa1','#ff9da7'];
        new Chart(document.getElementById('medicinePieChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($topMedByRevenue)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values(array_map(function($d){ return round($d['revenue'], 2); }, $topMedByRevenue))); ?>,
                    backgroundColor: pieColors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 11 } } },
                    tooltip: { callbacks: { label: ctx => ctx.label + ': ₹' + ctx.parsed.toLocaleString() } }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html> 