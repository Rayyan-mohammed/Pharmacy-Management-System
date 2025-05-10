<?php
include_once '../database.php';
include_once '../models/Medicine.php';

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);

$message = '';
$days_threshold = isset($_GET['days']) ? (int)$_GET['days'] : 30;

// Get expiring and expired medicines
$expiring_medicines = $medicine->getExpiringMedicines($days_threshold);
$expired_medicines = $medicine->getExpiredMedicines();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expiration Management - Pharmacy Management System</title>
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

        <!-- Expired Medicines -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h3>Expired Medicines</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Medicine Name</th>
                                <th>Expiration Date</th>
                                <th>Current Stock</th>
                                <th>Inventory Price</th>
                                <th>Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_value = 0;
                            while ($row = $expired_medicines->fetch(PDO::FETCH_ASSOC)): 
                                $value = $row['stock'] * $row['inventory_price'];
                                $total_value += $value;
                            ?>
                                <tr>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['expiration_date'])); ?></td>
                                    <td><?php echo $row['stock']; ?></td>
                                    <td>$<?php echo number_format($row['inventory_price'], 2); ?></td>
                                    <td>$<?php echo number_format($value, 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Total Value of Expired Stock:</strong></td>
                                <td><strong>$<?php echo number_format($total_value, 2); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Expiring Medicines -->
        <div class="card">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h3>Expiring Medicines (Next <?php echo $days_threshold; ?> Days)</h3>
                <form method="GET" class="d-flex align-items-center">
                    <label class="me-2">Days:</label>
                    <select name="days" class="form-select me-2" onchange="this.form.submit()">
                        <option value="7" <?php echo $days_threshold == 7 ? 'selected' : ''; ?>>7</option>
                        <option value="14" <?php echo $days_threshold == 14 ? 'selected' : ''; ?>>14</option>
                        <option value="30" <?php echo $days_threshold == 30 ? 'selected' : ''; ?>>30</option>
                        <option value="60" <?php echo $days_threshold == 60 ? 'selected' : ''; ?>>60</option>
                        <option value="90" <?php echo $days_threshold == 90 ? 'selected' : ''; ?>>90</option>
                    </select>
                    <button type="submit" class="btn btn-light">Update</button>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Medicine Name</th>
                                <th>Expiration Date</th>
                                <th>Days Until Expiry</th>
                                <th>Current Stock</th>
                                <th>Inventory Price</th>
                                <th>Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_value = 0;
                            while ($row = $expiring_medicines->fetch(PDO::FETCH_ASSOC)): 
                                $value = $row['stock'] * $row['inventory_price'];
                                $total_value += $value;
                                $days_until_expiry = floor((strtotime($row['expiration_date']) - time()) / (60 * 60 * 24));
                            ?>
                                <tr>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['expiration_date'])); ?></td>
                                    <td><?php echo $days_until_expiry; ?></td>
                                    <td><?php echo $row['stock']; ?></td>
                                    <td>$<?php echo number_format($row['inventory_price'], 2); ?></td>
                                    <td>$<?php echo number_format($value, 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Total Value of Expiring Stock:</strong></td>
                                <td><strong>$<?php echo number_format($total_value, 2); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 