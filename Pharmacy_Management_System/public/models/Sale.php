<?php
class Sale {
    private $conn;
    private $table_name = "sales";

    public $id;
    public $medicine_id;
    public $quantity;
    public $total_price;
    public $customer_name;
    public $sale_date;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new sale
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                (medicine_id, quantity, total_price, customer_name)
                VALUES
                (:medicine_id, :quantity, :total_price, :customer_name)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->medicine_id = htmlspecialchars(strip_tags($this->medicine_id));
        $this->quantity = htmlspecialchars(strip_tags($this->quantity));
        $this->total_price = htmlspecialchars(strip_tags($this->total_price));
        $this->customer_name = htmlspecialchars(strip_tags($this->customer_name));

        // Bind values
        $stmt->bindParam(":medicine_id", $this->medicine_id);
        $stmt->bindParam(":quantity", $this->quantity);
        $stmt->bindParam(":total_price", $this->total_price);
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
}
?> 