<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

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
    <title>Top Selling - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="../styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="../dashboard/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row align-items-center mb-4">
             <div class="col">
                <h2 class="fw-bold text-primary mb-1">Top Selling Medicines</h2>
                <p class="text-secondary mb-0">Performance analytics and sales leaders.</p>
            </div>
             <div class="col-auto">
                 <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i> Print Report</button>
             </div>
        </div>

        <div class="row mb-5 g-4">
            <div class="col-lg-6">
                <div class="card shadow border-0 h-100">
                    <div class="card-header bg-white border-subtle py-3">
                        <h6 class="mb-0 fw-bold text-center">Sales Volume (Quantity)</h6>
                    </div>
                    <div class="card-body">
                         <canvas id="quantityChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                 <div class="card shadow border-0 h-100">
                    <div class="card-header bg-white border-subtle py-3">
                        <h6 class="mb-0 fw-bold text-center">Revenue Generated (₹)</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow border-0">
            <div class="card-header bg-white py-3 border-bottom">
                 <h5 class="mb-0 text-primary"><i class="bi bi-trophy me-2"></i>Sales Leaderboard</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th class="px-4 py-3 text-center" style="width: 100px;">Rank</th>
                                <th class="py-3">Medicine Name</th>
                                <th class="py-3 text-center">Total Quantity</th>
                                <th class="py-3 text-end px-4">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            // Ensure data is available for table
                            if($top_medicines->rowCount() > 0) {
                                $top_medicines->execute(); // Reset just in case, though usually not needed if not consumed yet. 
                                // Actually, standard PDOStmt matches fetched rows. The logic below relies on fetch. 
                            }
                            
                            while ($row = $top_medicines->fetch(PDO::FETCH_ASSOC)): 
                                $rankBadge = '<span class="badge bg-secondary rounded-pill" style="width: 30px;">'.$rank.'</span>';
                                if($rank == 1) $rankBadge = '<i class="bi bi-trophy-fill text-warning fs-4" title="1st Place"></i>';
                                if($rank == 2) $rankBadge = '<i class="bi bi-award-fill text-secondary fs-4" title="2nd Place"></i>';
                                if($rank == 3) $rankBadge = '<i class="bi bi-award-fill text-danger fs-4" style="color: #cd7f32 !important;" title="3rd Place"></i>'; // Bronze
                            ?>
                                <tr>
                                    <td class="px-4 text-center"><?php echo $rankBadge; ?></td>
                                    <td>
                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border"><?php echo $row['total_quantity']; ?> units</span>
                                    </td>
                                    <td class="text-end px-4 fw-bold text-success">₹<?php echo number_format($row['total_sales'], 2); ?></td>
                                </tr>
                            <?php 
                                $rank++;
                                endwhile; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        <?php
        // Reset the pointer for Charts
        $top_medicines->execute();
        
        $labels = [];
        $quantities = [];
        $sales = [];
        
        while ($row = $top_medicines->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['name'];
            $quantities[] = $row['total_quantity'];
            // Normalize sales to float
            $sales[] = (float)$row['total_sales'];
        }
        ?>

        // Common Chart Options
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [2, 4] }
                },
                x: {
                   grid: { display: false }
                }
            }
        };

        // Quantity Chart
        const ctxQty = document.getElementById('quantityChart').getContext('2d');
        new Chart(ctxQty, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Quantity Sold',
                    data: <?php echo json_encode($quantities); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: commonOptions
        });

        // Sales Chart
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        new Chart(ctxSales, {
            type: 'line', // Variation: Line chart for sales revenue often looks good
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Total Revenue (₹)',
                    data: <?php echo json_encode($sales); ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    borderColor: '#198754',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#198754',
                    pointRadius: 4
                }]
            },
            options: commonOptions
        });
    </script>
</body>
</html> 