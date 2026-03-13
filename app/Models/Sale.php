<?php
class Sale {
    private $conn;
    private $table_name = "sales";

    public $id;
    public $medicine_id;
    public $quantity;
    public $unit_price;
    public $total_price;
    public $profit;
    public $customer_name;
    public $customer_id;
    public $invoice_number;
    public $discount;
    public $subtotal;
    public $discount_type;
    public $discount_value;
    public $discount_amount;
    public $taxable_amount;
    public $tax_percent;
    public $tax_amount;
    public $cgst_amount;
    public $sgst_amount;
    public $igst_amount;
    public $net_total;
    public $customer_phone;
    public $payment_method;
    public $payment_reference;
    public $upi_txn_id;
    public $card_last4;
    public $card_auth_ref;
    public $amount_tendered;
    public $change_due;
    public $branch_id;
    public $sale_date;

    private $columnExistsCache = [];

    public function __construct($db) {
        $this->conn = $db;
    }

    private function hasColumn($columnName) {
        if (!array_key_exists($columnName, $this->columnExistsCache)) {
            try {
                $stmt = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = :column");
                $stmt->bindValue(':column', $columnName);
                $stmt->execute();
                $this->columnExistsCache[$columnName] = ((int)$stmt->fetchColumn()) > 0;
            } catch (Exception $e) {
                $this->columnExistsCache[$columnName] = false;
            }
        }
        return $this->columnExistsCache[$columnName];
    }

