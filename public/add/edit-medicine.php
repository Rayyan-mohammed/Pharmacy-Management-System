<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);

// Require a valid medicine ID
if (empty($_GET['id']) && empty($_POST['id'])) {
    header("Location: ../check/check-stock.php");
    exit();
}

$medicine_id = (int)($_POST['id'] ?? $_GET['id']);
$medicine->id = $medicine_id;

// Get categories for dropdown
$categories = $medicine->getCategories();
$categoriesArray = $categories->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token();

        $medicine->name = trim($_POST['medicine-name'] ?? '');
        $medicine->category_id = $_POST['category-id'] ?? null;
        $medicine->description = trim($_POST['description'] ?? '');
        $medicine->inventory_price = $_POST['inventory-price'] ?? 0;
        $medicine->sale_price = $_POST['sale-price'] ?? 0;
        $medicine->reorder_level = $_POST['reorder-level'] ?? 10;
        $medicine->prescription_needed = isset($_POST['prescription-needed']) ? 1 : 0;

        if (empty($medicine->name)) {
            throw new Exception("Medicine name is required.");
        }
        if ($medicine->inventory_price <= 0 || $medicine->sale_price <= 0) {
            throw new Exception("Prices must be greater than zero.");
        }

        if ($medicine->update()) {
            $message = "Medicine updated successfully.";
            $message_type = 'success';
            try {
                $al = new ActivityLog($db);
                $al->log('UPDATE', "Updated medicine: {$medicine->name}", 'medicine', $medicine_id);
            } catch (Exception $e) {}
        } else {
            throw new Exception("Failed to update medicine.");
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'danger';
    }
}

// Load current medicine data (or reload after update)
if (!$medicine->readOne()) {
    $_SESSION['error'] = "Medicine not found.";
    header("Location: ../check/check-stock.php");
    exit();
}

// Get batches for this medicine
$batchQuery = "SELECT * FROM medicine_batches WHERE medicine_id = :mid ORDER BY expiration_date ASC";
$batchStmt = $db->prepare($batchQuery);
$batchStmt->bindParam(':mid', $medicine_id, PDO::PARAM_INT);
$batchStmt->execute();
$batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Medicine - PharmaFlow Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm mb-4">
                        <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-primary"><i class="bi bi-pencil-square me-2"></i>Edit Medicine</h4>
                            <a href="../check/check-stock.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left me-1"></i>Back to Stock
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="id" value="<?php echo $medicine_id; ?>">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="medicine-name" class="form-label">Medicine Name</label>
                                    <input type="text" class="form-control" id="medicine-name" name="medicine-name" required
                                           value="<?php echo htmlspecialchars($medicine->name); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="category-id" class="form-label">Category</label>
                                    <select class="form-select" id="category-id" name="category-id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categoriesArray as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo ($medicine->category_id == $cat['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($medicine->description); ?></textarea>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="inventory-price" class="form-label">Inventory Price (₹)</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control" id="inventory-price" name="inventory-price" required
                                           value="<?php echo htmlspecialchars($medicine->inventory_price); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="sale-price" class="form-label">Sale Price (₹)</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control" id="sale-price" name="sale-price" required
                                           value="<?php echo htmlspecialchars($medicine->sale_price); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="reorder-level" class="form-label">Reorder Level</label>
                                <input type="number" min="1" class="form-control" id="reorder-level" name="reorder-level" required
                                       value="<?php echo htmlspecialchars($medicine->reorder_level ?? 10); ?>">
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Current Total Stock</label>
                                    <input type="text" class="form-control bg-light" disabled
                                           value="<?php echo (int)$medicine->stock; ?> units">
                                    <div class="form-text">Stock is managed via batches. Use <a href="../update/update-stock.php?id=<?php echo $medicine_id; ?>">Update Stock</a> to adjust.</div>
                                </div>
                                <div class="col-md-6 d-flex align-items-end pb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="prescription-needed" name="prescription-needed"
                                            <?php echo $medicine->prescription_needed ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="prescription-needed">Prescription Required</label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check-lg me-1"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($batches)): ?>
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-primary"><i class="bi bi-boxes me-2"></i>Batch Details</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Batch #</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-center">Expiration</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batches as $batch): 
                                        $isExpired = strtotime($batch['expiration_date']) <= time();
                                        $isExpiringSoon = !$isExpired && strtotime($batch['expiration_date']) <= strtotime('+30 days');
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($batch['batch_number']); ?></td>
                                        <td class="text-center"><?php echo (int)$batch['quantity']; ?></td>
                                        <td class="text-center"><?php echo date('M d, Y', strtotime($batch['expiration_date'])); ?></td>
                                        <td class="text-center">
                                            <?php if ($isExpired): ?>
                                                <span class="badge bg-danger rounded-pill">Expired</span>
                                            <?php elseif ($isExpiringSoon): ?>
                                                <span class="badge bg-warning text-dark rounded-pill">Expiring Soon</span>
                                            <?php elseif ($batch['quantity'] == 0): ?>
                                                <span class="badge bg-secondary rounded-pill">Depleted</span>
                                            <?php else: ?>
                                                <span class="badge bg-success rounded-pill">Active</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

