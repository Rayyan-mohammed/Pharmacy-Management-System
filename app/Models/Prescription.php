<?php
class Prescription {
    private $conn;
    private $table_name = "prescriptions";
    private $items_table = "prescription_items";

    public $id;
    public $prescription_code;
    public $patient_name;
    public $prescription_date;
    public $doctor_name;
    public $status;
    public $notes;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new prescription
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                (prescription_code, patient_name, prescription_date, doctor_name, status, notes)
                VALUES
                (:prescription_code, :patient_name, :prescription_date, :doctor_name, :status, :notes)";

        $stmt = $this->conn->prepare($query);

        // Generate Code if not set
        if(empty($this->prescription_code)){
            $this->prescription_code = "RX-" . date("Ymd") . "-" . rand(100,999);
        }

        // Sanitize input
        $this->prescription_code = htmlspecialchars(strip_tags($this->prescription_code));
        $this->patient_name = htmlspecialchars(strip_tags($this->patient_name));
        $this->prescription_date = htmlspecialchars(strip_tags($this->prescription_date));
        $this->doctor_name = htmlspecialchars(strip_tags($this->doctor_name));
            $this->status = htmlspecialchars(strip_tags($this->status));
        $this->notes = htmlspecialchars(strip_tags($this->notes));

        // Bind values
        $stmt->bindParam(":prescription_code", $this->prescription_code);
        $stmt->bindParam(":patient_name", $this->patient_name);
        $stmt->bindParam(":prescription_date", $this->prescription_date);
        $stmt->bindParam(":doctor_name", $this->doctor_name);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":notes", $this->notes);

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
            $this->prescription_code = $row['prescription_code'];
            $this->patient_name = $row['patient_name'];
            $this->prescription_date = $row['prescription_date'];
            $this->doctor_name = $row['doctor_name'];
            $this->status = $row['status'];
            $this->notes = $row['notes'];
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
    public function addMedicine($medicine_id, $quantity, $dosage = '', $instructions = '') {
        $query = "INSERT INTO " . $this->items_table . "
                (prescription_id, medicine_id, quantity, dosage, instructions)
                VALUES
                (:prescription_id, :medicine_id, :quantity, :dosage, :instructions)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $medicine_id = htmlspecialchars(strip_tags($medicine_id));
        $quantity = htmlspecialchars(strip_tags($quantity));
        $dosage = htmlspecialchars(strip_tags($dosage));
        $instructions = htmlspecialchars(strip_tags($instructions));

        // Bind values
        $stmt->bindParam(":prescription_id", $this->id);
        $stmt->bindParam(":medicine_id", $medicine_id);
        $stmt->bindParam(":quantity", $quantity);
        $stmt->bindParam(":dosage", $dosage);
        $stmt->bindParam(":instructions", $instructions);

        return $stmt->execute();
    }

    // Get prescription medicines
    public function getMedicines() {
        $query = "SELECT pi.*, m.name as medicine_name, m.sale_price
                FROM " . $this->items_table . " pi
                LEFT JOIN medicines m ON pi.medicine_id = m.id
                WHERE pi.prescription_id = :prescription_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":prescription_id", $this->id);
        $stmt->execute();
        return $stmt;
    }

    // Ensure status enum supports pending/filled/rejected (idempotent)
    private function ensureStatusEnum() {
        try {
            $alter = "ALTER TABLE " . $this->table_name . " MODIFY status ENUM('pending','filled','rejected') DEFAULT 'pending'";
            $this->conn->exec($alter);
        } catch (Exception $e) {
            // Ignore if not supported or already adjusted
        }
    }

    // Ensure status log table exists (idempotent)
    private function ensureStatusLogTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS prescription_status_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        prescription_id INT NOT NULL,
                        status VARCHAR(50) NOT NULL,
                        changed_by VARCHAR(255) DEFAULT NULL,
                        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX (prescription_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $this->conn->exec($sql);
        } catch (Exception $e) {
            // Silently continue; logging is best-effort
        }
    }

    // Update prescription status with audit trail
    public function updateStatus($status, $changedBy = 'system') {
        $this->ensureStatusEnum();
        $this->ensureStatusLogTable();

        $query = "UPDATE " . $this->table_name . "
                SET status = :status
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $status = htmlspecialchars(strip_tags($status));
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            // Write log
            try {
                $log = "INSERT INTO prescription_status_logs (prescription_id, status, changed_by) VALUES (:pid, :status, :by)";
                $stmtLog = $this->conn->prepare($log);
                $stmtLog->bindParam(":pid", $this->id);
                $stmtLog->bindParam(":status", $status);
                $stmtLog->bindParam(":by", $changedBy);
                $stmtLog->execute();
            } catch (Exception $e) {
                // Ignore logging failure
            }
            return true;
        }
        return false;
    }

    // Fetch status logs (latest first)
    public function getStatusLogs($limit = 10) {
        $this->ensureStatusLogTable();
        $query = "SELECT * FROM prescription_status_logs WHERE prescription_id = :pid ORDER BY changed_at DESC LIMIT :lim";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":pid", $this->id, PDO::PARAM_INT);
        $stmt->bindParam(":lim", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
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
