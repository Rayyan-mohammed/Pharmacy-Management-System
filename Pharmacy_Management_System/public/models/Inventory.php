<?php
class Inventory {
    private $conn;
    private $table_name = "inventory_logs";

    public $id;
    public $medicine_id;
    public $type;
    public $quantity;
    public $reason;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create inventory log
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                (medicine_id, type, quantity, reason)
                VALUES
                (:medicine_id, :type, :quantity, :reason)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->medicine_id = htmlspecialchars(strip_tags($this->medicine_id));
        $this->type = htmlspecialchars(strip_tags($this->type));
        $this->quantity = htmlspecialchars(strip_tags($this->quantity));
        $this->reason = htmlspecialchars(strip_tags($this->reason));

        // Bind values
        $stmt->bindParam(":medicine_id", $this->medicine_id);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":quantity", $this->quantity);
        $stmt->bindParam(":reason", $this->reason);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Read all inventory logs
    public function read() {
        $query = "SELECT i.*, m.name as medicine_name 
                FROM " . $this->table_name . " i
                LEFT JOIN medicines m ON i.medicine_id = m.id
                ORDER BY i.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Read inventory logs by date range
    public function readByDateRange($start_date, $end_date) {
        $query = "SELECT i.*, m.name as medicine_name 
                FROM " . $this->table_name . " i
                LEFT JOIN medicines m ON i.medicine_id = m.id
                WHERE i.created_at BETWEEN :start_date AND :end_date
                ORDER BY i.created_at DESC";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":start_date", $start_date);
        $stmt->bindParam(":end_date", $end_date);
        
        $stmt->execute();
        return $stmt;
    }

    // Get low stock medicines
    public function getLowStockMedicines($threshold = 10) {
        $query = "SELECT m.*, 
                (SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) 
                FROM " . $this->table_name . " 
                WHERE medicine_id = m.id) as current_stock
                FROM medicines m
                HAVING current_stock <= :threshold
                ORDER BY current_stock ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":threshold", $threshold, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Get medicine stock history
    public function getMedicineStockHistory($medicine_id) {
        $query = "SELECT i.*, m.name as medicine_name 
                FROM " . $this->table_name . " i
                LEFT JOIN medicines m ON i.medicine_id = m.id
                WHERE i.medicine_id = :medicine_id
                ORDER BY i.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":medicine_id", $medicine_id);
        $stmt->execute();
        return $stmt;
    }

    // Get current stock for a medicine
    public function getCurrentStock($medicine_id) {
        $query = "SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as current_stock
                FROM " . $this->table_name . "
                WHERE medicine_id = :medicine_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":medicine_id", $medicine_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['current_stock'] ?? 0;
    }
}
?> 