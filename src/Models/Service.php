<?php
namespace App\Models;

use PDO;
use App\Utils\Database;

class Service {
    private $pdo;

    public $id;
    public $name;
    public $description;
    public $default_price;
    public $estimated_time_hours;
    public $is_active;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getAll(bool $activeOnly = false): array {
        $sql = "SELECT * FROM services";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY name ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?self {
        $stmt = $this->pdo->prepare("SELECT * FROM services WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $service = new self();
            foreach ($data as $key => $value) {
                if (property_exists($service, $key)) {
                    $service->$key = $value;
                }
            }
            return $service;
        }
        return null;
    }

    public function findByName(string $name): ?self {
        $stmt = $this->pdo->prepare("SELECT * FROM services WHERE name = :name");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
         if ($data) {
            $service = new self();
            foreach ($data as $key => $value) {
                if (property_exists($service, $key)) {
                    $service->$key = $value;
                }
            }
            return $service;
        }
        return null;
    }


    public function create(array $data): ?int {
        if (empty($data['name']) || !isset($data['default_price'])) {
            error_log("Service creation failed: Name and default price are required.");
            return null;
        }
        if (!is_numeric($data['default_price']) || $data['default_price'] < 0) {
            error_log("Service creation failed: Default price must be a non-negative number.");
            return null;
        }
         if (isset($data['estimated_time_hours']) && (!is_numeric($data['estimated_time_hours']) || $data['estimated_time_hours'] < 0)) {
            error_log("Service creation failed: Estimated time must be a non-negative number if provided.");
            return null;
        }

        $sql = "INSERT INTO services (name, description, default_price, estimated_time_hours, is_active)
                VALUES (:name, :description, :default_price, :estimated_time_hours, :is_active)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindParam(':name', $data['name']);
        $stmt->bindValue(':description', $data['description'] ?? null);
        $stmt->bindValue(':default_price', $data['default_price']);
        $stmt->bindValue(':estimated_time_hours', $data['estimated_time_hours'] ?? null);
        $stmt->bindValue(':is_active', $data['is_active'] ?? 1, PDO::PARAM_INT);

        try {
            $stmt->execute();
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Service creation DB error: " . $e->getMessage() . " (Code: {$e->getCode()})");
            // Code 23000 for unique constraint on name
            return null;
        }
    }

    public function update(int $id, array $data): bool {
        if (empty($data['name']) || !isset($data['default_price'])) {
            error_log("Service update failed for ID {$id}: Name and default price are required.");
            return false;
        }
        if (!is_numeric($data['default_price']) || $data['default_price'] < 0) {
            error_log("Service update failed for ID {$id}: Default price must be a non-negative number.");
            return false;
        }
        if (isset($data['estimated_time_hours']) && $data['estimated_time_hours'] !== null && (!is_numeric($data['estimated_time_hours']) || $data['estimated_time_hours'] < 0)) {
            error_log("Service update failed for ID {$id}: Estimated time must be a non-negative number if provided.");
            return false;
        }

        $sql = "UPDATE services SET
                    name = :name,
                    description = :description,
                    default_price = :default_price,
                    estimated_time_hours = :estimated_time_hours,
                    is_active = :is_active
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindValue(':description', $data['description'] ?? null);
        $stmt->bindValue(':default_price', $data['default_price']);
        $stmt->bindValue(':estimated_time_hours', $data['estimated_time_hours'] ?? null);
        $stmt->bindValue(':is_active', $data['is_active'] ?? 1, PDO::PARAM_INT);

        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Service update DB error for ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool {
        // Check if this service is used in job_card_services.
        // Schema: jcs_service_id_fk FOREIGN KEY (service_id) REFERENCES services (id) ON UPDATE CASCADE
        // This means service ID changes would cascade, but deletion is RESTRICT by default.
        // So, if a service is on a job card, it cannot be deleted directly.
        // A soft delete (is_active = 0) is much safer.
        // For now, let's try a hard delete and see if it's blocked by FK.
        // A better approach would be to check first or only allow soft delete.

        // To implement soft delete, you'd change `is_active` to 0 via an UPDATE query.
        // e.g., $this->update($id, ['is_active' => 0, ... other current fields to satisfy update method constraints]);
        // For now, actual delete:
        try {
            // First, check if the service is in use.
            $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM job_card_services WHERE service_id = :service_id");
            $checkStmt->bindParam(':service_id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            if ($checkStmt->fetchColumn() > 0) {
                error_log("Attempt to delete service ID {$id} failed: Service is in use on job cards. Consider deactivating instead.");
                return false; // Cannot delete if in use
            }

            $stmt = $this->pdo->prepare("DELETE FROM services WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Service deletion DB error for ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function searchByName(string $searchTerm, bool $activeOnly = true): array {
        $searchTermLike = "%" . $searchTerm . "%";
        $sql = "SELECT id, name, default_price, description
                FROM services
                WHERE name LIKE :term";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY name ASC LIMIT 20";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':term', $searchTermLike);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
