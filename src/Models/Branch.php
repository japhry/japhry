<?php
namespace App\Models;

use PDO;
use App\Utils\Database;

class Branch {
    private $pdo;

    public $id;
    public $name;
    public $address;
    public $phone;
    public $email;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getAll(): array {
        $stmt = $this->pdo->query("SELECT * FROM branches ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?self {
        $stmt = $this->pdo->prepare("SELECT * FROM branches WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $branchData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($branchData) {
            $branch = new self();
            $branch->id = $branchData['id'];
            $branch->name = $branchData['name'];
            $branch->address = $branchData['address'];
            $branch->phone = $branchData['phone'];
            $branch->email = $branchData['email'];
            $branch->created_at = $branchData['created_at'];
            $branch->updated_at = $branchData['updated_at'];
            return $branch;
        }
        return null;
    }

    public function create(array $data): ?int {
        if (empty($data['name'])) {
            // error_log("Branch creation failed: Name is required.");
            return null;
        }

        $sql = "INSERT INTO branches (name, address, phone, email)
                VALUES (:name, :address, :phone, :email)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindParam(':name', $data['name']);
        $stmt->bindValue(':address', $data['address'] ?? null);
        $stmt->bindValue(':phone', $data['phone'] ?? null);
        $stmt->bindValue(':email', $data['email'] ?? null);

        try {
            $stmt->execute();
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            // Log error, handle duplicate name, etc.
            // error_log("Branch creation DB error: " . $e->getMessage());
            if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., duplicate name)
                // Handle duplicate name specifically if needed
            }
            return null;
        }
    }

    public function update(int $id, array $data): bool {
        if (empty($data['name'])) {
            // error_log("Branch update failed: Name is required for branch ID {$id}.");
            return false;
        }

        $sql = "UPDATE branches SET
                name = :name,
                address = :address,
                phone = :phone,
                email = :email
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindValue(':address', $data['address'] ?? null);
        $stmt->bindValue(':phone', $data['phone'] ?? null);
        $stmt->bindValue(':email', $data['email'] ?? null);

        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            // Log error
            // error_log("Branch update DB error for ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool {
        // Consider constraints: what happens to users/job cards associated with this branch?
        // The schema uses ON DELETE SET NULL or ON DELETE RESTRICT for some FKs.
        // For users, branch_id becomes NULL. For job_cards, it's more complex (preserved).
        // Soft delete (is_active flag) might be better for branches.
        // For now, a hard delete:
        try {
            $stmt = $this->pdo->prepare("DELETE FROM branches WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            // error_log("Branch deletion DB error for ID {$id}: " . $e->getMessage());
            // If there are foreign key constraints preventing deletion, this will fail.
            return false;
        }
    }
}
?>
