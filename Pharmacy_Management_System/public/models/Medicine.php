<?php
class Medicine {
    private $conn;
    private $table_name = "medicines";

    public $id;
    public $name;
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
                (name, inventory_price, sale_price, stock, prescription_needed, expiration_date)
                VALUES
                (:name, :inventory_price, :sale_price, :stock, :prescription_needed, :expiration_date)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->inventory_price = htmlspecialchars(strip_tags($this->inventory_price));
        $this->sale_price = htmlspecialchars(strip_tags($this->sale_price));
        $this->stock = htmlspecialchars(strip_tags($this->stock));
        $this->prescription_needed = htmlspecialchars(strip_tags($this->prescription_needed));
        $this->expiration_date = htmlspecialchars(strip_tags($this->expiration_date));

        // Bind values
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

    // Read all medicines
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Read single medicine
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->name = $row['name'];
            $this->inventory_price = $row['inventory_price'];
            $this->sale_price = $row['sale_price'];
            $this->stock = $row['stock'];
            $this->prescription_needed = $row['prescription_needed'];
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

    // Get expiring medicines
    public function getExpiringMedicines($days_threshold = 30) {
        $query = "SELECT * FROM " . $this->table_name . "
                WHERE expiration_date IS NOT NULL
                AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
                ORDER BY expiration_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":days", $days_threshold, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Get expired medicines
    public function getExpiredMedicines() {
        $query = "SELECT * FROM " . $this->table_name . "
                WHERE expiration_date IS NOT NULL
                AND expiration_date < CURDATE()
                ORDER BY expiration_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}
?> 