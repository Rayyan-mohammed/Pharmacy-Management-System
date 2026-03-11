<?php
/**
 * Database migration for new features:
 * 1. medicine_categories table
 * 2. returns table
 * 3. activity_logs table
 * 4. Add category_id to medicines
 */

require_once __DIR__ . '/../../app/Config/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Medicine Categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS medicine_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default categories
    $categories = [
        ['Analgesics', 'Pain relievers such as paracetamol, ibuprofen'],
        ['Antibiotics', 'Anti-bacterial medicines'],
        ['Antacids', 'Medicines for acidity and gastric issues'],
        ['Antihistamines', 'Allergy relief medicines'],
        ['Antihypertensives', 'Blood pressure control medicines'],
        ['Antidiabetics', 'Diabetes management medicines'],
        ['Vitamins & Supplements', 'Nutritional supplements and vitamins'],
        ['Cough & Cold', 'Cold, cough, and flu remedies'],
        ['Dermatological', 'Skin creams, ointments, and treatments'],
        ['Gastrointestinal', 'Digestive system medicines'],
        ['Cardiovascular', 'Heart and blood vessel medicines'],
        ['Antipyretics', 'Fever reducing medicines'],
        ['Antiseptics', 'Infection prevention and wound care'],
        ['Others', 'Uncategorized medicines']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO medicine_categories (name, description) VALUES (?, ?)");
    foreach ($categories as $cat) {
        $stmt->execute($cat);
    }

    // 2. Add category_id to medicines if not exists
    $cols = $pdo->query("SHOW COLUMNS FROM medicines LIKE 'category_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN category_id INT DEFAULT NULL AFTER description");
        $pdo->exec("ALTER TABLE medicines ADD FOREIGN KEY (category_id) REFERENCES medicine_categories(id) ON DELETE SET NULL");
    }

    // 3. Returns table
    $pdo->exec("CREATE TABLE IF NOT EXISTS returns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        medicine_id INT NOT NULL,
        quantity INT NOT NULL,
        reason TEXT NOT NULL,
        refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        processed_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(id),
        FOREIGN KEY (medicine_id) REFERENCES medicines(id),
        FOREIGN KEY (processed_by) REFERENCES users(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4. Activity Logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_name VARCHAR(100) NOT NULL,
        action VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id INT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "SUCCESS: All new feature tables created successfully!\n";
    echo "- medicine_categories: Created with " . count($categories) . " default categories\n";
    echo "- medicines.category_id: Column added\n";
    echo "- returns: Table created\n";
    echo "- activity_logs: Table created\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
