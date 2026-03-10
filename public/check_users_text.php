<?php
header('Content-Type: text/plain');
require_once '../app/init.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT id, email, role, is_active, password_hash FROM users";
    $stmt = $db->query($query);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hash = $row['password_hash'];
        $is_bcrypt = (strpos($hash, '$2y$') === 0);
        $status = $row['is_active'] ? 'Active' : 'Inactive';
        
        echo "ID: " . $row['id'] . "\n";
        echo "Email: " . $row['email'] . "\n";
        echo "Role: " . $row['role'] . "\n";
        echo "Status: " . $status . "\n";
        echo "Hash Valid: " . ($is_bcrypt ? 'Yes' : 'No') . "\n";
        echo "--------------------------\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>