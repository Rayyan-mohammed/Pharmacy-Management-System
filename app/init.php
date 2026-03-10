<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Configuration
require_once __DIR__ . '/Config/config.php';

// Autoloader
spl_autoload_register(function ($class_name) {
    // Check Core
    $core_file = __DIR__ . '/Core/' . $class_name . '.php';
    if (file_exists($core_file)) {
        require_once $core_file;
        return;
    }

    // Check Models
    $model_file = __DIR__ . '/Models/' . $class_name . '.php';
    if (file_exists($model_file)) {
        require_once $model_file;
        return;
    }
});

// CSRF Protection Functions
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

/* 
 * Verify CSRF token on POST requests.
 * Call this at the start of POST processing blocks.
 * You must call this function manually in sensitive forms.
 */
function verify_csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die('CSRF validation failed. Please refresh the page and try again.');
        }
    }
}

