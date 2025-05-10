<?php
class Prescription {
    private $conn;
    private $table_name = "prescriptions";

    public $id;
    public $patient_name;
    public $prescription_date;
    public $doctor_name;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new prescription
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                (patient_name, prescription_date, doctor_name, status)
                VALUES
                (:patient_name, :prescription_date, :doctor_name, :status)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->patient_name = htmlspecialchars(strip_tags($this->patient_name));
        $this->prescription_date = htmlspecialchars(strip_tags($this->prescription_date));
        $this->doctor_name = htmlspecialchars(strip_tags($this->doctor_name));
        $this->status = htmlspecialchars(strip_tags($this->status));

        // Bind values
        $stmt->bindParam(":patient_name", $this->patient_name);
        $stmt->bindParam(":prescription_date", $this->prescription_date);
        $stmt->bindParam(":doctor_name", $this->doctor_name);
        $stmt->bindParam(":status", $this->status);

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Read all prescriptions
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY prescription_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Read single prescription
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->patient_name = $row['patient_name'];
            $this->prescription_date = $row['prescription_date'];
            $this->doctor_name = $row['doctor_name'];
            $this->status = $row['status'];
            return true;
        }
        return false;
    }

    // Update prescription
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET
                    patient_name = :patient_name,
                    prescription_date = :prescription_date,
                    doctor_name = :doctor_name,
                    status = :status
                WHERE
                    id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->patient_name = htmlspecialchars(strip_tags($this->patient_name));
        $this->prescription_date = htmlspecialchars(strip_tags($this->prescription_date));
        $this->doctor_name = htmlspecialchars(strip_tags($this->doctor_name));
        $this->status = htmlspecialchars(strip_tags($this->status));

        // Bind values
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":patient_name", $this->patient_name);
        $stmt->bindParam(":prescription_date", $this->prescription_date);
        $stmt->bindParam(":doctor_name", $this->doctor_name);
        $stmt->bindParam(":status", $this->status);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete prescription
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

    // Add medicine to prescription
    public function addMedicine($medicine_id, $quantity) {
        $query = "INSERT INTO prescription_medicines
                (prescription_id, medicine_id, quantity)
                VALUES
                (:prescription_id, :medicine_id, :quantity)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $medicine_id = htmlspecialchars(strip_tags($medicine_id));
        $quantity = htmlspecialchars(strip_tags($quantity));

        // Bind values
        $stmt->bindParam(":prescription_id", $this->id);
        $stmt->bindParam(":medicine_id", $medicine_id);
        $stmt->bindParam(":quantity", $quantity);

        return $stmt->execute();
    }

    // Get prescription medicines
    public function getMedicines() {
        $query = "SELECT pm.*, m.name as medicine_name, m.sale_price
                FROM prescription_medicines pm
                LEFT JOIN medicines m ON pm.medicine_id = m.id
                WHERE pm.prescription_id = :prescription_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":prescription_id", $this->id);
        $stmt->execute();
        return $stmt;
    }

    // Update prescription status
    public function updateStatus($status) {
        $query = "UPDATE " . $this->table_name . "
                SET status = :status
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $status = htmlspecialchars(strip_tags($status));
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    // Search prescriptions
    public function search($keywords) {
        $query = "SELECT * FROM " . $this->table_name . "
                WHERE patient_name LIKE ? OR doctor_name LIKE ?
                ORDER BY prescription_date DESC";

        $stmt = $this->conn->prepare($query);

        // Sanitize keywords
        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";

        // Bind keywords
        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);

        $stmt->execute();
        return $stmt;
    }
}
?> 