    // Create new sale
    public function create() {
        $columns = ['medicine_id', 'quantity', 'unit_price', 'total_price', 'profit', 'customer_name'];
        $placeholders = [':medicine_id', ':quantity', ':unit_price', ':total_price', ':profit', ':customer_name'];

        $optionalColumns = [
            'customer_id', 'invoice_number', 'discount',
            'subtotal', 'discount_type', 'discount_value', 'discount_amount',
            'taxable_amount', 'tax_percent', 'tax_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'net_total',
            'customer_phone', 'payment_method', 'payment_reference', 'upi_txn_id',
            'card_last4', 'card_auth_ref', 'amount_tendered', 'change_due', 'branch_id'
        ];

        foreach ($optionalColumns as $col) {
            if ($this->hasColumn($col)) {
                $columns[] = $col;
                $placeholders[] = ':' . $col;
            }
        }

        $query = "INSERT INTO {$this->table_name} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":medicine_id", $this->medicine_id, PDO::PARAM_INT);
        $stmt->bindParam(":quantity", $this->quantity, PDO::PARAM_INT);
        $stmt->bindParam(":unit_price", $this->unit_price);
        $stmt->bindParam(":total_price", $this->total_price);
        $stmt->bindParam(":profit", $this->profit);
        $stmt->bindParam(":customer_name", $this->customer_name);

        if ($this->hasColumn('customer_id')) {
            $customerId = !empty($this->customer_id) ? (int)$this->customer_id : null;
            $stmt->bindValue(':customer_id', $customerId, $customerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        }
        if ($this->hasColumn('invoice_number')) {
            $stmt->bindValue(':invoice_number', $this->invoice_number ?: null);
        }
        if ($this->hasColumn('discount')) {
            $stmt->bindValue(':discount', (float)($this->discount ?? 0));
        }
        if ($this->hasColumn('subtotal')) {
            $stmt->bindValue(':subtotal', (float)($this->subtotal ?? $this->total_price));
        }
        if ($this->hasColumn('discount_type')) {
            $stmt->bindValue(':discount_type', $this->discount_type ?: 'amount');
        }
        if ($this->hasColumn('discount_value')) {
            $stmt->bindValue(':discount_value', (float)($this->discount_value ?? 0));
        }
        if ($this->hasColumn('discount_amount')) {
            $stmt->bindValue(':discount_amount', (float)($this->discount_amount ?? 0));
        }
        if ($this->hasColumn('taxable_amount')) {
            $stmt->bindValue(':taxable_amount', (float)($this->taxable_amount ?? $this->total_price));
        }
        if ($this->hasColumn('tax_percent')) {
            $stmt->bindValue(':tax_percent', (float)($this->tax_percent ?? 0));
        }
        if ($this->hasColumn('tax_amount')) {
            $stmt->bindValue(':tax_amount', (float)($this->tax_amount ?? 0));
        }
        if ($this->hasColumn('cgst_amount')) {
            $stmt->bindValue(':cgst_amount', (float)($this->cgst_amount ?? 0));
        }
        if ($this->hasColumn('sgst_amount')) {
            $stmt->bindValue(':sgst_amount', (float)($this->sgst_amount ?? 0));
        }
        if ($this->hasColumn('igst_amount')) {
            $stmt->bindValue(':igst_amount', (float)($this->igst_amount ?? 0));
        }
        if ($this->hasColumn('net_total')) {
            $stmt->bindValue(':net_total', (float)($this->net_total ?? $this->total_price));
        }
        if ($this->hasColumn('customer_phone')) {
            $stmt->bindValue(':customer_phone', $this->customer_phone ?: null);
        }
        if ($this->hasColumn('payment_method')) {
            $stmt->bindValue(':payment_method', $this->payment_method ?: 'Cash');
        }
        if ($this->hasColumn('payment_reference')) {
            $stmt->bindValue(':payment_reference', $this->payment_reference ?: null);
        }
        if ($this->hasColumn('upi_txn_id')) {
            $stmt->bindValue(':upi_txn_id', $this->upi_txn_id ?: null);
        }
        if ($this->hasColumn('card_last4')) {
            $stmt->bindValue(':card_last4', $this->card_last4 ?: null);
        }
        if ($this->hasColumn('card_auth_ref')) {
            $stmt->bindValue(':card_auth_ref', $this->card_auth_ref ?: null);
        }
        if ($this->hasColumn('amount_tendered')) {
            $stmt->bindValue(':amount_tendered', (float)($this->amount_tendered ?? 0));
        }
        if ($this->hasColumn('change_due')) {
            $stmt->bindValue(':change_due', (float)($this->change_due ?? 0));
        }
        if ($this->hasColumn('branch_id')) {
            $stmt->bindValue(':branch_id', (int)($this->branch_id ?? 1), PDO::PARAM_INT);
        }

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Generate unique invoice number
    public function generateInvoiceNumber() {
        $prefix = 'INV-' . date('Ymd') . '-';
        if (!$this->hasColumn('invoice_number')) {
            return $prefix . '0001';
        }
        $query = "SELECT invoice_number FROM {$this->table_name} 
                  WHERE invoice_number LIKE :prefix 
                  ORDER BY id DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $like = $prefix . '%';
        $stmt->bindParam(':prefix', $like);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['invoice_number']) {
            $lastNum = (int)substr($row['invoice_number'], strlen($prefix));
            return $prefix . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        }
        return $prefix . '0001';
    }

    // Read all sales
    public function read() {
        $query = "SELECT s.*, m.name as medicine_name 
                FROM " . $this->table_name . " s
                LEFT JOIN medicines m ON s.medicine_id = m.id
                ORDER BY s.sale_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Read sales by date range
    public function readByDateRange($start_date, $end_date) {
        $query = "SELECT s.*, m.name as medicine_name 
                FROM " . $this->table_name . " s
                LEFT JOIN medicines m ON s.medicine_id = m.id
                WHERE s.sale_date BETWEEN :start_date AND :end_date
                ORDER BY s.sale_date DESC";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":start_date", $start_date);
        $stmt->bindParam(":end_date", $end_date);
        
        $stmt->execute();
        return $stmt;
    }

    // Get total sales amount
    public function getTotalSales() {
        $query = "SELECT SUM(total_price) as total FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    // Get top selling medicines
    public function getTopSellingMedicines($limit = 5) {
        $query = "SELECT m.name, SUM(s.quantity) as total_quantity, SUM(s.total_price) as total_sales
                FROM " . $this->table_name . " s
                LEFT JOIN medicines m ON s.medicine_id = m.id
                GROUP BY m.id, m.name
                ORDER BY total_quantity DESC
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Get daily sales trend for last N days
    public function getDailySalesTrend($days = 30) {
        $query = "SELECT DATE(sale_date) as sale_day, 
                         COUNT(*) as num_transactions, 
                         SUM(quantity) as total_qty, 
                         SUM(total_price) as revenue, 
                         SUM(profit) as profit
                  FROM {$this->table_name}
                  WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                  GROUP BY DATE(sale_date)
                  ORDER BY sale_day ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get hourly sales distribution
    public function getHourlySalesDistribution() {
        $query = "SELECT HOUR(sale_date) as sale_hour, 
                         COUNT(*) as num_transactions, 
                         SUM(total_price) as revenue
                  FROM {$this->table_name}
                  GROUP BY HOUR(sale_date)
                  ORDER BY sale_hour ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get top selling with profit info
    public function getTopSellingWithProfit($limit = 10) {
        $query = "SELECT m.id, m.name, m.sale_price, m.inventory_price,
                         COUNT(s.id) as num_sales,
                         SUM(s.quantity) as total_quantity, 
                         SUM(s.total_price) as total_revenue,
                         SUM(s.profit) as total_profit,
                         AVG(s.quantity) as avg_qty_per_sale,
                         MAX(s.sale_date) as last_sold
                  FROM {$this->table_name} s
                  JOIN medicines m ON s.medicine_id = m.id
                  GROUP BY m.id, m.name, m.sale_price, m.inventory_price
                  ORDER BY total_quantity DESC
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get sales summary stats
    public function getSalesSummary() {
        $query = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(total_price) as total_revenue,
                    SUM(profit) as total_profit,
                    AVG(total_price) as avg_order_value,
                    MAX(total_price) as highest_sale,
                    SUM(quantity) as total_units_sold,
                    COUNT(DISTINCT medicine_id) as unique_medicines,
                    COUNT(DISTINCT DATE(sale_date)) as active_days
                  FROM {$this->table_name}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get monthly comparison (current vs previous month)
    public function getMonthlyComparison() {
        $query = "SELECT 
                    SUM(CASE WHEN YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE()) THEN total_price ELSE 0 END) as current_month_revenue,
                    SUM(CASE WHEN YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE()) THEN profit ELSE 0 END) as current_month_profit,
                    COUNT(CASE WHEN YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE()) THEN 1 END) as current_month_count,
                    SUM(CASE WHEN YEAR(sale_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(sale_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN total_price ELSE 0 END) as prev_month_revenue,
                    SUM(CASE WHEN YEAR(sale_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(sale_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN profit ELSE 0 END) as prev_month_profit,
                    COUNT(CASE WHEN YEAR(sale_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(sale_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN 1 END) as prev_month_count
                  FROM {$this->table_name}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get top customers
    public function getTopCustomers($limit = 10) {
        $query = "SELECT customer_name,
                         COUNT(*) as num_purchases,
                         SUM(quantity) as total_qty,
                         SUM(total_price) as total_spent,
                         MAX(sale_date) as last_purchase
                  FROM {$this->table_name}
                  WHERE customer_name IS NOT NULL AND customer_name != ''
                  GROUP BY customer_name
                  ORDER BY total_spent DESC
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
