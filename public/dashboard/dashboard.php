<?php
require_once '../../app/auth.php';

$database = new Database();
$db = $database->getConnection();

$user = $_SESSION['currentUser'] ?? ['role' => 'Staff', 'username' => 'Guest'];
$userRole = $user['role'] ?? 'Staff';

if (!empty($user['first_name'])) {
    $userName = $user['first_name'] . ' ' . ($user['last_name'] ?? ''); 
} else {
    $userName = $user['username'] ?? 'Guest';
}

$userRoleDisplay = ucfirst($userRole);
$userNameDisplay = htmlspecialchars($userName);

function canAccess($feature, $role) {
    // Admin has access to everything
    if ($role === 'Administrator') return true;

    $permissions = [
        'sales_records' => ['Pharmacist'],
        'top_selling' => ['Pharmacist'],
        'statistics' => [], // Admin only
        'expiration_management' => ['Pharmacist'],
        'supplier_management' => ['Pharmacist'],
        'prescription_management' => ['Pharmacist', 'Staff'],
        'user_management' => [], // Admin only
        'sell_medicine' => ['Pharmacist', 'Staff'],
        'add_medicine' => ['Pharmacist'],
        'check_stock' => ['Pharmacist', 'Staff'],
        'update_stock' => ['Pharmacist'],
        'inventory_report' => ['Pharmacist'],
    ];

    if (isset($permissions[$feature])) {
        return in_array($role, $permissions[$feature]);
    }

    return false;
}

// Get Statistics
$medicine = new Medicine($db);
$stats = $medicine->getDashboardStats();
$salesChartData = $medicine->getSalesChartData();
$lowStockItems = $medicine->getLowStockItems();

