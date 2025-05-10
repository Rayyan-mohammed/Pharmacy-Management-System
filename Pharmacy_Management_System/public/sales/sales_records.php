<?php
include_once '../database.php';
include_once '../models/Medicine.php';

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);

// Get sales records
$query = "SELECT s.*, m.name as medicine_name, m.inventory_price 
          FROM sales s 
          JOIN medicines m ON s.medicine_id = m.id 
          ORDER BY s.sale_date DESC";
$stmt = $db->query($query);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalRevenue = 0;
$totalProfit = 0;
foreach ($sales as $sale) {
    $totalRevenue += $sale['total_price'];
    $totalProfit += $sale['total_price'] - ($sale['inventory_price'] * $sale['quantity']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Records - Pharmacy Management System</title>
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
                <h3>Sales Records</h3>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Sales</h5>
                                <h3 class="text-primary">₹<?php echo number_format($totalRevenue, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Profit</h5>
                                <h3 class="text-success">₹<?php echo number_format($totalProfit, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Transactions</h5>
                                <h3 class="text-info"><?php echo count($sales); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Medicine</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Price</th>
                                <th>Customer</th>
                                <th>Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): 
                                $profit = $sale['total_price'] - ($sale['inventory_price'] * $sale['quantity']);
                            ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($sale['sale_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($sale['medicine_name']); ?></td>
                                    <td><?php echo $sale['quantity']; ?></td>
                                    <td>₹<?php echo number_format($sale['total_price'] / $sale['quantity'], 2); ?></td>
                                    <td>₹<?php echo number_format($sale['total_price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'N/A'); ?></td>
                                    <td class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        ₹<?php echo number_format($profit, 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <td colspan="4"><strong>Total</strong></td>
                                <td><strong>₹<?php echo number_format($totalRevenue, 2); ?></strong></td>
                                <td></td>
                                <td class="<?php echo $totalProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <strong>₹<?php echo number_format($totalProfit, 2); ?></strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 