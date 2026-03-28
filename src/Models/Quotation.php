<?php
namespace App\Models;

use PDO;
use App\Utils\Database;

class Quotation {
    private $pdo;

    // Quotation Main Properties
    public $id;
    public $quotation_number;
    public $branch_id;
    public $customer_id;
    public $vehicle_id; // Optional
    public $date_issued;
    public $valid_until_date;
    public $status; // ENUM: 'draft', 'sent', 'accepted', 'rejected', 'expired'
    public $sub_total;
    public $discount_amount;
    public $tax_amount;
    public $total_amount;
    public $terms_and_conditions;
    public $notes;
    public $created_by_user_id;
    public $job_card_id; // If converted to job card
    public $created_at;
    public $updated_at;

    // Related data for display
    public $branch_name;
    public $customer_name;
    public $vehicle_details_display; // e.g. "Toyota Corolla (T123ABC)"
    public $creator_name;
    public $items = []; // Array of QuotationItem objects or arrays

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    private function generateQuotationNumber(int $branchId): string {
        $datePart = date('Ymd');
        $randomPart = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
        return "QT-B{$branchId}-{$datePart}-{$randomPart}";
    }

    public function calculateTotals(array $items, float $discountPercentage = 0, float $taxRatePercentage = 0): array {
        $subTotal = 0;
        foreach ($items as $item) {
            $subTotal += (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
        }

        $discountAmount = $subTotal * ($discountPercentage / 100);
        $totalAfterDiscount = $subTotal - $discountAmount;
        $taxAmount = $totalAfterDiscount * ($taxRatePercentage / 100);
        $totalAmount = $totalAfterDiscount + $taxAmount;

        return [
            'sub_total' => round($subTotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($totalAmount, 2)
        ];
    }


    public function create(array $data): ?int {
        // Required fields for quotations table
        if (empty($data['branch_id']) || empty($data['customer_id']) || empty($data['date_issued']) || empty($data['created_by_user_id'])) {
            error_log("Quotation creation: Missing required core fields (branch, customer, date_issued, created_by).");
            return null;
        }

        $this->pdo->beginTransaction();

        try {
            $quotationNumber = $this->generateQuotationNumber((int)$data['branch_id']);

            // Calculate totals based on items, discount, tax
            // Assuming items are passed in $data['items'] as array of ['item_type', 'item_id', 'description', 'quantity', 'unit_price']
            // And discount/tax rates are passed in $data
            $discountPercentage = (float)($data['discount_percentage'] ?? 0);
            $taxRatePercentage = (float)($data['tax_rate_percentage'] ?? 0); // e.g. 18 for 18%

            $calculatedTotals = $this->calculateTotals($data['items'] ?? [], $discountPercentage, $taxRatePercentage);

            $sql = "INSERT INTO quotations (quotation_number, branch_id, customer_id, vehicle_id, date_issued, valid_until_date,
                                       status, sub_total, discount_amount, tax_amount, total_amount,
                                       terms_and_conditions, notes, created_by_user_id)
                    VALUES (:quotation_number, :branch_id, :customer_id, :vehicle_id, :date_issued, :valid_until_date,
                            :status, :sub_total, :discount_amount, :tax_amount, :total_amount,
                            :terms_and_conditions, :notes, :created_by_user_id)";

            $stmt = $this->pdo->prepare($sql);

            $stmt->bindValue(':quotation_number', $quotationNumber);
            $stmt->bindValue(':branch_id', (int)$data['branch_id'], PDO::PARAM_INT);
            $stmt->bindValue(':customer_id', (int)$data['customer_id'], PDO::PARAM_INT);
            $stmt->bindValue(':vehicle_id', !empty($data['vehicle_id']) ? (int)$data['vehicle_id'] : null, PDO::PARAM_INT_OR_NULL);
            $stmt->bindValue(':date_issued', $data['date_issued']);
            $stmt->bindValue(':valid_until_date', !empty($data['valid_until_date']) ? $data['valid_until_date'] : null);
            $stmt->bindValue(':status', $data['status'] ?? 'draft');
            $stmt->bindValue(':sub_total', $calculatedTotals['sub_total']);
            $stmt->bindValue(':discount_amount', $calculatedTotals['discount_amount']);
            $stmt->bindValue(':tax_amount', $calculatedTotals['tax_amount']);
            $stmt->bindValue(':total_amount', $calculatedTotals['total_amount']);
            $stmt->bindValue(':terms_and_conditions', $data['terms_and_conditions'] ?? null);
            $stmt->bindValue(':notes', $data['notes'] ?? null);
            $stmt->bindValue(':created_by_user_id', (int)$data['created_by_user_id'], PDO::PARAM_INT);

            $stmt->execute();
            $quotationId = (int)$this->pdo->lastInsertId();

            // Add Quotation Items
            if (!empty($data['items']) && is_array($data['items'])) {
                $itemSql = "INSERT INTO quotation_items (quotation_id, item_type, item_id, description, quantity, unit_price, total_price)
                            VALUES (:quotation_id, :item_type, :item_id, :description, :quantity, :unit_price, :total_price)";
                $itemStmt = $this->pdo->prepare($itemSql);
                foreach ($data['items'] as $item) {
                    if (empty($item['item_type']) || empty($item['description']) || !isset($item['quantity']) || !isset($item['unit_price'])) continue;

                    $item_id_val = null;
                    if ($item['item_type'] === 'service' && !empty($item['service_id'])) {
                        $item_id_val = (int)$item['service_id'];
                    } elseif ($item['item_type'] === 'part' && !empty($item['inventory_item_id'])) {
                         $item_id_val = (int)$item['inventory_item_id'];
                    } else {
                        // If misc item, item_id might be null, but description is key.
                        // The schema for quotation_items has item_id NOT NULL, so this needs adjustment if we allow truly misc items without an ID.
                        // For now, assume item_id is always provided for service/part.
                        if ($item['item_type'] !== 'misc') continue; // Skip if not misc and no ID
                    }


                    $itemStmt->bindValue(':quotation_id', $quotationId, PDO::PARAM_INT);
                    $itemStmt->bindValue(':item_type', $item['item_type']); // 'service', 'part', or 'misc'
                    $itemStmt->bindValue(':item_id', $item_id_val, PDO::PARAM_INT_OR_NULL); // ID of service or inventory_item, or NULL for misc
                    $itemStmt->bindValue(':description', $item['description']); // Can be overridden from default
                    $itemStmt->bindValue(':quantity', $item['quantity']);
                    $itemStmt->bindValue(':unit_price', $item['unit_price']);
                    $itemStmt->bindValue(':total_price', (float)$item['quantity'] * (float)$item['unit_price']);
                    $itemStmt->execute();
                }
            }

            $this->pdo->commit();
            return $quotationId;

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log("Quotation creation transaction failed: " . $e->getMessage());
            return null;
        }
    }

    public function findById(int $id): ?self {
        $sql = "SELECT q.*,
                       b.name as branch_name,
                       c.full_name as customer_name,
                       CONCAT(v.make, ' ', v.model, ' (', v.license_plate, ')') as vehicle_details_display,
                       u.full_name as creator_name
                FROM quotations q
                JOIN branches b ON q.branch_id = b.id
                JOIN customers c ON q.customer_id = c.id
                LEFT JOIN vehicles v ON q.vehicle_id = v.id
                JOIN users u ON q.created_by_user_id = u.id
                WHERE q.id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $quotation = new self();
            // Populate main properties from $data
            foreach ($data as $key => $value) {
                if (property_exists($quotation, $key)) {
                    $quotation->$key = $value;
                }
            }
            // Populate related names
            $quotation->branch_name = $data['branch_name'];
            $quotation->customer_name = $data['customer_name'];
            $quotation->vehicle_details_display = $data['vehicle_details_display'];
            $quotation->creator_name = $data['creator_name'];

            // Fetch associated items
            $itemStmt = $this->pdo->prepare(
                "SELECT qi.* FROM quotation_items qi WHERE qi.quotation_id = :quotation_id ORDER BY qi.id ASC"
            );
            $itemStmt->bindParam(':quotation_id', $id, PDO::PARAM_INT);
            $itemStmt->execute();
            $quotation->items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            return $quotation;
        }
        return null;
    }

