<?php
require_once '../app/init.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "<h1>User Diagnosis</h1>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Email</th><th>Role</th><th>Is Active?</th><th>Password Hash Format</th><th>Action Needed?</th></tr>";

    $query = "SELECT id, email, role, is_active, password_hash FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hash = $row['password_hash'];
        $is_bcrypt = (strpos($hash, '$2y$') === 0);
        $is_plain = (strlen($hash) < 60 && strpos($hash, '$') === false); // Rough check
        
        $status = $row['is_active'] ? 'Active (1)' : 'Inactive (0)';
        $hash_status = $is_bcrypt ? 'Valid (BCrypt)' : ($is_plain ? 'Invalid (Likely Plaintext)' : 'Unknown/Legacy');
        
        $action = [];
        if (!$row['is_active']) $action[] = "Set Active";
        if (!$is_bcrypt) $action[] = "Reset Password";
        
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>{$row['role']}</td>";
        echo "<td>{$status}</td>";
        echo "<td>{$hash_status}</td>";
        echo "<td>" . (empty($action) ? 'None' : implode(', ', $action)) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>