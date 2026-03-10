<?php
require_once '../app/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
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
    <title>Login - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h3>Pharmacy Pro</h3>
            <p class="text-muted">Secure Access Portal</p>
        </div>
        
        <div class="card shadow-lg">
            <div class="card-body p-4">
                <h5 class="card-title text-center mb-4">Sign In</h5>
                
                <?php if (!empty($loginError)): ?>
                    <div class="alert alert-danger mb-4 py-2 border-0 shadow-sm">
                        <small>⚠️ <?php echo htmlspecialchars($loginError); ?></small>
                    </div>
                <?php endif; ?>

                <form id="login-form" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@company.com" required>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">Access Dashboard</button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light text-center py-3">
                <small class="text-muted">Need help? Contact system administrator.</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>