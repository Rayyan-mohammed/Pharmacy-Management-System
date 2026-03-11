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
    public $sale_date;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new sale
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                (medicine_id, quantity, unit_price, total_price, profit, customer_name)
                VALUES
                (:medicine_id, :quantity, :unit_price, :total_price, :profit, :customer_name)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->medicine_id = htmlspecialchars(strip_tags($this->medicine_id));
        $this->quantity = htmlspecialchars(strip_tags($this->quantity));
        $this->unit_price = htmlspecialchars(strip_tags($this->unit_price));
        $this->total_price = htmlspecialchars(strip_tags($this->total_price));
        $this->profit = htmlspecialchars(strip_tags($this->profit));
        $this->customer_name = htmlspecialchars(strip_tags($this->customer_name));

        // Bind values
        $stmt->bindParam(":medicine_id", $this->medicine_id);
        $stmt->bindParam(":quantity", $this->quantity);
        $stmt->bindParam(":unit_price", $this->unit_price);
        $stmt->bindParam(":total_price", $this->total_price);
        $stmt->bindParam(":profit", $this->profit);
        $stmt->bindParam(":customer_name", $this->customer_name);

        if($stmt->execute()) {
            return true;
        }
        return false;
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
                  GROUP BY m.id, m.name, m.selling_price, m.inventory_price
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
