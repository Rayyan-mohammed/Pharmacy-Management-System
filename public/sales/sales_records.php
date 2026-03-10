<?php
require_once '../../app/auth.php';

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$userRole = $_SESSION['currentUser']['role'] ?? 'Staff';

// Filters
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';
$filterError = '';

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

$whereSql = count($conditions) ? (' WHERE ' . implode(' AND ', $conditions)) : '';

$query = "SELECT s.*, m.name as medicine_name, m.inventory_price 
          FROM sales s 
          JOIN medicines m ON s.medicine_id = m.id" .
          $whereSql .
          " ORDER BY s.sale_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalRevenue = 0;
$totalProfit = 0;
foreach ($sales as $sale) {
    $totalRevenue += $sale['total_price'];
    $totalProfit += $sale['total_price'] - ($sale['inventory_price'] * $sale['quantity']);
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
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="../dashboard/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <!-- Header & Stats -->
        <div class="row align-items-center mb-3">
            <div class="col">
                <h2 class="fw-bold text-primary mb-1">Sales Records</h2>
                <p class="text-secondary mb-0">Overview of transactions and revenue.</p>
            </div>
            <div class="col-auto d-flex gap-2 align-items-center">
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
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-2"></i>Apply Filter</button>
                        <?php if($startDate || $endDate): ?>
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
                                <h3 class="mb-0 fw-bold"><?php echo count($sales); ?></h3>
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
                                <th class="py-3 text-end">Total</th>
                                <th class="py-3">Customer</th>
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
                                          : ($sale['total_price'] - ($sale['inventory_price'] * $sale['quantity']));
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
                                    <td>
                                        <?php if(!empty($sale['customer_name'])): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-light rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">
                                                    <i class="bi bi-person text-secondary" style="font-size: 0.8rem;"></i>
                                                </div>
                                                <?php echo htmlspecialchars($sale['customer_name']); ?>
                                            </div>
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
                                <td colspan="4" class="text-end fw-bold py-3">Totals:</td>
                                <td class="text-end fw-bold py-3 text-primary">₹<?php echo number_format($totalRevenue, 2); ?></td>
                                <td></td>
                                <?php if ($userRole !== 'Staff'): ?>
                                    <td class="text-end fw-bold py-3 text-success px-4">₹<?php echo number_format($totalProfit, 2); ?></td>
                                <?php endif; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 