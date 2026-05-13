<?php
namespace App\Models;

use PDO;
use App\Utils\Database;

class JobCard {
    private $pdo;

    // Job Card Main Properties
    public $id;
    public $job_card_number;
    public $branch_id;
    public $vehicle_id;
    public $customer_id;
    public $assigned_mechanic_id;
    public $status; // ENUM: 'pending_approval', 'approved', 'in_progress', 'awaiting_parts', 'completed', 'invoiced', 'paid', 'cancelled'
    public $date_received;
    public $date_promised_completion;
    public $date_actual_completion;
    public $customer_complaints;
    public $mechanic_findings;
    public $estimated_cost;
    public $actual_cost;
    public $payment_status; // ENUM: 'unpaid', 'partially_paid', 'paid'
    public $internal_notes;
    public $created_by_user_id;
    public $created_at;
    public $updated_at;

    // Related data (for display/details)
    public $branch_name;
    public $vehicle_details; // e.g., make, model, license_plate, vin
    public $customer_details; // e.g., name, phone, email
    public $mechanic_name;
    public $creator_name;
    public $services = []; // Array of associated services
    public $parts = []; // Array of associated parts

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    private function generateJobCardNumber(int $branchId): string {
        // Basic example: BR<branch_id>-YYYYMMDD-XXXX (random 4 digits)
        // This should be more robust in a production system to ensure uniqueness,
        // perhaps using a sequence in the database or a more complex generation.
        $datePart = date('Ymd');
        $randomPart = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4)); // Simple random part
        return "JC-B{$branchId}-{$datePart}-{$randomPart}";
    }

    public function create(array $data): ?int {
        // Required fields for the job_cards table
        if (empty($data['branch_id']) || empty($data['vehicle_id']) || empty($data['customer_id']) ||
            empty($data['date_received']) || empty($data['created_by_user_id']) || empty($data['customer_complaints'])) {
            error_log("JobCard creation: Missing required core fields (branch, vehicle, customer, date_received, created_by, complaints).");
            return null;
        }

        $this->pdo->beginTransaction();

        try {
            $jobCardNumber = $this->generateJobCardNumber((int)$data['branch_id']);
            // Check if somehow this number already exists (highly unlikely with good generation)
            $stmtCheck = $this->pdo->prepare("SELECT id FROM job_cards WHERE job_card_number = :jcn");
            $stmtCheck->bindParam(':jcn', $jobCardNumber);
            $stmtCheck->execute();
            if ($stmtCheck->fetch()) {
                // Handle collision - try generating again or fail
                error_log("JobCard number collision for: " . $jobCardNumber);
                $this->pdo->rollBack();
                return null;
            }


            $sql = "INSERT INTO job_cards (job_card_number, branch_id, vehicle_id, customer_id, assigned_mechanic_id,
                                       status, date_received, date_promised_completion, customer_complaints,
                                       mechanic_findings, internal_notes, created_by_user_id, estimated_cost)
                    VALUES (:job_card_number, :branch_id, :vehicle_id, :customer_id, :assigned_mechanic_id,
                            :status, :date_received, :date_promised_completion, :customer_complaints,
                            :mechanic_findings, :internal_notes, :created_by_user_id, :estimated_cost)";

            $stmt = $this->pdo->prepare($sql);

            $stmt->bindValue(':job_card_number', $jobCardNumber);
            $stmt->bindValue(':branch_id', (int)$data['branch_id'], PDO::PARAM_INT);
            $stmt->bindValue(':vehicle_id', (int)$data['vehicle_id'], PDO::PARAM_INT);
            $stmt->bindValue(':customer_id', (int)$data['customer_id'], PDO::PARAM_INT);
            $stmt->bindValue(':assigned_mechanic_id', !empty($data['assigned_mechanic_id']) ? (int)$data['assigned_mechanic_id'] : null, PDO::PARAM_INT_OR_NULL);
            $stmt->bindValue(':status', $data['status'] ?? 'pending_approval');
            $stmt->bindValue(':date_received', $data['date_received']);
            $stmt->bindValue(':date_promised_completion', !empty($data['date_promised_completion']) ? $data['date_promised_completion'] : null);
            $stmt->bindValue(':customer_complaints', $data['customer_complaints']);
            $stmt->bindValue(':mechanic_findings', $data['mechanic_findings'] ?? null);
            $stmt->bindValue(':internal_notes', $data['internal_notes'] ?? null);
            $stmt->bindValue(':created_by_user_id', (int)$data['created_by_user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':estimated_cost', $data['estimated_cost'] ?? null);

            $stmt->execute();
            $jobCardId = (int)$this->pdo->lastInsertId();

            // Add Services
            if (!empty($data['services']) && is_array($data['services'])) {
                $serviceSql = "INSERT INTO job_card_services (job_card_id, service_id, description_override, quantity, unit_price, total_price, notes)
                               VALUES (:job_card_id, :service_id, :description_override, :quantity, :unit_price, :total_price, :notes)";
                $serviceStmt = $this->pdo->prepare($serviceSql);
                foreach ($data['services'] as $serviceItem) {
                    if (empty($serviceItem['service_id']) || !isset($serviceItem['quantity']) || !isset($serviceItem['unit_price'])) continue;
                    $serviceStmt->bindValue(':job_card_id', $jobCardId, PDO::PARAM_INT);
                    $serviceStmt->bindValue(':service_id', (int)$serviceItem['service_id'], PDO::PARAM_INT);
                    $serviceStmt->bindValue(':description_override', $serviceItem['description_override'] ?? null);
                    $serviceStmt->bindValue(':quantity', $serviceItem['quantity']);
                    $serviceStmt->bindValue(':unit_price', $serviceItem['unit_price']);
                    $serviceStmt->bindValue(':total_price', (float)$serviceItem['quantity'] * (float)$serviceItem['unit_price']);
                    $serviceStmt->bindValue(':notes', $serviceItem['notes'] ?? null);
                    $serviceStmt->execute();
                }
            }

            // Add Parts
            if (!empty($data['parts']) && is_array($data['parts'])) {
                $partSql = "INSERT INTO job_card_parts (job_card_id, inventory_item_id, description_override, quantity_used, unit_price, total_price, notes)
                            VALUES (:job_card_id, :inventory_item_id, :description_override, :quantity_used, :unit_price, :total_price, :notes)";
                $partStmt = $this->pdo->prepare($partSql);
                $inventoryModel = new InventoryItem(); // For stock adjustment

                foreach ($data['parts'] as $partItem) {
                    if (empty($partItem['inventory_item_id']) || !isset($partItem['quantity_used']) || !isset($partItem['unit_price'])) continue;
                    $partStmt->bindValue(':job_card_id', $jobCardId, PDO::PARAM_INT);
                    $partStmt->bindValue(':inventory_item_id', (int)$partItem['inventory_item_id'], PDO::PARAM_INT);
                    $partStmt->bindValue(':description_override', $partItem['description_override'] ?? null);
                    $partStmt->bindValue(':quantity_used', (int)$partItem['quantity_used']);
                    $partStmt->bindValue(':unit_price', $partItem['unit_price']);
                    $partStmt->bindValue(':total_price', (int)$partItem['quantity_used'] * (float)$partItem['unit_price']);
                    $partStmt->bindValue(':notes', $partItem['notes'] ?? null);
                    $partStmt->execute();

                    // Adjust stock - this should ideally be more robust, e.g. part of the transaction
                    // or handled by a separate inventory service.
                    if (!$inventoryModel->adjustStock((int)$partItem['inventory_item_id'], -(int)$partItem['quantity_used'], 'job_card_use')) {
                        error_log("Failed to adjust stock for item ID {$partItem['inventory_item_id']} for job card ID {$jobCardId}.");
                        // This could be a reason to roll back if stock accuracy is critical at this stage.
                        // For now, just log. $this->pdo->rollBack(); return null;
                    }
                }
            }

            $this->pdo->commit();
            return $jobCardId;

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log("JobCard creation transaction failed: " . $e->getMessage());
            return null;
        }
    }

    public function findById(int $id): ?self {
        $sql = "SELECT jc.*,
                       b.name as branch_name,
                       c.full_name as customer_name, c.phone as customer_phone, c.email as customer_email, c.company_name as customer_company, c.tin_number as customer_tin, c.vrn_number as customer_vrn,
                       v.make as vehicle_make, v.model as vehicle_model, v.year as vehicle_year, v.vin as vehicle_vin, v.license_plate as vehicle_license_plate, v.color as vehicle_color,
                       mech.full_name as mechanic_full_name,
                       creator.full_name as creator_full_name
                FROM job_cards jc
                JOIN branches b ON jc.branch_id = b.id
                JOIN customers c ON jc.customer_id = c.id
                JOIN vehicles v ON jc.vehicle_id = v.id
                LEFT JOIN users mech ON jc.assigned_mechanic_id = mech.id
                JOIN users creator ON jc.created_by_user_id = creator.id
                WHERE jc.id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $jobCard = new self();
            // Populate main properties
            $jobCard->id = $data['id'];
            $jobCard->job_card_number = $data['job_card_number'];
            $jobCard->branch_id = $data['branch_id'];
            $jobCard->vehicle_id = $data['vehicle_id'];
            $jobCard->customer_id = $data['customer_id'];
            $jobCard->assigned_mechanic_id = $data['assigned_mechanic_id'];
            $jobCard->status = $data['status'];
            $jobCard->date_received = $data['date_received'];
            $jobCard->date_promised_completion = $data['date_promised_completion'];
            $jobCard->date_actual_completion = $data['date_actual_completion'];
            $jobCard->customer_complaints = $data['customer_complaints'];
            $jobCard->mechanic_findings = $data['mechanic_findings'];
            $jobCard->estimated_cost = $data['estimated_cost'];
            $jobCard->actual_cost = $data['actual_cost'];
            $jobCard->payment_status = $data['payment_status'];
            $jobCard->internal_notes = $data['internal_notes'];
            $jobCard->created_by_user_id = $data['created_by_user_id'];
            $jobCard->created_at = $data['created_at'];
            $jobCard->updated_at = $data['updated_at'];

            // Populate related details
            $jobCard->branch_name = $data['branch_name'];
            $jobCard->customer_details = [
                'name' => $data['customer_name'], 'phone' => $data['customer_phone'], 'email' => $data['customer_email'],
                'company' => $data['customer_company'], 'tin' => $data['customer_tin'], 'vrn' => $data['customer_vrn']
            ];
            $jobCard->vehicle_details = [
                'make' => $data['vehicle_make'], 'model' => $data['vehicle_model'], 'year' => $data['vehicle_year'],
                'vin' => $data['vehicle_vin'], 'license_plate' => $data['vehicle_license_plate'], 'color' => $data['vehicle_color']
            ];
            $jobCard->mechanic_name = $data['mechanic_full_name'];
            $jobCard->creator_name = $data['creator_full_name'];

            // Fetch associated services
            $serviceStmt = $this->pdo->prepare(
                "SELECT jcs.*, s.name as service_name
                 FROM job_card_services jcs
                 JOIN services s ON jcs.service_id = s.id
                 WHERE jcs.job_card_id = :job_card_id"
            );
            $serviceStmt->bindParam(':job_card_id', $id, PDO::PARAM_INT);
            $serviceStmt->execute();
            $jobCard->services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch associated parts
            $partStmt = $this->pdo->prepare(
                "SELECT jcp.*, i.name as item_name, i.sku as item_sku
                 FROM job_card_parts jcp
                 JOIN inventory_items i ON jcp.inventory_item_id = i.id
                 WHERE jcp.job_card_id = :job_card_id"
            );
            $partStmt->bindParam(':job_card_id', $id, PDO::PARAM_INT);
            $partStmt->execute();
            $jobCard->parts = $partStmt->fetchAll(PDO::FETCH_ASSOC);

            return $jobCard;
        }
        return null;
    }

    public function getAll(int $limit = 25, int $offset = 0, ?int $branchIdFilter = null): array {
        $sql = "SELECT jc.id, jc.job_card_number, jc.status, jc.date_received,
                       c.full_name as customer_name,
                       v.make as vehicle_make, v.model as vehicle_model, v.license_plate as vehicle_license_plate,
                       b.name as branch_name,
                       mech.full_name as mechanic_name
                FROM job_cards jc
                JOIN customers c ON jc.customer_id = c.id
                JOIN vehicles v ON jc.vehicle_id = v.id
                JOIN branches b ON jc.branch_id = b.id
                LEFT JOIN users mech ON jc.assigned_mechanic_id = mech.id";

        $params = [];
        if ($branchIdFilter !== null) {
            $sql .= " WHERE jc.branch_id = :branch_id_filter";
            $params[':branch_id_filter'] = $branchIdFilter;
        }

        $sql .= " ORDER BY jc.date_received DESC, jc.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $jobCardId, string $newStatus): bool {
        // Add validation for allowed status transitions if needed
        $allowedStatuses = ['pending_approval', 'approved', 'in_progress', 'awaiting_parts', 'completed', 'invoiced', 'paid', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses)) {
            error_log("Invalid status '{$newStatus}' for job card ID {$jobCardId}.");
            return false;
        }

        $sql = "UPDATE job_cards SET status = :status";
        // If completing, set actual completion date
        if ($newStatus === 'completed') {
            $sql .= ", date_actual_completion = CURDATE()"; // Or a specific date passed in
        }
        $sql .= " WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $jobCardId, PDO::PARAM_INT);

        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Failed to update status for job card ID {$jobCardId}: " . $e->getMessage());
            return false;
        }
    }

    // Add methods for updating job card services, parts, mechanic findings, etc.
    // These would typically involve deleting existing related records and inserting new ones, or targeted updates.
    // Example: public function updateServices(int $jobCardId, array $servicesData) { ... }
    // Example: public function updateParts(int $jobCardId, array $partsData) { ... }
    // Example: public function updateMechanicFindings(int $jobCardId, string $findings) { ... }
    // Example: public function assignMechanic(int $jobCardId, int $mechanicUserId) { ... }
}
?>
