<?php
require_once '../../app/auth.php';

$userRole = $_SESSION['currentUser']['role'] ?? 'Staff';
$userName = $_SESSION['currentUser']['first_name'] . ' ' . $_SESSION['currentUser']['last_name'];
$userRoleDisplay = htmlspecialchars($userRole);
$userNameDisplay = htmlspecialchars($userName);

function canAccess($feature, $role) {
    if ($role === 'Administrator' || $role === 'Pharmacist') {
        return true;
    }
    if ($role === 'Staff') {
        $allowed = [
            'sell_medicine',
            'check_stock',
            'prescription_management',
            'sales_records',
            'expiration_management'
        ];
        return in_array($feature, $allowed);
    }
    
    // Administrator only
    if ($feature === 'user_management' && $role === 'Administrator') {
        return true;
    }
    
    return false;
}
?>
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
                <span class="text-white me-3">Logged in as: <?php echo $userRoleDisplay; ?> (<?php echo $userNameDisplay; ?>)</span>
                <a class="nav-link text-white" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Dashboard Buttons -->
            <?php if (canAccess('add_medicine', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../add/add-medicine.php" class="card dashboard-btn"><div class="dashboard-icon">💊</div>Add Medicine</a></div>
            <?php endif; ?>
            
            <?php if (canAccess('update_stock', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../update/update-stock.php" class="card dashboard-btn"><div class="dashboard-icon">📦</div>Update Stock</a></div>
            <?php endif; ?>
            
            <?php if (canAccess('check_stock', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../check/check-stock.php" class="card dashboard-btn"><div class="dashboard-icon">🔍</div>Check Stock</a></div>
            <?php endif; ?>
            
            <?php if (canAccess('inventory_report', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../inventory/inventory_report.php" class="card dashboard-btn"><div class="dashboard-icon">📋</div>Inventory Report</a></div>
            <?php endif; ?>
            
            <?php if (canAccess('sell_medicine', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../sales/sell_medicine.php" class="card dashboard-btn"><div class="dashboard-icon">💰</div>Sell Medicine</a></div>
            <?php endif; ?>
            
            <?php if (canAccess('sales_records', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../sales/sales_records.php" class="card dashboard-btn"><div class="dashboard-icon">📊</div>Sales Records</a></div>
            <?php endif; ?>
            
            <?php if (canAccess('top_selling', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../top_sales/top-selling.php" class="card dashboard-btn"><div class="dashboard-icon">📈</div>Top Selling Analysis</a></div>
            <?php endif; ?>
            
            <?php if (canAccess('statistics', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../statistics/statistics.php" class="card dashboard-btn"><div class="dashboard-icon">📉</div>Statistics</a></div>
            <?php endif; ?>
            
            <?php if (canAccess('expiration_management', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../expiration/expiration-management.php" class="card dashboard-btn"><div class="dashboard-icon">📅</div>Expiration Management</a></div>
            <?php endif; ?>
            
            <?php if (canAccess('supplier_management', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../supplier/supplier-management.php" class="card dashboard-btn"><div class="dashboard-icon">🏢</div>Supplier Management</a></div>
            <?php endif; ?>
            
            <?php if (canAccess('prescription_management', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../prescription/prescription-management.php" class="card dashboard-btn"><div class="dashboard-icon">📝</div>Prescription Management</a></div>
            <?php endif; ?>

            <?php if (canAccess('user_management', $userRole)): ?>
            <div class="col-md-3 mb-4"><a href="../users/add_user.php" class="card dashboard-btn"><div class="dashboard-icon">👤</div>User Management</a></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
