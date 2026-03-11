<?php
class User {
    private $conn;
    private $table_name = "users";

    public $user_id;
    public $email;
    public $password_hash;
    public $first_name;
    public $last_name;
    public $role;
    public $phone;
    public $address;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        try {
            $query = "INSERT INTO " . $this->table_name . "
                    (email, password_hash, first_name, last_name, role, phone, address, is_active)
                    VALUES
                    (:email, :password_hash, :first_name, :last_name, :role, :phone, :address, TRUE)";

            $stmt = $this->conn->prepare($query);

            $this->email = htmlspecialchars(strip_tags($this->email));
            $this->first_name = htmlspecialchars(strip_tags($this->first_name));
            $this->last_name = htmlspecialchars(strip_tags($this->last_name));
            $this->role = htmlspecialchars(strip_tags($this->role));
            $this->phone = htmlspecialchars(strip_tags($this->phone));
            $this->address = htmlspecialchars(strip_tags($this->address));

            $stmt->bindParam(":email", $this->email);
            $stmt->bindParam(":password_hash", $this->password_hash);
            $stmt->bindParam(":first_name", $this->first_name);
            $stmt->bindParam(":last_name", $this->last_name);
            $stmt->bindParam(":role", $this->role);
            $stmt->bindParam(":phone", $this->phone);
            $stmt->bindParam(":address", $this->address);

            if($stmt->execute()) {
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }

    public function login($email, $password) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email AND is_active = TRUE";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($password, $row['password_hash'])) {
                return $row;
            }
        }
        return false;
    }

    public function emailExists() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Get all users
    public function readAll() {
        $query = "SELECT user_id, email, first_name, last_name, role, phone, address, is_active, created_at 
                  FROM " . $this->table_name . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get single user
    public function readOne($id) {
        $query = "SELECT user_id, email, first_name, last_name, role, phone, address, is_active, created_at 
                  FROM " . $this->table_name . " WHERE user_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update user details (admin)
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['first_name', 'last_name', 'email', 'role', 'phone', 'address'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = htmlspecialchars(strip_tags($data[$field]));
            }
        }
        if (empty($fields)) return false;

        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $fields) . " WHERE user_id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    // Toggle user active status
    public function toggleActive($id) {
        $query = "UPDATE " . $this->table_name . " SET is_active = NOT is_active WHERE user_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Change password
    public function changePassword($id, $newPasswordHash) {
        $query = "UPDATE " . $this->table_name . " SET password_hash = :hash WHERE user_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":hash", $newPasswordHash);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Verify current password
    public function verifyPassword($id, $password) {
        $query = "SELECT password_hash FROM " . $this->table_name . " WHERE user_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && password_verify($password, $row['password_hash']);
    }

    // Update own profile
    public function updateProfile($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['first_name', 'last_name', 'phone', 'address'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = htmlspecialchars(strip_tags($data[$field]));
            }
        }
        if (empty($fields)) return false;

        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $fields) . " WHERE user_id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    // Count users by role
    public function countByRole() {
        $query = "SELECT role, COUNT(*) as count FROM " . $this->table_name . " GROUP BY role";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
 