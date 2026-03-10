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

