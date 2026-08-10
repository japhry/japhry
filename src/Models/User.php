<?php
namespace App\Models;

use PDO;
use App\Utils\Database;

class User {
    private $pdo;

    public $id;
    public $branch_id;
    public $username;
    public $password_hash;
    public $email;
    public $full_name;
    public $role;
    public $is_active;
    public $last_login_at;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findByUsername(string $username) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Populate object properties for current instance if needed for other methods
            $this->id = $user['id'];
            $this->branch_id = $user['branch_id'];
            $this->username = $user['username'];
            $this->password_hash = $user['password_hash'];
            $this->email = $user['email'];
            $this->full_name = $user['full_name'];
            $this->role = $user['role'];
            $this->is_active = $user['is_active'];
            $this->last_login_at = $user['last_login_at'];
            $this->created_at = $user['created_at'];
            $this->updated_at = $user['updated_at'];
            return $this;
        }
        return null;
    }

    // Overload or specific method to return array data for forms etc.
    public function findById(int $id, bool $returnArray = false) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($returnArray) {
                return $user;
            }
            // Populate object properties
            $this->id = $user['id'];
            $this->branch_id = $user['branch_id'];
            $this->username = $user['username'];
            $this->password_hash = $user['password_hash'];
            $this->email = $user['email'];
            $this->full_name = $user['full_name'];
            $this->role = $user['role'];
            $this->is_active = $user['is_active'];
            $this->last_login_at = $user['last_login_at'];
            $this->created_at = $user['created_at'];
            $this->updated_at = $user['updated_at'];
            return $this;
        }
        return null;
    }

    public function verifyPassword(string $password): bool {
        if ($this->password_hash) {
            return password_verify($password, $this->password_hash);
        }
        return false;
    }

    public function create(array $data): ?int {
        if (empty($data['username']) || empty($data['password']) || empty($data['email']) || empty($data['role'])) {
            error_log("User creation failed: Missing required fields. Username: {$data['username']}, Email: {$data['email']}, Role: {$data['role']}");
            return null;
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        if (!$hashedPassword) {
            error_log("User creation failed: Password hashing failed for user {$data['username']}.");
            return null;
        }

        $sql = "INSERT INTO users (branch_id, username, password_hash, email, full_name, role, is_active)
                VALUES (:branch_id, :username, :password_hash, :email, :full_name, :role, :is_active)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':branch_id', $data['branch_id'] ? (int)$data['branch_id'] : null, PDO::PARAM_INT_OR_NULL);
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':password_hash', $hashedPassword);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindValue(':full_name', $data['full_name'] ?? null);
        $stmt->bindParam(':role', $data['role']);
        $stmt->bindValue(':is_active', $data['is_active'] ?? 1, PDO::PARAM_INT);

        try {
            $stmt->execute();
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            error_log("User creation DB error for {$data['username']}: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
            return null;
        }
    }

    public function updateUser(int $userId, array $data): bool {
        if (empty($data['email']) || empty($data['role'])) {
            error_log("User update failed for ID {$userId}: Email and role are required.");
            return false;
        }

        $fields = [
            'email' => $data['email'],
            'full_name' => $data['full_name'] ?? null,
            'role' => $data['role'],
            'branch_id' => isset($data['branch_id']) ? (empty($data['branch_id']) ? null : (int)$data['branch_id']) : null, // Handle empty string for branch_id
            'is_active' => $data['is_active'] ?? 1,
        ];

        // Conditionally add password to update if provided
        if (!empty($data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            if (!$hashedPassword) {
                error_log("User update failed for ID {$userId}: Password hashing failed.");
                return false;
            }
            $fields['password_hash'] = $hashedPassword;
        }

        // Build the SET part of the SQL query
        $setClauses = [];
        foreach (array_keys($fields) as $field) {
            $setClauses[] = "{$field} = :{$field}";
        }
        $setSql = implode(', ', $setClauses);

        $sql = "UPDATE users SET {$setSql} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);

        // Bind values
        foreach ($fields as $field => $value) {
            if ($field === 'branch_id') {
                 $stmt->bindValue(":$field", $value, PDO::PARAM_INT_OR_NULL);
            } elseif ($field === 'is_active'){
                 $stmt->bindValue(":$field", $value, PDO::PARAM_INT);
            }
             else {
                $stmt->bindValue(":$field", $value);
            }
        }
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);

        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("User update DB error for ID {$userId}: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
            return false;
        }
    }

    public function updateLastLogin(int $userId): bool {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Failed to update last login for user ID {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function getAllUsers(): array {
        // Consider adding branch name via JOIN for easier display
        $stmt = $this->pdo->query("SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id ORDER BY u.username ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUsersByBranch(int $branchId): array {
        $stmt = $this->pdo->prepare("SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.branch_id = :branch_id ORDER BY u.username ASC");
        $stmt->bindParam(':branch_id', $branchId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Delete user - consider soft delete (is_active = 0) instead of hard delete
    // public function deleteUser(int $userId): bool { ... }

}
?>
