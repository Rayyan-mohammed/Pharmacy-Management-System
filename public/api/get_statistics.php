<?php
require_once '../../app/auth.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    $period = $_GET['period'] ?? '30'; // days
    $period = (int)$period;
    if ($period < 1 || $period > 365) $period = 30;

    // ── KPI Cards ──────────────────────────────────────────
    // Total medicines
    $stmt = $db->query("SELECT COUNT(*) as total FROM medicines");
    $totalMedicines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total stock (from batches)
    $stmt = $db->query("SELECT COALESCE(SUM(quantity), 0) as total FROM medicine_batches");
    $totalStock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Low stock items (< 10)
    $stmt = $db->query("SELECT COUNT(*) as total FROM (
        SELECT m.id, COALESCE(SUM(mb.quantity), 0) as current_stock 
        FROM medicines m LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
        GROUP BY m.id HAVING current_stock < 10
    ) as low_stock");
    $lowStockItems = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Out of stock
    $stmt = $db->query("SELECT COUNT(*) as total FROM (
        SELECT m.id, COALESCE(SUM(mb.quantity), 0) as current_stock 
        FROM medicines m LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
        GROUP BY m.id HAVING current_stock = 0
    ) as oos");
    $outOfStock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Expired batches count
    $stmt = $db->query("SELECT COUNT(*) as total FROM medicine_batches WHERE expiration_date <= CURDATE() AND quantity > 0");
    $expiredBatches = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Expiring soon (next 30 days)
    $stmt = $db->query("SELECT COUNT(*) as total FROM medicine_batches 
        WHERE expiration_date > CURDATE() AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND quantity > 0");
    $expiringSoon = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Inventory value (cost)
    $stmt = $db->query("SELECT COALESCE(SUM(m.inventory_price * mb.quantity), 0) as total 
        FROM medicine_batches mb JOIN medicines m ON mb.medicine_id = m.id WHERE mb.quantity > 0");
    $inventoryCost = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Inventory value (retail)
    $stmt = $db->query("SELECT COALESCE(SUM(m.sale_price * mb.quantity), 0) as total 
        FROM medicine_batches mb JOIN medicines m ON mb.medicine_id = m.id WHERE mb.quantity > 0");
    $inventoryRetail = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ── Sales KPIs (for selected period) ───────────────────
    $stmtSales = $db->prepare("SELECT 
        COALESCE(SUM(total_price), 0) as revenue,
        COALESCE(SUM(profit), 0) as profit,
        COUNT(*) as total_transactions,
        COALESCE(SUM(quantity), 0) as total_units,
        COALESCE(AVG(total_price), 0) as avg_order_value
        FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL :period DAY)");
    $stmtSales->bindParam(':period', $period, PDO::PARAM_INT);
    $stmtSales->execute();
    $salesKPI = $stmtSales->fetch(PDO::FETCH_ASSOC);

    // Previous period for comparison
    $stmtPrev = $db->prepare("SELECT 
        COALESCE(SUM(total_price), 0) as revenue,
        COALESCE(SUM(profit), 0) as profit,
        COUNT(*) as total_transactions
        FROM sales 
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL :period2 DAY) 
        AND sale_date < DATE_SUB(CURDATE(), INTERVAL :period1 DAY)");
    $period2 = $period * 2;
    $stmtPrev->bindParam(':period2', $period2, PDO::PARAM_INT);
    $stmtPrev->bindParam(':period1', $period, PDO::PARAM_INT);
    $stmtPrev->execute();
    $prevKPI = $stmtPrev->fetch(PDO::FETCH_ASSOC);

    // Growth percentages
    $revenueGrowth = $prevKPI['revenue'] > 0 ? (($salesKPI['revenue'] - $prevKPI['revenue']) / $prevKPI['revenue']) * 100 : 0;
    $profitGrowth = $prevKPI['profit'] > 0 ? (($salesKPI['profit'] - $prevKPI['profit']) / $prevKPI['profit']) * 100 : 0;
    $txnGrowth = $prevKPI['total_transactions'] > 0 ? (($salesKPI['total_transactions'] - $prevKPI['total_transactions']) / $prevKPI['total_transactions']) * 100 : 0;

    // Profit margin
    $profitMargin = $salesKPI['revenue'] > 0 ? ($salesKPI['profit'] / $salesKPI['revenue']) * 100 : 0;

    // ── Revenue & Profit Trend (daily) ─────────────────────
    $stmtTrend = $db->prepare("SELECT 
        DATE(sale_date) as date,
        SUM(total_price) as revenue,
        SUM(profit) as profit,
        COUNT(*) as transactions,
        SUM(quantity) as units
        FROM sales 
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        GROUP BY DATE(sale_date) ORDER BY date ASC");
    $stmtTrend->bindParam(':period', $period, PDO::PARAM_INT);
    $stmtTrend->execute();
    $dailyTrend = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

    // ── Monthly Revenue (last 12 months) ───────────────────
    $stmt = $db->query("SELECT 
        DATE_FORMAT(sale_date, '%Y-%m') as month,
        SUM(total_price) as revenue,
        SUM(profit) as profit,
        COUNT(*) as transactions
        FROM sales 
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(sale_date, '%Y-%m') ORDER BY month ASC");
    $monthlyRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Top 10 Selling Medicines ───────────────────────────
    $stmtTop = $db->prepare("SELECT m.name, 
        SUM(s.quantity) as total_qty, 
        SUM(s.total_price) as total_revenue,
        SUM(s.profit) as total_profit
        FROM sales s JOIN medicines m ON s.medicine_id = m.id
        WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        GROUP BY m.id, m.name ORDER BY total_qty DESC LIMIT 10");
    $stmtTop->bindParam(':period', $period, PDO::PARAM_INT);
    $stmtTop->execute();
    $topSelling = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

    // ── Slowest Moving Medicines ───────────────────────────
    $stmt = $db->query("SELECT m.name, 
        COALESCE(SUM(mb.quantity), 0) as stock,
        COALESCE(ts.total_sold, 0) as sold_last_90d
        FROM medicines m 
        LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
        LEFT JOIN (
            SELECT medicine_id, SUM(quantity) as total_sold FROM sales 
            WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY medicine_id
        ) ts ON m.id = ts.medicine_id
        GROUP BY m.id, m.name
        HAVING stock > 0
        ORDER BY sold_last_90d ASC, stock DESC LIMIT 10");
    $slowMoving = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Sales by Hour of Day ───────────────────────────────
    $stmtHour = $db->prepare("SELECT 
        HOUR(sale_date) as hour,
        COUNT(*) as transactions,
        SUM(total_price) as revenue
        FROM sales 
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        GROUP BY HOUR(sale_date) ORDER BY hour ASC");
    $stmtHour->bindParam(':period', $period, PDO::PARAM_INT);
    $stmtHour->execute();
    $hourlyData = $stmtHour->fetchAll(PDO::FETCH_ASSOC);

    // ── Sales by Day of Week ───────────────────────────────
    $stmtDow = $db->prepare("SELECT 
        DAYOFWEEK(sale_date) as dow,
        DAYNAME(sale_date) as day_name,
        COUNT(*) as transactions,
        SUM(total_price) as revenue
        FROM sales 
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        GROUP BY DAYOFWEEK(sale_date), DAYNAME(sale_date) ORDER BY dow ASC");
    $stmtDow->bindParam(':period', $period, PDO::PARAM_INT);
    $stmtDow->execute();
    $weekdayData = $stmtDow->fetchAll(PDO::FETCH_ASSOC);

    // ── Low Stock Details ──────────────────────────────────
    $stmt = $db->query("SELECT m.name, m.inventory_price, m.sale_price,
        COALESCE(SUM(mb.quantity), 0) as current_stock
        FROM medicines m LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
        GROUP BY m.id HAVING current_stock < 10 AND current_stock > 0
        ORDER BY current_stock ASC LIMIT 15");
    $lowStockDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Expiring Soon Details ──────────────────────────────
    $stmt = $db->query("SELECT m.name, mb.batch_number, mb.expiration_date, mb.quantity,
        DATEDIFF(mb.expiration_date, CURDATE()) as days_left
        FROM medicine_batches mb JOIN medicines m ON mb.medicine_id = m.id
        WHERE mb.expiration_date > CURDATE() 
        AND mb.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) 
        AND mb.quantity > 0
        ORDER BY mb.expiration_date ASC LIMIT 15");
    $expiringDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Recent Sales ───────────────────────────────────────
    $stmt = $db->query("SELECT s.*, m.name as medicine_name 
        FROM sales s JOIN medicines m ON s.medicine_id = m.id
        ORDER BY s.sale_date DESC LIMIT 10");
    $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Supplier Count ─────────────────────────────────────
    $stmt = $db->query("SELECT COUNT(*) as total FROM suppliers");
    $totalSuppliers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ── Prescription Stats ─────────────────────────────────
    $stmtRx = $db->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Dispensed' THEN 1 ELSE 0 END) as dispensed
        FROM prescriptions 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :period DAY)");
    $stmtRx->bindParam(':period', $period, PDO::PARAM_INT);
    $stmtRx->execute();
    $rxStats = $stmtRx->fetch(PDO::FETCH_ASSOC);

    // ── Today's Summary ────────────────────────────────────
    $stmt = $db->query("SELECT 
        COALESCE(SUM(total_price), 0) as revenue,
        COALESCE(SUM(profit), 0) as profit,
        COUNT(*) as transactions
        FROM sales WHERE DATE(sale_date) = CURDATE()");
    $todaySummary = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        // KPI cards
        'totalMedicines' => (int)$totalMedicines,
        'totalStock' => (int)$totalStock,
        'lowStockItems' => (int)$lowStockItems,
        'outOfStock' => (int)$outOfStock,
        'expiredBatches' => (int)$expiredBatches,
        'expiringSoon' => (int)$expiringSoon,
        'inventoryCost' => round((float)$inventoryCost, 2),
        'inventoryRetail' => round((float)$inventoryRetail, 2),
        'totalSuppliers' => (int)$totalSuppliers,

        // Sales KPIs
        'revenue' => round((float)$salesKPI['revenue'], 2),
        'profit' => round((float)$salesKPI['profit'], 2),
        'profitMargin' => round($profitMargin, 1),
        'totalTransactions' => (int)$salesKPI['total_transactions'],
        'totalUnitsSold' => (int)$salesKPI['total_units'],
        'avgOrderValue' => round((float)$salesKPI['avg_order_value'], 2),

        // Growth
        'revenueGrowth' => round($revenueGrowth, 1),
        'profitGrowth' => round($profitGrowth, 1),
        'txnGrowth' => round($txnGrowth, 1),

        // Today
        'todayRevenue' => round((float)$todaySummary['revenue'], 2),
        'todayProfit' => round((float)$todaySummary['profit'], 2),
        'todayTransactions' => (int)$todaySummary['transactions'],

        // Charts
        'dailyTrend' => $dailyTrend,
        'monthlyRevenue' => $monthlyRevenue,
        'topSelling' => $topSelling,
        'slowMoving' => $slowMoving,
        'hourlyData' => $hourlyData,
        'weekdayData' => $weekdayData,

        // Tables
        'lowStockDetails' => $lowStockDetails,
        'expiringDetails' => $expiringDetails,
        'recentSales' => $recentSales,

        // Prescriptions
        'rxTotal' => (int)($rxStats['total'] ?? 0),
        'rxPending' => (int)($rxStats['pending'] ?? 0),
        'rxDispensed' => (int)($rxStats['dispensed'] ?? 0),

        'period' => $period
    ]);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}