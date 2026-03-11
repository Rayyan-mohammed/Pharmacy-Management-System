<?php
require_once '../../app/auth.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$currentUser = $_SESSION['currentUser'];
$userId = (int)$currentUser['user_id'];
$message = '';
$messageType = '';
$activeTab = 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $data = [
            'first_name' => $_POST['first_name'] ?? '',
            'last_name'  => $_POST['last_name'] ?? '',
            'phone'      => $_POST['phone'] ?? '',
            'address'    => $_POST['address'] ?? '',
        ];

        if (empty($data['first_name']) || empty($data['last_name'])) {
            $message = 'First name and last name are required.';
            $messageType = 'danger';
        } elseif ($user->updateProfile($userId, $data)) {
            // Update session data
            $_SESSION['currentUser']['first_name'] = htmlspecialchars(strip_tags($data['first_name']));
            $_SESSION['currentUser']['last_name'] = htmlspecialchars(strip_tags($data['last_name']));
            $_SESSION['currentUser']['phone'] = htmlspecialchars(strip_tags($data['phone']));
            $_SESSION['currentUser']['address'] = htmlspecialchars(strip_tags($data['address']));
            $currentUser = $_SESSION['currentUser'];
            $message = 'Profile updated successfully.';
            $messageType = 'success';
        } else {
            $message = 'Failed to update profile.';
            $messageType = 'danger';
        }
        $activeTab = 'profile';
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            $message = 'Please fill in all password fields.';
            $messageType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match.';
            $messageType = 'danger';
        } elseif (strlen($newPassword) < 6) {
            $message = 'New password must be at least 6 characters.';
            $messageType = 'danger';
        } elseif (!$user->verifyPassword($userId, $currentPassword)) {
            $message = 'Current password is incorrect.';
            $messageType = 'danger';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($user->changePassword($userId, $hash)) {
                $message = 'Password changed successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to change password.';
                $messageType = 'danger';
            }
        }
        $activeTab = 'password';
    }
}

// Get dashboard link based on role
$dashboardLink = '../dashboard/dashboard.php';
if ($currentUser['role'] === 'Pharmacist') $dashboardLink = '../dashboard/pharmacist_dashboard.php';
if ($currentUser['role'] === 'Staff') $dashboardLink = '../dashboard/staff_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo $dashboardLink; ?>">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="fw-bold text-primary mb-4"><i class="bi bi-person-gear me-2"></i>My Profile</h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Profile Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width:64px;height:64px;">
                                <i class="bi bi-person-circle text-primary fs-1"></i>
                            </div>
                            <div>
                                <h4 class="mb-0"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h4>
                                <span class="badge bg-<?php echo ['Administrator'=>'danger','Pharmacist'=>'success','Staff'=>'info'][$currentUser['role']] ?? 'secondary'; ?>">
                                    <?php echo htmlspecialchars($currentUser['role']); ?>
                                </span>
                                <small class="text-muted ms-2"><?php echo htmlspecialchars($currentUser['email']); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link <?php echo $activeTab==='profile'?'active':''; ?>" data-bs-toggle="tab" data-bs-target="#profileTab">
                            <i class="bi bi-person me-1"></i>Edit Profile
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link <?php echo $activeTab==='password'?'active':''; ?>" data-bs-toggle="tab" data-bs-target="#passwordTab">
                            <i class="bi bi-shield-lock me-1"></i>Change Password
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Profile Tab -->
                    <div class="tab-pane fade <?php echo $activeTab==='profile'?'show active':''; ?>" id="profileTab">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">First Name *</label>
                                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['first_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Last Name *</label>
                                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['last_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($currentUser['email']); ?>" disabled>
                                            <small class="text-muted">Contact admin to change email.</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone</label>
                                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Address</label>
                                            <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($currentUser['address'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Save Changes</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Password Tab -->
                    <div class="tab-pane fade <?php echo $activeTab==='password'?'show active':''; ?>" id="passwordTab">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="change_password">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Current Password *</label>
                                            <input type="password" name="current_password" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">New Password *</label>
                                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                                            <small class="text-muted">Minimum 6 characters</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Confirm New Password *</label>
                                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-warning"><i class="bi bi-shield-check me-2"></i>Change Password</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
