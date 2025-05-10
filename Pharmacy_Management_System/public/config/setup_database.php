<?php
require_once 'database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/create_tables.sql');
    $db->exec($sql);
    
    echo "Database tables created successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 