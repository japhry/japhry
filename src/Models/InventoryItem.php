<?php
namespace App\Models;

use PDO;
use App\Utils\Database;

class InventoryItem {
    private $pdo;

    public $id;
    public $category_id;
    public $branch_id; // For branch-specific stock
    public $name;
    public $description;
    public $sku;
    public $quantity_on_hand;
    public $unit_price; // Selling price
    public $cost_price; // Purchase price
    public $supplier_id; // To be linked later
    public $reorder_level;
    public $is_active;
    public $created_at;
    public $updated_at;

    // For display
    public $category_name;
    public $branch_name;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    private function baseQuery(): string {
        return "SELECT ii.*, ic.name as category_name, b.name as branch_name
                FROM inventory_items ii
                LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
                LEFT JOIN branches b ON ii.branch_id = b.id";
    }

    public function getAll(int $limit = 100, int $offset = 0, ?int $filterBranchId = null, bool $activeOnly = false): array {
        $sql = $this->baseQuery();
        $conditions = [];
        $params = [];

        if ($filterBranchId !== null) {
            // Show items for specific branch OR global items (branch_id IS NULL)
            $conditions[] = "(ii.branch_id = :filterBranchId OR ii.branch_id IS NULL)";
            $params[':filterBranchId'] = $filterBranchId;
        }
        if ($activeOnly) {
            $conditions[] = "ii.is_active = 1";
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY ii.name ASC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);

        foreach($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array { // Returning array for easier form population
        $sql = $this->baseQuery() . " WHERE ii.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC); // Return as array
    }

    public function findBySkuAndBranch(string $sku, ?int $branchId = null): ?array {
        $sql = $this->baseQuery() . " WHERE ii.sku = :sku";
        if ($branchId === null) {
            $sql .= " AND ii.branch_id IS NULL";
        } else {
            $sql .= " AND ii.branch_id = :branch_id";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':sku', $sku);
        if ($branchId !== null) {
            $stmt->bindParam(':branch_id', $branchId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function create(array $data): ?int {
        if (empty($data['name']) || !isset($data['unit_price']) || empty($data['sku'])) {
            error_log("InventoryItem creation: Name, SKU, and unit price are required.");
            return null;
        }
        if (!is_numeric($data['unit_price']) || $data['unit_price'] < 0) {
            error_log("InventoryItem creation: Unit price must be non-negative number.");
            return null;
        }
        if (isset($data['cost_price']) && $data['cost_price'] !== '' && (!is_numeric($data['cost_price']) || $data['cost_price'] < 0)) {
            error_log("InventoryItem creation: Cost price must be non-negative number if provided.");
            return null;
        }
        if (!isset($data['quantity_on_hand']) || !is_numeric($data['quantity_on_hand']) || $data['quantity_on_hand'] < 0) {
            error_log("InventoryItem creation: Quantity on hand must be a non-negative integer.");
            return null;
        }


        $sql = "INSERT INTO inventory_items (category_id, branch_id, name, description, sku, quantity_on_hand, unit_price, cost_price, reorder_level, is_active)
                VALUES (:category_id, :branch_id, :name, :description, :sku, :quantity_on_hand, :unit_price, :cost_price, :reorder_level, :is_active)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':category_id', !empty($data['category_id']) ? (int)$data['category_id'] : null, PDO::PARAM_INT_OR_NULL);
        $stmt->bindValue(':branch_id', !empty($data['branch_id']) ? (int)$data['branch_id'] : null, PDO::PARAM_INT_OR_NULL);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindValue(':description', $data['description'] ?? null);
        $stmt->bindParam(':sku', $data['sku']);
        $stmt->bindValue(':quantity_on_hand', (int)$data['quantity_on_hand']);
        $stmt->bindValue(':unit_price', $data['unit_price']);
        $stmt->bindValue(':cost_price', ($data['cost_price'] !== '' && $data['cost_price'] !== null) ? $data['cost_price'] : null);
        $stmt->bindValue(':reorder_level', isset($data['reorder_level']) && $data['reorder_level'] !== '' ? (int)$data['reorder_level'] : 0, PDO::PARAM_INT);
        $stmt->bindValue(':is_active', $data['is_active'] ?? 1, PDO::PARAM_INT);
        // supplier_id to be added later

        try {
            $stmt->execute();
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            error_log("InventoryItem creation DB error: " . $e->getMessage() . " (Code: {$e->getCode()})");
            // sku_branch_unique constraint might trigger error 23000
            return null;
        }
    }

    public function update(int $id, array $data): bool {
        if (empty($data['name']) || !isset($data['unit_price']) || empty($data['sku'])) {
            error_log("InventoryItem update ID {$id}: Name, SKU, and unit price are required.");
            return false;
        }
         if (!is_numeric($data['unit_price']) || $data['unit_price'] < 0) {
            error_log("InventoryItem update ID {$id}: Unit price must be non-negative number.");
            return false;
        }
        if (isset($data['cost_price']) && $data['cost_price'] !== '' && $data['cost_price'] !== null && (!is_numeric($data['cost_price']) || $data['cost_price'] < 0)) {
            error_log("InventoryItem update ID {$id}: Cost price must be non-negative number if provided.");
            return false;
        }
        if (!isset($data['quantity_on_hand']) || !is_numeric($data['quantity_on_hand']) || $data['quantity_on_hand'] < 0) {
            error_log("InventoryItem update ID {$id}: Quantity on hand must be a non-negative integer.");
            return false;
        }

        $sql = "UPDATE inventory_items SET
                    category_id = :category_id,
                    branch_id = :branch_id,
                    name = :name,
                    description = :description,
                    sku = :sku,
                    quantity_on_hand = :quantity_on_hand,
                    unit_price = :unit_price,
                    cost_price = :cost_price,
                    reorder_level = :reorder_level,
                    is_active = :is_active
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':category_id', !empty($data['category_id']) ? (int)$data['category_id'] : null, PDO::PARAM_INT_OR_NULL);
        $stmt->bindValue(':branch_id', !empty($data['branch_id']) ? (int)$data['branch_id'] : null, PDO::PARAM_INT_OR_NULL);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindValue(':description', $data['description'] ?? null);
        $stmt->bindParam(':sku', $data['sku']);
        $stmt->bindValue(':quantity_on_hand', (int)$data['quantity_on_hand']);
        $stmt->bindValue(':unit_price', $data['unit_price']);
        $stmt->bindValue(':cost_price', ($data['cost_price'] !== '' && $data['cost_price'] !== null) ? $data['cost_price'] : null);
        $stmt->bindValue(':reorder_level', isset($data['reorder_level']) && $data['reorder_level'] !== '' ? (int)$data['reorder_level'] : 0, PDO::PARAM_INT);
        $stmt->bindValue(':is_active', $data['is_active'] ?? 1, PDO::PARAM_INT);

        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("InventoryItem update DB error for ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool {
        // Check if item is used in job_card_parts.
        // Schema: jcp_inventory_item_id_fk FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON UPDATE CASCADE
        // Deletion RESTRICTED by default. Soft delete (is_active=0) is safer.
        try {
            $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM job_card_parts WHERE inventory_item_id = :item_id");
            $checkStmt->bindParam(':item_id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            if ($checkStmt->fetchColumn() > 0) {
                error_log("Attempt to delete inventory item ID {$id} failed: Item is in use on job cards. Consider deactivating instead.");
                return false;
            }

            $stmt = $this->pdo->prepare("DELETE FROM inventory_items WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("InventoryItem deletion DB error for ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    // Adjust stock levels (e.g., after a sale or stock intake)
    public function adjustStock(int $itemId, int $quantityChange, string $adjustmentType = 'sale'): bool {
        // $quantityChange will be negative for sales, positive for stock intake
        $item = $this->findById($itemId);
        if (!$item) return false;

        $newQuantity = (int)$item['quantity_on_hand'] + $quantityChange;
        if ($newQuantity < 0) {
            // Prevent stock from going negative, unless settings allow backorders (not implemented yet)
            error_log("Stock adjustment for item ID {$itemId} would result in negative stock ({$newQuantity}). Adjustment blocked.");
            return false;
        }

        $sql = "UPDATE inventory_items SET quantity_on_hand = :quantity WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':quantity', $newQuantity, PDO::PARAM_INT);
        $stmt->bindParam(':id', $itemId, PDO::PARAM_INT);

        try {
            $success = $stmt->execute();
            if ($success) {
                // Log stock movement (to be implemented in a separate audit log table)
                // logStockMovement($itemId, $quantityChange, $adjustmentType, $item['quantity_on_hand'], $newQuantity);
            }
            return $success;
        } catch (\PDOException $e) {
            error_log("Stock adjustment DB error for item ID {$itemId}: " . $e->getMessage());
            return false;
        }
    }

    public function searchByNameOrSku(string $searchTerm, ?int $branchId = null, bool $activeOnly = true): array {
        $searchTermLike = "%" . $searchTerm . "%";
        $sql = $this->baseQuery();

        $conditions = ["(ii.name LIKE :term OR ii.sku LIKE :term)"];
        $params = [':term' => $searchTermLike];

        if ($branchId !== null) {
            $conditions[] = "(ii.branch_id = :branchId OR ii.branch_id IS NULL)"; // Items for this branch or global
            $params[':branchId'] = $branchId;
        }
        if ($activeOnly) {
            $conditions[] = "ii.is_active = 1";
        }

        $sql .= " WHERE " . implode(" AND ", $conditions);
        $sql .= " ORDER BY ii.name ASC LIMIT 20";

        $stmt = $this->pdo->prepare($sql);
        foreach($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
