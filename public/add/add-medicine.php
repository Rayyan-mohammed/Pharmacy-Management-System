<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$inventory = new Inventory($db);

// Get categories for dropdown
$categories = $medicine->getCategories();

$message = '';
$message_type = 'info'; // Can be 'info' or 'danger' for Bootstrap alerts

if($_POST) {
    try {
        verify_csrf_token(); // Verify CSRF

        // Set medicine property values
        $medicine->name = $_POST['medicine-name'] ?? '';
        $medicine->category_id = $_POST['category-id'] ?? null;
        $medicine->description = $_POST['description'] ?? '';
        $medicine->inventory_price = $_POST['inventory-price'] ?? 0;
        $medicine->sale_price = $_POST['sale-price'] ?? 0;
        $medicine->stock = $_POST['medicine-stock'] ?? 0;
        $medicine->reorder_level = $_POST['reorder-level'] ?? 10;
        $medicine->prescription_needed = isset($_POST['prescription-needed']) ? 1 : 0;
        $medicine->expiration_date = $_POST['expiration-date'] ?? '';
        $batch_number = $_POST['batch-number'] ?? 'INITIAL-' . date('Ymd'); // Default Batch

        // Validate inputs
        if(empty($medicine->name)) {
            throw new Exception("Medicine name is required.");
        }
        
        if($medicine->inventory_price <= 0 || $medicine->sale_price <= 0) {
            throw new Exception("Prices must be greater than zero.");
        }
        
        if($medicine->stock < 0) {
            throw new Exception("Stock quantity cannot be negative.");
        }
        
        if(empty($medicine->expiration_date)) {
            throw new Exception("Expiration date is required.");
        }

        // Start Transaction
        $db->beginTransaction();

        // Create the medicine
        if($medicine->create()) {
            // Get the last inserted medicine ID
            $medicine_id = $medicine->id; // Medicine::create() sets ID
            
            // Create Batch
            if ($medicine->stock > 0) {
                if (!$medicine->addBatch($batch_number, $medicine->stock, $medicine->expiration_date)) {
                     throw new Exception("Failed to create initial batch.");
                }
            }

            // Create initial inventory log entry
            $inventory->medicine_id = $medicine_id;
            $inventory->type = 'in';
            $inventory->quantity = $medicine->stock;
            $inventory->reason = "Initial stock (Batch: $batch_number)";
            
            if($inventory->create()) { // Inventory::create handles its own logging, does not use transaction commands internally in create() (it uses in adjustStock)
                $db->commit();
                $message = "Medicine was created successfully with Batch #$batch_number.";
                $message_type = 'success';
                try { $al = new ActivityLog($db); $al->log('CREATE', "Added medicine: {$medicine->name} (stock: {$medicine->stock}, batch: {$batch_number})", 'medicine', $medicine_id); } catch(Exception $e) {}
            } else {
                throw new Exception("Unable to create initial inventory log.");
            }
        } else {
            throw new Exception("Unable to create medicine. Database error occurred.");
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $message = $e->getMessage();
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Medicine - PharmaFlow Pro</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>PharmaFlow Pro
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-primary">Add New Medicine</h4>
                            <a href="../dashboard/dashboard.php" class="btn btn-outline-secondary btn-sm">Wait, Go Back</a>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> shadow-sm border-0 mb-4">
                                <i class="bi bi-info-circle me-2"></i><?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="medicine-name" class="form-label">Medicine Name</label>
                            <input type="text" class="form-control" id="medicine-name" name="medicine-name" required
                                   value="<?php echo isset($_POST['medicine-name']) ? htmlspecialchars($_POST['medicine-name']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="category-id" class="form-label">Category</label>
                            <select class="form-select" id="category-id" name="category-id">
                                <option value="">Select Category</option>
                                <?php while ($cat = $categories->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="inventory-price" class="form-label">Inventory Price</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="inventory-price" name="inventory-price" required
                                   value="<?php echo isset($_POST['inventory-price']) ? htmlspecialchars($_POST['inventory-price']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="sale-price" class="form-label">Sale Price</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="sale-price" name="sale-price" required
                                   value="<?php echo isset($_POST['sale-price']) ? htmlspecialchars($_POST['sale-price']) : ''; ?>">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="medicine-stock" class="form-label">Initial Stock</label>
                            <input type="number" min="0" class="form-control" id="medicine-stock" name="medicine-stock" required
                                   value="<?php echo isset($_POST['medicine-stock']) ? htmlspecialchars($_POST['medicine-stock']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="reorder-level" class="form-label">Reorder Level</label>
                            <input type="number" min="1" class="form-control" id="reorder-level" name="reorder-level" required
                                   value="<?php echo isset($_POST['reorder-level']) ? htmlspecialchars($_POST['reorder-level']) : '10'; ?>">
                            <div class="form-text">Alert when stock falls to this level.</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="batch-number" class="form-label">Batch Number</label>
                            <input type="text" class="form-control" id="batch-number" name="batch-number" pattern="[A-Za-z0-9\-_]+" title="Alphanumeric, dash and underscore only"
                                   value="<?php echo isset($_POST['batch-number']) ? htmlspecialchars($_POST['batch-number']) : 'BATCH-' . date('Ymd'); ?>">
                            <div class="form-text">Auto-generated or enter custom batch ID.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="expiration-date" class="form-label">Expiration Date (For this Batch)</label>
                        <input type="date" class="form-control" id="expiration-date" name="expiration-date" required
                               value="<?php echo isset($_POST['expiration-date']) ? htmlspecialchars($_POST['expiration-date']) : ''; ?>">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="prescription-needed" name="prescription-needed"
                            <?php echo (isset($_POST['prescription-needed']) ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="prescription-needed">Prescription Required</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Add Medicine</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
