<?php
// Include database connection
require_once 'app/init.php';

// Get database connection
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo "Database connection failed\n";
    exit();
}

// Get all medicines
$query = "SELECT id, name FROM medicines";
$stmt = $conn->query($query);
$medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add initial stock for each medicine
$count = 0;
foreach ($medicines as $medicine) {
    // Generate a random stock between 50 and 200
    $stock = rand(50, 200);
    
    // Insert inventory log
    $query = "INSERT INTO inventory_logs (medicine_id, type, quantity, reason) 
              VALUES (:medicine_id, 'in', :quantity, 'Initial stock')";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':medicine_id', $medicine['id']);
    $stmt->bindParam(':quantity', $stock);
    
    if ($stmt->execute()) {
        echo "Added initial stock of $stock for " . $medicine['name'] . "\n";
        $count++;
    } else {
        echo "Failed to add stock for " . $medicine['name'] . "\n";
    }
}

echo "Added initial stock for $count medicines\n";
?> 