<?php
include_once '../database.php';
include_once '../models/Medicine.php';
include_once '../models/Inventory.php';

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$inventory = new Inventory($db);

$message = '';
$error = '';

if($_POST) {
    // Set inventory property values
    $inventory->medicine_id = $_POST['medicine_id'];
    $inventory->type = $_POST['type'];
    $inventory->quantity = $_POST['quantity'];
    $inventory->reason = $_POST['reason'];
    
    // Create the inventory log
    if($inventory->create()) {
        $message = "Stock updated successfully.";
    } else {
        $error = "Unable to update stock.";
    }
}

// Get all medicines with their current stock
$query = "SELECT m.*, 
          (SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) 
           FROM inventory_logs 
           WHERE medicine_id = m.id) as current_stock
          FROM medicines m
          ORDER BY m.name ASC";
$stmt = $db->query($query);
$medicines_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="medicine_id" class="form-label">Select Medicine</label>
                        <select class="form-select" id="medicine_id" name="medicine_id" required onchange="showCurrentStock(this.value)">
                            <option value="">Select a medicine</option>
                            <?php foreach ($medicines_list as $item): ?>
                                <option value="<?php echo $item['id']; ?>" 
                                        data-stock="<?php echo $item['current_stock']; ?>"
                                        data-price="<?php echo $item['sale_price']; ?>">
                                    <?php echo htmlspecialchars($item['name']); ?> 
                                    (Current Stock: <?php echo $item['current_stock']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label">Update Type</label>
                        <select class="form-select" id="type" name="type" required onchange="updateMaxQuantity()">
                            <option value="in">Stock In</option>
                            <option value="out">Stock Out</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                        <div class="form-text">Current Stock: <span id="current_stock_display">0</span></div>
                    </div>

                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Stock</button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h3>Current Stock Levels</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Medicine Name</th>
                                <th>Current Stock</th>
                                <th>Unit Price</th>
                                <th>Total Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicines_list as $item): 
                                $status_class = $item['current_stock'] <= 10 ? 'danger' : 
                                             ($item['current_stock'] <= 50 ? 'warning' : 'success');
                                $status_text = $item['current_stock'] <= 10 ? 'Low Stock' : 
                                            ($item['current_stock'] <= 50 ? 'Medium Stock' : 'In Stock');
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo $item['current_stock']; ?></td>
                                    <td>₹<?php echo number_format($item['sale_price'], 2); ?></td>
                                    <td>₹<?php echo number_format($item['current_stock'] * $item['sale_price'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showCurrentStock(medicineId) {
            const select = document.getElementById('medicine_id');
            const option = select.options[select.selectedIndex];
            const currentStock = option.getAttribute('data-stock');
            document.getElementById('current_stock_display').textContent = currentStock;
            updateMaxQuantity();
        }

        function updateMaxQuantity() {
            const select = document.getElementById('medicine_id');
            const option = select.options[select.selectedIndex];
            const currentStock = parseInt(option.getAttribute('data-stock')) || 0;
            const typeSelect = document.getElementById('type');
            const quantityInput = document.getElementById('quantity');
            
            if (typeSelect.value === 'out' && currentStock) {
                quantityInput.max = currentStock;
            } else {
                quantityInput.max = '';
            }
        }
    </script>
</body>
</html> 