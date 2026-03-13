<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist', 'Staff']);

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$searchTerm = '%' . $q . '%';
$results = [];

// Search medicines
$stmt = $db->prepare("SELECT id, name, sale_price, stock FROM medicines WHERE name LIKE :q ORDER BY name ASC LIMIT 5");
$stmt->bindParam(':q', $searchTerm);
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $results[] = [
        'type' => 'medicine',
        'icon' => 'bi-capsule',
        'title' => $row['name'],
        'detail' => '₹' . number_format($row['sale_price'], 2) . ' · Stock: ' . (int)$row['stock'],
        'url' => '../add/edit-medicine.php?id=' . $row['id']
    ];
}

// Search suppliers
$stmt = $db->prepare("SELECT id, name, contact_person, phone FROM suppliers WHERE name LIKE :q OR contact_person LIKE :q2 LIMIT 5");
$stmt->bindParam(':q', $searchTerm);
$stmt->bindParam(':q2', $searchTerm);
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $results[] = [
        'type' => 'supplier',
        'icon' => 'bi-truck',
        'title' => $row['name'],
        'detail' => $row['contact_person'] . ($row['phone'] ? ' · ' . $row['phone'] : ''),
        'url' => '../supplier/supplier-management.php?action=edit&id=' . $row['id']
    ];
}

// Search prescriptions
$stmt = $db->prepare("SELECT id, patient_name, doctor_name, status FROM prescriptions WHERE patient_name LIKE :q OR doctor_name LIKE :q2 ORDER BY id DESC LIMIT 5");
$stmt->bindParam(':q', $searchTerm);
$stmt->bindParam(':q2', $searchTerm);
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $results[] = [
        'type' => 'prescription',
        'icon' => 'bi-file-medical',
        'title' => $row['patient_name'],
        'detail' => 'Dr. ' . $row['doctor_name'] . ' · ' . ucfirst($row['status']),
        'url' => '../prescription/prescription-management.php?action=view&id=' . $row['id']
    ];
}

// Search sales (by customer name or invoice number)
try {
    $stmt = $db->prepare("SELECT s.id, s.customer_name, s.invoice_number, s.total_price, s.sale_date, m.name as medicine_name 
                          FROM sales s JOIN medicines m ON s.medicine_id = m.id
                          WHERE s.customer_name LIKE :q OR s.invoice_number LIKE :q2 OR m.name LIKE :q3
                          ORDER BY s.id DESC LIMIT 5");
    $stmt->bindParam(':q', $searchTerm);
    $stmt->bindParam(':q2', $searchTerm);
    $stmt->bindParam(':q3', $searchTerm);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $inv = $row['invoice_number'] ?? 'Sale #' . $row['id'];
        $results[] = [
            'type' => 'sale',
            'icon' => 'bi-receipt',
            'title' => $inv . ' — ' . $row['medicine_name'],
            'detail' => ($row['customer_name'] ?: 'Walk-in') . ' · ₹' . number_format($row['total_price'], 2),
            'url' => $row['invoice_number'] ? '../sales/invoice.php?invoice=' . urlencode($row['invoice_number']) : '../sales/invoice.php?id=' . $row['id']
        ];
    }
} catch (Exception $e) {
    // invoice_number column may not exist yet
}

echo json_encode(['results' => $results]);
