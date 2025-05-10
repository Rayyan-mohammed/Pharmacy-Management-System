<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get total medicines
    $query = "SELECT COUNT(*) as total FROM medicines";
    $stmt = $db->query($query);
    $totalMedicines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total stock
    $query = "SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as total FROM inventory_logs";
    $stmt = $db->query($query);
    $totalStock = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Get low stock items (less than 50)
    $query = "SELECT COUNT(*) as total FROM (
        SELECT medicine_id, SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as current_stock 
        FROM inventory_logs 
        GROUP BY medicine_id 
        HAVING current_stock < 50
    ) as low_stock";
    $stmt = $db->query($query);
    $lowStockItems = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get stock value
    $query = "SELECT SUM(m.sale_price * (
        SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) 
        FROM inventory_logs 
        WHERE medicine_id = m.id
    )) as total FROM medicines m";
    $stmt = $db->query($query);
    $stockValue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Get total sales revenue and profit
    $query = "SELECT 
        SUM(total_price) as revenue,
        SUM(total_price - (m.inventory_price * s.quantity)) as profit
    FROM sales s
    JOIN medicines m ON s.medicine_id = m.id";
    $stmt = $db->query($query);
    $salesData = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalSalesRevenue = $salesData['revenue'] ?? 0;
    $totalSalesProfit = $salesData['profit'] ?? 0;

    // Get sales data for chart
    $query = "SELECT 
        DATE(sale_date) as date,
        SUM(total_price) as revenue,
        SUM(total_price - (m.inventory_price * s.quantity)) as profit
    FROM sales s
    JOIN medicines m ON s.medicine_id = m.id
    GROUP BY DATE(sale_date)
    ORDER BY date DESC
    LIMIT 30";
    $stmt = $db->query($query);
    $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'totalMedicines' => $totalMedicines,
        'totalStock' => $totalStock,
        'lowStockItems' => $lowStockItems,
        'stockValue' => $stockValue,
        'totalSalesRevenue' => $totalSalesRevenue,
        'totalSalesProfit' => $totalSalesProfit,
        'salesData' => $salesData
    ]);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 