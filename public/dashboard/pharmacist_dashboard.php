<?php
require_once '../../app/auth.php';

// Ensure only Pharmacist can see this page
if (!isset($_SESSION['currentUser']) || $_SESSION['currentUser']['role'] !== 'Pharmacist') {
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user = $_SESSION['currentUser'];
if (!empty($user['first_name'])) {
    $userName = $user['first_name'] . ' ' . ($user['last_name'] ?? '');
} else {
    $userName = $user['username'] ?? 'Guest';
}
$userNameDisplay = htmlspecialchars($userName);

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
    <title>Pharmacist Dashboard - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
            <div class="d-flex align-items-center gap-2">
                <a class="btn btn-outline-light btn-sm" href="../users/profile.php"><i class="bi bi-person-circle"></i></a>
                <a class="btn btn-light btn-sm" href="../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Welcome Banner -->
        <div class="card bg-primary text-white mb-4">
            <div class="card-body p-4 position-relative">
                <h4 class="mb-1 fw-bold">Welcome back, <?php echo $userNameDisplay; ?></h4>
                <p class="mb-0 opacity-75" style="font-size:0.875rem;">Pharmacist &middot; <?php echo date('l, d M Y'); ?></p>
            </div>
        </div>

        <!-- Statistics Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="../inventory/inventory_report.php" class="text-decoration-none">
                    <div class="card stat-card bg-primary p-3 h-100 transition-hover">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Total Medicines</h6>
                                <h2 class="mb-0"><?php echo number_format($stats['total_medicines']); ?></h2>
                            </div>
                            <div class="fs-1" style="color:var(--primary);"><i class="bi bi-capsule"></i></div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="../check/check-stock.php" class="text-decoration-none">
                    <div class="card stat-card bg-warning p-3 h-100 transition-hover">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Low Stock Items</h6>
                                <h2 class="mb-0"><?php echo number_format($stats['low_stock']); ?></h2>
                            </div>
                            <div class="fs-1" style="color:var(--warning);"><i class="bi bi-exclamation-triangle"></i></div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="../expiration/expiration-management.php" class="text-decoration-none">
                    <div class="card stat-card bg-danger p-3 h-100 transition-hover">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Expired Medicines</h6>
                                <h2 class="mb-0"><?php echo number_format($stats['expired']); ?></h2>
                            </div>
                            <div class="fs-1" style="color:var(--danger);"><i class="bi bi-calendar-x"></i></div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Main Actions Column -->
            <div class="col-lg-8">
                <h4 class="mb-3 text-secondary">Quick Actions</h4>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4 col-lg-3"><a href="../sales/sell_medicine.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-cart-check"></i></div>Sell</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../add/add-medicine.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-plus-circle"></i></div>Add Medicine</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../check/check-stock.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-search"></i></div>Check Stock</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../prescription/prescription-management.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-file-earmark-medical"></i></div>Prescriptions</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../sales/sales_records.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-receipt"></i></div>Sales</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../expiration/expiration-management.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-clock-history"></i></div>Expirations</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../update/update-stock.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-box-seam"></i></div>Restock</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../inventory/inventory_report.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-clipboard-data"></i></div>Inventory</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../top_sales/top-selling.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-trophy"></i></div>Top Selling</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../supplier/supplier-management.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-truck"></i></div>Suppliers</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../sales/returns.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-arrow-return-left"></i></div>Returns</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../add/categories.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-tags"></i></div>Categories</a></div>
                    <div class="col-6 col-md-4 col-lg-3"><a href="../inventory/alerts.php" class="card dashboard-btn"><div class="dashboard-icon"><i class="bi bi-bell"></i></div>Alerts</a></div>
                </div>

                <!-- Sales Chart -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up me-2" style="color:var(--primary);"></i>Revenue &mdash; Last 7 Days</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="150"></canvas>
                    </div>
                </div>
            </div>

            <!-- Side Widgets Column -->
            <div class="col-lg-4">
                <!-- Low Stock Widget -->
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill" style="color:var(--danger);"></i>
                        <h5 class="mb-0">Low Stock Alerts</h5>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php if (empty($lowStockItems)): ?>
                            <li class="list-group-item text-center py-4" style="color:var(--text-tertiary);">
                                <i class="bi bi-check-circle me-1" style="color:var(--success);"></i>All stock levels healthy
                            </li>
                        <?php else: ?>
                            <?php foreach($lowStockItems as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span style="font-size:0.875rem;"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <span class="badge bg-danger rounded-pill"><?php echo $item['stock']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <div class="card-footer text-center">
                        <a href="../update/update-stock.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-arrow-repeat me-1"></i>Restock Now
                        </a>
                    </div>
                </div>

                <!-- System Info -->
                <div class="card">
                    <div class="card-header d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle" style="color:var(--primary);"></i>
                        <h5 class="mb-0">System Info</h5>
                    </div>
                    <div class="card-body" style="font-size:0.875rem;">
                        <div class="d-flex justify-content-between mb-2">
                            <span style="color:var(--text-secondary);">Date</span>
                            <span class="fw-600"><?php echo date('d M Y'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span style="color:var(--text-secondary);">Server</span>
                            <span class="fw-600" style="color:var(--success);"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Online</span>
                        </div>
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
                        label: 'Revenue',
                        data: <?php echo json_encode($chartData); ?>,
                        borderColor: '#4F46E5',
                        backgroundColor: 'rgba(79, 70, 229, 0.08)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#4F46E5',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2.5
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#18181B',
                            titleFont: { family: 'Inter', weight: '600' },
                            bodyFont: { family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return '₹' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                            ticks: { font: { family: 'Inter', size: 11 }, color: '#71717A' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Inter', size: 11 }, color: '#71717A' }
                        }
                    }
                }
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
