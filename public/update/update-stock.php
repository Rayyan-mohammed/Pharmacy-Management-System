<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$inventory = new Inventory($db);

$message = '';
$message_type = ''; 

// Handle Form Submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        verify_csrf_token(); // Check CSRF

        if (empty($_POST['medicine_id'])) throw new Exception("Please select a medicine.");
        if (empty($_POST['quantity']) || $_POST['quantity'] <= 0) throw new Exception("Quantity must be a positive number.");
        if (empty($_POST['reason'])) throw new Exception("Please provide a reason.");

        $medicine_id = $_POST['medicine_id'];
        $type = $_POST['type'];
        $quantity = $_POST['quantity'];
        $reason = $_POST['reason'];
        
        $batch_number = !empty($_POST['batch_number']) ? $_POST['batch_number'] : null;
        $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;

        // Use the new adjustStock method that handles Logs + MedicineStock + Batches atomically
        if ($inventory->adjustStock($medicine_id, $quantity, $type, $reason, $batch_number, $expiration_date)) {
             $message = "Stock updated successfully!";
             $message_type = 'success';
             $_POST = [];
             try { $al = new ActivityLog($db); $al->log('UPDATE', "Stock {$type} of {$quantity} units for medicine #{$medicine_id}: {$reason}", 'medicine', $medicine_id); } catch(Exception $e) {}
        } else {
             throw new Exception("Failed to update stock. Please check logs.");
        }

    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'danger';
    }
}

// Get medicines list for dropdown
$medicines_stmt = $medicine->read();
$medicines = $medicines_stmt ? $medicines_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Stock - PharmaFlow Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>PharmaFlow Pro
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                
                <?php if($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                        <?php if($message_type == 'success'): ?>
                            <i class="bi bi-check-circle-fill me-2"></i>
                        <?php else: ?>
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php endif; ?>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow border-0">
                    <div class="card-header bg-primary text-white py-3 text-center">
                        <h4 class="mb-0"><i class="bi bi-box-seam me-2"></i>Update Stock</h4>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                            <div class="mb-4">
                                <label for="medicine_id" class="form-label text-muted fw-bold">Select Medicine</label>
                                <select class="form-select form-select-lg" id="medicine_id" name="medicine_id" required>
                                    <option value="">Choose medicine...</option>
                                    <?php foreach ($medicines as $med): ?>
                                        <option value="<?php echo $med['id']; ?>" 
                                            <?php echo (isset($_POST['medicine_id']) && $_POST['medicine_id'] == $med['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($med['name']); ?> 
                                            (Stock: <?php echo $med['current_stock']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-bold">Action</label>
                                    <div class="btn-group w-100" role="group" aria-label="Stock Action">
                                        <input type="radio" class="btn-check" name="type" id="type_in" value="in" autocomplete="off" 
                                            onchange="toggleBatchFields()"
                                            <?php echo (!isset($_POST['type']) || $_POST['type'] == 'in') ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-success" for="type_in"><i class="bi bi-plus-lg me-1"></i> Add Stock</label>

                                        <input type="radio" class="btn-check" name="type" id="type_out" value="out" autocomplete="off" 
                                            onchange="toggleBatchFields()"
                                            <?php echo (isset($_POST['type']) && $_POST['type'] == 'out') ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-danger" for="type_out"><i class="bi bi-dash-lg me-1"></i> Remove Stock</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="quantity" class="form-label text-muted fw-bold">Quantity</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" min="1" placeholder="0" required
                                           value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row g-3 mb-4" id="batchFields">
                                <div class="col-md-6">
                                    <label for="batch_number" class="form-label text-muted fw-bold">Batch Number</label>
                                    <input type="text" class="form-control" id="batch_number" name="batch_number" 
                                           placeholder="Auto if empty"
                                           value="<?php echo isset($_POST['batch_number']) ? htmlspecialchars($_POST['batch_number']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="expiration_date" class="form-label text-muted fw-bold">Expiration Date</label>
                                    <input type="date" class="form-control" id="expiration_date" name="expiration_date" 
                                           value="<?php echo isset($_POST['expiration_date']) ? htmlspecialchars($_POST['expiration_date']) : ''; ?>">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="reason" class="form-label text-muted fw-bold">Reason / Notes</label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="e.g. New shipment, Damaged goods..." required><?php echo isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : ''; ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                    Update Inventory
                                </button>
                                <a href="../dashboard/dashboard.php" class="btn btn-light text-muted">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <p class="text-muted small">Need to add a new medicine? <a href="../add/add-medicine.php" class="text-decoration-none">Click here</a>.</p>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleBatchFields() {
            const typeIn = document.getElementById('type_in');
            const batchFields = document.getElementById('batchFields');
            const batchInput = document.getElementById('batch_number');
            const expInput = document.getElementById('expiration_date');
            
            if (typeIn.checked) {
                // Adding stock needs expiration possibly
                batchFields.style.opacity = '1';
                // expInput.required = true; // Optional if we default to 1 year
            } else {
                // Removing stock - maybe hide or keep optional to target specific batch
                // batchFields.style.opacity = '0.5'; 
            }
        }
        // Initialize
        toggleBatchFields();
    </script>
</body>
</html>
