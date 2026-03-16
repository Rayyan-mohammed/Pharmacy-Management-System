<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();

function medicine_column_exists($db, $column) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medicines' AND COLUMN_NAME = :column");
        $stmt->bindValue(':column', $column);
        $stmt->execute();
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Exception $e) {
        return false;
    }
}

$hasReorderLevel = medicine_column_exists($db, 'reorder_level');
$reorderExpr = $hasReorderLevel ? 'm.reorder_level' : '10';

// Low stock medicines (stock <= reorder level, fallback 10)
$lowStockQuery = "SELECT m.id, m.name, m.stock, m.sale_price, m.inventory_price,
                  {$reorderExpr} as reorder_level,
                  COALESCE(mc.name, 'Uncategorized') as category
                  FROM medicines m
                  LEFT JOIN medicine_categories mc ON m.category_id = mc.id
                  WHERE m.stock <= {$reorderExpr}
                  ORDER BY m.stock ASC";
$lowStockStmt = $db->query($lowStockQuery);
$lowStock = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);

// Out of stock
$outOfStockQuery = "SELECT COUNT(*) as count FROM medicines WHERE stock = 0";
$outOfStockStmt = $db->query($outOfStockQuery);
$outOfStockCount = $outOfStockStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Expiring within 30 days
$expiringQuery = "SELECT m.name, mb.batch_number, mb.quantity, mb.expiration_date,
                  DATEDIFF(mb.expiration_date, CURDATE()) as days_left
                  FROM medicine_batches mb
                  JOIN medicines m ON mb.medicine_id = m.id
                  WHERE mb.quantity > 0 AND mb.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                  ORDER BY mb.expiration_date ASC";
$expiringStmt = $db->query($expiringQuery);
$expiring = $expiringStmt->fetchAll(PDO::FETCH_ASSOC);

// Already expired
$expiredQuery = "SELECT m.name, mb.batch_number, mb.quantity, mb.expiration_date,
                 ABS(DATEDIFF(mb.expiration_date, CURDATE())) as days_ago
                 FROM medicine_batches mb
                 JOIN medicines m ON mb.medicine_id = m.id
                 WHERE mb.quantity > 0 AND mb.expiration_date < CURDATE()
                 ORDER BY mb.expiration_date ASC";
$expiredStmt = $db->query($expiredQuery);
$expired = $expiredStmt->fetchAll(PDO::FETCH_ASSOC);

