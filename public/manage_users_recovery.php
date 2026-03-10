<?php
require_once '../app/init.php';

// WARNING: This script is for recovery only. Delete after use.
// It allows resetting passwords without being logged in.

$database = new Database();
$db = $database->getConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? 0;
    $action = $_POST['action'] ?? '';

    if ($user_id) {
        if ($action === 'reset_password') {
            $new_password = 'password123';
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmt->bindParam(':hash', $hash);
            $stmt->bindParam(':id', $user_id);
            if ($stmt->execute()) {
                $message = "Password for User ID $user_id reset to 'password123'.";
            }
        } elseif ($action === 'activate') {
            $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = :id");
            $stmt->bindParam(':id', $user_id);
            if ($stmt->execute()) {
                $message = "User ID $user_id activated.";
            }
        }
    }
}

$users = $db->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Recovery Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">
    <div class="container">
        <h1>User Recovery Tool</h1>
        <div class="alert alert-warning">
            <strong>Warning:</strong> This tool allows modifying users without login. Delete this file immediately after fixing your login issues.
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                
                                <?php if (!$user['is_active']): ?>
                                    <button type="submit" name="action" value="activate" class="btn btn-sm btn-success">Activate</button>
                                <?php endif; ?>
                                
                                <button type="submit" name="action" value="reset_password" class="btn btn-sm btn-warning" onclick="return confirm('Reset password to password123?')">Reset Password</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="mt-4">
            <a href="index.php" class="btn btn-primary">Go to Login</a>
        </div>
    </div>
</body>
</html>