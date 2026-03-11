<?php
require_once '../app/init.php';

// Log logout before destroying session
if (isset($_SESSION['currentUser'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $activityLog = new ActivityLog($db);
        $activityLog->log('LOGOUT', 'User logged out: ' . ($_SESSION['currentUser']['email'] ?? ''), 'user', $_SESSION['currentUser']['user_id'] ?? 0);
    } catch (Exception $e) {
        // activity_logs table may not exist yet
    }
}

session_destroy();
header('Location: index.php');
exit();
