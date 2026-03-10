<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$inventory = new Inventory($db);

// Get detailed inventory report by batch
$query = "SELECT m.name, m.sale_price, m.prescription_needed, 
          mb.batch_number, mb.quantity, mb.expiration_date
          FROM medicine_batches mb
          JOIN medicines m ON mb.medicine_id = m.id
          WHERE mb.quantity > 0
          ORDER BY m.name ASC, mb.expiration_date ASC";
$stmt = $db->query($query);
$inventory_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals from batches
$total_batches = count($inventory_report);
$total_value = 0;
// Note: Low Stock is calculated per medicine, not per batch. We need a separate query or aggregation for that.
// Let's get generic medicine stats first using aggregated batch data
$med_stats_query = "SELECT COUNT(*) as total_meds, 
                    COALESCE(SUM(CASE WHEN total_stock < 50 THEN 1 ELSE 0 END), 0) as low_stock_count
                    FROM (
                        SELECT m.id, COALESCE(SUM(mb.quantity), 0) as total_stock
                        FROM medicines m
                        LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
                        GROUP BY m.id
                    ) as stock_data";
$med_stats = $db->query($med_stats_query)->fetch(PDO::FETCH_ASSOC);

$total_medicines = $med_stats['total_meds'];
$low_stock_items = $med_stats['low_stock_count'];

// Calculate total value from batches
foreach ($inventory_report as $item) {
    $total_value += $item['quantity'] * $item['sale_price'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4 shadow-sm bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../add/add-medicine.php">Add Medicine</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Inventory Report</a>
                    </li>
                </ul>
                <div class="navbar-nav ms-auto">
                     <a class="btn btn-light btn-sm fw-bold text-primary px-3" href="../logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Metrics Row -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="card-subtitle opacity-75">Total Batches</h6>
                            <i class="bi bi-box-seam fs-4"></i>
                        </div>
                        <h2 class="card-title mb-0"><?php echo number_format($total_batches); ?> <small class="fs-6 opacity-75">(<?php echo $total_medicines; ?> Meds)</small></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="card-subtitle opacity-75">Inventory Value</h6>
                            <i class="bi bi-currency-rupee fs-4"></i>
                        </div>
                        <h2 class="card-title mb-0">₹<?php echo number_format($total_value, 0); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                    $lowStockClass = $low_stock_items > 0 ? 'bg-danger' : 'bg-success';
                    $lowStockIcon = $low_stock_items > 0 ? 'bi-exclamation-octagon' : 'bi-check-circle';
                ?>
                <div class="card stat-card <?php echo $lowStockClass; ?> text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="card-subtitle opacity-75">Low Stock Meds (<50)</h6>
                            <i class="bi <?php echo $lowStockIcon; ?> fs-4"></i>
                        </div>
                        <h2 class="card-title mb-0"><?php echo number_format($low_stock_items); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-dark h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="card-subtitle opacity-75">Avg. Batch Size</h6>
                            <i class="bi bi-bar-chart fs-4"></i>
                        </div>
                        <h2 class="card-title mb-0">
                            <?php 
                            $avg_stock = $total_batches > 0 ? array_sum(array_column($inventory_report, 'quantity')) / $total_batches : 0;
                            echo number_format($avg_stock, 0);
                            ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary">Detailed Batch Inventory List</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print Report</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Medicine Name</th>
                                <th class="text-center">Batch #</th>
                                <th class="text-center">Stock</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total Batch Value</th>
                                <th>Expiry Date</th>
                                <th class="text-center">Rx</th>
                                <th class="text-center pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory_report as $item): 
                                $stock = $item['quantity'];
                                // Warning if batch is expiring soon (within 30 days) or expired
                                $days_to_expiry = (strtotime($item['expiration_date']) - time()) / (60 * 60 * 24);
                                
                                $status_badge = 'bg-success'; 
                                $status_label = 'Good';

                                if ($days_to_expiry < 0) {
                                    $status_badge = 'bg-danger';
                                    $status_label = 'Expired';
                                } elseif ($days_to_expiry < 30) {
                                    $status_badge = 'bg-warning text-dark';
                                    $status_label = 'Expiring Soon';
                                } elseif ($stock <= 10) {
                                    $status_badge = 'bg-warning text-dark';
                                    $status_label = 'Low Batch';
                                }
                            ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td class="text-center text-secondary font-monospace"><?php echo htmlspecialchars($item['batch_number']); ?></td>
                                    <td class="text-center">
                                        <span class="fs-6 fw-bold"><?php echo $stock; ?></span>
                                    </td>
                                    <td class="text-end">₹<?php echo number_format($item['sale_price'], 2); ?></td>
                                    <td class="text-end fw-bold text-secondary">₹<?php echo number_format($stock * $item['sale_price'], 2); ?></td>
                                    <td>
                                        <?php if(strtotime($item['expiration_date']) < time()): ?>
                                            <span class="text-danger fw-bold"><i class="bi bi-exclamation-circle me-1"></i><?php echo date('d M Y', strtotime($item['expiration_date'])); ?></span>
                                        <?php else: ?>
                                            <?php echo date('d M Y', strtotime($item['expiration_date'])); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if($item['prescription_needed']): ?>
                                            <i class="bi bi-prescription2 text-primary fs-5" title="Required"></i>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-4">
                                        <span class="badge <?php echo $status_badge; ?> rounded-pill px-3"><?php echo $status_label; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light text-end py-3">
                <small class="text-muted">Report generated on <?php echo date('d M Y, h:i A'); ?> | Pharmacy Pro System</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 