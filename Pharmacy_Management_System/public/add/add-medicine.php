<?php
include_once '../database.php';
include_once '../models/Medicine.php';
include_once '../models/Inventory.php';

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$inventory = new Inventory($db);

$message = '';
$message_type = 'info'; // Can be 'info' or 'danger' for Bootstrap alerts

if($_POST) {
    try {
        // Set medicine property values
        $medicine->name = $_POST['medicine-name'] ?? '';
        $medicine->inventory_price = $_POST['inventory-price'] ?? 0;
        $medicine->sale_price = $_POST['sale-price'] ?? 0;
        $medicine->stock = $_POST['medicine-stock'] ?? 0;
        $medicine->prescription_needed = isset($_POST['prescription-needed']) ? 1 : 0;
        $medicine->expiration_date = $_POST['expiration-date'] ?? '';

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

        // Create the medicine
        if($medicine->create()) {
            // Get the last inserted medicine ID
            $medicine_id = $db->lastInsertId();
            
            // Create initial inventory log entry
            $inventory->medicine_id = $medicine_id;
            $inventory->type = 'in';
            $inventory->quantity = $medicine->stock;
            $inventory->reason = "Initial stock";
            
            if($inventory->create()) {
                $message = "Medicine was created successfully.";
                $message_type = 'success';
            } else {
                throw new Exception("Unable to create initial inventory log.");
            }
        } else {
            throw new Exception("Unable to create medicine. Database error occurred.");
        }
    } catch (Exception $e) {
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
    <title>Add Medicine - Pharmacy Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Pharmacy Management System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/medical_management_new/public/dashboard/dashboard.php">Dashboard</a>
                <a class="nav-link" href="/medical_management_new/public/index.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Add New Medicine</h3>
            </div>
            <div class="card-body">
                <?php if($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="medicine-name" class="form-label">Medicine Name</label>
                        <input type="text" class="form-control" id="medicine-name" name="medicine-name" required
                               value="<?php echo isset($_POST['medicine-name']) ? htmlspecialchars($_POST['medicine-name']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="inventory-price" class="form-label">Inventory Price</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="inventory-price" name="inventory-price" required
                               value="<?php echo isset($_POST['inventory-price']) ? htmlspecialchars($_POST['inventory-price']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="sale-price" class="form-label">Sale Price</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="sale-price" name="sale-price" required
                               value="<?php echo isset($_POST['sale-price']) ? htmlspecialchars($_POST['sale-price']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="medicine-stock" class="form-label">Initial Stock</label>
                        <input type="number" min="0" class="form-control" id="medicine-stock" name="medicine-stock" required
                               value="<?php echo isset($_POST['medicine-stock']) ? htmlspecialchars($_POST['medicine-stock']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="expiration-date" class="form-label">Expiration Date</label>
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