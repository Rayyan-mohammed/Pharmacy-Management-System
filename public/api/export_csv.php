<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist', 'Staff']);

$database = new Database();
$db = $database->getConnection();

$type = $_GET['type'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if ($type === 'sales') {
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
    $where = count($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $query = "SELECT s.id, s.sale_date, m.name as medicine_name, s.quantity, s.unit_price, s.total_price, s.profit, s.customer_name 
              FROM sales s JOIN medicines m ON s.medicine_id = m.id" . $where . " ORDER BY s.sale_date DESC";
    $stmt = $db->prepare($query);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'sales_report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Sale ID', 'Date', 'Medicine', 'Qty', 'Unit Price', 'Total', 'Profit', 'Customer']);
    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'],
            date('Y-m-d H:i', strtotime($row['sale_date'])),
            $row['medicine_name'],
            $row['quantity'],
            $row['unit_price'],
            $row['total_price'],
            $row['profit'],
            $row['customer_name'] ?? ''
        ]);
    }
    fclose($output);
    exit;

} elseif ($type === 'inventory') {
    $query = "SELECT m.id, m.name, m.description, m.inventory_price, m.sale_price, m.stock,
              mc.name as category,
              GROUP_CONCAT(CONCAT(mb.batch_number, ' (', mb.quantity, ' / exp:', mb.expiration_date, ')') SEPARATOR '; ') as batches
              FROM medicines m
              LEFT JOIN medicine_categories mc ON m.category_id = mc.id
              LEFT JOIN medicine_batches mb ON mb.medicine_id = m.id AND mb.quantity > 0
              GROUP BY m.id
              ORDER BY m.name ASC";
    $stmt = $db->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'inventory_report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Medicine', 'Description', 'Cost Price', 'Sale Price', 'Stock', 'Category', 'Batches']);
    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['description'] ?? '',
            $row['inventory_price'],
            $row['sale_price'],
            $row['stock'],
            $row['category'] ?? 'Uncategorized',
            $row['batches'] ?? ''
        ]);
    }
    fclose($output);
    exit;

} elseif ($type === 'expiring') {
    $days = (int)($_GET['days'] ?? 90);
    $query = "SELECT m.name as medicine_name, mb.batch_number, mb.quantity, mb.expiration_date,
              DATEDIFF(mb.expiration_date, CURDATE()) as days_left, m.inventory_price
              FROM medicine_batches mb
              JOIN medicines m ON mb.medicine_id = m.id
              WHERE mb.quantity > 0 AND mb.expiration_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
              ORDER BY mb.expiration_date ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'expiring_medicines_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Medicine', 'Batch', 'Quantity', 'Expiry Date', 'Days Left', 'Value at Risk']);
    foreach ($data as $row) {
        fputcsv($output, [
            $row['medicine_name'],
            $row['batch_number'],
            $row['quantity'],
            $row['expiration_date'],
            $row['days_left'],
            number_format($row['quantity'] * $row['inventory_price'], 2)
        ]);
    }
    fclose($output);
    exit;

} else {
    http_response_code(400);
    echo 'Invalid export type. Use ?type=sales, ?type=inventory, or ?type=expiring';
}
