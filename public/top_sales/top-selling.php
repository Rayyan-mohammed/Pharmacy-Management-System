<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();
$sale = new Sale($db);

// Get analytics data
$topMedicines = $sale->getTopSellingWithProfit(10);
$summary = $sale->getSalesSummary();
$monthly = $sale->getMonthlyComparison();
$hourly = $sale->getHourlySalesDistribution();
$dailyTrend = $sale->getDailySalesTrend(30);
$topCustomers = $sale->getTopCustomers(5);

// Calculate month-over-month change
$revenueChange = 0;
if ($monthly['prev_month_revenue'] > 0) {
    $revenueChange = (($monthly['current_month_revenue'] - $monthly['prev_month_revenue']) / $monthly['prev_month_revenue']) * 100;
}
$profitMargin = $summary['total_revenue'] > 0 ? ($summary['total_profit'] / $summary['total_revenue']) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Selling Analytics - PharmaFlow Pro</title>
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
                <i class="bi bi-heart-pulse-fill me-2"></i>PharmaFlow Pro
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Header -->
        <div class="row align-items-center mb-4">
            <div class="col">
                <h2 class="fw-bold text-primary mb-1"><i class="bi bi-trophy me-2"></i>Top Selling Analytics</h2>
                <p class="text-secondary mb-0">Comprehensive sales performance insights and trends.</p>
            </div>
            <div class="col-auto">
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i> Print</button>
            </div>
        </div>

        <!-- KPI Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted small mb-1 text-uppercase fw-bold">Total Revenue</p>
                                <h4 class="fw-bold mb-0">₹<?php echo number_format($summary['total_revenue'], 2); ?></h4>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-2 rounded"><i class="bi bi-currency-rupee text-primary fs-4"></i></div>
                        </div>
                        <div class="mt-2">
                            <?php if ($revenueChange != 0): ?>
                                <span class="badge <?php echo $revenueChange >= 0 ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 <?php echo $revenueChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <i class="bi bi-arrow-<?php echo $revenueChange >= 0 ? 'up' : 'down'; ?>"></i>
                                    <?php echo abs(round($revenueChange, 1)); ?>% vs last month
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">No prior month data</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted small mb-1 text-uppercase fw-bold">Total Profit</p>
                                <h4 class="fw-bold text-success mb-0">₹<?php echo number_format($summary['total_profit'], 2); ?></h4>
                            </div>
                            <div class="bg-success bg-opacity-10 p-2 rounded"><i class="bi bi-graph-up-arrow text-success fs-4"></i></div>
                        </div>
                        <div class="mt-2">
                            <span class="text-muted small">Margin: <strong><?php echo round($profitMargin, 1); ?>%</strong></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted small mb-1 text-uppercase fw-bold">Avg Order Value</p>
                                <h4 class="fw-bold text-info mb-0">₹<?php echo number_format($summary['avg_order_value'], 2); ?></h4>
                            </div>
                            <div class="bg-info bg-opacity-10 p-2 rounded"><i class="bi bi-receipt text-info fs-4"></i></div>
                        </div>
                        <div class="mt-2">
                            <span class="text-muted small">Highest: ₹<?php echo number_format($summary['highest_sale'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted small mb-1 text-uppercase fw-bold">Units Sold</p>
                                <h4 class="fw-bold text-warning mb-0"><?php echo number_format($summary['total_units_sold']); ?></h4>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-2 rounded"><i class="bi bi-box-seam text-warning fs-4"></i></div>
                        </div>
                        <div class="mt-2">
                            <span class="text-muted small"><?php echo $summary['unique_medicines']; ?> medicines across <?php echo $summary['active_days']; ?> days</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Month Comparison Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted fw-bold mb-3"><i class="bi bi-calendar-check me-1"></i> This Month</h6>
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Revenue</span>
                                <h5 class="fw-bold mb-0">₹<?php echo number_format($monthly['current_month_revenue'], 2); ?></h5>
                            </div>
                            <div>
                                <span class="text-muted small">Profit</span>
                                <h5 class="fw-bold text-success mb-0">₹<?php echo number_format($monthly['current_month_profit'], 2); ?></h5>
                            </div>
                            <div>
                                <span class="text-muted small">Transactions</span>
                                <h5 class="fw-bold text-primary mb-0"><?php echo $monthly['current_month_count']; ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted fw-bold mb-3"><i class="bi bi-calendar-minus me-1"></i> Last Month</h6>
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Revenue</span>
                                <h5 class="fw-bold mb-0">₹<?php echo number_format($monthly['prev_month_revenue'], 2); ?></h5>
                            </div>
                            <div>
                                <span class="text-muted small">Profit</span>
                                <h5 class="fw-bold text-success mb-0">₹<?php echo number_format($monthly['prev_month_profit'], 2); ?></h5>
                            </div>
                            <div>
                                <span class="text-muted small">Transactions</span>
                                <h5 class="fw-bold text-primary mb-0"><?php echo $monthly['prev_month_count']; ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1: Daily trend + Revenue share -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up me-2 text-primary"></i>Daily Sales Trend (Last 30 Days)</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyTrendChart" height="260"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-pie-chart me-2 text-success"></i>Revenue Share (Top 10)</h6>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <canvas id="revenueShareChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2: Quantity bar + Peak hours -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart me-2 text-info"></i>Sales Volume by Medicine</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="quantityChart" height="280"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-clock me-2 text-warning"></i>Peak Sales Hours</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="hourlyChart" height="280"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit Analysis Chart -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-cash-stack me-2 text-success"></i>Profit Analysis by Medicine</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="profitChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Leaderboard Table -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 text-primary"><i class="bi bi-trophy me-2"></i>Sales Leaderboard</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th class="px-4 py-3 text-center" style="width: 70px;">Rank</th>
                                <th class="py-3">Medicine</th>
                                <th class="py-3 text-center">Sales Count</th>
                                <th class="py-3 text-center">Total Qty</th>
                                <th class="py-3 text-center">Avg Qty/Sale</th>
                                <th class="py-3 text-end">Revenue</th>
                                <th class="py-3 text-end">Profit</th>
                                <th class="py-3 text-center">Margin</th>
                                <th class="py-3 text-end px-4">Last Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topMedicines as $rank => $med): 
                                $r = $rank + 1;
                                $margin = $med['total_revenue'] > 0 ? ($med['total_profit'] / $med['total_revenue']) * 100 : 0;
                                $rankBadge = '<span class="badge bg-secondary rounded-pill">'.$r.'</span>';
                                if($r == 1) $rankBadge = '<i class="bi bi-trophy-fill text-warning fs-5"></i>';
                                if($r == 2) $rankBadge = '<i class="bi bi-award-fill text-secondary fs-5"></i>';
                                if($r == 3) $rankBadge = '<i class="bi bi-award-fill fs-5" style="color:#cd7f32"></i>';
                            ?>
                                <tr>
                                    <td class="px-4 text-center"><?php echo $rankBadge; ?></td>
                                    <td><span class="fw-bold"><?php echo htmlspecialchars($med['name']); ?></span></td>
                                    <td class="text-center"><?php echo $med['num_sales']; ?></td>
                                    <td class="text-center"><span class="badge bg-primary bg-opacity-10 text-primary"><?php echo $med['total_quantity']; ?> units</span></td>
                                    <td class="text-center"><?php echo round($med['avg_qty_per_sale'], 1); ?></td>
                                    <td class="text-end fw-bold">₹<?php echo number_format($med['total_revenue'], 2); ?></td>
                                    <td class="text-end">
                                        <span class="<?php echo $med['total_profit'] >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                            ₹<?php echo number_format($med['total_profit'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $mColor = $margin >= 30 ? 'success' : ($margin >= 15 ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge bg-<?php echo $mColor; ?> bg-opacity-10 text-<?php echo $mColor; ?>"><?php echo round($margin, 1); ?>%</span>
                                    </td>
                                    <td class="text-end px-4 text-muted small"><?php echo date('d M Y', strtotime($med['last_sold'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Customers -->
        <?php if (!empty($topCustomers)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 text-primary"><i class="bi bi-people me-2"></i>Top Customers</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="py-3">Customer</th>
                                <th class="py-3 text-center">Purchases</th>
                                <th class="py-3 text-center">Total Qty</th>
                                <th class="py-3 text-end">Total Spent</th>
                                <th class="py-3 text-end px-4">Last Purchase</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topCustomers as $ci => $cust): ?>
                                <tr>
                                    <td class="px-4"><?php echo $ci + 1; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;">
                                                <i class="bi bi-person text-primary"></i>
                                            </div>
                                            <strong><?php echo htmlspecialchars($cust['customer_name']); ?></strong>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php echo $cust['num_purchases']; ?></td>
                                    <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $cust['total_qty']; ?></span></td>
                                    <td class="text-end fw-bold text-success">₹<?php echo number_format($cust['total_spent'], 2); ?></td>
                                    <td class="text-end px-4 text-muted small"><?php echo date('d M Y', strtotime($cust['last_purchase'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php
        // Prepare chart data
        $topLabels = array_column($topMedicines, 'name');
        $topQty = array_column($topMedicines, 'total_quantity');
        $topRevenue = array_map('floatval', array_column($topMedicines, 'total_revenue'));
        $topProfit = array_map('floatval', array_column($topMedicines, 'total_profit'));
        $topCost = [];
        foreach ($topMedicines as $m) {
            $topCost[] = round((float)$m['total_revenue'] - (float)$m['total_profit'], 2);
        }

        $trendDates = array_column($dailyTrend, 'sale_day');
        $trendRevenue = array_map('floatval', array_column($dailyTrend, 'revenue'));
        $trendProfit = array_map('floatval', array_column($dailyTrend, 'profit'));

        // Fill 24 hours for hourly chart
        $hourlyData = array_fill(0, 24, 0);
        foreach ($hourly as $h) {
            $hourlyData[(int)$h['sale_hour']] = (int)$h['num_transactions'];
        }
        $hourLabels = [];
        for ($i = 0; $i < 24; $i++) {
            $hourLabels[] = sprintf('%02d:00', $i);
        }
        ?>

        const pastelColors = [
            '#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f',
            '#edc948','#b07aa1','#ff9da7','#9c755f','#bab0ac'
        ];

        // 1. Daily Trend Chart (dual axis: revenue line + profit bars)
        new Chart(document.getElementById('dailyTrendChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($d) { return date('d M', strtotime($d)); }, $trendDates)); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode($trendRevenue); ?>,
                    borderColor: '#4e79a7',
                    backgroundColor: 'rgba(78,121,167,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#4e79a7',
                    borderWidth: 2
                }, {
                    label: 'Profit',
                    data: <?php echo json_encode($trendProfit); ?>,
                    borderColor: '#59a14f',
                    backgroundColor: 'rgba(89,161,79,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#59a14f',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: { label: ctx => ctx.dataset.label + ': ₹' + ctx.parsed.y.toLocaleString() }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2,4] }, ticks: { callback: v => '₹' + v.toLocaleString() } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Revenue Share Doughnut
        new Chart(document.getElementById('revenueShareChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($topLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($topRevenue); ?>,
                    backgroundColor: pastelColors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 11 } } },
                    tooltip: {
                        callbacks: { label: ctx => ctx.label + ': ₹' + ctx.parsed.toLocaleString() + ' (' + ((ctx.parsed / <?php echo max(1, array_sum($topRevenue)); ?>) * 100).toFixed(1) + '%)' }
                    }
                }
            }
        });

        // 3. Quantity Bar Chart (horizontal)
        new Chart(document.getElementById('quantityChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($topLabels); ?>,
                datasets: [{
                    label: 'Units Sold',
                    data: <?php echo json_encode($topQty); ?>,
                    backgroundColor: pastelColors.map(c => c + 'CC'),
                    borderColor: pastelColors,
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { borderDash: [2,4] } },
                    y: { grid: { display: false } }
                }
            }
        });

        // 4. Peak Hours Chart
        new Chart(document.getElementById('hourlyChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($hourLabels); ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?php echo json_encode(array_values($hourlyData)); ?>,
                    backgroundColor: <?php echo json_encode(array_values($hourlyData)); ?>.map((v, i) => {
                        const max = Math.max(...<?php echo json_encode(array_values($hourlyData)); ?>);
                        const intensity = max > 0 ? v / max : 0;
                        return `rgba(255, 159, 64, ${0.3 + intensity * 0.7})`;
                    }),
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1,
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2,4] }, ticks: { stepSize: 1 } },
                    x: { grid: { display: false }, ticks: { maxRotation: 45, font: { size: 10 } } }
                }
            }
        });

        // 5. Profit Analysis (stacked bar: cost vs profit)
        new Chart(document.getElementById('profitChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($topLabels); ?>,
                datasets: [{
                    label: 'Cost',
                    data: <?php echo json_encode($topCost); ?>,
                    backgroundColor: 'rgba(220, 53, 69, 0.3)',
                    borderColor: 'rgba(220, 53, 69, 0.8)',
                    borderWidth: 1,
                    borderRadius: 2
                }, {
                    label: 'Profit',
                    data: <?php echo json_encode($topProfit); ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.4)',
                    borderColor: 'rgba(25, 135, 84, 0.8)',
                    borderWidth: 1,
                    borderRadius: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: { label: ctx => ctx.dataset.label + ': ₹' + ctx.parsed.y.toLocaleString() }
                    }
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, grid: { borderDash: [2,4] }, ticks: { callback: v => '₹' + v.toLocaleString() } }
                }
            }
        });
    </script>
</body>
</html> 
