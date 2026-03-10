<?php
require_once '../../app/auth.php';
checkRole(['Administrator']);

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$registrationError = '';
$registrationSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $role = $_POST['role'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';

    // Validate input
    if (empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($role)) {
        $registrationError = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $registrationError = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $registrationError = 'Password must be at least 6 characters long.';
    } else {
        // Check if email already exists
        $user->email = $email;
        if ($user->emailExists()) {
            $registrationError = 'Email already exists.';
        } else {
            // Create new user
            $user->email = $email;
            $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
            $user->first_name = $first_name;
            $user->last_name = $last_name;
            $user->role = $role;
            $user->phone = $phone;
            $user->address = $address;

            if ($user->create()) {
                $registrationSuccess = 'User created successfully.';
            } else {
                $registrationError = 'Failed to create user. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #e6f7ff;
            padding-top: 20px;
        }
        #register-container {
            max-width: 600px;
            margin: 0 auto;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../dashboard/dashboard.php">Dashboard</a>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div id="register-container" class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="text-center">User Management</h3>
            <p class="text-center">Add New User</p>
        </div>
        <div class="card-body">
            <?php if (!empty($registrationError)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($registrationError); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($registrationSuccess)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($registrationSuccess); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">First Name *</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address *</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Role *</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="Administrator">Administrator</option>
                        <option value="Pharmacist">Pharmacist</option>
                        <option value="Staff">Staff</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone">
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100">Create User</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 