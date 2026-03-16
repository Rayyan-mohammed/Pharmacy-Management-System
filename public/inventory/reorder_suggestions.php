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

$query = "SELECT m.id, m.name, m.stock, {$reorderExpr} as reorder_level,
                 COALESCE(mc.name, 'Uncategorized') as category,
                 m.inventory_price,
                 GREATEST(({$reorderExpr} * 2) - m.stock, 0) as suggested_qty,
                 (GREATEST(({$reorderExpr} * 2) - m.stock, 0) * m.inventory_price) as est_cost
          FROM medicines m
          LEFT JOIN medicine_categories mc ON m.category_id = mc.id
          WHERE m.stock <= {$reorderExpr}
          ORDER BY (m.stock - {$reorderExpr}) ASC, m.name ASC";
$stmt = $db->query($query);
$suggestions = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$totalEst = 0;
foreach ($suggestions as $s) {
    $totalEst += (float)$s['est_cost'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reorder Suggestions - PharmaFlow Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php"><i class="bi bi-heart-pulse-fill me-2"></i>PharmaFlow Pro</a>
    </div>
</nav>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-1"><i class="bi bi-cart-plus me-2"></i>Reorder Suggestions</h2>
            <p class="text-muted mb-0">Auto-generated list for medicines below reorder threshold.</p>
        </div>
        <div class="text-end">
            <div class="small text-muted">Estimated Restock Cost</div>
            <h5 class="mb-0 text-primary">₹<?php echo number_format($totalEst, 2); ?></h5>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary">Suggested Purchase List</h5>
            <a href="../purchase/purchase-management.php" class="btn btn-sm btn-success"><i class="bi bi-truck me-1"></i>Create Purchase</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-3">Medicine</th>
                        <th>Category</th>
                        <th class="text-center">Current Stock</th>
                        <th class="text-center">Reorder Level</th>
                        <th class="text-center">Suggested Qty</th>
                        <th class="text-end">Cost/Unit</th>
                        <th class="text-end px-3">Est Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suggestions)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No reorder needed. All stock above threshold.</td></tr>
                    <?php else: ?>
                        <?php foreach ($suggestions as $row): ?>
                            <tr>
                                <td class="px-3 fw-semibold"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($row['category']); ?></small></td>
                                <td class="text-center"><?php echo (int)$row['stock']; ?></td>
                                <td class="text-center"><?php echo (int)$row['reorder_level']; ?></td>
                                <td class="text-center"><span class="badge bg-warning text-dark"><?php echo (int)$row['suggested_qty']; ?></span></td>
                                <td class="text-end">₹<?php echo number_format((float)$row['inventory_price'], 2); ?></td>
                                <td class="text-end px-3 fw-bold">₹<?php echo number_format((float)$row['est_cost'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>

