<?php
include_once '../database.php';
include_once '../models/Medicine.php';
include_once '../models/Inventory.php';

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$inventory = new Inventory($db);

// Get inventory report
$query = "SELECT m.*, 
          (SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) 
           FROM inventory_logs 
           WHERE medicine_id = m.id) as current_stock
          FROM medicines m
          ORDER BY m.name ASC";
$stmt = $db->query($query);
$inventory_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_items = count($inventory_report);
$total_value = 0;
$low_stock_items = 0;
foreach ($inventory_report as $item) {
    $total_value += $item['current_stock'] * $item['sale_price'];
    if ($item['current_stock'] < 50) {
        $low_stock_items++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report - Pharmacy Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Pharmacy Management System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/medical_management_new/public/dashboard/dashboard.php">Dashboard</a>
                <a class="nav-link" href="/medical_management_new/public/index.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Inventory Report</h3>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Items</h5>
                                <h3 class="text-primary"><?php echo $total_items; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Value</h5>
                                <h3 class="text-success">₹<?php echo number_format($total_value, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock Items</h5>
                                <h3 class="text-warning"><?php echo $low_stock_items; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Average Stock Level</h5>
                                <h3 class="text-info">
                                    <?php 
                                    $avg_stock = array_sum(array_column($inventory_report, 'current_stock')) / $total_items;
                                    echo number_format($avg_stock, 1);
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Medicine Name</th>
                                <th>Current Stock</th>
                                <th>Unit Price</th>
                                <th>Total Value</th>
                                <th>Expiry Date</th>
                                <th>Prescription Required</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory_report as $item): 
                                $status_class = $item['current_stock'] <= 10 ? 'danger' : 
                                             ($item['current_stock'] <= 50 ? 'warning' : 'success');
                                $status_text = $item['current_stock'] <= 10 ? 'Low Stock' : 
                                            ($item['current_stock'] <= 50 ? 'Medium Stock' : 'In Stock');
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo $item['current_stock']; ?></td>
                                    <td>₹<?php echo number_format($item['sale_price'], 2); ?></td>
                                    <td>₹<?php echo number_format($item['current_stock'] * $item['sale_price'], 2); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($item['expiration_date'])); ?></td>
                                    <td><?php echo $item['prescription_needed'] ? 'Yes' : 'No'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <td colspan="3"><strong>Total</strong></td>
                                <td><strong>₹<?php echo number_format($total_value, 2); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 