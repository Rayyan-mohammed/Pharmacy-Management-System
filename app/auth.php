<?php
require_once __DIR__ . '/init.php';

// Check if user is logged in
if (!isset($_SESSION['currentUser'])) {
    // Redirect to login page
    // Adjust path if necessary or use absolute URL
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

function hasRole($allowedRoles) {
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    return isset($_SESSION['currentUser']['role']) && in_array($_SESSION['currentUser']['role'], $allowedRoles);
}

function checkRole($allowedRoles) {
    if (!hasRole($allowedRoles)) {
        // Redirect to dashboard with error or just access denied
        header('Location: ' . BASE_URL . '/dashboard/dashboard.php');
        exit();
    }
}

function getCurrentBranchId() {
    return (int)($_SESSION['currentUser']['branch_id'] ?? 1);
}

function hasPermission($permissionKey) {
    if (!isset($_SESSION['currentUser']['role'])) {
        return false;
    }

    $role = $_SESSION['currentUser']['role'];
    if ($role === 'Administrator') {
        return true;
    }

    try {
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("SELECT is_allowed FROM role_permissions WHERE role_name = :role AND permission_key = :perm LIMIT 1");
        $stmt->bindValue(':role', $role);
        $stmt->bindValue(':perm', $permissionKey);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ((int)$row['is_allowed'] === 1) : false;
    } catch (Exception $e) {
        return false;
    }
}

function checkPermission($permissionKey) {
    if (!hasPermission($permissionKey)) {
        header('Location: ' . BASE_URL . '/dashboard/dashboard.php');
        exit();
    }
}

