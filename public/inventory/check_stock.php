<?php
require_once '../../app/auth.php';
require_once '../../app/Config/config.php';
require_once '../../app/Core/Database.php';

$database = new Database();
$db = $database->getConnection();

// Ensure medicine_batches exists (some older DB dumps missed it)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS medicine_batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        medicine_id INT NOT NULL,
        batch_number VARCHAR(100) NOT NULL,
        expiration_date DATE NOT NULL,
        quantity INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // If creation fails, continue; we'll still try to show medicine-level stock
}

// Get medicines with batch details; fall back to medicine stock if no batches
$query = "SELECT 
            m.id,
            m.name,
            m.type,
            m.unit,
            mb.batch_number,
            COALESCE(mb.quantity, m.stock, 0) AS current_stock,
            COALESCE(mb.expiration_date, mb.expiry_date, m.expiration_date) AS expiration_date,
            m.sale_price
          FROM medicines m
          LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
          ORDER BY m.name ASC, expiration_date ASC";

try {
    $stmt = $db->query($query);
    $inventory_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $inventory_list = [];
}

// Fallback: if no batch rows found, show medicine-level stock so page isn't empty
if (empty($inventory_list)) {
    try {
        $fallback = "SELECT id, name, type, unit, stock AS current_stock, expiration_date, sale_price FROM medicines ORDER BY name ASC";
        $stmt2 = $db->query($fallback);
        $inventory_list = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $fallback_mode = true;
    } catch (PDOException $e) {
        $fallback_mode = false;
    }
} else {
    $fallback_mode = false;
}

// Calculate totals
$total_batches = 0; 
$total_value = 0;
$low_stock_count = 0;
$expiring_soon_count = 0;
$expired_count = 0;

$today = strtotime('today');
$thirty_days_later = strtotime('+30 days');

foreach ($inventory_list as $item) {
    $total_batches++;
    $price = isset($item['sale_price']) ? $item['sale_price'] : 0;
    $total_value += $item['current_stock'] * $price;
    
    // Check stock status (arbitrary threshold of 10 for low stock per batch)
    if ($item['current_stock'] < 10) {
        $low_stock_count++;
    }

    // Check expiry
    if (!empty($item['expiration_date'])) {
        $expiry_ts = strtotime($item['expiration_date']);
        if ($expiry_ts < $today) {
            $expired_count++;
        } elseif ($expiry_ts <= $thirty_days_later) {
            $expiring_soon_count++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Stock - Pharmacy Pro</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Inventory Report</a>
                    </li>
                </ul>
                <div class="navbar-nav ms-auto">
                    <a class="nav-link text-white" href="../dashboard/dashboard.php">Dashboard</a>
                    <a class="nav-link text-white" href="../logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 text-primary"><i class="bi bi-clipboard-data me-2"></i>Current Stock Levels (By Batch)</h4>
                    <div class="text-end">
                        <span class="badge bg-primary rounded-pill px-3 py-2">Total Value: ₹<?php echo number_format($total_value, 2); ?></span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4 g-3">
                    <div class="col-md-3">
                        <div class="p-3 border rounded bg-light text-center h-100">
                            <h6 class="text-muted mb-2">Total Batches</h6>
                            <h3 class="mb-0 text-primary fw-bold"><?php echo $total_batches; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 border rounded bg-light text-center h-100">
                            <h6 class="text-muted mb-2">Low Stock Batches</h6>
                            <h3 class="mb-0 text-warning fw-bold"><?php echo $low_stock_count; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 border rounded bg-light text-center h-100">
                            <h6 class="text-muted mb-2">Expiring Soon</h6>
                            <h3 class="mb-0 text-info fw-bold"><?php echo $expiring_soon_count; ?></h3>
                            <small class="text-muted">Next 30 days</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 border rounded bg-light text-center h-100">
                            <h6 class="text-muted mb-2">Expired</h6>
                            <h3 class="mb-0 text-danger fw-bold"><?php echo $expired_count; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Medicine Name</th> 
                                <th>Batch No.</th>
                                <th class="text-center">Current Stock</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total Value</th>
                                <th class="text-center">Expiry Date</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($inventory_list) > 0): ?>
                                <?php foreach ($inventory_list as $item): 
                                    $status_class = 'success';
                                    $status_text = 'Good';
                                    $row_class = '';
                                    
                                    $expiry_ts = strtotime($item['expiration_date']);
                                    
                                    // Status Logic
                                    if ($expiry_ts < $today) {
                                        $status_class = 'danger';
                                        $status_text = 'Expired';
                                        $row_class = 'table-danger'; // Highlight expired rows
                                    } elseif ($expiry_ts <= $thirty_days_later) {
                                        $status_class = 'warning';
                                        $status_text = 'Expiring Soon';
                                    } elseif ($item['current_stock'] <= 5) {
                                        $status_class = 'warning';
                                        $status_text = 'Low Stock';
                                    }
                                    
                                    $price = isset($item['sale_price']) ? $item['sale_price'] : 0;
                                    $unitLabel = isset($item['unit']) && $item['unit'] ? $item['unit'] : '';
                                ?>
                                    <tr class="<?php echo $row_class; ?>">
                                        <td class="fw-bold">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                            <?php if(!empty($item['type'])): ?>
                                                <small class="d-block text-muted fw-normal"><?php echo htmlspecialchars($item['type']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($item['batch_number']); ?></span></td>
                                        <td class="text-center">
                                            <span class="fw-bold"><?php echo $item['current_stock']; ?></span> 
                                            <?php if($unitLabel): ?><small class="text-muted"><?php echo htmlspecialchars($unitLabel); ?></small><?php endif; ?>
                                        </td>
                                        <td class="text-end">₹<?php echo number_format($price, 2); ?></td>
                                        <td class="text-end">₹<?php echo number_format($item['current_stock'] * $price, 2); ?></td>
                                        <td class="text-center">
                                            <?php echo $expiry_ts ? date('d M Y', $expiry_ts) : '-'; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No stock found in inventory.</td>
                                </tr>
                            <?php endif; ?>
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
