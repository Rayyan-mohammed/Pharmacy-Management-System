<?php
include_once '../database.php';
include_once '../models/Medicine.php';
include_once '../models/Inventory.php';

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$inventory = new Inventory($db);

$message = '';
$message_type = 'info'; // Can be 'info' or 'danger' for error messages
$medicines_list = $medicine->read();

// Get current stock for all medicines upfront for better performance
$current_stocks = [];
if ($medicines_list) {
    while ($row = $medicines_list->fetch(PDO::FETCH_ASSOC)) {
        $current_stocks[$row['id']] = $inventory->getCurrentStock($row['id']);
    }
    // Reset pointer to beginning for the form select
    $medicines_list->execute(); // Re-execute query to reset pointer
}

if($_POST) {
    try {
        // Validate inputs
        if (empty($_POST['medicine_id'])) {
            throw new Exception("Please select a medicine.");
        }
        if (empty($_POST['quantity']) || $_POST['quantity'] <= 0) {
            throw new Exception("Quantity must be a positive number.");
        }
        if (empty($_POST['reason'])) {
            throw new Exception("Please provide a reason for the stock update.");
        }

        // Set inventory log values
        $inventory->medicine_id = $_POST['medicine_id'];
        $inventory->type = $_POST['type'];
        $inventory->quantity = $_POST['quantity'];
        $inventory->reason = $_POST['reason'];

        // Create inventory log
        if($inventory->create()) {
            // Update the stock in medicines table
            $medicine->id = $_POST['medicine_id'];
            $medicine->readOne(); // Get current medicine data
            
            // Calculate new stock based on type (in/out)
            $new_stock = $_POST['type'] == 'in' 
                ? $medicine->stock + $_POST['quantity']
                : $medicine->stock - $_POST['quantity'];
            
            // Update the stock
            $medicine->stock = $new_stock;
            $medicine->update();
            
            $message = "Stock updated successfully.";
            $message_type = 'success';
            
            // Refresh current stock data after update
            $current_stocks[$_POST['medicine_id']] = $inventory->getCurrentStock($_POST['medicine_id']);
            $medicines_list->execute(); // Re-execute query to reset pointer
        } else {
            throw new Exception("Unable to update stock. Database error occurred.");
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
    <title>Update Stock - Pharmacy Management System</title>
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
                <h3>Update Stock</h3>
            </div>
            <div class="card-body">
                <?php if($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="medicine_id" class="form-label">Select Medicine</label>
                        <select class="form-select" id="medicine_id" name="medicine_id" required>
                            <option value="">Select a medicine</option>
                            <?php if ($medicines_list): ?>
                                <?php while ($row = $medicines_list->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $row['id']; ?>" 
                                        <?php echo (isset($_POST['medicine_id']) && $_POST['medicine_id'] == $row['id'] ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($row['name']); ?> 
                                        (Current Stock: <?php echo isset($current_stocks[$row['id']]) ? $current_stocks[$row['id']] : 0; ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">Update Type</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="in" <?php echo (isset($_POST['type']) && $_POST['type'] == 'in') ? 'selected' : ''; ?>>Stock In</option>
                            <option value="out" <?php echo (isset($_POST['type']) && $_POST['type'] == 'out') ? 'selected' : ''; ?>>Stock Out</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required
                               value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required><?php 
                            echo isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : ''; 
                        ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Stock</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>