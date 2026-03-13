<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist', 'Staff']);

$database = new Database();
$db = $database->getConnection();

// CSV injection sanitizer — prevents formula injection in Excel
function sanitize_csv_value($value) {
    if (is_string($value) && strlen($value) > 0) {
        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@', "\t", "\r"])) {
            $value = "'" . $value;
        }
    }
    return $value;
}

function write_csv_row($output, $row) {
    fputcsv($output, array_map('sanitize_csv_value', $row));
}

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

$type = $_GET['type'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';

if ($type === 'sales') {
    $hasCustomerPhone = sales_column_exists($db, 'customer_phone');
    $hasPaymentMethod = sales_column_exists($db, 'payment_method');
    $hasNetTotal = sales_column_exists($db, 'net_total');
    $hasDiscountAmount = sales_column_exists($db, 'discount_amount');
    $hasTaxAmount = sales_column_exists($db, 'tax_amount');
    $hasPaymentReference = sales_column_exists($db, 'payment_reference');
    $allowedMethods = ['Cash', 'Card', 'UPI'];
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $paymentMethod = '';
    }

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
    $where = count($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $selectCustomerPhone = $hasCustomerPhone ? ', s.customer_phone' : ', NULL AS customer_phone';
    $selectPaymentMethod = $hasPaymentMethod ? ', s.payment_method' : ', NULL AS payment_method';
    $selectNetTotal = $hasNetTotal ? ', s.net_total' : ', s.total_price as net_total';
    $selectDiscountAmount = $hasDiscountAmount ? ', s.discount_amount' : ', 0 as discount_amount';
    $selectTaxAmount = $hasTaxAmount ? ', s.tax_amount' : ', 0 as tax_amount';
    $selectPaymentReference = $hasPaymentReference ? ', s.payment_reference' : ', NULL as payment_reference';

    $query = "SELECT s.id, s.sale_date, m.name as medicine_name, s.quantity, s.unit_price, s.total_price, s.profit, s.customer_name" . $selectCustomerPhone . $selectPaymentMethod . $selectNetTotal . $selectDiscountAmount . $selectTaxAmount . $selectPaymentReference . "
              FROM sales s JOIN medicines m ON s.medicine_id = m.id" . $where . " ORDER BY s.sale_date DESC";
    $stmt = $db->prepare($query);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'sales_report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Sale ID', 'Date', 'Medicine', 'Qty', 'Unit Price', 'Subtotal', 'Discount', 'Tax', 'Net Total', 'Profit', 'Customer', 'Customer Mobile', 'Payment Method', 'Payment Reference']);
    foreach ($data as $row) {
        write_csv_row($output, [
            $row['id'],
            date('Y-m-d H:i', strtotime($row['sale_date'])),
            $row['medicine_name'],
            $row['quantity'],
            $row['unit_price'],
            $row['total_price'],
            $row['discount_amount'] ?? 0,
            $row['tax_amount'] ?? 0,
            $row['net_total'] ?? $row['total_price'],
            $row['profit'],
            $row['customer_name'] ?? '',
            $row['customer_phone'] ?? '',
            $row['payment_method'] ?? '',
            $row['payment_reference'] ?? ''
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
        write_csv_row($output, [
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
        write_csv_row($output, [
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

} elseif ($type === 'purchases') {
    $conditions = [];
    $params = [];
    $supplierId = (int)($_GET['supplier_id'] ?? 0);
    $status = $_GET['payment_status'] ?? '';
    $allowedStatuses = ['Paid', 'Partial', 'Pending'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = '';
    }

    if ($startDate) {
        $conditions[] = "p.purchase_date >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if ($endDate) {
        $conditions[] = "p.purchase_date <= :end_date";
        $params[':end_date'] = $endDate;
    }
    if ($supplierId > 0) {
        $conditions[] = "p.supplier_id = :supplier_id";
        $params[':supplier_id'] = $supplierId;
    }
    if ($status !== '') {
        $conditions[] = "p.payment_status = :payment_status";
        $params[':payment_status'] = $status;
    }

    $where = count($conditions) ? (' WHERE ' . implode(' AND ', $conditions)) : '';

    $query = "SELECT p.id, p.invoice_number, p.purchase_date, s.name as supplier_name,
                     p.subtotal, p.discount_amount, p.tax_amount, p.total_amount, p.amount_paid, p.due_amount, p.payment_status,
                     DATEDIFF(CURDATE(), p.purchase_date) as bill_age_days
              FROM purchases p
              JOIN suppliers s ON s.id = p.supplier_id" . $where . "
              ORDER BY p.purchase_date DESC, p.id DESC";
    $stmt = $db->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'purchase_report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['GRN ID', 'Invoice', 'Date', 'Supplier', 'Subtotal', 'Discount', 'Tax', 'Total', 'Paid', 'Due', 'Status', 'Age (Days)']);
    foreach ($data as $row) {
        write_csv_row($output, [
            $row['id'],
            $row['invoice_number'],
            $row['purchase_date'],
            $row['supplier_name'],
            $row['subtotal'],
            $row['discount_amount'],
            $row['tax_amount'],
            $row['total_amount'],
            $row['amount_paid'],
            $row['due_amount'],
            $row['payment_status'],
            $row['bill_age_days']
        ]);
    }
    fclose($output);
    exit;

} elseif ($type === 'supplier_payables') {
    $query = "SELECT s.name as supplier_name,
                     COUNT(CASE WHEN p.due_amount > 0 THEN 1 END) as due_bills,
                     SUM(CASE WHEN p.due_amount > 0 THEN p.due_amount ELSE 0 END) as total_due,
                     SUM(CASE WHEN p.due_amount > 0 AND DATEDIFF(CURDATE(), p.purchase_date) BETWEEN 0 AND 30 THEN p.due_amount ELSE 0 END) as bucket_0_30,
                     SUM(CASE WHEN p.due_amount > 0 AND DATEDIFF(CURDATE(), p.purchase_date) BETWEEN 31 AND 60 THEN p.due_amount ELSE 0 END) as bucket_31_60,
                     SUM(CASE WHEN p.due_amount > 0 AND DATEDIFF(CURDATE(), p.purchase_date) BETWEEN 61 AND 90 THEN p.due_amount ELSE 0 END) as bucket_61_90,
                     SUM(CASE WHEN p.due_amount > 0 AND DATEDIFF(CURDATE(), p.purchase_date) > 90 THEN p.due_amount ELSE 0 END) as bucket_90_plus
              FROM suppliers s
              LEFT JOIN purchases p ON p.supplier_id = s.id
              GROUP BY s.id, s.name
              HAVING total_due > 0
              ORDER BY total_due DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'supplier_payable_aging_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Supplier', 'Due Bills', '0-30 Days', '31-60 Days', '61-90 Days', '90+ Days', 'Total Due']);
    foreach ($data as $row) {
        write_csv_row($output, [
            $row['supplier_name'],
            $row['due_bills'],
            $row['bucket_0_30'],
            $row['bucket_31_60'],
            $row['bucket_61_90'],
            $row['bucket_90_plus'],
            $row['total_due']
        ]);
    }
    fclose($output);
    exit;

} elseif ($type === 'settlements') {
    $query = "SELECT pp.id, pp.payment_date, p.invoice_number, s.name as supplier_name,
                     pp.amount, pp.payment_method, pp.reference_no, pp.notes
              FROM purchase_payments pp
              JOIN purchases p ON p.id = pp.purchase_id
              JOIN suppliers s ON s.id = p.supplier_id
              ORDER BY pp.payment_date DESC, pp.id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'supplier_settlements_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'Invoice', 'Supplier', 'Amount', 'Method', 'Reference', 'Notes']);
    foreach ($data as $row) {
        write_csv_row($output, [
            $row['id'],
            $row['payment_date'],
            $row['invoice_number'],
            $row['supplier_name'],
            $row['amount'],
            $row['payment_method'],
            $row['reference_no'],
            $row['notes']
        ]);
    }
    fclose($output);
    exit;

} elseif ($type === 'purchase_returns') {
    $query = "SELECT pr.id, pr.return_number, pr.return_date, p.invoice_number, s.name as supplier_name,
                     pr.total_amount, pr.credit_applied, pr.status, pr.reason
              FROM purchase_returns pr
              JOIN purchases p ON p.id = pr.purchase_id
              JOIN suppliers s ON s.id = pr.supplier_id
              ORDER BY pr.return_date DESC, pr.id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'purchase_returns_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Return #', 'Date', 'Invoice', 'Supplier', 'Return Amount', 'Credit Applied', 'Status', 'Reason']);
    foreach ($data as $row) {
        write_csv_row($output, [
            $row['id'],
            $row['return_number'],
            $row['return_date'],
            $row['invoice_number'],
            $row['supplier_name'],
            $row['total_amount'],
            $row['credit_applied'],
            $row['status'],
            $row['reason']
        ]);
    }
    fclose($output);
    exit;

} else {
    http_response_code(400);
    echo 'Invalid export type. Use ?type=sales, ?type=inventory, ?type=expiring, ?type=purchases, ?type=supplier_payables, ?type=settlements, or ?type=purchase_returns';
}
