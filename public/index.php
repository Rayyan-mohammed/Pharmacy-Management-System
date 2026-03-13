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
        // Rate limiting: check failed attempts
        $maxAttempts = 5;
        $lockoutMinutes = 15;
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }
        // Clean old attempts
        $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], function($t) use ($lockoutMinutes) {
            return $t > time() - ($lockoutMinutes * 60);
        });
        
        if (count($_SESSION['login_attempts']) >= $maxAttempts) {
            $loginError = "Too many failed attempts. Please try again in $lockoutMinutes minutes.";
        } else {
            $userData = $user->login($email, $password);
            
            if ($userData) {
                // Clear failed attempts on success
                unset($_SESSION['login_attempts']);
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                $_SESSION['currentUser'] = $userData;
            
            // Log login activity
            try {
                $activityLog = new ActivityLog($db);
                $activityLog->log('LOGIN', 'User logged in: ' . $userData['email'], 'user', $userData['user_id']);
            } catch (Exception $e) {
                // activity_logs table may not exist yet
            }
            
            if ($userData['role'] === 'Staff') {
                header('Location: dashboard/staff_dashboard.php');
            } elseif ($userData['role'] === 'Pharmacist') {
                header('Location: dashboard/pharmacist_dashboard.php');
            } else {
                header('Location: dashboard/dashboard.php');
            }
            exit();
        } else {
            // Track failed attempt
            $_SESSION['login_attempts'][] = time();
            $loginError = 'Invalid email or password.';
        }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div style="display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:12px;background:var(--primary-50);margin-bottom:1rem;">
                <i class="bi bi-heart-pulse-fill" style="font-size:1.5rem;color:var(--primary);"></i>
            </div>
            <h3>Pharmacy Pro</h3>
            <p>Sign in to your account</p>
        </div>
        
        <div class="card">
            <div class="card-body">
                <?php if (!empty($loginError)): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($loginError); ?></span>
                    </div>
                <?php endif; ?>

                <form id="login-form" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="you@pharmacy.com" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            Sign in
                            <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <p class="text-center mt-4" style="color:var(--text-tertiary);font-size:0.8125rem;">
            Need access? Contact your system administrator.
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>