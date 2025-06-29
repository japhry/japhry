<?php
namespace App\Models;

use PDO;
use App\Utils\Database;

class Customer {
    private $pdo;

    public $id;
    public $user_id; // Optional link to a user account
    public $full_name;
    public $phone;
    public $email;
    public $address;
    public $company_name;
    public $tin_number;
    public $vrn_number;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getAll(int $limit = 100, int $offset = 0): array {
        $stmt = $this->pdo->prepare("SELECT * FROM customers ORDER BY full_name ASC LIMIT :limit OFFSET :offset");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?self {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $customer = new self();
            foreach ($data as $key => $value) {
                if (property_exists($customer, $key)) {
                    $customer->$key = $value;
                }
            }
            return $customer;
        }
        return null;
    }

    public function findByEmail(string $email): ?self {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $customer = new self();
            foreach ($data as $key => $value) {
                if (property_exists($customer, $key)) {
                    $customer->$key = $value;
                }
            }
            return $customer;
        }
        return null;
    }


    public function create(array $data): ?int {
        if (empty($data['full_name'])) {
            error_log("Customer creation failed: Full name is required.");
            return null;
        }
        // Basic email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            error_log("Customer creation failed: Invalid email format for {$data['email']}.");
            return null;
        }

        $sql = "INSERT INTO customers (user_id, full_name, phone, email, address, company_name, tin_number, vrn_number)
                VALUES (:user_id, :full_name, :phone, :email, :address, :company_name, :tin_number, :vrn_number)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':user_id', !empty($data['user_id']) ? (int)$data['user_id'] : null, PDO::PARAM_INT_OR_NULL);
        $stmt->bindParam(':full_name', $data['full_name']);
        $stmt->bindValue(':phone', $data['phone'] ?? null);
        $stmt->bindValue(':email', $data['email'] ?? null);
        $stmt->bindValue(':address', $data['address'] ?? null);
        $stmt->bindValue(':company_name', $data['company_name'] ?? null);
        $stmt->bindValue(':tin_number', $data['tin_number'] ?? null);
        $stmt->bindValue(':vrn_number', $data['vrn_number'] ?? null);

        try {
            $stmt->execute();
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Customer creation DB error: " . $e->getMessage() . " (Code: {$e->getCode()})");
            // Code 23000 is integrity constraint violation (e.g. duplicate email if unique constraint exists)
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'customer_email_unique') !== false) {
                // Specific error for duplicate email
            }
            return null;
        }
    }

    public function update(int $id, array $data): bool {
        if (empty($data['full_name'])) {
            error_log("Customer update failed for ID {$id}: Full name is required.");
            return false;
        }
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            error_log("Customer update failed for ID {$id}: Invalid email format.");
            return false;
        }

        // Construct SQL query dynamically based on provided fields
        $fieldsToUpdate = [];
        $allowedFields = ['user_id', 'full_name', 'phone', 'email', 'address', 'company_name', 'tin_number', 'vrn_number'];
        foreach($allowedFields as $field) {
            if (isset($data[$field])) {
                $fieldsToUpdate[$field] = $data[$field];
            }
        }

        if (empty($fieldsToUpdate)) {
            error_log("Customer update for ID {$id}: No data provided for update.");
            return true; // Or false, depending on desired behavior for no-op updates
        }

        $setClauses = [];
        foreach (array_keys($fieldsToUpdate) as $field) {
            $setClauses[] = "{$field} = :{$field}";
        }
        $setSql = implode(', ', $setClauses);

        $sql = "UPDATE customers SET {$setSql} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        foreach ($fieldsToUpdate as $field => $value) {
             if ($field === 'user_id') {
                 $stmt->bindValue(":$field", $value ? (int)$value : null, PDO::PARAM_INT_OR_NULL);
             } else {
                $stmt->bindValue(":$field", $value);
             }
        }

        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Customer update DB error for ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool {
        // Check for associated vehicles first? Or rely on DB constraints (ON DELETE CASCADE for vehicles)
        // Current schema: vehicles_customer_id_fk FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
        // So deleting a customer will delete their vehicles. This might be desired or not.
        // For now, we proceed with hard delete.
        try {
            $stmt = $this->pdo->prepare("DELETE FROM customers WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Customer deletion DB error for ID {$id}: " . $e->getMessage());
            // This could fail if other tables have RESTRICT constraints on customer_id
            return false;
        }
    }

    public function searchByNameOrPhone(string $searchTerm): array {
        $searchTermLike = "%" . $searchTerm . "%";
        $stmt = $this->pdo->prepare(
            "SELECT id, full_name, phone, email, company_name
             FROM customers
             WHERE full_name LIKE :term
                OR phone LIKE :term
                OR company_name LIKE :term
                OR email LIKE :term
             ORDER BY full_name ASC
             LIMIT 20"
        );
        $stmt->bindParam(':term', $searchTermLike);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
