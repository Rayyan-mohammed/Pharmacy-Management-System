<?php
include_once '../database.php';
include_once '../models/Sale.php';

$database = new Database();
$db = $database->getConnection();
$sale = new Sale($db);

// Get top selling medicines
$top_medicines = $sale->getTopSellingMedicines(10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Selling Medicines - Pharmacy Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../styles.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <h3>Top Selling Medicines</h3>
            </div>
            <div class="card-body">
                <!-- Chart -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <canvas id="quantityChart"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Medicine Name</th>
                                <th>Total Quantity Sold</th>
                                <th>Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            while ($row = $top_medicines->fetch(PDO::FETCH_ASSOC)): 
                            ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['total_quantity']; ?></td>
                                    <td>$<?php echo number_format($row['total_sales'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart Data -->
    <script>
        <?php
        // Reset the pointer
        $top_medicines->execute();
        
        // Prepare data for charts
        $labels = [];
        $quantities = [];
        $sales = [];
        
        while ($row = $top_medicines->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['name'];
            $quantities[] = $row['total_quantity'];
            $sales[] = $row['total_sales'];
        }
        ?>

        // Quantity Chart
        new Chart(document.getElementById('quantityChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Quantity Sold',
                    data: <?php echo json_encode($quantities); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Sales Chart
        new Chart(document.getElementById('salesChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Total Sales ($)',
                    data: <?php echo json_encode($sales); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html> 