<?php
namespace App\Models;

use PDO;
use App\Utils\Database;

class InventoryCategory {
    private $pdo;

    public $id;
    public $name;
    public $description;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getAll(): array {
        $stmt = $this->pdo->query("SELECT * FROM inventory_categories ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?self {
        $stmt = $this->pdo->prepare("SELECT * FROM inventory_categories WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $category = new self();
            foreach ($data as $key => $value) {
                if (property_exists($category, $key)) {
                    $category->$key = $value;
                }
            }
            return $category;
        }
        return null;
    }

    public function findByName(string $name): ?self {
        $stmt = $this->pdo->prepare("SELECT * FROM inventory_categories WHERE name = :name");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
         if ($data) {
            $category = new self();
            foreach ($data as $key => $value) {
                if (property_exists($category, $key)) {
                    $category->$key = $value;
                }
            }
            return $category;
        }
        return null;
    }


    public function create(array $data): ?int {
        if (empty($data['name'])) {
            error_log("InventoryCategory creation failed: Name is required.");
            return null;
        }

        $sql = "INSERT INTO inventory_categories (name, description) VALUES (:name, :description)";
        $stmt = $this->pdo->prepare($sql);

        $stmt->bindParam(':name', $data['name']);
        $stmt->bindValue(':description', $data['description'] ?? null);

        try {
            $stmt->execute();
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            error_log("InventoryCategory creation DB error: " . $e->getMessage() . " (Code: {$e->getCode()})");
            return null; // Name might be duplicate if unique constraint exists
        }
    }

    public function update(int $id, array $data): bool {
        if (empty($data['name'])) {
            error_log("InventoryCategory update failed for ID {$id}: Name is required.");
            return false;
        }

        $sql = "UPDATE inventory_categories SET name = :name, description = :description WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindValue(':description', $data['description'] ?? null);

        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("InventoryCategory update DB error for ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool {
        // Check if category is in use by inventory_items
        // Schema: item_category_id_fk FOREIGN KEY (category_id) REFERENCES inventory_categories (id) ON DELETE SET NULL
        // This means if a category is deleted, items in it will have category_id set to NULL.
        // This is acceptable.
        try {
            // Check if category is in use first (optional, as DB handles it with SET NULL)
            $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM inventory_items WHERE category_id = :category_id");
            $checkStmt->bindParam(':category_id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            if ($checkStmt->fetchColumn() > 0) {
                // Informative log, but deletion will proceed and items will be disassociated.
                error_log("InventoryCategory ID {$id} is in use. Items in this category will have their category set to NULL upon deletion.");
            }

            $stmt = $this->pdo->prepare("DELETE FROM inventory_categories WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("InventoryCategory deletion DB error for ID {$id}: " . $e->getMessage());
            return false;
        }
    }
}
?>
