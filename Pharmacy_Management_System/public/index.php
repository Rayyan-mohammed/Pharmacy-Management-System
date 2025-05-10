<?php
session_start();
require_once 'database.php';
require_once 'models/User.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        $userData = $user->login($email, $password);
        
        if ($userData) {
            $_SESSION['currentUser'] = $userData;
            header('Location: dashboard/dashboard.php');
            exit();
        } else {
            $loginError = 'Invalid email or password.';
        }
    } else {
        $loginError = 'Please enter both email and password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #e6f7ff;
            padding-top: 20px;
        }
        #login-container {
            max-width: 400px;
            margin: 0 auto;
            margin-top: 100px;
        }
        .hidden {
            display: none;
        }
        .register-link {
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div id="login-container" class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="text-center">Pharmacy Management System</h3>
            <p class="text-center">Welcome to the Pharmacy Management System</p>
        </div>
        <div class="card-body">
            <form id="login-form" method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            <div class="register-link">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
            <?php if (!empty($loginError)): ?>
                <div id="login-error" class="alert alert-danger mt-3"><?php echo htmlspecialchars($loginError); ?></div>
            <?php else: ?>
                <div id="login-error" class="alert alert-danger mt-3 hidden"></div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>