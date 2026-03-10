<?php
header('Content-Type: text/plain');
require_once '../app/init.php';

$database = new Database();
$db = $database->getConnection();

try {
    $medCount = $db->query("SELECT COUNT(*) AS c FROM medicines")->fetch(PDO::FETCH_ASSOC)['c'];
    $batchCount = $db->query("SELECT COUNT(*) AS c FROM medicine_batches")->fetch(PDO::FETCH_ASSOC)['c'];
    echo "medicines: {$medCount}\n";
    echo "medicine_batches: {$batchCount}\n";

    echo "\nTop 10 medicines (id, name, stock):\n";
    $stmt = $db->query("SELECT id, name, stock FROM medicines ORDER BY id LIMIT 10");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%d | %s | stock=%s\n", $r['id'], $r['name'], $r['stock']);
    }

    echo "\nTop 10 batches (medicine_id, batch_number, qty, expiration):\n";
    $stmt2 = $db->query("SELECT medicine_id, batch_number, quantity, expiration_date FROM medicine_batches ORDER BY id LIMIT 10");
    while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("mid=%s | batch=%s | qty=%s | exp=%s\n", $r['medicine_id'], $r['batch_number'], $r['quantity'], $r['expiration_date']);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
