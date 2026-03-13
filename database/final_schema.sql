-- Pharmacy Management System - Final Database Schema
-- Generated: 2026-03-11
-- This structure replaces previous individual SQL files.

SET FOREIGN_KEY_CHECKS = 0;
CREATE DATABASE IF NOT EXISTS medical_management;
USE medical_management;

-- -----------------------------------------------------
-- Table: users
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role VARCHAR(20) NOT NULL, -- Administrator, Pharmacist, Staff
    phone VARCHAR(20),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Default Administrator
-- Password is 'admin123'
INSERT INTO users (email, password_hash, first_name, last_name, role, phone, address, is_active)
VALUES ('admin1@pharmacy.com', '$2y$10$62NElCifPbW0Ou15zpgrjO4DwT47K.atkluqZ5jCWuESCo.tWE0pW', 'System', 'Admin', 'Administrator', '0000000000', 'System', 1)
ON DUPLICATE KEY UPDATE email=email;

-- -----------------------------------------------------
-- Table: medicine_categories
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS medicine_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: medicines
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category_id INT DEFAULT NULL,
    description TEXT,
    inventory_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    prescription_needed BOOLEAN DEFAULT FALSE,
    expiration_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_medicines_name (name),
    INDEX idx_medicines_category (category_id),
    FOREIGN KEY (category_id) REFERENCES medicine_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: purchases
-- Supplier purchase bills / GRN header
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS purchases (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_purchases_supplier (supplier_id),
    INDEX idx_purchases_date (purchase_date),
    INDEX idx_purchases_status (payment_status),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: purchase_items
-- Line items from supplier purchase bill
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    medicine_id INT NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    expiration_date DATE NOT NULL,
    quantity INT NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_purchase_items_purchase (purchase_id),
    INDEX idx_purchase_items_medicine (medicine_id),
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: medicine_batches
-- Tracks expiration dates for specific batches
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS medicine_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    expiration_date DATE NOT NULL,
    quantity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batches_medicine (medicine_id),
    INDEX idx_batches_expiry (expiration_date),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: suppliers
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: prescriptions
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_code VARCHAR(50) UNIQUE,
    patient_name VARCHAR(255) NOT NULL,
    doctor_name VARCHAR(255),
    prescription_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prescriptions_status (status),
    INDEX idx_prescriptions_date (prescription_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: prescription_items
-- Links prescriptions to specific medicines with dosage
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS prescription_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    dosage VARCHAR(100),
    instructions TEXT,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: prescription_status_logs
-- Audit trail for prescription status changes
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS prescription_status_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    changed_by VARCHAR(255) DEFAULT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_logs_prescription (prescription_id),
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: inventory_logs
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    type ENUM('in', 'out') NOT NULL,
    quantity INT NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inventory_medicine (medicine_id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: sales
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(20) DEFAULT NULL,
    medicine_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_type VARCHAR(20) NOT NULL DEFAULT 'amount',
    discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    taxable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_price DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    profit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    customer_name VARCHAR(255),
    customer_phone VARCHAR(20) DEFAULT NULL,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'Cash',
    payment_reference VARCHAR(100) DEFAULT NULL,
    upi_txn_id VARCHAR(100) DEFAULT NULL,
    card_last4 VARCHAR(4) DEFAULT NULL,
    card_auth_ref VARCHAR(100) DEFAULT NULL,
    amount_tendered DECIMAL(10,2) DEFAULT NULL,
    change_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    INDEX idx_sales_date (sale_date),
    INDEX idx_sales_medicine (medicine_id),
    INDEX idx_sales_invoice (invoice_number),
    INDEX idx_sales_payment_method (payment_method),
    INDEX idx_sales_customer (customer_id),
    INDEX idx_sales_mobile (customer_phone),
    INDEX idx_sales_net_total (net_total),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: customers
-- Basic CRM customer ledger
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
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
    UNIQUE KEY uq_customers_mobile (mobile),
    INDEX idx_customers_last_visit (last_visit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: returns
-- Tracks medicine returns and refunds
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    reason TEXT,
    refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    original_payment_method VARCHAR(20) DEFAULT NULL,
    refund_method VARCHAR(20) DEFAULT NULL,
    refund_reference VARCHAR(100) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    processed_by INT DEFAULT NULL,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    refunded_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_returns_status (status),
    INDEX idx_returns_refund_method (refund_method),
    FOREIGN KEY (sale_id) REFERENCES sales(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    FOREIGN KEY (processed_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: cash_register_sessions
-- End-of-day cash register summary
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS cash_register_sessions (
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
    UNIQUE KEY uq_cash_register_date (business_date),
    INDEX idx_cash_register_date (business_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: cash_movements
-- Cash in / cash out manual adjustments
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS cash_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_date DATE NOT NULL,
    movement_type ENUM('in','out') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cash_movements_date (business_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: activity_logs
-- System-wide audit trail
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(255),
    action VARCHAR(50) NOT NULL,
    description TEXT,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_action (action),
    INDEX idx_activity_date (created_at),
    INDEX idx_activity_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
