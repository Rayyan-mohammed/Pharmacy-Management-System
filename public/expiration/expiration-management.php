<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist', 'Staff']);

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
    <title>Expiration Management - PharmaFlow Pro</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <!-- Custom CSS -->
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

    <!-- Main Content -->
    <div class="container py-4">
        <?php if($message): ?>
            <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row align-items-center mb-4">
             <div class="col">
                <h2 class="fw-bold text-primary mb-1">Expiration Management</h2>
                <p class="text-secondary mb-0">Track expired batches and upcoming expirations.</p>
            </div>
        </div>

        <!-- Expired Medicines -->
        <div class="card border-0 shadow mb-5 border-start border-4 border-danger">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-danger"><i class="bi bi-exclamation-octagon-fill me-2"></i>Expired Medicines</h5>
                <span class="badge bg-danger">Action Required</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th class="px-4 py-3">Medicine Name</th>
                                <th class="py-3">Batch No</th>
                                <th class="py-3">Expiration Date</th>
                                <th class="py-3 text-center">Quantity</th>
                                <th class="py-3 text-end">Price</th>
                                <th class="py-3 text-end px-4">Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_value_expired = 0;
                            $has_expired = false;
                            
                            // fetchAll is safer if we want to iterate freely, but fetch loop is fine too.
                            // The previous replace replaced the while loop start with foreach on fetchAll result, 
                            // but I haven't closed it properly or updated the content inside. 
                            // Let's rewrite the whole table body section to be clean.
                             
                            while ($row = $expired_medicines->fetch(PDO::FETCH_ASSOC)): 
                                $has_expired = true;
                                $price = $row['inventory_price'] ?? 0;
                                $qty = $row['quantity'];
                                $value = $qty * $price;
                                $total_value_expired += $value;
                            ?>
                                <tr>
                                    <td class="px-4 fw-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['batch_number']); ?></span></td>
                                    <td>
                                        <span class="badge bg-danger text-white">
                                            <?php echo date('d M Y', strtotime($row['expiration_date'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                       <span class="fw-bold"><?php echo $qty; ?></span>
                                    </td>
                                    <td class="text-end text-muted">₹<?php echo number_format($price, 2); ?></td>
                                    <td class="text-end fw-bold px-4">₹<?php echo number_format($value, 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            
                            <?php if(!$has_expired): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No expired medicines found. Good job!</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if($has_expired): ?>
                        <tfoot class="bg-light">
                             <tr>
                                <td colspan="5" class="text-end fw-bold py-3">Total Value of Expired Stock:</td>
                                <td class="text-end fw-bold py-3 text-danger px-4">₹<?php echo number_format($total_value_expired, 2); ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Expiring Medicines -->
        <div class="card border-0 shadow border-start border-4 border-warning">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0 text-warning text-dark"><i class="bi bi-hourglass-split me-2"></i>Expiring Soon</h5>
                
                <form method="GET" class="d-flex align-items-center">
                    <label class="me-2 text-muted small">Show expiring in:</label>
                    <div class="input-group input-group-sm">
                        <select name="days" class="form-select form-select-sm border-secondary" onchange="this.form.submit()">
                            <option value="7" <?php echo $days_threshold == 7 ? 'selected' : ''; ?>>7 Days</option>
                            <option value="14" <?php echo $days_threshold == 14 ? 'selected' : ''; ?>>14 Days</option>
                            <option value="30" <?php echo $days_threshold == 30 ? 'selected' : ''; ?>>30 Days</option>
                            <option value="60" <?php echo $days_threshold == 60 ? 'selected' : ''; ?>>60 Days</option>
                            <option value="90" <?php echo $days_threshold == 90 ? 'selected' : ''; ?>>90 Days</option>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary">Update</button>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th class="px-4 py-3">Medicine Name</th>
                                <th class="py-3">Batch No</th>
                                <th class="py-3">Expiration Date</th>
                                <th class="py-3 text-center">Days Left</th>
                                <th class="py-3 text-center">Quantity</th>
                                <th class="py-3 text-end">Price</th>
                                <th class="py-3 text-end px-4">Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_value_expiring = 0;
                            $has_expiring = false;
                            while ($row = $expiring_medicines->fetch(PDO::FETCH_ASSOC)): 
                                $has_expiring = true;
                                $price = $row['inventory_price'] ?? 0;
                                $qty = $row['quantity'];
                                $value = $qty * $price;
                                $total_value_expiring += $value;
                                $days_until_expiry = floor((strtotime($row['expiration_date']) - time()) / (60 * 60 * 24));
                                
                                // Color code based on urgency
                                $badgeClass = 'bg-warning text-dark';
                                if($days_until_expiry < 7) $badgeClass = 'bg-danger text-white';
                                elseif($days_until_expiry > 30) $badgeClass = 'bg-info text-dark';
                            ?>
                                <tr>
                                    <td class="px-4 fw-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['batch_number']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($row['expiration_date'])); ?></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill <?php echo $badgeClass; ?>">
                                            <?php echo $days_until_expiry; ?> days
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo $qty; ?></td>
                                    <td class="text-end text-muted">₹<?php echo number_format($price, 2); ?></td>
                                    <td class="text-end fw-bold px-4">₹<?php echo number_format($value, 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            
                             <?php if(!$has_expiring): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No medicines expiring within <?php echo $days_threshold; ?> days.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if($has_expiring): ?>
                        <tfoot class="bg-light">
                             <tr>
                                <td colspan="6" class="text-end fw-bold py-3">Total Value of At-Risk Stock:</td>
                                <td class="text-end fw-bold py-3 text-warning text-dark px-4">₹<?php echo number_format($total_value_expiring, 2); ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
