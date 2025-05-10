<?php
include_once '../database.php';
include_once '../models/Prescription.php';
include_once '../models/Medicine.php';

$database = new Database();
$db = $database->getConnection();
$prescription = new Prescription($db);
$medicine = new Medicine($db);

$message = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Handle form submissions
if($_POST) {
    if(isset($_POST['add_prescription'])) {
        // Set prescription property values
        $prescription->patient_name = $_POST['patient_name'];
        $prescription->prescription_date = $_POST['prescription_date'];
        $prescription->doctor_name = $_POST['doctor_name'];
        $prescription->status = 'pending';

        // Create the prescription
        if($prescription_id = $prescription->create()) {
            // Add medicines to prescription
            foreach($_POST['medicines'] as $index => $medicine_id) {
                if($medicine_id && $_POST['quantities'][$index]) {
                    $prescription->id = $prescription_id;
                    $prescription->addMedicine($medicine_id, $_POST['quantities'][$index]);
                }
            }
            $message = "Prescription was created successfully.";
            $action = 'list';
        } else {
            $message = "Unable to create prescription.";
        }
    }
    elseif(isset($_POST['update_status'])) {
        $prescription->id = $_POST['id'];
        if($prescription->updateStatus($_POST['status'])) {
            $message = "Prescription status was updated successfully.";
            $action = 'list';
        } else {
            $message = "Unable to update prescription status.";
        }
    }
    elseif(isset($_POST['delete_prescription'])) {
        $prescription->id = $_POST['id'];
        if($prescription->delete()) {
            $message = "Prescription was deleted successfully.";
            $action = 'list';
        } else {
            $message = "Unable to delete prescription.";
        }
    }
}

// Get prescriptions list
if($search) {
    $prescriptions_list = $prescription->search($search);
} else {
    $prescriptions_list = $prescription->read();
}

// Get medicines list for dropdown
$medicines_list = $medicine->read();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Management - Pharmacy Management System</title>
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
        <?php if($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($action == 'list'): ?>
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3>Prescription Management</h3>
                    <a href="?action=add" class="btn btn-light">Add New Prescription</a>
                </div>
                <div class="card-body">
                    <!-- Search Form -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-8">
                            <input type="text" class="form-control" name="search" placeholder="Search prescriptions..." value="<?php echo $search; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>

                    <!-- Prescriptions Table -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient Name</th>
                                    <th>Doctor Name</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $prescriptions_list->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($row['prescription_date'])); ?></td>
                                        <td><?php echo $row['patient_name']; ?></td>
                                        <td><?php echo $row['doctor_name']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $row['status'] == 'pending' ? 'warning' : 
                                                    ($row['status'] == 'filled' ? 'success' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?action=view&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">View</a>
                                            <?php if($row['status'] == 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="status" value="filled">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-success">Fill</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="delete_prescription" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this prescription?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif($action == 'add'): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3>Add New Prescription</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="patient_name" class="form-label">Patient Name</label>
                            <input type="text" class="form-control" id="patient_name" name="patient_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="prescription_date" class="form-label">Prescription Date</label>
                            <input type="date" class="form-control" id="prescription_date" name="prescription_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="doctor_name" class="form-label">Doctor Name</label>
                            <input type="text" class="form-control" id="doctor_name" name="doctor_name" required>
                        </div>

                        <div id="medicines-container">
                            <h4>Medicines</h4>
                            <div class="row mb-3">
                                <div class="col-md-5">
                                    <label class="form-label">Medicine</label>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Quantity</label>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                </div>
                            </div>
                            <div class="medicine-row mb-3">
                                <div class="row">
                                    <div class="col-md-5">
                                        <select class="form-select" name="medicines[]" required>
                                            <option value="">Select Medicine</option>
                                            <?php while ($row = $medicines_list->fetch(PDO::FETCH_ASSOC)): ?>
                                                <option value="<?php echo $row['id']; ?>">
                                                    <?php echo $row['name']; ?> ($<?php echo $row['sale_price']; ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <input type="number" class="form-control" name="quantities[]" min="1" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger remove-medicine">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary mb-3" id="add-medicine">Add Another Medicine</button>

                        <div class="d-flex justify-content-between">
                            <a href="?action=list" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="add_prescription" class="btn btn-primary">Create Prescription</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif($action == 'view'): ?>
            <?php
            $prescription->id = $_GET['id'];
            $prescription->readOne();
            $medicines = $prescription->getMedicines();
            ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3>View Prescription</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Patient Name:</strong> <?php echo $prescription->patient_name; ?></p>
                            <p><strong>Doctor Name:</strong> <?php echo $prescription->doctor_name; ?></p>
                            <p><strong>Date:</strong> <?php echo date('Y-m-d', strtotime($prescription->prescription_date)); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php 
                                    echo $prescription->status == 'pending' ? 'warning' : 
                                        ($prescription->status == 'filled' ? 'success' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($prescription->status); ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <h4>Medicines</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                while ($row = $medicines->fetch(PDO::FETCH_ASSOC)): 
                                    $subtotal = $row['quantity'] * $row['sale_price'];
                                    $total += $subtotal;
                                ?>
                                    <tr>
                                        <td><?php echo $row['medicine_name']; ?></td>
                                        <td><?php echo $row['quantity']; ?></td>
                                        <td>$<?php echo number_format($row['sale_price'], 2); ?></td>
                                        <td>$<?php echo number_format($subtotal, 2); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td><strong>$<?php echo number_format($total, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <a href="?action=list" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('medicines-container');
            const addButton = document.getElementById('add-medicine');
            const medicinesList = <?php 
                $medicines_list->execute();
                echo json_encode($medicines_list->fetchAll(PDO::FETCH_ASSOC)); 
            ?>;

            function createMedicineRow() {
                const row = document.createElement('div');
                row.className = 'medicine-row mb-3';
                row.innerHTML = `
                    <div class="row">
                        <div class="col-md-5">
                            <select class="form-select" name="medicines[]" required>
                                <option value="">Select Medicine</option>
                                ${medicinesList.map(medicine => `
                                    <option value="${medicine.id}">
                                        ${medicine.name} ($${medicine.sale_price})
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input type="number" class="form-control" name="quantities[]" min="1" required>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger remove-medicine">Remove</button>
                        </div>
                    </div>
                `;

                row.querySelector('.remove-medicine').addEventListener('click', function() {
                    row.remove();
                });

                return row;
            }

            addButton.addEventListener('click', function() {
                container.appendChild(createMedicineRow());
            });

            // Add remove button functionality to existing rows
            document.querySelectorAll('.remove-medicine').forEach(button => {
                button.addEventListener('click', function() {
                    this.closest('.medicine-row').remove();
                });
            });
        });
    </script>
</body>
</html> 