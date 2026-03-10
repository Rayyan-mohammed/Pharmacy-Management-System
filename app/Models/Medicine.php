<?php
class Medicine {
    private $conn;
    private $table_name = "medicines";

    public $id;
    public $name;
    public $category_id;
    public $description;
    public $inventory_price;
    public $sale_price;
    public $stock;
    public $prescription_needed;
    public $expiration_date;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new medicine
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                (name, category_id, description, inventory_price, sale_price, stock, prescription_needed, expiration_date)
                VALUES
                (:name, :category_id, :description, :inventory_price, :sale_price, :stock, :prescription_needed, :expiration_date)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->category_id = !empty($this->category_id) ? htmlspecialchars(strip_tags($this->category_id)) : null;
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->inventory_price = htmlspecialchars(strip_tags($this->inventory_price));
        $this->sale_price = htmlspecialchars(strip_tags($this->sale_price));
        $this->stock = htmlspecialchars(strip_tags($this->stock));
        $this->prescription_needed = htmlspecialchars(strip_tags($this->prescription_needed));
        $this->expiration_date = htmlspecialchars(strip_tags($this->expiration_date));

        // Bind values
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":inventory_price", $this->inventory_price);
        $stmt->bindParam(":sale_price", $this->sale_price);
        $stmt->bindParam(":stock", $this->stock);
        $stmt->bindParam(":prescription_needed", $this->prescription_needed);
        $stmt->bindParam(":expiration_date", $this->expiration_date);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // Add Batch
    public function addBatch($batch_number, $quantity, $expiration_date) {
        $query = "INSERT INTO medicine_batches (medicine_id, batch_number, quantity, expiration_date) 
                  VALUES (:medicine_id, :batch_number, :quantity, :expiration_date)";
        $stmt = $this->conn->prepare($query);
        
        $batch_number = htmlspecialchars(strip_tags($batch_number));
        $quantity = htmlspecialchars(strip_tags($quantity));
        $expiration_date = htmlspecialchars(strip_tags($expiration_date));

        $stmt->bindParam(":medicine_id", $this->id);
        $stmt->bindParam(":batch_number", $batch_number);
        $stmt->bindParam(":quantity", $quantity);
        $stmt->bindParam(":expiration_date", $expiration_date);

        return $stmt->execute();
    }


    // Get all categories
    public function getCategories() {
        $query = "SELECT * FROM medicine_categories ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get simple dashboard statistics
    public function getDashboardStats() {
        $stats = [];
        
        // Total Medicines
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        try {
            $stmt->execute();
            $stats['total_medicines'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) { $stats['total_medicines'] = 0; }

        // Low Stock (< 10)
        // Group batches by medicine and count those with sum < 10 or no batches (if we include 0 stock)
        // A simpler approach for dashboard is just checking those with existing batches sum < 10
        $query = "SELECT COUNT(*) as count FROM (
                    SELECT m.id, COALESCE(SUM(mb.quantity), 0) as total_stock
                    FROM medicines m
                    LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
                    GROUP BY m.id
                    HAVING total_stock < 10
                  ) as low_stock_table";
        $stmt = $this->conn->prepare($query);
        try {
            $stmt->execute();
            $stats['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) { $stats['low_stock'] = 0; }

        // Expired (Batches expired today or past with quantity > 0)
        try {
            $query = "SELECT COUNT(*) as count FROM medicine_batches 
                    WHERE expiration_date <= CURDATE() AND quantity > 0";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['expired'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) { 
             // Fallback to checking expiration_date in medicines table if batch table fails or empty 
             $queryFallback = "SELECT COUNT(*) as count FROM medicines WHERE expiration_date <= CURDATE()";
             try {
                $stmtFallback = $this->conn->prepare($queryFallback);
                $stmtFallback->execute();
                $stats['expired'] = $stmtFallback->fetch(PDO::FETCH_ASSOC)['count'];
             } catch(Exception $ex) { $stats['expired'] = 0; }
        }

        return $stats;
    }

    // Get sales data for charts (last 7 days)
    public function getSalesChartData() {
        $query = "SELECT DATE(sale_date) as date, SUM(total_price) as total 
                  FROM sales 
                  WHERE sale_date >= DATE(NOW()) - INTERVAL 7 DAY 
                  GROUP BY DATE(sale_date) 
                  ORDER BY date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get low stock items details
    public function getLowStockItems($limit = 5) {
        $query = "SELECT m.name, COALESCE(SUM(mb.quantity), 0) as stock 
                  FROM " . $this->table_name . " m
                  LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
                  GROUP BY m.id
                  HAVING stock < 10
                  ORDER BY stock ASC 
                  LIMIT " . (int)$limit;
        
        $stmt = $this->conn->prepare($query);
        try {
            $stmt->execute();
        } catch (PDOException $e) { return []; }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Read all medicines with calculated stock
    public function read() {
        $query = "SELECT m.*, COALESCE(SUM(mb.quantity), 0) as current_stock 
                  FROM " . $this->table_name . " m
                  LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
                  GROUP BY m.id
                  ORDER BY m.name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Read single medicine with aggregated stock
    public function readOne() {
        $query = "SELECT m.*, COALESCE(SUM(mb.quantity), 0) as current_stock 
                  FROM " . $this->table_name . " m
                  LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
                  WHERE m.id = ? 
                  GROUP BY m.id
                  LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->name = $row['name'];
            $this->inventory_price = $row['inventory_price'];
            $this->sale_price = $row['sale_price'];
            $this->stock = $row['current_stock']; // Use aggregated stock
            $this->prescription_needed = $row['prescription_needed'];
            // Expiration date from medicine table is likely not useful here if we have batches
            // But for compatibility let's keep it or pick the nearest batch expiry?
            // For now, keep it as is from DB (null or whatever)
            $this->expiration_date = $row['expiration_date']; 
            return true;
        }
        return false;
    }

    // Update medicine
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET
                    name = :name,
                    inventory_price = :inventory_price,
                    sale_price = :sale_price,
                    stock = :stock,
                    prescription_needed = :prescription_needed,
                    expiration_date = :expiration_date
                WHERE
                    id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->inventory_price = htmlspecialchars(strip_tags($this->inventory_price));
        $this->sale_price = htmlspecialchars(strip_tags($this->sale_price));
        $this->stock = htmlspecialchars(strip_tags($this->stock));
        $this->prescription_needed = htmlspecialchars(strip_tags($this->prescription_needed));
        $this->expiration_date = htmlspecialchars(strip_tags($this->expiration_date));

        // Bind values
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":inventory_price", $this->inventory_price);
        $stmt->bindParam(":sale_price", $this->sale_price);
        $stmt->bindParam(":stock", $this->stock);
        $stmt->bindParam(":prescription_needed", $this->prescription_needed);
        $stmt->bindParam(":expiration_date", $this->expiration_date);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete medicine
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Get expiring medicines (From Batches)
    public function getExpiringMedicines($days_threshold = 30) {
        $query = "SELECT mb.medicine_id, mb.batch_number, mb.expiration_date, mb.quantity, m.name, m.inventory_price 
                  FROM medicine_batches mb
                  JOIN medicines m ON mb.medicine_id = m.id
                  WHERE mb.quantity > 0 
                  AND mb.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
                  ORDER BY mb.expiration_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":days", $days_threshold, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Get expired medicines
    public function getExpiredMedicines() {
        $query = "SELECT mb.medicine_id, mb.batch_number, mb.expiration_date, mb.quantity, m.name, m.inventory_price 
                  FROM medicine_batches mb
                  JOIN medicines m ON mb.medicine_id = m.id
                  WHERE mb.quantity > 0 
                  AND mb.expiration_date < CURDATE()
                  ORDER BY mb.expiration_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}
