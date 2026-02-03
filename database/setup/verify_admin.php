<?php
require_once __DIR__ . '/../../app/init.php';

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM users WHERE email = 'admin1@pharmacy.com'";
$stmt = $db->prepare($query);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "VERIFICATION SUCCESSFUL:\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Role: " . $user['role'] . "\n";
    echo "Status: Active in Database\n";
} else {
    echo "User not found in database.\n";
}
?>