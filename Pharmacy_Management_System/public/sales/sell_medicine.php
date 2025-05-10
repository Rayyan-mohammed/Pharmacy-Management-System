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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicine_id = $_POST['medicine_id'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;
    $customer_name = $_POST['customer_name'] ?? '';
    $has_prescription = isset($_POST['has_prescription']) ? 1 : 0;

    if (empty($medicine_id) || $quantity <= 0) {
        $error = "Please select a medicine and enter a valid quantity.";
    } else {
        // Check if medicine requires prescription
        $medicine->id = $medicine_id;
        if ($medicine->readOne()) {
            if ($medicine->prescription_needed && !$has_prescription) {
                $error = "This medicine requires a valid prescription.";
            } else {
                // Check stock availability
                $current_stock = $inventory->getCurrentStock($medicine_id);
                if ($current_stock < $quantity) {
                    $error = "Insufficient stock. Available: " . $current_stock;
                } else {
                    // Process sale
                    $query = "INSERT INTO sales (medicine_id, quantity, total_price, customer_name) 
                             VALUES (:medicine_id, :quantity, :total_price, :customer_name)";
                    $stmt = $db->prepare($query);
                    
                    $total_price = $medicine->sale_price * $quantity;
                    $stmt->bindParam(':medicine_id', $medicine_id);
                    $stmt->bindParam(':quantity', $quantity);
                    $stmt->bindParam(':total_price', $total_price);
                    $stmt->bindParam(':customer_name', $customer_name);
                    
                    if ($stmt->execute()) {
                        // Update inventory
                        $inventory->medicine_id = $medicine_id;
                        $inventory->type = 'out';
                        $inventory->quantity = $quantity;
                        $inventory->reason = "Sale to " . $customer_name;
                        $inventory->create();
                        
                        $message = "Sale completed successfully. Total: ₹" . number_format($total_price, 2);
                    } else {
                        $error = "Error processing sale.";
                    }
                }
            }
        }
    }
}

// Get all medicines
$medicines = $medicine->read();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell Medicine - Pharmacy Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Pharmacy Management System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/medical_management_new/public/dashboard/dashboard.php">Dashboard</a>
                <a class="nav-link" href="/medical_management_new/public/index.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Sell Medicine</h3>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="medicine_id" class="form-label">Select Medicine</label>
                        <select class="form-select" id="medicine_id" name="medicine_id" required onchange="checkPrescriptionRequired(this.value)">
                            <option value="">Select a medicine</option>
                            <?php while ($row = $medicines->fetch(PDO::FETCH_ASSOC)): 
                                $current_stock = $inventory->getCurrentStock($row['id']);
                            ?>
                                <option value="<?php echo $row['id']; ?>" 
                                        data-prescription="<?php echo $row['prescription_needed']; ?>"
                                        data-price="<?php echo $row['sale_price']; ?>"
                                        data-stock="<?php echo $current_stock; ?>">
                                    <?php echo htmlspecialchars($row['name']); ?> 
                                    - ₹<?php echo number_format($row['sale_price'], 2); ?> 
                                    (Stock: <?php echo $current_stock; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required 
                               onchange="updateTotalPrice()" oninput="validateQuantity(this)">
                        <small id="quantityHelp" class="form-text text-muted"></small>
                    </div>

                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Customer Name</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                    </div>

                    <div class="mb-3 form-check" id="prescriptionCheckContainer" style="display:none;">
                        <input class="form-check-input" type="checkbox" id="has_prescription" name="has_prescription">
                        <label class="form-check-label" for="has_prescription">
                            Customer has valid prescription
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Total Price</label>
                        <div class="form-control" id="total_price">₹0.00</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Complete Sale</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkPrescriptionRequired(medicineId) {
            const select = document.getElementById('medicine_id');
            const option = select.options[select.selectedIndex];
            const prescriptionRequired = option.getAttribute('data-prescription') === '1';
            const prescriptionCheckbox = document.getElementById('has_prescription');
            const prescriptionContainer = document.getElementById('prescriptionCheckContainer');
            
            if (prescriptionRequired) {
                prescriptionContainer.style.display = 'block';
                prescriptionCheckbox.required = true;
                prescriptionCheckbox.checked = false;
            } else {
                prescriptionContainer.style.display = 'none';
                prescriptionCheckbox.required = false;
                prescriptionCheckbox.checked = false;
            }
            
            updateTotalPrice();
            validateQuantity(document.getElementById('quantity'));
        }

        function updateTotalPrice() {
            const select = document.getElementById('medicine_id');
            const option = select.options[select.selectedIndex];
            const price = parseFloat(option.getAttribute('data-price')) || 0;
            const quantity = parseInt(document.getElementById('quantity').value) || 0;
            const totalPrice = price * quantity;
            
            document.getElementById('total_price').textContent = `₹${totalPrice.toFixed(2)}`;
        }

        function validateQuantity(input) {
            const select = document.getElementById('medicine_id');
            if (!select.value) {
                document.getElementById('quantityHelp').textContent = 'Please select a medicine first';
                input.setCustomValidity('Please select a medicine first');
                return;
            }

            const option = select.options[select.selectedIndex];
            const maxStock = parseInt(option.getAttribute('data-stock')) || 0;
            const quantity = parseInt(input.value) || 0;

            if (quantity > maxStock) {
                document.getElementById('quantityHelp').textContent = `Available stock: ${maxStock}`;
                document.getElementById('quantityHelp').className = 'form-text text-danger';
                input.setCustomValidity(`Quantity cannot exceed available stock (${maxStock})`);
            } else {
                document.getElementById('quantityHelp').textContent = `Available stock: ${maxStock}`;
                document.getElementById('quantityHelp').className = 'form-text text-muted';
                input.setCustomValidity('');
            }
        }
    </script>
</body>
</html>