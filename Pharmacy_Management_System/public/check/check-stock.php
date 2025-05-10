<?php
// Start session
session_start();

// Include database connection
require_once '../database.php';

// Function to get all medicines
function getMedicines($conn) {
    try {
        $query = "SELECT m.*, 
                 (SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) 
                  FROM inventory_logs 
                  WHERE medicine_id = m.id) as current_stock
                 FROM medicines m 
                 ORDER BY m.name";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt;
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error getting medicines: " . $e->getMessage();
        return false;
    }
}

// Get database connection
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    $_SESSION['error'] = "Database connection failed";
    header("Location: ../dashboard/dashboard.php");
    exit();
}

// Get medicines data
$medicines = getMedicines($conn);
$medicinesArray = $medicines ? $medicines->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Stock - Pharmacy Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/styles.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Pharmacy Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                <a class="nav-link" href="/medical_management_new/public/dashboard/dashboard.php">Dashboard</a>
                <a class="nav-link" href="/medical_management_new/public/index.php">Logout</a>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Current Stock</h3>
                <a href="../add/add-medicine.php" class="btn btn-light">Add New Medicine</a>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Medicine</th>
                                <th>Stock</th>
                                <th>Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($medicinesArray)): ?>
                                <?php foreach ($medicinesArray as $medicine): ?>
                                    <?php
                                    $status = $medicine['current_stock'] < 50 ? "Low Stock" : "In Stock";
                                    $statusClass = $medicine['current_stock'] < 50 ? "text-warning" : "text-success";
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($medicine['id']); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['name']); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['current_stock']); ?></td>
                                        <td>₹<?php echo number_format($medicine['sale_price'], 2); ?></td>
                                        <td class="<?php echo $statusClass; ?> fw-bold"><?php echo $status; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">No medicines found in inventory</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn = null;
}
?>