// Format chart data
$chartLabels = [];
$chartData = [];
foreach($salesChartData as $data) {
    $chartLabels[] = date('d M', strtotime($data['date']));
    $chartData[] = $data['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto align-items-center">
                    <span class="text-white me-3">
                        <small class="opacity-75">Logged in as:</small><br>
                        <strong><?php echo $userRoleDisplay; ?> (<?php echo $userNameDisplay; ?>)</strong>
                    </span>
                    <a class="btn btn-light btn-sm fw-bold text-primary px-3" href="../logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Statistics Row -->
        <div class="row mb-5">
            <div class="col-md-4">
                <a href="../inventory/inventory_report.php" class="text-decoration-none text-white">
                    <div class="card stat-card bg-primary p-3 h-100 transition-hover">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total Medicines</h6>
                                <h2 class="mb-0"><?php echo number_format($stats['total_medicines']); ?></h2>
                            </div>
                            <div class="fs-1 opacity-50">💊</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="../check/check-stock.php" class="text-decoration-none text-dark">
                    <div class="card stat-card bg-warning p-3 h-100 transition-hover">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Low Stock Items</h6>
                                <h2 class="mb-0"><?php echo number_format($stats['low_stock']); ?></h2>
                            </div>
                            <div class="fs-1 opacity-50">⚠️</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="../expiration/expiration-management.php" class="text-decoration-none text-white">
                    <div class="card stat-card bg-danger p-3 h-100 transition-hover">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Expired Medicines</h6>
                                <h2 class="mb-0"><?php echo number_format($stats['expired']); ?></h2>
                            </div>
                            <div class="fs-1 opacity-50">📅</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Main Actions Column -->
            <div class="col-lg-8">
                <h4 class="mb-3 text-secondary">Quick Actions</h4>
                <div class="row g-3 mb-5">
                    <!-- Dashboard Buttons -->
                    <?php if (canAccess('sell_medicine', $userRole)): ?>
                    <div class="col-md-4"><a href="../sales/sell_medicine.php" class="card dashboard-btn"><div class="dashboard-icon">💰</div>Sell Medicine</a></div>
                    <?php endif; ?>

                    <?php if (canAccess('add_medicine', $userRole)): ?>
                    <div class="col-md-4"><a href="../add/add-medicine.php" class="card dashboard-btn"><div class="dashboard-icon">💊</div>Add Medicine</a></div>
                    <?php endif; ?>
                    
                    <?php if (canAccess('check_stock', $userRole)): ?>
                    <div class="col-md-4"><a href="../check/check-stock.php" class="card dashboard-btn"><div class="dashboard-icon">🔍</div>Check Stock</a></div>
                    <?php endif; ?>
                    
                    <?php if (canAccess('prescription_management', $userRole)): ?>
                    <div class="col-md-4"><a href="../prescription/prescription-management.php" class="card dashboard-btn"><div class="dashboard-icon">📝</div>Prescriptions</a></div>
                    <?php endif; ?>

                    <?php if (canAccess('sales_records', $userRole)): ?>
                    <div class="col-md-4"><a href="../sales/sales_records.php" class="card dashboard-btn"><div class="dashboard-icon">📊</div>Sales Records</a></div>
                    <?php endif; ?>

                    <?php if (canAccess('expiration_management', $userRole)): ?>
                    <div class="col-md-4"><a href="../expiration/expiration-management.php" class="card dashboard-btn"><div class="dashboard-icon">⏳</div>Expirations</a></div>
                    <?php endif; ?>

                    <?php if (canAccess('update_stock', $userRole)): ?>
                    <div class="col-md-4"><a href="../update/update-stock.php" class="card dashboard-btn"><div class="dashboard-icon">📦</div>Update Stock</a></div>
                    <?php endif; ?>
                    
                    <?php if (canAccess('inventory_report', $userRole)): ?>
                    <div class="col-md-4"><a href="../inventory/inventory_report.php" class="card dashboard-btn"><div class="dashboard-icon">📋</div>Inv. Report</a></div>
                    <?php endif; ?>

                    <?php if (canAccess('top_selling', $userRole)): ?>
                    <div class="col-md-4"><a href="../top_sales/top-selling.php" class="card dashboard-btn"><div class="dashboard-icon">📈</div>Top Selling</a></div>
                    <?php endif; ?>

                    <?php if (canAccess('statistics', $userRole)): ?>
                    <div class="col-md-4"><a href="../statistics/statistics.php" class="card dashboard-btn"><div class="dashboard-icon">📉</div>Statistics</a></div>
                    <?php endif; ?>

                    <?php if (canAccess('supplier_management', $userRole)): ?>
                    <div class="col-md-4"><a href="../supplier/supplier-management.php" class="card dashboard-btn"><div class="dashboard-icon">🏢</div>Suppliers</a></div>
                    <?php endif; ?>

                    <?php if (canAccess('user_management', $userRole)): ?>
                    <div class="col-md-4"><a href="../users/add_user.php" class="card dashboard-btn"><div class="dashboard-icon">👤</div>User Mgmt</a></div>
                    <?php endif; ?>
                </div>

                <!-- Sales Chart -->
                <?php if (canAccess('sales_records', $userRole)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Revenue Overview (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="150"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Side Widgets Column -->
            <div class="col-lg-4">
                <!-- Low Stock Widget -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom-0">
                        <h5 class="mb-0 text-danger">⚠️ Low Stock Alerts</h5>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php if (empty($lowStockItems)): ?>
                            <li class="list-group-item text-muted text-center py-4">All stock levels are healthy!</li>
                        <?php else: ?>
                            <?php foreach($lowStockItems as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                    <span class="badge bg-danger rounded-pill"><?php echo $item['stock']; ?> left</span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <div class="card-footer bg-white text-center">
                        <a href="../update/update-stock.php" class="btn btn-sm btn-outline-primary">Restock Now</a>
                    </div>
                </div>

                 <!-- Quick Links/Info -->
                 <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">System Info</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Today:</strong> <?php echo date('d M Y'); ?></p>
                        <p class="mb-0"><strong>Server Status:</strong> <span class="text-success">Online</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Config -->
    <script>
        const ctx = document.getElementById('salesChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Sales Revenue',
                        data: <?php echo json_encode($chartData); ?>,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    </script>
</body>
</html>
