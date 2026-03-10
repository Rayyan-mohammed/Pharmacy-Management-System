<?php
require_once '../../app/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
            <div class="navbar-nav ms-auto">
                <span class="text-white me-3 d-flex align-items-center">Logged in as: Staff (staff)</span>
                <a class="nav-link text-white" href="../index.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Dashboard Buttons -->
            <div class="col-md-3 mb-4"><a href="/public/add/add-medicine.html" class="card dashboard-btn"><div class="dashboard-icon">💊</div>Add Medicine</a></div>
            <div class="col-md-3 mb-4"><a href="/public/update/update-stock.html" class="card dashboard-btn"><div class="dashboard-icon">📦</div>Update Stock</a></div>
            <div class="col-md-3 mb-4"><a href="/public/check/check-stock.html" class="card dashboard-btn"><div class="dashboard-icon">🔍</div>Check Stock</a></div>
            <div class="col-md-3 mb-4"><a href="/public/inventory/inventory-report.html" class="card dashboard-btn"><div class="dashboard-icon">📋</div>Inventory Report</a></div>
            <div class="col-md-3 mb-4"><a href="/public/sales/sell-medicine.html" class="card dashboard-btn"><div class="dashboard-icon">💰</div>Sell Medicine</a></div>
            <div class="col-md-3 mb-4"><a href="/public/sales_records/sales-records.html" class="card dashboard-btn"><div class="dashboard-icon">📊</div>Sales Records</a></div>
            <div class="col-md-3 mb-4"><a href="/public/expiry/expiration-management.html" class="card dashboard-btn"><div class="dashboard-icon">📅</div>Expiration Management</a></div>
            <div class="col-md-3 mb-4"><a href="/public/prescription/prescription-management.html" class="card dashboard-btn"><div class="dashboard-icon">📝</div>Prescription Management</a></div>
        </div>
    </div>
</body>
</html>
