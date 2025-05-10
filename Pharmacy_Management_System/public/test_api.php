<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test database connection
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "Database connection successful!<br>";
        
        // Test if tables exist
        $tables = ['medicines', 'inventory_logs', 'sales'];
        foreach ($tables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "Table '$table' exists<br>";
            } else {
                echo "Table '$table' does NOT exist<br>";
            }
        }
    } else {
        echo "Database connection failed!";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 