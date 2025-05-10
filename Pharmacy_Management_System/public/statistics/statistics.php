<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - Pharmacy Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            margin-bottom: 20px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
        }
        .navbar {
            background-color: #007bff;
        }
        .navbar-brand, .nav-link {
            color: white !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
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
                <h3>Pharmacy Statistics</h3>
            </div>
            <div class="card-body" id="statisticsContent">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Medicines</h5>
                                <div class="stat-value" id="totalMedicines"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Items in Stock</h5>
                                <div class="stat-value" id="totalStock"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Items with Low Stock</h5>
                                <div class="stat-value" id="lowStockItems"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Inventory Value</h5>
                                <div class="stat-value" id="stockValue"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Sales Revenue</h5>
                                <div class="stat-value" id="totalSalesRevenue"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Profit</h5>
                                <div class="stat-value" id="totalSalesProfit"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">Sales Performance</div>
                            <div class="card-body">
                                <canvas id="sales-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showStatistics() {
            console.log('Attempting to fetch statistics...');
            // Fetch statistics from the server with correct path
            fetch('../api/get_statistics.php')
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                            throw new Error('Invalid JSON response from server');
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed data:', data);
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    // Update statistics with proper formatting and handling of null/undefined values
                    document.getElementById('totalMedicines').textContent = data.totalMedicines || '0';
                    document.getElementById('totalStock').textContent = data.totalStock || '0';
                    document.getElementById('lowStockItems').textContent = data.lowStockItems || '0';
                    document.getElementById('stockValue').textContent = data.stockValue ? `₹${parseFloat(data.stockValue).toFixed(2)}` : '₹0.00';
                    document.getElementById('totalSalesRevenue').textContent = data.totalSalesRevenue ? `₹${parseFloat(data.totalSalesRevenue).toFixed(2)}` : '₹0.00';
                    document.getElementById('totalSalesProfit').textContent = data.totalSalesProfit ? `₹${parseFloat(data.totalSalesProfit).toFixed(2)}` : '₹0.00';

                    // Create chart if we have sales data
                    if (data.salesData && data.salesData.length > 0) {
                        const dates = data.salesData.map(item => item.date);
                        const revenues = data.salesData.map(item => item.revenue);
                        const profits = data.salesData.map(item => item.profit);
                        
                        const salesChart = new Chart(document.getElementById('sales-chart'), {
                            type: 'line',
                            data: {
                                labels: dates,
                                datasets: [
                                    {
                                        label: 'Revenue',
                                        data: revenues,
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                        tension: 0.1,
                                        fill: true
                                    },
                                    {
                                        label: 'Profit',
                                        data: profits,
                                        borderColor: 'rgba(75, 192, 192, 1)',
                                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                        tension: 0.1,
                                        fill: true
                                    }
                                ]
                            },
                            options: {
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    } else {
                        document.getElementById('sales-chart').parentElement.innerHTML = `
                            <div class="alert alert-info">
                                <h5>No Sales Data Available</h5>
                                <p>Start adding medicines and making sales to see statistics here.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching statistics:', error);
                    document.getElementById('statisticsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <h4>Error loading statistics</h4>
                            <p>${error.message}</p>
                            <p>Please check:</p>
                            <ul>
                                <li>Database connection and credentials</li>
                                <li>Database tables exist and are properly set up</li>
                                <li>API endpoint is accessible</li>
                            </ul>
                            <p>If you haven't set up the database yet, please follow these steps:</p>
                            <ol>
                                <li>Create a database named 'medical_management'</li>
                                <li>Import the database schema from the SQL file</li>
                                <li>Update database credentials in config/database.php if needed</li>
                            </ol>
                        </div>
                    `;
                });
        }

        // Call the function when the page loads
        document.addEventListener('DOMContentLoaded', showStatistics);
    </script>
</body>
</html>