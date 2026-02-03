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
