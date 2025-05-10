<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #dff3ff;
        }
        .navbar {
            background-color: #007bff;
        }
        .dashboard-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: black;
            font-weight: bold;
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .dashboard-btn:hover {
            transform: scale(1.05);
        }
        .dashboard-icon {
            font-size: 30px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Pharmacy Management System</a>
            <div class="navbar-nav ms-auto">
                <span class="text-white me-3">Logged in as: Administrator (admin)</span>
                <a class="nav-link text-white" href="/medical_management_new/public/index.html">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Dashboard Buttons -->
            <div class="col-md-3 mb-4"><a href="../add/add-medicine.php" class="card dashboard-btn"><div class="dashboard-icon">💊</div>Add Medicine</a></div>
            <div class="col-md-3 mb-4"><a href="../update/update-stock.php" class="card dashboard-btn"><div class="dashboard-icon">📦</div>Update Stock</a></div>
            <div class="col-md-3 mb-4"><a href="../check/check-stock.php" class="card dashboard-btn"><div class="dashboard-icon">🔍</div>Check Stock</a></div>
            <div class="col-md-3 mb-4"><a href="../inventory/inventory_report.php" class="card dashboard-btn"><div class="dashboard-icon">📋</div>Inventory Report</a></div>
            <div class="col-md-3 mb-4"><a href="../sales/sell_medicine.php" class="card dashboard-btn"><div class="dashboard-icon">💰</div>Sell Medicine</a></div>
            <div class="col-md-3 mb-4"><a href="../sales/sales_records.php" class="card dashboard-btn"><div class="dashboard-icon">📊</div>Sales Records</a></div>
            <div class="col-md-3 mb-4"><a href="../top_sales/top-selling.php" class="card dashboard-btn"><div class="dashboard-icon">📈</div>Top Selling Analysis</a></div>
            <div class="col-md-3 mb-4"><a href="../statistics/statistics.php" class="card dashboard-btn"><div class="dashboard-icon">📉</div>Statistics</a></div>
            <div class="col-md-3 mb-4"><a href="../expiration/expiration-management.php" class="card dashboard-btn"><div class="dashboard-icon">📅</div>Expiration Management</a></div>
            <div class="col-md-3 mb-4"><a href="../supplier/supplier-management.php" class="card dashboard-btn"><div class="dashboard-icon">🏢</div>Supplier Management</a></div>
            <div class="col-md-3 mb-4"><a href="../prescription/prescription-management.php" class="card dashboard-btn"><div class="dashboard-icon">📝</div>Prescription Management</a></div>
        </div>
    </div>
</body>
</html>
