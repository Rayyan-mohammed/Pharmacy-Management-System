<?php
/**
 * Database Migration Script
 * Adds missing tables, columns, and indexes to bring the database up to date.
 * Safe to run multiple times (uses IF NOT EXISTS / IF NOT EXISTS guards).
 */
require_once __DIR__ . '/../../app/Config/config.php';
require_once __DIR__ . '/../../app/Core/Database.php';

$db = (new Database())->getConnection();

$migrations = [
    // medicine_categories table
    "CREATE TABLE IF NOT EXISTS medicine_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // returns table
    "CREATE TABLE IF NOT EXISTS `returns` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        medicine_id INT NOT NULL,
        quantity INT NOT NULL,
        reason TEXT,
        refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        processed_by INT DEFAULT NULL,
        processed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // activity_logs table
    "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        user_name VARCHAR(255),
        action VARCHAR(50) NOT NULL,
        description TEXT,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id INT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // prescription_status_logs table
    "CREATE TABLE IF NOT EXISTS prescription_status_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prescription_id INT NOT NULL,
        status VARCHAR(50) NOT NULL,
        changed_by VARCHAR(255) DEFAULT NULL,
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (prescription_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // customers table (basic CRM ledger)
    "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        email VARCHAR(100) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        total_orders INT NOT NULL DEFAULT 0,
        total_spent DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        last_visit TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_customers_mobile (mobile)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // cash register summary table
    "CREATE TABLE IF NOT EXISTS cash_register_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        business_date DATE NOT NULL,
        opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        cash_sales DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        cash_refunds DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        cash_in DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        cash_out DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        expected_closing DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        actual_closing DECIMAL(12,2) DEFAULT NULL,
        variance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        closed_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cash_register_date (business_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // cash movement audit table
    "CREATE TABLE IF NOT EXISTS cash_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        business_date DATE NOT NULL,
        movement_type ENUM('in','out') NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        reason VARCHAR(255) NOT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // purchases table
    "CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        invoice_number VARCHAR(100) NOT NULL,
        purchase_date DATE NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        due_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        payment_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // purchase items table
    "CREATE TABLE IF NOT EXISTS purchase_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        medicine_id INT NOT NULL,
        batch_number VARCHAR(100) NOT NULL,
        expiration_date DATE NOT NULL,
        quantity INT NOT NULL,
        cost_price DECIMAL(10,2) NOT NULL,
        sale_price DECIMAL(10,2) NOT NULL,
        line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // purchase payment settlements
    "CREATE TABLE IF NOT EXISTS purchase_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        payment_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        payment_method VARCHAR(20) NOT NULL DEFAULT 'Cash',
        reference_no VARCHAR(100) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // purchase returns header
    "CREATE TABLE IF NOT EXISTS purchase_returns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        supplier_id INT NOT NULL,
        return_number VARCHAR(50) NOT NULL,
        return_date DATE NOT NULL,
        reason TEXT DEFAULT NULL,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        credit_applied DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(20) NOT NULL DEFAULT 'Processed',
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // purchase return line items
    "CREATE TABLE IF NOT EXISTS purchase_return_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_return_id INT NOT NULL,
        medicine_id INT NOT NULL,
        batch_number VARCHAR(100) NOT NULL,
        quantity INT NOT NULL,
        unit_cost DECIMAL(10,2) NOT NULL,
        line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // role-based permission matrix
    "CREATE TABLE IF NOT EXISTS role_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL,
        permission_key VARCHAR(100) NOT NULL,
        is_allowed TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_role_permission (role_name, permission_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // branches for multi-store setup
    "CREATE TABLE IF NOT EXISTS branches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_name VARCHAR(100) NOT NULL,
        code VARCHAR(20) NOT NULL,
        address TEXT DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_branch_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // inter-branch transfers header
    "CREATE TABLE IF NOT EXISTS stock_transfers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transfer_number VARCHAR(50) NOT NULL,
        from_branch_id INT NOT NULL,
        to_branch_id INT NOT NULL,
        transfer_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Completed',
        notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_transfer_number (transfer_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // inter-branch transfer items
    "CREATE TABLE IF NOT EXISTS stock_transfer_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transfer_id INT NOT NULL,
        medicine_id INT NOT NULL,
        quantity INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // hold/resume billing carts
    "CREATE TABLE IF NOT EXISTS held_carts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hold_code VARCHAR(30) NOT NULL,
        customer_name VARCHAR(255) DEFAULT NULL,
        customer_phone VARCHAR(20) DEFAULT NULL,
        cart_json LONGTEXT NOT NULL,
        bill_snapshot_json LONGTEXT DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Held',
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resumed_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY uq_hold_code (hold_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // alert escalations and acknowledgment tracking
    "CREATE TABLE IF NOT EXISTS alert_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alert_type VARCHAR(50) NOT NULL,
        severity VARCHAR(20) NOT NULL,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id INT DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        details TEXT DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Open',
        acknowledged_by INT DEFAULT NULL,
        acknowledged_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // backup metadata registry for retention and integrity checks
    "CREATE TABLE IF NOT EXISTS backup_runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_name VARCHAR(255) NOT NULL,
        file_size BIGINT NOT NULL DEFAULT 0,
        checksum_sha256 VARCHAR(64) DEFAULT NULL,
        run_status VARCHAR(20) NOT NULL DEFAULT 'SUCCESS',
        notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // optional operating expense log for P&L-lite
    "CREATE TABLE IF NOT EXISTS expense_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_date DATE NOT NULL,
        category VARCHAR(100) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

// Column additions (safe — uses column existence check)
$columnAdditions = [
    ['medicines', 'category_id', "ALTER TABLE medicines ADD COLUMN category_id INT DEFAULT NULL AFTER name"],
    ['medicines', 'reorder_level', "ALTER TABLE medicines ADD COLUMN reorder_level INT NOT NULL DEFAULT 10 AFTER stock"],
    ['medicines', 'lead_time_days', "ALTER TABLE medicines ADD COLUMN lead_time_days INT NOT NULL DEFAULT 7 AFTER reorder_level"],
    ['medicines', 'safety_stock', "ALTER TABLE medicines ADD COLUMN safety_stock INT NOT NULL DEFAULT 5 AFTER lead_time_days"],
    ['medicines', 'hsn_code', "ALTER TABLE medicines ADD COLUMN hsn_code VARCHAR(20) DEFAULT NULL AFTER description"],
    ['medicines', 'gst_percent', "ALTER TABLE medicines ADD COLUMN gst_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER hsn_code"],
    ['medicines', 'barcode', "ALTER TABLE medicines ADD COLUMN barcode VARCHAR(64) DEFAULT NULL AFTER gst_percent"],
    ['medicines', 'branch_id', "ALTER TABLE medicines ADD COLUMN branch_id INT DEFAULT 1 AFTER category_id"],
    ['sales', 'invoice_number', "ALTER TABLE sales ADD COLUMN invoice_number VARCHAR(20) DEFAULT NULL AFTER id"],
    ['sales', 'discount', "ALTER TABLE sales ADD COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_price"],
    ['sales', 'customer_phone', "ALTER TABLE sales ADD COLUMN customer_phone VARCHAR(20) DEFAULT NULL AFTER customer_name"],
    ['sales', 'customer_id', "ALTER TABLE sales ADD COLUMN customer_id INT DEFAULT NULL AFTER customer_name"],
    ['sales', 'payment_method', "ALTER TABLE sales ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'Cash' AFTER customer_phone"],
    ['sales', 'payment_reference', "ALTER TABLE sales ADD COLUMN payment_reference VARCHAR(100) DEFAULT NULL AFTER payment_method"],
    ['sales', 'upi_txn_id', "ALTER TABLE sales ADD COLUMN upi_txn_id VARCHAR(100) DEFAULT NULL AFTER payment_reference"],
    ['sales', 'card_last4', "ALTER TABLE sales ADD COLUMN card_last4 VARCHAR(4) DEFAULT NULL AFTER upi_txn_id"],
    ['sales', 'card_auth_ref', "ALTER TABLE sales ADD COLUMN card_auth_ref VARCHAR(100) DEFAULT NULL AFTER card_last4"],
    ['sales', 'subtotal', "ALTER TABLE sales ADD COLUMN subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unit_price"],
    ['sales', 'discount_type', "ALTER TABLE sales ADD COLUMN discount_type VARCHAR(20) NOT NULL DEFAULT 'amount' AFTER subtotal"],
    ['sales', 'discount_value', "ALTER TABLE sales ADD COLUMN discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount_type"],
    ['sales', 'discount_amount', "ALTER TABLE sales ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount_value"],
    ['sales', 'taxable_amount', "ALTER TABLE sales ADD COLUMN taxable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount_amount"],
    ['sales', 'tax_percent', "ALTER TABLE sales ADD COLUMN tax_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER taxable_amount"],
    ['sales', 'tax_amount', "ALTER TABLE sales ADD COLUMN tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER tax_percent"],
    ['sales', 'net_total', "ALTER TABLE sales ADD COLUMN net_total DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER tax_amount"],
    ['sales', 'cgst_amount', "ALTER TABLE sales ADD COLUMN cgst_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER tax_amount"],
    ['sales', 'sgst_amount', "ALTER TABLE sales ADD COLUMN sgst_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER cgst_amount"],
    ['sales', 'igst_amount', "ALTER TABLE sales ADD COLUMN igst_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER sgst_amount"],
    ['sales', 'branch_id', "ALTER TABLE sales ADD COLUMN branch_id INT DEFAULT 1 AFTER medicine_id"],
    ['sales', 'amount_tendered', "ALTER TABLE sales ADD COLUMN amount_tendered DECIMAL(10,2) DEFAULT NULL AFTER payment_method"],
    ['sales', 'change_due', "ALTER TABLE sales ADD COLUMN change_due DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER amount_tendered"],
    ['returns', 'original_payment_method', "ALTER TABLE `returns` ADD COLUMN original_payment_method VARCHAR(20) DEFAULT NULL AFTER refund_amount"],
    ['returns', 'refund_method', "ALTER TABLE `returns` ADD COLUMN refund_method VARCHAR(20) DEFAULT NULL AFTER original_payment_method"],
    ['returns', 'refund_reference', "ALTER TABLE `returns` ADD COLUMN refund_reference VARCHAR(100) DEFAULT NULL AFTER refund_method"],
    ['returns', 'refunded_at', "ALTER TABLE `returns` ADD COLUMN refunded_at TIMESTAMP NULL DEFAULT NULL AFTER processed_at"],
    ['inventory_logs', 'branch_id', "ALTER TABLE inventory_logs ADD COLUMN branch_id INT DEFAULT 1 AFTER medicine_id"],
    ['purchases', 'branch_id', "ALTER TABLE purchases ADD COLUMN branch_id INT DEFAULT 1 AFTER supplier_id"],
    ['users', 'branch_id', "ALTER TABLE users ADD COLUMN branch_id INT DEFAULT 1 AFTER role"],
];

// Indexes
$indexes = [
    "CREATE INDEX idx_sales_date ON sales(sale_date)",
    "CREATE INDEX idx_sales_medicine ON sales(medicine_id)",
    "CREATE INDEX idx_sales_invoice ON sales(invoice_number)",
    "CREATE INDEX idx_sales_payment_method ON sales(payment_method)",
    "CREATE INDEX idx_sales_customer ON sales(customer_id)",
    "CREATE INDEX idx_sales_mobile ON sales(customer_phone)",
    "CREATE INDEX idx_sales_net_total ON sales(net_total)",
    "CREATE INDEX idx_sales_branch ON sales(branch_id)",
    "CREATE INDEX idx_returns_refund_method ON `returns`(refund_method)",
    "CREATE INDEX idx_cash_register_date ON cash_register_sessions(business_date)",
    "CREATE INDEX idx_cash_movements_date ON cash_movements(business_date)",
    "CREATE INDEX idx_customers_last_visit ON customers(last_visit)",
    "CREATE INDEX idx_purchases_supplier ON purchases(supplier_id)",
    "CREATE INDEX idx_purchases_date ON purchases(purchase_date)",
    "CREATE INDEX idx_purchases_status ON purchases(payment_status)",
    "CREATE INDEX idx_purchase_items_purchase ON purchase_items(purchase_id)",
    "CREATE INDEX idx_purchase_items_medicine ON purchase_items(medicine_id)",
    "CREATE INDEX idx_batches_medicine ON medicine_batches(medicine_id)",
    "CREATE INDEX idx_batches_expiry ON medicine_batches(expiration_date)",
    "CREATE INDEX idx_medicines_name ON medicines(name)",
    "CREATE INDEX idx_medicines_category ON medicines(category_id)",
    "CREATE INDEX idx_medicines_barcode ON medicines(barcode)",
    "CREATE INDEX idx_medicines_branch ON medicines(branch_id)",
    "CREATE INDEX idx_prescriptions_status ON prescriptions(status)",
    "CREATE INDEX idx_activity_action ON activity_logs(action)",
    "CREATE INDEX idx_activity_date ON activity_logs(created_at)",
    "CREATE INDEX idx_returns_status ON `returns`(status)",
    "CREATE INDEX idx_purchase_payments_purchase ON purchase_payments(purchase_id)",
    "CREATE INDEX idx_purchase_payments_date ON purchase_payments(payment_date)",
    "CREATE INDEX idx_purchase_returns_purchase ON purchase_returns(purchase_id)",
    "CREATE INDEX idx_purchase_returns_supplier ON purchase_returns(supplier_id)",
    "CREATE INDEX idx_purchase_return_items_return ON purchase_return_items(purchase_return_id)",
    "CREATE INDEX idx_stock_transfers_date ON stock_transfers(transfer_date)",
    "CREATE INDEX idx_stock_transfer_items_transfer ON stock_transfer_items(transfer_id)",
    "CREATE INDEX idx_alert_events_type_status ON alert_events(alert_type, status)",
    "CREATE INDEX idx_alert_events_created ON alert_events(created_at)",
    "CREATE INDEX idx_backup_runs_created ON backup_runs(created_at)",
    "CREATE INDEX idx_expense_entries_date ON expense_entries(expense_date)",
];

// Seed default branch and base permissions
try {
    $db->exec("INSERT INTO branches (id, branch_name, code, is_active) VALUES (1, 'Main Branch', 'MAIN', 1) ON DUPLICATE KEY UPDATE branch_name = branch_name");
} catch (Exception $e) {}

$permissionSeeds = [
    ['Administrator', 'users.manage'],
    ['Administrator', 'backup.restore'],
    ['Administrator', 'returns.approve'],
    ['Administrator', 'settings.financial'],
    ['Pharmacist', 'returns.approve'],
    ['Pharmacist', 'purchase.create'],
    ['Pharmacist', 'sales.create'],
    ['Staff', 'sales.create']
];

foreach ($permissionSeeds as $perm) {
    try {
        $stmtPerm = $db->prepare("INSERT INTO role_permissions (role_name, permission_key, is_allowed) VALUES (:role, :perm, 1) ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)");
        $stmtPerm->bindValue(':role', $perm[0]);
        $stmtPerm->bindValue(':perm', $perm[1]);
        $stmtPerm->execute();
    } catch (Exception $e) {}
}

echo "=== Running Database Migrations ===\n\n";

// Run table creations
foreach ($migrations as $sql) {
    try {
        $db->exec($sql);
        echo "OK: " . substr($sql, 0, 60) . "...\n";
    } catch (Exception $e) {
        echo "SKIP: " . $e->getMessage() . "\n";
    }
}

// Run column additions
foreach ($columnAdditions as list($table, $column, $sql)) {
    try {
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :db AND table_name = :table AND column_name = :col");
        $checkStmt->execute([':db' => DB_NAME, ':table' => $table, ':col' => $column]);
        if ($checkStmt->fetchColumn() == 0) {
            $db->exec($sql);
            echo "COL OK: Added $column to $table\n";
        } else {
            echo "COL SKIP: $table.$column already exists\n";
        }
    } catch (Exception $e) {
        echo "COL ERR: $table.$column - " . $e->getMessage() . "\n";
    }
}

// Run index creations
foreach ($indexes as $sql) {
    try {
        $db->exec($sql);
        echo "IDX OK: " . substr($sql, 13, 50) . "\n";
    } catch (Exception $e) {
        echo "IDX SKIP: " . substr($sql, 13, 30) . " (already exists)\n";
    }
}

echo "\n=== Migration Complete! ===\n";
