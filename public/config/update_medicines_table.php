<?php
require_once '../../app/init.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/../../database/alter_medicines_table.sql');
    $db->exec($sql);
    
    echo "Medicines table updated successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 