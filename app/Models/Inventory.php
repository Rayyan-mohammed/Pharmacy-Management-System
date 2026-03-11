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
        // This method is kept for backward compatibility but adjustStock should be preferred
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

    // Adjust stock atomically (Log + Update Batches + Update Medicine Total)
    public function adjustStock($medicine_id, $quantity, $type, $reason, $batch_number = null, $expiration_date = null) {
        // Check if transaction is already active
        $transactionStartedLocal = false;
        if (!$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
            $transactionStartedLocal = true;
        }

        try {
            // 1. Create Log
            $logQuery = "INSERT INTO " . $this->table_name . " (medicine_id, type, quantity, reason) VALUES (:medicine_id, :type, :quantity, :reason)";
            $stmt = $this->conn->prepare($logQuery);
            $stmt->bindParam(":medicine_id", $medicine_id);
            $stmt->bindParam(":type", $type);
            $stmt->bindParam(":quantity", $quantity);
            $stmt->bindParam(":reason", $reason);
            $stmt->execute();

            // 2. Handle Batches
            if ($type == 'in') {
                // Adding Stock: Require Batch info or use 'General'
                $batch_number = $batch_number ?? 'GENERAL-' . date('Ymd');
                $expiration_date = $expiration_date ?? date('Y-m-d', strtotime('+1 year')); // Default 1 year if missing
                
                // Check if batch exists for this medicine
                $checkBatch = "SELECT id, quantity FROM medicine_batches WHERE medicine_id = :mid AND batch_number = :bn";
                $stmtCheck = $this->conn->prepare($checkBatch);
                $stmtCheck->bindParam(':mid', $medicine_id);
                $stmtCheck->bindParam(':bn', $batch_number);
                $stmtCheck->execute();
                
                if ($row = $stmtCheck->fetch(PDO::FETCH_ASSOC)) {
                    // Update existing batch
                    $updateBatch = "UPDATE medicine_batches SET quantity = quantity + :qty WHERE id = :id";
                    $stmtup = $this->conn->prepare($updateBatch);
                    $stmtup->bindParam(':qty', $quantity);
                    $stmtup->bindParam(':id', $row['id']);
                    $stmtup->execute();
                } else {
                    // Create new batch
                    $insertBatch = "INSERT INTO medicine_batches (medicine_id, batch_number, expiration_date, quantity) VALUES (:mid, :bn, :exp, :qty)";
                    $stmtins = $this->conn->prepare($insertBatch);
                    $stmtins->bindParam(':mid', $medicine_id);
                    $stmtins->bindParam(':bn', $batch_number);
                    $stmtins->bindParam(':exp', $expiration_date);
                    $stmtins->bindParam(':qty', $quantity);
                    $stmtins->execute();
                }

            } else {
                // Removing Stock (out) - FIFO Logic if batch not specified
                // Or if batch specified, deduct from it.
                // For Sales, we usually don't specify batch, so FIFO.
                // For Corrections, we might.
                
                $remaining_to_deduct = $quantity;

                if ($batch_number) {
                     // Specific Batch Deduction
                     $checkBatch = "SELECT id, quantity FROM medicine_batches WHERE medicine_id = :mid AND batch_number = :bn";
                     $stmtCheck = $this->conn->prepare($checkBatch);
                     $stmtCheck->bindParam(':mid', $medicine_id);
                     $stmtCheck->bindParam(':bn', $batch_number);
                     $stmtCheck->execute();
                     $batch = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                     
                     if (!$batch || $batch['quantity'] < $quantity) {
                         throw new Exception("Insufficient stock in batch " . $batch_number);
                     }
                     
                     $updateBatch = "UPDATE medicine_batches SET quantity = quantity - :qty WHERE id = :id";
                     $stmtup = $this->conn->prepare($updateBatch);
                     $stmtup->bindParam(':qty', $quantity);
                     $stmtup->bindParam(':id', $batch['id']);
                     $stmtup->execute();

                } else {
                    // FIFO Deduction
                    // Get batches ordered by expiration date (earliest first)
                    $getBatches = "SELECT id, quantity FROM medicine_batches WHERE medicine_id = :mid AND quantity > 0 ORDER BY expiration_date ASC";
                    $stmtBatches = $this->conn->prepare($getBatches);
                    $stmtBatches->bindParam(':mid', $medicine_id);
                    $stmtBatches->execute();
                    $batches = $stmtBatches->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($batches as $batch) {
                        if ($remaining_to_deduct <= 0) break;

                        $deduct = min($batch['quantity'], $remaining_to_deduct);
                        
                        $updateBatch = "UPDATE medicine_batches SET quantity = quantity - :deduct WHERE id = :id";
                        $stmtup = $this->conn->prepare($updateBatch);
                        $stmtup->bindParam(':deduct', $deduct);
                        $stmtup->bindParam(':id', $batch['id']);
                        $stmtup->execute();

                        $remaining_to_deduct -= $deduct;
                    }

                    if ($remaining_to_deduct > 0) {
                        throw new Exception("Insufficient stock across all batches.");
                    }
                }
            }

            // 3. Update Medicine Total Stock (Truth source)
            // Re-calculate total from batches to ensure consistency
            $sumQuery = "SELECT SUM(quantity) as total FROM medicine_batches WHERE medicine_id = :mid";
            $stmtSum = $this->conn->prepare($sumQuery);
            $stmtSum->bindParam(':mid', $medicine_id);
            $stmtSum->execute();
            $rowSum = $stmtSum->fetch(PDO::FETCH_ASSOC);
            $newTotal = $rowSum['total'] ?? 0;

            $updateMedicine = "UPDATE medicines SET stock = :total WHERE id = :mid";
            $stmtMed = $this->conn->prepare($updateMedicine);
            $stmtMed->bindParam(':total', $newTotal);
            $stmtMed->bindParam(':mid', $medicine_id);
            $stmtMed->execute();

            if ($transactionStartedLocal) {
                $this->conn->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($transactionStartedLocal) {
                $this->conn->rollBack();
            }
            // Log error or rethrow
            error_log("Inventory Error: " . $e->getMessage());
            return false;
        }
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
        $query = "SELECT m.*, m.stock as current_stock
                FROM medicines m
                WHERE m.stock <= :threshold
                ORDER BY m.stock ASC";
        
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
        $query = "SELECT COALESCE(SUM(quantity), 0) as current_stock
                FROM medicine_batches
                WHERE medicine_id = :medicine_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":medicine_id", $medicine_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['current_stock'] ?? 0;
    }
}
