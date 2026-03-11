<?php
require_once '../../app/auth.php';
checkRole(['Administrator']);

$user = $_SESSION['currentUser'];
$userName = htmlspecialchars(!empty($user['first_name']) ? $user['first_name'] : $user['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-card { border: none; border-radius: 1rem; transition: transform 0.2s, box-shadow 0.2s; }
        .kpi-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.1); }
        .kpi-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .kpi-value { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
        .kpi-label { font-size: 0.8rem; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .growth-badge { font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
        .growth-up { background: #dcfce7; color: #16a34a; }
        .growth-down { background: #fee2e2; color: #dc2626; }
        .growth-neutral { background: #f1f5f9; color: #64748b; }
        .chart-card { border: none; border-radius: 1rem; }
        .chart-card .card-header { background: white; border-bottom: 1px solid #f1f5f9; border-radius: 1rem 1rem 0 0 !important; padding: 1rem 1.5rem; }
        .chart-card .card-body { padding: 1.5rem; }
        .period-btn.active { background: var(--primary-color); color: white; }
        .table-analytics th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; }
        .table-analytics td { font-size: 0.875rem; vertical-align: middle; }
        .rank-badge { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; }
        .rank-1 { background: #fef3c7; color: #d97706; }
        .rank-2 { background: #e2e8f0; color: #475569; }
        .rank-3 { background: #fed7aa; color: #c2410c; }
        .rank-default { background: #f1f5f9; color: #64748b; }
        .nav-tabs-analytics .nav-link { font-weight: 500; color: #64748b; border: none; padding: 0.75rem 1.25rem; }
        .nav-tabs-analytics .nav-link.active { color: var(--primary-color); border-bottom: 2px solid var(--primary-color); background: transparent; }
        .skeleton { background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 8px; height: 20px; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        .today-highlight { background: linear-gradient(135deg, #4F46E5, #4338CA); border-radius: 1rem; color: white; }
        .progress-thin { height: 6px; border-radius: 3px; }
        @media print { .no-print { display: none !important; } .container { max-width: 100%; } }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-primary no-print">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Analytics Dashboard</h3>
                <p class="text-muted mb-0">Comprehensive pharmacy performance insights</p>
            </div>
            <div class="d-flex gap-2 no-print">
                <div class="btn-group" role="group">
                    <button class="btn btn-outline-primary btn-sm period-btn" data-period="7">7D</button>
                    <button class="btn btn-outline-primary btn-sm period-btn active" data-period="30">30D</button>
                    <button class="btn btn-outline-primary btn-sm period-btn" data-period="90">90D</button>
                    <button class="btn btn-outline-primary btn-sm period-btn" data-period="365">1Y</button>
                </div>
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>

        <!-- Today's Highlight Bar -->
        <div class="today-highlight p-4 mb-4 shadow-sm">
            <div class="row align-items-center">
                <div class="col-auto"><i class="bi bi-calendar-check fs-3 opacity-75"></i></div>
                <div class="col">
                    <div class="fw-bold mb-1">Today's Performance &mdash; <?php echo date('l, d M Y'); ?></div>
                    <div class="d-flex gap-4 flex-wrap">
                        <span><i class="bi bi-cash-stack me-1"></i>Revenue: <strong id="todayRevenue">₹0</strong></span>
                        <span><i class="bi bi-graph-up me-1"></i>Profit: <strong id="todayProfit">₹0</strong></span>
                        <span><i class="bi bi-receipt me-1"></i>Transactions: <strong id="todayTxn">0</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card kpi-card shadow-sm p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Revenue</div>
                            <div class="kpi-value text-primary" id="kpiRevenue">₹0</div>
                            <span class="growth-badge growth-neutral" id="growthRevenue">--</span>
                        </div>
                        <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash-stack"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card kpi-card shadow-sm p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Net Profit</div>
                            <div class="kpi-value text-success" id="kpiProfit">₹0</div>
                            <span class="growth-badge growth-neutral" id="growthProfit">--</span>
                        </div>
                        <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="bi bi-graph-up-arrow"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card kpi-card shadow-sm p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Profit Margin</div>
                            <div class="kpi-value" id="kpiMargin">0%</div>
                            <small class="text-muted">Avg Order: <span id="kpiAOV">₹0</span></small>
                        </div>
                        <div class="kpi-icon bg-info bg-opacity-10 text-info"><i class="bi bi-percent"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card kpi-card shadow-sm p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Transactions</div>
                            <div class="kpi-value" id="kpiTxn">0</div>
                            <span class="growth-badge growth-neutral" id="growthTxn">--</span>
                        </div>
                        <div class="kpi-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-receipt"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card kpi-card shadow-sm p-3 h-100 text-center">
                    <div class="kpi-label">Medicines</div>
                    <div class="kpi-value" id="kpiMeds">0</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card kpi-card shadow-sm p-3 h-100 text-center">
                    <div class="kpi-label">Total Stock</div>
                    <div class="kpi-value" id="kpiStock">0</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card kpi-card shadow-sm p-3 h-100 text-center">
                    <div class="kpi-label text-warning">Low Stock</div>
                    <div class="kpi-value text-warning" id="kpiLow">0</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card kpi-card shadow-sm p-3 h-100 text-center">
                    <div class="kpi-label text-danger">Out of Stock</div>
                    <div class="kpi-value text-danger" id="kpiOOS">0</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card kpi-card shadow-sm p-3 h-100 text-center">
                    <div class="kpi-label text-danger">Expired</div>
                    <div class="kpi-value text-danger" id="kpiExpired">0</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card kpi-card shadow-sm p-3 h-100 text-center">
                    <div class="kpi-label text-warning">Expiring Soon</div>
                    <div class="kpi-value text-warning" id="kpiExpiring">0</div>
                </div>
            </div>
        </div>

        <!-- Inventory Value Bar -->
        <div class="card kpi-card shadow-sm p-3 mb-4">
            <div class="row align-items-center">
                <div class="col-md-4 text-center border-end">
                    <div class="kpi-label">Inventory Cost Value</div>
                    <div class="fw-bold fs-5 text-primary" id="kpiInvCost">₹0</div>
                </div>
                <div class="col-md-4 text-center border-end">
                    <div class="kpi-label">Inventory Retail Value</div>
                    <div class="fw-bold fs-5 text-success" id="kpiInvRetail">₹0</div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="kpi-label">Potential Markup</div>
                    <div class="fw-bold fs-5" id="kpiMarkup">₹0</div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1: Revenue Trend + Monthly -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card chart-card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">Revenue & Profit Trend</h6>
                        <small class="text-muted" id="trendPeriodLabel">Last 30 days</small>
                    </div>
                    <div class="card-body"><canvas id="revenueTrendChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card chart-card shadow-sm h-100">
                    <div class="card-header"><h6 class="mb-0 fw-bold">Monthly Revenue</h6></div>
                    <div class="card-body"><canvas id="monthlyChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2: Top Selling + Sales Patterns -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card chart-card shadow-sm h-100">
                    <div class="card-header"><h6 class="mb-0 fw-bold"><i class="bi bi-trophy me-2 text-warning"></i>Top Selling Medicines</h6></div>
                    <div class="card-body"><canvas id="topSellingChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card chart-card shadow-sm h-100">
                    <div class="card-header"><h6 class="mb-0 fw-bold">Sales by Hour</h6></div>
                    <div class="card-body"><canvas id="hourlyChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card chart-card shadow-sm h-100">
                    <div class="card-header"><h6 class="mb-0 fw-bold">Sales by Weekday</h6></div>
                    <div class="card-body"><canvas id="weekdayChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Tabs: Tables -->
        <div class="card chart-card shadow-sm mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs-analytics nav-tabs card-header-tabs" id="dataTabs">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabTopSelling"><i class="bi bi-trophy me-1"></i>Top Selling</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabSlowMoving"><i class="bi bi-arrow-down-circle me-1"></i>Slow Moving</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabLowStock"><i class="bi bi-exclamation-triangle me-1"></i>Low Stock</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabExpiring"><i class="bi bi-clock-history me-1"></i>Expiring Soon</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabRecent"><i class="bi bi-receipt me-1"></i>Recent Sales</a></li>
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="tab-content">
                    <!-- Top Selling Table -->
                    <div class="tab-pane fade show active" id="tabTopSelling">
                        <div class="table-responsive">
                            <table class="table table-analytics table-hover mb-0">
                                <thead><tr><th class="px-4">Rank</th><th>Medicine</th><th class="text-end">Qty Sold</th><th class="text-end">Revenue</th><th class="text-end">Profit</th><th class="text-end px-4">Margin</th></tr></thead>
                                <tbody id="topSellingTable"></tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Slow Moving Table -->
                    <div class="tab-pane fade" id="tabSlowMoving">
                        <div class="table-responsive">
                            <table class="table table-analytics table-hover mb-0">
                                <thead><tr><th class="px-4">Medicine</th><th class="text-end">Current Stock</th><th class="text-end">Sold (90d)</th><th class="text-end px-4">Status</th></tr></thead>
                                <tbody id="slowMovingTable"></tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Low Stock Table -->
                    <div class="tab-pane fade" id="tabLowStock">
                        <div class="table-responsive">
                            <table class="table table-analytics table-hover mb-0">
                                <thead><tr><th class="px-4">Medicine</th><th class="text-end">Stock</th><th class="text-end">Cost Price</th><th class="text-end">Sale Price</th><th class="text-end px-4">Stock Value</th></tr></thead>
                                <tbody id="lowStockTable"></tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Expiring Soon Table -->
                    <div class="tab-pane fade" id="tabExpiring">
                        <div class="table-responsive">
                            <table class="table table-analytics table-hover mb-0">
                                <thead><tr><th class="px-4">Medicine</th><th>Batch</th><th class="text-end">Qty</th><th class="text-end">Expiry Date</th><th class="text-end px-4">Days Left</th></tr></thead>
                                <tbody id="expiringTable"></tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Recent Sales Table -->
                    <div class="tab-pane fade" id="tabRecent">
                        <div class="table-responsive">
                            <table class="table table-analytics table-hover mb-0">
                                <thead><tr><th class="px-4">Date</th><th>Medicine</th><th>Customer</th><th class="text-end">Qty</th><th class="text-end">Total</th><th class="text-end px-4">Profit</th></tr></thead>
                                <tbody id="recentSalesTable"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Prescription Stats -->
        <div class="card chart-card shadow-sm mb-4">
            <div class="card-header"><h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-medical me-2"></i>Prescription Overview</h6></div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <h4 class="fw-bold text-primary" id="rxTotal">0</h4>
                        <small class="text-muted text-uppercase">Total Prescriptions</small>
                    </div>
                    <div class="col-md-4">
                        <h4 class="fw-bold text-warning" id="rxPending">0</h4>
                        <small class="text-muted text-uppercase">Pending</small>
                    </div>
                    <div class="col-md-4">
                        <h4 class="fw-bold text-success" id="rxDispensed">0</h4>
                        <small class="text-muted text-uppercase">Dispensed</small>
                    </div>
                </div>
                <div class="progress progress-thin mt-3">
                    <div class="progress-bar bg-success" id="rxProgressDispensed" style="width: 0%"></div>
                    <div class="progress-bar bg-warning" id="rxProgressPending" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ── Utility Functions ───────────────────────────────
    const fmt = (n) => '₹' + Number(n).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const fmtShort = (n) => {
        if(n >= 10000000) return '₹' + (n/10000000).toFixed(1) + 'Cr';
        if(n >= 100000) return '₹' + (n/100000).toFixed(1) + 'L';
        if(n >= 1000) return '₹' + (n/1000).toFixed(1) + 'K';
        return fmt(n);
    };
    const num = (n) => Number(n).toLocaleString('en-IN');

    function growthBadge(val, elId) {
        const el = document.getElementById(elId);
        if(val > 0) { el.className = 'growth-badge growth-up'; el.innerHTML = '<i class="bi bi-caret-up-fill"></i> ' + val.toFixed(1) + '%'; }
        else if(val < 0) { el.className = 'growth-badge growth-down'; el.innerHTML = '<i class="bi bi-caret-down-fill"></i> ' + Math.abs(val).toFixed(1) + '%'; }
        else { el.className = 'growth-badge growth-neutral'; el.textContent = 'No change'; }
    }

    function rankClass(i) { return i === 0 ? 'rank-1' : i === 1 ? 'rank-2' : i === 2 ? 'rank-3' : 'rank-default'; }

    // ── Chart Instances ─────────────────────────────────
    let charts = {};
    const chartColors = {
        blue:    { bg: 'rgba(79,70,229,0.15)', border: '#4F46E5' },
        green:   { bg: 'rgba(16,185,129,0.15)', border: '#10b981' },
        amber:   { bg: 'rgba(245,158,11,0.15)', border: '#f59e0b' },
        red:     { bg: 'rgba(239,68,68,0.15)',  border: '#ef4444' },
        purple:  { bg: 'rgba(139,92,246,0.15)', border: '#8b5cf6' },
        palette: ['#4F46E5','#059669','#D97706','#DC2626','#8b5cf6','#0284C7','#f97316','#84cc16','#e879f9','#6366f1']
    };

    const defaultOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
    };

    function destroyChart(name) { if(charts[name]) { charts[name].destroy(); charts[name] = null; } }

    // ── Load Data ───────────────────────────────────────
    let currentPeriod = 30;

    function loadStats(period) {
        currentPeriod = period;
        document.getElementById('trendPeriodLabel').textContent = 'Last ' + period + ' days';

        fetch('../api/get_statistics.php?period=' + period)
        .then(r => r.json())
        .then(d => {
            if(d.error) throw new Error(d.error);

            // Today
            document.getElementById('todayRevenue').textContent = fmt(d.todayRevenue);
            document.getElementById('todayProfit').textContent = fmt(d.todayProfit);
            document.getElementById('todayTxn').textContent = num(d.todayTransactions);

            // Sales KPIs
            document.getElementById('kpiRevenue').textContent = fmtShort(d.revenue);
            document.getElementById('kpiProfit').textContent = fmtShort(d.profit);
            document.getElementById('kpiMargin').textContent = d.profitMargin + '%';
            document.getElementById('kpiMargin').style.color = d.profitMargin >= 20 ? '#10b981' : d.profitMargin >= 10 ? '#f59e0b' : '#ef4444';
            document.getElementById('kpiAOV').textContent = fmt(d.avgOrderValue);
            document.getElementById('kpiTxn').textContent = num(d.totalTransactions);
            growthBadge(d.revenueGrowth, 'growthRevenue');
            growthBadge(d.profitGrowth, 'growthProfit');
            growthBadge(d.txnGrowth, 'growthTxn');

            // Inventory KPIs
            document.getElementById('kpiMeds').textContent = num(d.totalMedicines);
            document.getElementById('kpiStock').textContent = num(d.totalStock);
            document.getElementById('kpiLow').textContent = num(d.lowStockItems);
            document.getElementById('kpiOOS').textContent = num(d.outOfStock);
            document.getElementById('kpiExpired').textContent = num(d.expiredBatches);
            document.getElementById('kpiExpiring').textContent = num(d.expiringSoon);
            document.getElementById('kpiInvCost').textContent = fmtShort(d.inventoryCost);
            document.getElementById('kpiInvRetail').textContent = fmtShort(d.inventoryRetail);
            document.getElementById('kpiMarkup').textContent = fmtShort(d.inventoryRetail - d.inventoryCost);

            // Prescriptions
            document.getElementById('rxTotal').textContent = num(d.rxTotal);
            document.getElementById('rxPending').textContent = num(d.rxPending);
            document.getElementById('rxDispensed').textContent = num(d.rxDispensed);
            const rxPct = d.rxTotal > 0 ? (d.rxDispensed / d.rxTotal * 100) : 0;
            const rxPendPct = d.rxTotal > 0 ? (d.rxPending / d.rxTotal * 100) : 0;
            document.getElementById('rxProgressDispensed').style.width = rxPct + '%';
            document.getElementById('rxProgressPending').style.width = rxPendPct + '%';

            // ── Revenue Trend Chart ─────────────────────
            destroyChart('revenueTrend');
            const trendCtx = document.getElementById('revenueTrendChart');
            trendCtx.parentElement.style.height = '300px';
            charts.revenueTrend = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: d.dailyTrend.map(r => { const dt = new Date(r.date); return dt.toLocaleDateString('en-IN', {day:'2-digit', month:'short'}); }),
                    datasets: [
                        { label: 'Revenue', data: d.dailyTrend.map(r => r.revenue), borderColor: chartColors.blue.border, backgroundColor: chartColors.blue.bg, fill: true, tension: 0.4, pointRadius: 2 },
                        { label: 'Profit', data: d.dailyTrend.map(r => r.profit), borderColor: chartColors.green.border, backgroundColor: chartColors.green.bg, fill: true, tension: 0.4, pointRadius: 2 }
                    ]
                },
                options: { ...defaultOpts, plugins: { legend: { display: true, position: 'top' } } }
            });

            // ── Monthly Revenue Chart ───────────────────
            destroyChart('monthly');
            const monthCtx = document.getElementById('monthlyChart');
            monthCtx.parentElement.style.height = '300px';
            charts.monthly = new Chart(monthCtx, {
                type: 'bar',
                data: {
                    labels: d.monthlyRevenue.map(r => { const [y,m] = r.month.split('-'); return new Date(y, m-1).toLocaleDateString('en-IN', {month:'short', year:'2-digit'}); }),
                    datasets: [
                        { label: 'Revenue', data: d.monthlyRevenue.map(r => r.revenue), backgroundColor: chartColors.blue.bg, borderColor: chartColors.blue.border, borderWidth: 1, borderRadius: 6 },
                        { label: 'Profit', data: d.monthlyRevenue.map(r => r.profit), backgroundColor: chartColors.green.bg, borderColor: chartColors.green.border, borderWidth: 1, borderRadius: 6 }
                    ]
                },
                options: { ...defaultOpts, plugins: { legend: { display: true, position: 'top' } } }
            });

            // ── Top Selling Chart ───────────────────────
            destroyChart('topSelling');
            const topCtx = document.getElementById('topSellingChart');
            topCtx.parentElement.style.height = '300px';
            charts.topSelling = new Chart(topCtx, {
                type: 'bar',
                data: {
                    labels: d.topSelling.map(r => r.name.length > 18 ? r.name.substring(0,18) + '...' : r.name),
                    datasets: [{
                        label: 'Quantity Sold',
                        data: d.topSelling.map(r => r.total_qty),
                        backgroundColor: chartColors.palette.slice(0, d.topSelling.length),
                        borderRadius: 6
                    }]
                },
                options: { ...defaultOpts, indexAxis: 'y', scales: { x: { beginAtZero: true, grid: { color: '#f1f5f9' } }, y: { grid: { display: false } } } }
            });

            // ── Hourly Chart ────────────────────────────
            destroyChart('hourly');
            const hourCtx = document.getElementById('hourlyChart');
            hourCtx.parentElement.style.height = '300px';
            const hourLabels = Array.from({length: 24}, (_, i) => i.toString().padStart(2,'0') + ':00');
            const hourValues = new Array(24).fill(0);
            d.hourlyData.forEach(r => { hourValues[r.hour] = r.transactions; });
            charts.hourly = new Chart(hourCtx, {
                type: 'bar',
                data: { labels: hourLabels, datasets: [{ data: hourValues, backgroundColor: chartColors.purple.bg, borderColor: chartColors.purple.border, borderWidth: 1, borderRadius: 4 }] },
                options: { ...defaultOpts, scales: { x: { display: false }, y: { beginAtZero: true, grid: { color: '#f1f5f9' } } } }
            });

            // ── Weekday Chart ───────────────────────────
            destroyChart('weekday');
            const dowCtx = document.getElementById('weekdayChart');
            dowCtx.parentElement.style.height = '300px';
            charts.weekday = new Chart(dowCtx, {
                type: 'doughnut',
                data: {
                    labels: d.weekdayData.map(r => r.day_name.substring(0,3)),
                    datasets: [{ data: d.weekdayData.map(r => r.revenue), backgroundColor: chartColors.palette.slice(0,7), borderWidth: 0 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 11 } } } } }
            });

            // ── Tables ──────────────────────────────────
            // Top Selling
            document.getElementById('topSellingTable').innerHTML = d.topSelling.length === 0
                ? '<tr><td colspan="6" class="text-center text-muted py-4">No sales data</td></tr>'
                : d.topSelling.map((r, i) => {
                    const margin = r.total_revenue > 0 ? ((r.total_profit / r.total_revenue) * 100).toFixed(1) : 0;
                    return `<tr><td class="px-4"><span class="rank-badge ${rankClass(i)}">${i+1}</span></td><td class="fw-medium">${escHtml(r.name)}</td><td class="text-end">${num(r.total_qty)}</td><td class="text-end">${fmt(r.total_revenue)}</td><td class="text-end text-success">${fmt(r.total_profit)}</td><td class="text-end px-4">${margin}%</td></tr>`;
                }).join('');

            // Slow Moving
            document.getElementById('slowMovingTable').innerHTML = d.slowMoving.length === 0
                ? '<tr><td colspan="4" class="text-center text-muted py-4">No data</td></tr>'
                : d.slowMoving.map(r => {
                    const status = r.sold_last_90d == 0 ? '<span class="badge bg-danger">Dead Stock</span>' : '<span class="badge bg-warning text-dark">Slow</span>';
                    return `<tr><td class="px-4 fw-medium">${escHtml(r.name)}</td><td class="text-end">${num(r.stock)}</td><td class="text-end">${num(r.sold_last_90d)}</td><td class="text-end px-4">${status}</td></tr>`;
                }).join('');

            // Low Stock
            document.getElementById('lowStockTable').innerHTML = d.lowStockDetails.length === 0
                ? '<tr><td colspan="5" class="text-center text-muted py-4">All stock levels healthy</td></tr>'
                : d.lowStockDetails.map(r => {
                    return `<tr><td class="px-4 fw-medium">${escHtml(r.name)}</td><td class="text-end"><span class="badge bg-danger">${r.current_stock}</span></td><td class="text-end">${fmt(r.inventory_price)}</td><td class="text-end">${fmt(r.sale_price)}</td><td class="text-end px-4">${fmt(r.sale_price * r.current_stock)}</td></tr>`;
                }).join('');

            // Expiring
            document.getElementById('expiringTable').innerHTML = d.expiringDetails.length === 0
                ? '<tr><td colspan="5" class="text-center text-muted py-4">No medicines expiring soon</td></tr>'
                : d.expiringDetails.map(r => {
                    const cls = r.days_left <= 7 ? 'bg-danger' : r.days_left <= 30 ? 'bg-warning text-dark' : 'bg-info';
                    return `<tr><td class="px-4 fw-medium">${escHtml(r.name)}</td><td>${escHtml(r.batch_number)}</td><td class="text-end">${num(r.quantity)}</td><td class="text-end">${new Date(r.expiration_date).toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'})}</td><td class="text-end px-4"><span class="badge ${cls}">${r.days_left}d</span></td></tr>`;
                }).join('');

            // Recent Sales
            document.getElementById('recentSalesTable').innerHTML = d.recentSales.length === 0
                ? '<tr><td colspan="6" class="text-center text-muted py-4">No recent sales</td></tr>'
                : d.recentSales.map(r => {
                    return `<tr><td class="px-4">${new Date(r.sale_date).toLocaleDateString('en-IN', {day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'})}</td><td class="fw-medium">${escHtml(r.medicine_name)}</td><td>${escHtml(r.customer_name || 'Walk-in')}</td><td class="text-end">${r.quantity}</td><td class="text-end">${fmt(r.total_price)}</td><td class="text-end px-4 text-success">${fmt(r.profit)}</td></tr>`;
                }).join('');
        })
        .catch(err => {
            console.error(err);
            document.querySelector('.container.pb-5').innerHTML += `<div class="alert alert-danger mt-3"><strong>Error:</strong> Failed to load analytics data. Please check database connection.</div>`;
        });
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    // ── Period Buttons ──────────────────────────────────
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadStats(parseInt(this.dataset.period));
        });
    });

    // ── Init ────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => loadStats(30));
    </script>
</body>
</html>