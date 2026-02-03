<?php
require_once '../../app/init.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/../../database/add_contact_to_suppliers.sql');
    $db->exec($sql);
    
    echo "Suppliers table updated successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 