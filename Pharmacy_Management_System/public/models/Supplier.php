<?php
class Supplier {
    private $conn;
    private $table_name = "suppliers";

    public $id;
    public $name;
    public $contact;
    public $email;
    public $phone;
    public $address;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new supplier
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                (name, contact, email, phone, address)
                VALUES
                (:name, :contact, :email, :phone, :address)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->contact = htmlspecialchars(strip_tags($this->contact));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));

        // Bind values
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":contact", $this->contact);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":address", $this->address);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Read all suppliers
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Read single supplier
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->name = $row['name'];
            $this->contact = $row['contact'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->address = $row['address'];
            return true;
        }
        return false;
    }

    // Update supplier
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET
                    name = :name,
                    contact = :contact,
                    email = :email,
                    phone = :phone,
                    address = :address
                WHERE
                    id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->contact = htmlspecialchars(strip_tags($this->contact));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));

        // Bind values
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":contact", $this->contact);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":address", $this->address);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete supplier
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

    // Search suppliers
    public function search($keywords) {
        $query = "SELECT * FROM " . $this->table_name . "
                WHERE name LIKE ? OR contact LIKE ? OR email LIKE ? OR phone LIKE ?
                ORDER BY name";

        $stmt = $this->conn->prepare($query);

        // Sanitize keywords
        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";

        // Bind keywords
        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);
        $stmt->bindParam(3, $keywords);
        $stmt->bindParam(4, $keywords);

        $stmt->execute();
        return $stmt;
    }
}
?> 