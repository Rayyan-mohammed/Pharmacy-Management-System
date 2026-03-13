<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist', 'Staff']);

// Function to get aggregated medicine stock
function getMedicinesStock($conn) {
    try {
        // Aggregate stock from medicine_batches
        // We only care about active batches
        $query = "SELECT 
                    m.id, 
                    m.name, 
                    m.sale_price,
                    COALESCE(SUM(mb.quantity), 0) as total_stock
                  FROM medicines m
                  LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
                  GROUP BY m.id, m.name, m.sale_price
                  ORDER BY m.name";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt;
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error getting medicines: " . $e->getMessage();
        return false;
    }
}

// Get database connection
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    $_SESSION['error'] = "Database connection failed";
    header("Location: ../dashboard/dashboard.php");
    exit();
}

// Get medicines data
$medicines = getMedicinesStock($conn);
$medicinesArray = $medicines ? $medicines->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Overview - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary">Medicine Stock Overview</h5>
                <a href="../add/add-medicine.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Medicine</a>
            </div>
            
            <div class="card-body p-0">
                <div class="p-3 pb-0">
                    <label for="medicine_search" class="form-label text-muted small mb-1">Search medicine</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" id="medicine_search" class="form-control" placeholder="Type to filter..." list="medicine_options">
                    </div>
                    <datalist id="medicine_options">
                        <?php foreach ($medicinesArray as $medicine): ?>
                            <option value="<?php echo htmlspecialchars($medicine['name'], ENT_QUOTES, 'UTF-8'); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success m-3 alert-dismissible fade show">
                        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Item Name</th>
                                <th class="text-center">Total Stock</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($medicinesArray)): ?>
                                <?php foreach ($medicinesArray as $medicine): ?>
                                    <?php
                                    $stock = isset($medicine['total_stock']) ? $medicine['total_stock'] : 0;
                                    $isLow = $stock < 10;
                                    $statusClass = $isLow ? "bg-danger" : "bg-success";
                                    $statusText = $isLow ? "Low Stock" : "In Stock";
                                    if ($stock == 0) {
                                        $statusClass = "bg-secondary";
                                        $statusText = "Out of Stock";
                                    }
                                    $safeName = htmlspecialchars($medicine['name'], ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr data-medicine-row data-name="<?php echo strtolower($safeName); ?>">
                                        <td class="ps-4 fw-bold text-dark"><?php echo $safeName; ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border fs-6"><?php echo $stock; ?></span>
                                        </td>
                                        <td class="text-end">₹<?php echo number_format($medicine['sale_price'], 2); ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $statusClass; ?> rounded-pill px-3"><?php echo $statusText; ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="../add/edit-medicine.php?id=<?php echo $medicine['id']; ?>" class="btn btn-sm btn-outline-info me-1" title="View & Edit"><i class="bi bi-eye"></i> Details</a>
                                            <a href="../update/update-stock.php?id=<?php echo $medicine['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Update</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="noSearchResultsRow" style="display: none;">
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-search display-6 d-block mb-2"></i>
                                        No medicines match your search
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-box-seam display-4 d-block mb-3"></i>
                                        No medicines found in system
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('medicine_search');
            const rows = Array.from(document.querySelectorAll('[data-medicine-row]'));
            const noResultsRow = document.getElementById('noSearchResultsRow');

            function applyFilter() {
                const term = (searchInput.value || '').trim().toLowerCase();
                let visibleCount = 0;

                rows.forEach(row => {
                    const name = row.dataset.name || '';
                    const match = !term || name.includes(term);
                    row.style.display = match ? '' : 'none';
                    if (match) visibleCount++;
                });

                if (noResultsRow) {
                    noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
                } 
            }

            if (searchInput) {
                searchInput.addEventListener('input', applyFilter);
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn = null;
}
?>