    public function getAll(int $limit = 25, int $offset = 0, ?int $branchIdFilter = null): array {
        $sql = "SELECT q.id, q.quotation_number, q.status, q.date_issued, q.total_amount,
                       c.full_name as customer_name,
                       b.name as branch_name
                FROM quotations q
                JOIN customers c ON q.customer_id = c.id
                JOIN branches b ON q.branch_id = b.id";

        $params = [];
        if ($branchIdFilter !== null) {
            $sql .= " WHERE q.branch_id = :branch_id_filter";
            $params[':branch_id_filter'] = $branchIdFilter;
        }

        $sql .= " ORDER BY q.date_issued DESC, q.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $quotationId, string $newStatus): bool {
        $allowedStatuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];
        if (!in_array($newStatus, $allowedStatuses)) {
            error_log("Invalid status '{$newStatus}' for quotation ID {$quotationId}.");
            return false;
        }

        $sql = "UPDATE quotations SET status = :status WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $quotationId, PDO::PARAM_INT);

        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Failed to update status for quotation ID {$quotationId}: " . $e->getMessage());
            return false;
        }
    }

    public function convertToJobCard(int $quotationId, int $userId): ?int {
        $quotation = $this->findById($quotationId);
        if (!$quotation || $quotation->status !== 'accepted') {
            error_log("Quotation ID {$quotationId} not found or not accepted. Cannot convert to Job Card.");
            return null;
        }
        if ($quotation->job_card_id !== null) {
            error_log("Quotation ID {$quotationId} already converted to Job Card ID {$quotation->job_card_id}.");
            return $quotation->job_card_id; // Return existing job card ID
        }

        $jobCardData = [
            'branch_id' => $quotation->branch_id,
            'customer_id' => $quotation->customer_id,
            'vehicle_id' => $quotation->vehicle_id,
            'date_received' => date('Y-m-d'), // Today's date for received
            'customer_complaints' => "Work as per Quotation #{$quotation->quotation_number}.\n" . ($quotation->notes ?? ''),
            'created_by_user_id' => $userId,
            'status' => 'approved', // Start as approved since quote was accepted
            'estimated_cost' => $quotation->total_amount,
            'services' => [],
            'parts' => [],
        ];

        foreach($quotation->items as $item) {
            if ($item['item_type'] === 'service') {
                $jobCardData['services'][] = [
                    'service_id' => $item['item_id'],
                    'description_override' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ];
            } elseif ($item['item_type'] === 'part') {
                 $jobCardData['parts'][] = [
                    'inventory_item_id' => $item['item_id'],
                    'description_override' => $item['description'],
                    'quantity_used' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ];
            }
        }

        $jobCardModel = new JobCard();
        $newJobCardId = $jobCardModel->create($jobCardData);

        if ($newJobCardId) {
            // Link job card ID back to quotation
            $stmt = $this->pdo->prepare("UPDATE quotations SET job_card_id = :job_card_id WHERE id = :quotation_id");
            $stmt->bindParam(':job_card_id', $newJobCardId, PDO::PARAM_INT);
            $stmt->bindParam(':quotation_id', $quotationId, PDO::PARAM_INT);
            $stmt->execute();
            return $newJobCardId;
        }
        return null;
    }

    // Update method would be complex: update main details, then clear and re-add items, recalculate totals.
    // public function update(int $id, array $data): bool { ... }

    // Delete:
    // public function delete(int $id): bool { ... } // Also delete quotation_items.
}
?>
