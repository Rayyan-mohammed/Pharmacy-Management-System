<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();

$periodDays = max(7, (int)($_GET['days'] ?? 30));

$fastQuery = "SELECT m.id, m.name,
                     COALESCE(SUM(CASE WHEN s.sale_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY) THEN s.quantity ELSE 0 END), 0) as qty_sold,
                     COALESCE(SUM(mb.quantity), 0) as stock
              FROM medicines m
              LEFT JOIN sales s ON s.medicine_id = m.id
              LEFT JOIN medicine_batches mb ON mb.medicine_id = m.id
              GROUP BY m.id, m.name
              ORDER BY qty_sold DESC
              LIMIT 10";
$fastStmt = $db->prepare($fastQuery);
$fastStmt->bindValue(':days', $periodDays, PDO::PARAM_INT);
$fastStmt->execute();
$fastMoving = $fastStmt->fetchAll(PDO::FETCH_ASSOC);

$slowQuery = "SELECT m.id, m.name,
                     COALESCE(SUM(CASE WHEN s.sale_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY) THEN s.quantity ELSE 0 END), 0) as qty_sold,
                     COALESCE(SUM(mb.quantity), 0) as stock
              FROM medicines m
              LEFT JOIN sales s ON s.medicine_id = m.id
              LEFT JOIN medicine_batches mb ON mb.medicine_id = m.id
              GROUP BY m.id, m.name
              HAVING stock > 0
              ORDER BY qty_sold ASC
              LIMIT 10";
$slowStmt = $db->prepare($slowQuery);
$slowStmt->bindValue(':days', $periodDays, PDO::PARAM_INT);
$slowStmt->execute();
$slowMoving = $slowStmt->fetchAll(PDO::FETCH_ASSOC);

$deadStockQuery = "SELECT m.id, m.name, m.inventory_price,
                          COALESCE(SUM(mb.quantity), 0) as stock,
                          MAX(s.sale_date) as last_sale
                   FROM medicines m
                   LEFT JOIN medicine_batches mb ON mb.medicine_id = m.id
                   LEFT JOIN sales s ON s.medicine_id = m.id
                   GROUP BY m.id, m.name, m.inventory_price
                   HAVING stock > 0 AND (last_sale IS NULL OR last_sale < DATE_SUB(CURDATE(), INTERVAL 90 DAY))
                   ORDER BY stock DESC";
$deadStmt = $db->prepare($deadStockQuery);
$deadStmt->execute();
$deadStock = $deadStmt->fetchAll(PDO::FETCH_ASSOC);

$nearExpiryQuery = "SELECT m.id, m.name, mb.batch_number, mb.expiration_date, mb.quantity,
                           m.inventory_price,
                           (mb.quantity * m.inventory_price) as value_at_risk,
                           DATEDIFF(mb.expiration_date, CURDATE()) as days_left
                    FROM medicine_batches mb
                    JOIN medicines m ON m.id = mb.medicine_id
                    WHERE mb.quantity > 0
                      AND mb.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                    ORDER BY mb.expiration_date ASC";
$nearStmt = $db->prepare($nearExpiryQuery);
$nearStmt->execute();
$nearExpiry = $nearStmt->fetchAll(PDO::FETCH_ASSOC);

$totalDeadValue = 0;
foreach ($deadStock as $d) {
    $totalDeadValue += (float)$d['stock'] * (float)$d['inventory_price'];
}

$totalNearExpiryRisk = 0;
foreach ($nearExpiry as $n) {
    $totalNearExpiryRisk += (float)$n['value_at_risk'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Analytics - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php"><i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro</a>
    </div>
</nav>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary mb-0"><i class="bi bi-bar-chart-line me-2"></i>Stock Valuation & Purchase Analytics</h2>
        <form method="GET" class="d-flex gap-2">
            <select class="form-select" name="days">
                <option value="30" <?php echo $periodDays === 30 ? 'selected' : ''; ?>>Last 30 days</option>
                <option value="60" <?php echo $periodDays === 60 ? 'selected' : ''; ?>>Last 60 days</option>
                <option value="90" <?php echo $periodDays === 90 ? 'selected' : ''; ?>>Last 90 days</option>
            </select>
            <button class="btn btn-outline-primary" type="submit">Apply</button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Dead Stock Value</small><h4 class="text-danger">₹<?php echo number_format($totalDeadValue, 2); ?></h4></div></div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Near-Expiry Value at Risk</small><h4 class="text-warning">₹<?php echo number_format($totalNearExpiryRisk, 2); ?></h4></div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0 text-primary">Fast Moving Medicines</h5></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th class="px-3">Medicine</th><th class="text-end">Sold</th><th class="text-end px-3">Stock</th></tr></thead>
                        <tbody>
                            <?php foreach ($fastMoving as $f): ?>
                                <tr><td class="px-3"><?php echo htmlspecialchars($f['name']); ?></td><td class="text-end"><?php echo (int)$f['qty_sold']; ?></td><td class="text-end px-3"><?php echo (int)$f['stock']; ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0 text-primary">Slow Moving Medicines</h5></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th class="px-3">Medicine</th><th class="text-end">Sold</th><th class="text-end px-3">Stock</th></tr></thead>
                        <tbody>
                            <?php foreach ($slowMoving as $s): ?>
                                <tr><td class="px-3"><?php echo htmlspecialchars($s['name']); ?></td><td class="text-end"><?php echo (int)$s['qty_sold']; ?></td><td class="text-end px-3"><?php echo (int)$s['stock']; ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0 text-primary">Dead Stock (No sale for 90 days)</h5></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th class="px-3">Medicine</th><th class="text-end">Stock</th><th class="text-end">Value</th><th class="text-end px-3">Last Sale</th></tr></thead>
                        <tbody>
                            <?php if (empty($deadStock)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No dead stock detected.</td></tr>
                            <?php else: ?>
                                <?php foreach ($deadStock as $d): ?>
                                    <tr>
                                        <td class="px-3"><?php echo htmlspecialchars($d['name']); ?></td>
                                        <td class="text-end"><?php echo (int)$d['stock']; ?></td>
                                        <td class="text-end">₹<?php echo number_format((float)$d['stock'] * (float)$d['inventory_price'], 2); ?></td>
                                        <td class="text-end px-3"><?php echo !empty($d['last_sale']) ? date('d M Y', strtotime($d['last_sale'])) : 'Never'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0 text-primary">Near Expiry Value at Risk</h5></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th class="px-3">Batch</th><th>Medicine</th><th class="text-end">Days</th><th class="text-end px-3">Risk Value</th></tr></thead>
                        <tbody>
                            <?php if (empty($nearExpiry)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No near-expiry risk detected.</td></tr>
                            <?php else: ?>
                                <?php foreach ($nearExpiry as $n): ?>
                                    <tr>
                                        <td class="px-3"><?php echo htmlspecialchars($n['batch_number']); ?></td>
                                        <td><?php echo htmlspecialchars($n['name']); ?></td>
                                        <td class="text-end"><?php echo (int)$n['days_left']; ?></td>
                                        <td class="text-end px-3">₹<?php echo number_format((float)$n['value_at_risk'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
