<?php
require_once '../../app/auth.php';
checkRole(['Administrator']);

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_active') {
        $targetId = (int)$_POST['user_id'];
        // Prevent deactivating yourself
        if ($targetId === (int)$_SESSION['currentUser']['user_id']) {
            $message = 'You cannot deactivate your own account.';
            $messageType = 'danger';
        } elseif ($user->toggleActive($targetId)) {
            $message = 'User status updated successfully.';
            $messageType = 'success';
            try { $al = new ActivityLog($db); $al->log('UPDATE', "Toggled active status for user #{$targetId}", 'user', $targetId); } catch(Exception $e) {}
        } else {
            $message = 'Failed to update user status.';
            $messageType = 'danger';
        }
    } elseif ($action === 'update_user') {
        $targetId = (int)$_POST['user_id'];
        $data = [
            'first_name' => $_POST['first_name'] ?? '',
            'last_name'  => $_POST['last_name'] ?? '',
            'email'      => $_POST['email'] ?? '',
            'role'       => $_POST['role'] ?? '',
            'phone'      => $_POST['phone'] ?? '',
            'address'    => $_POST['address'] ?? '',
        ];

        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['role'])) {
            $message = 'Please fill in all required fields.';
            $messageType = 'danger';
        } elseif ($user->update($targetId, $data)) {
            $message = 'User updated successfully.';
            $messageType = 'success';
            try { $al = new ActivityLog($db); $al->log('UPDATE', "Updated user #{$targetId}: {$data['first_name']} {$data['last_name']} ({$data['role']})", 'user', $targetId); } catch(Exception $e) {}
        } else {
            $message = 'Failed to update user.';
            $messageType = 'danger';
        }
    } elseif ($action === 'reset_password') {
        $targetId = (int)$_POST['user_id'];
        $newPass = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 6) {
            $message = 'Password must be at least 6 characters.';
            $messageType = 'danger';
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            if ($user->changePassword($targetId, $hash)) {
                $message = 'Password reset successfully.';
                $messageType = 'success';
                try { $al = new ActivityLog($db); $al->log('UPDATE', "Reset password for user #{$targetId}", 'user', $targetId); } catch(Exception $e) {}
            } else {
                $message = 'Failed to reset password.';
                $messageType = 'danger';
            }
        }
    }
}

$allUsers = $user->readAll();
$roleCounts = $user->countByRole();
$roleMap = [];
foreach ($roleCounts as $rc) {
    $roleMap[$rc['role']] = $rc['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1"><i class="bi bi-people me-2"></i>User Management</h2>
                <p class="text-muted mb-0">Manage all system users, roles, and access.</p>
            </div>
            <a href="add_user.php" class="btn btn-primary"><i class="bi bi-person-plus me-2"></i>Add New User</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Role Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-4 border-primary">
                    <div class="card-body py-3">
                        <h6 class="text-muted small mb-1">Total Users</h6>
                        <h3 class="mb-0 fw-bold"><?php echo count($allUsers); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-4 border-danger">
                    <div class="card-body py-3">
                        <h6 class="text-muted small mb-1">Administrators</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $roleMap['Administrator'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-4 border-success">
                    <div class="card-body py-3">
                        <h6 class="text-muted small mb-1">Pharmacists</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $roleMap['Pharmacist'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-4 border-info">
                    <div class="card-body py-3">
                        <h6 class="text-muted small mb-1">Staff</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $roleMap['Staff'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3">User</th>
                                <th class="py-3">Email</th>
                                <th class="py-3">Role</th>
                                <th class="py-3">Phone</th>
                                <th class="py-3 text-center">Status</th>
                                <th class="py-3">Joined</th>
                                <th class="py-3 text-end px-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $u): ?>
                            <tr class="<?php echo !$u['is_active'] ? 'table-secondary' : ''; ?>">
                                <td class="px-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;">
                                            <i class="bi bi-person text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <?php
                                    $roleBadge = ['Administrator' => 'danger', 'Pharmacist' => 'success', 'Staff' => 'info'];
                                    $badge = $roleBadge[$u['role']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($u['role']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                                <td class="text-center">
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?php echo date('d M Y', strtotime($u['created_at'])); ?></small></td>
                                <td class="text-end px-4">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $u['user_id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetModal<?php echo $u['user_id']; ?>">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <?php if ($u['user_id'] !== (int)$_SESSION['currentUser']['user_id']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to <?php echo $u['is_active'] ? 'deactivate' : 'activate'; ?> this user?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-<?php echo $u['is_active'] ? 'danger' : 'success'; ?>">
                                            <i class="bi bi-<?php echo $u['is_active'] ? 'person-slash' : 'person-check'; ?>"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?php echo $u['user_id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="update_user">
                                            <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit User</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-6">
                                                        <label class="form-label">First Name *</label>
                                                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($u['first_name']); ?>" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label">Last Name *</label>
                                                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($u['last_name']); ?>" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Email *</label>
                                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($u['email']); ?>" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label">Role *</label>
                                                        <select name="role" class="form-select" required>
                                                            <option value="Administrator" <?php echo $u['role']==='Administrator'?'selected':''; ?>>Administrator</option>
                                                            <option value="Pharmacist" <?php echo $u['role']==='Pharmacist'?'selected':''; ?>>Pharmacist</option>
                                                            <option value="Staff" <?php echo $u['role']==='Staff'?'selected':''; ?>>Staff</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label">Phone</label>
                                                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>">
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Address</label>
                                                        <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($u['address'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Reset Password Modal -->
                            <div class="modal fade" id="resetModal<?php echo $u['user_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reset Password</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="text-muted small">Reset password for <strong><?php echo htmlspecialchars($u['first_name']); ?></strong></p>
                                                <label class="form-label">New Password *</label>
                                                <input type="password" name="new_password" class="form-control" minlength="6" required>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-warning">Reset</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