// Pending prescriptions
$pendingRxQuery = "SELECT COUNT(*) as count FROM prescriptions WHERE status = 'Pending'";
$pendingRxStmt = $db->query($pendingRxQuery);
$pendingRx = $pendingRxStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending returns
$pendingReturnsCount = 0;
try {
    $pendingReturnsQuery = "SELECT COUNT(*) as count FROM returns WHERE status = 'pending'";
    $pendingReturnsStmt = $db->query($pendingReturnsQuery);
    $pendingReturnsCount = $pendingReturnsStmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (Exception $e) {
    // returns table may not exist yet
}

$reorderNeededCount = count($lowStock);
$totalAlerts = count($lowStock) + count($expiring) + count($expired) + $pendingRx + $pendingReturnsCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts & Notifications - PharmaFlow Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>PharmaFlow Pro
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1"><i class="bi bi-bell me-2"></i>Alerts & Notifications</h2>
                <p class="text-muted mb-0">System-wide alerts requiring your attention.</p>
            </div>
            <div>
                <span class="badge bg-danger fs-6"><?php echo $totalAlerts; ?> alerts</span>
                <a href="reorder_suggestions.php" class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-arrow-repeat me-1"></i>Reorder Plan</a>
                <a href="../api/export_csv.php?type=expiring&days=90" class="btn btn-sm btn-outline-success ms-2"><i class="bi bi-download me-1"></i>Export Expiring</a>
            </div>
        </div>

        <!-- Alert Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-6">
                <div class="card border-0 shadow-sm text-center py-3 <?php echo count($expired) ? 'border-start border-4 border-danger' : ''; ?>">
                    <h3 class="mb-0 fw-bold text-danger"><?php echo count($expired); ?></h3>
                    <small class="text-muted">Expired</small>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card border-0 shadow-sm text-center py-3 <?php echo count($expiring) ? 'border-start border-4 border-warning' : ''; ?>">
                    <h3 class="mb-0 fw-bold text-warning"><?php echo count($expiring); ?></h3>
                    <small class="text-muted">Expiring Soon</small>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card border-0 shadow-sm text-center py-3 <?php echo $outOfStockCount ? 'border-start border-4 border-dark' : ''; ?>">
                    <h3 class="mb-0 fw-bold"><?php echo $outOfStockCount; ?></h3>
                    <small class="text-muted">Out of Stock</small>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card border-0 shadow-sm text-center py-3 <?php echo count($lowStock) ? 'border-start border-4 border-orange' : ''; ?>">
                    <h3 class="mb-0 fw-bold text-primary"><?php echo count($lowStock); ?></h3>
                    <small class="text-muted">Low Stock</small>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card border-0 shadow-sm text-center py-3 <?php echo $reorderNeededCount ? 'border-start border-4 border-info' : ''; ?>">
                    <h3 class="mb-0 fw-bold text-info"><?php echo $reorderNeededCount; ?></h3>
                    <small class="text-muted">Reorder Needed</small>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card border-0 shadow-sm text-center py-3">
                    <h3 class="mb-0 fw-bold text-info"><?php echo $pendingRx; ?></h3>
                    <small class="text-muted">Pending Rx</small>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card border-0 shadow-sm text-center py-3">
                    <h3 class="mb-0 fw-bold text-secondary"><?php echo $pendingReturnsCount; ?></h3>
                    <small class="text-muted">Pending Returns</small>
                </div>
            </div>
        </div>

        <!-- Expired Batches -->
        <?php if (!empty($expired)): ?>
        <div class="card border-0 shadow-sm mb-4 border-start border-4 border-danger">
            <div class="card-header bg-danger bg-opacity-10 border-0">
                <h5 class="mb-0 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Expired Batches (<?php echo count($expired); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light"><tr><th class="px-4">Medicine</th><th>Batch</th><th class="text-center">Qty</th><th>Expired On</th><th>Days Ago</th></tr></thead>
                        <tbody>
                            <?php foreach ($expired as $item): ?>
                            <tr>
                                <td class="px-4 fw-bold text-danger"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['batch_number']); ?></td>
                                <td class="text-center"><span class="badge bg-danger"><?php echo $item['quantity']; ?></span></td>
                                <td><?php echo date('d M Y', strtotime($item['expiration_date'])); ?></td>
                                <td><span class="text-danger fw-bold"><?php echo $item['days_ago']; ?> days</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Expiring Soon -->
        <?php if (!empty($expiring)): ?>
        <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
            <div class="card-header bg-warning bg-opacity-10 border-0">
                <h5 class="mb-0 text-warning"><i class="bi bi-clock-history me-2"></i>Expiring Within 30 Days (<?php echo count($expiring); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light"><tr><th class="px-4">Medicine</th><th>Batch</th><th class="text-center">Qty</th><th>Expires On</th><th>Days Left</th></tr></thead>
                        <tbody>
                            <?php foreach ($expiring as $item): ?>
                            <tr>
                                <td class="px-4 fw-bold"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['batch_number']); ?></td>
                                <td class="text-center"><span class="badge bg-warning text-dark"><?php echo $item['quantity']; ?></span></td>
                                <td><?php echo date('d M Y', strtotime($item['expiration_date'])); ?></td>
                                <td>
                                    <?php
                                    $urgency = $item['days_left'] <= 7 ? 'danger' : ($item['days_left'] <= 15 ? 'warning' : 'info');
                                    ?>
                                    <span class="badge bg-<?php echo $urgency; ?>"><?php echo $item['days_left']; ?> days</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Low Stock -->
        <?php if (!empty($lowStock)): ?>
        <div class="card border-0 shadow-sm mb-4 border-start border-4 border-primary">
            <div class="card-header bg-primary bg-opacity-10 border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary"><i class="bi bi-box-seam me-2"></i>Low Stock Items (<?php echo count($lowStock); ?>)</h5>
                <a href="../update/update-stock.php" class="btn btn-sm btn-primary">Restock Now</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light"><tr><th class="px-4">Medicine</th><th>Category</th><th class="text-center">Stock</th><th class="text-end">Cost</th><th class="text-end px-4">Sale Price</th></tr></thead>
                        <tbody>
                            <?php foreach ($lowStock as $item): ?>
                            <tr>
                                <td class="px-4 fw-bold"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($item['category']); ?></small></td>
                                <td class="text-center">
                                    <?php
                                    $stockClass = $item['stock'] == 0 ? 'bg-dark' : ($item['stock'] <= 5 ? 'bg-danger' : 'bg-warning text-dark');
                                    ?>
                                    <span class="badge <?php echo $stockClass; ?>"><?php echo $item['stock'] == 0 ? 'OUT' : $item['stock']; ?></span>
                                    <div><small class="text-muted">Target: <?php echo (int)$item['reorder_level']; ?></small></div>
                                </td>
                                <td class="text-end">₹<?php echo number_format($item['inventory_price'], 2); ?></td>
                                <td class="text-end px-4">₹<?php echo number_format($item['sale_price'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($totalAlerts === 0): ?>
        <div class="text-center py-5">
            <div class="fs-1 mb-3">✅</div>
            <h4 class="text-success">All Clear!</h4>
            <p class="text-muted">No alerts or notifications at this time.</p>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

