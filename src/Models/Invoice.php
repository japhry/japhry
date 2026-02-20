<?php
namespace App\Models;

use PDO;
use App\Utils\Database;

class Invoice {
    private $pdo;

    // Invoice Main Properties
    public $id;
    public $invoice_number;
    public $job_card_id; // Can be NULL
    public $quotation_id; // Can be NULL
    public $branch_id;
    public $customer_id;
    public $date_issued;
    public $date_due;
    public $status; // ENUM: 'draft', 'sent', 'paid', 'partially_paid', 'overdue', 'cancelled', 'void'
    public $sub_total;
    public $discount_type; // ENUM: 'percentage', 'fixed'
    public $discount_value; // Value for percentage or fixed amount
    public $discount_amount;
    public $tax_rate_percentage; // e.g., 18.00
    public $tax_amount;
    public $total_amount;
    public $amount_paid;
    public $balance_due; // Generated column in DB: total_amount - amount_paid
    public $payment_terms;
    public $notes_to_customer;
    public $internal_notes;
    public $created_by_user_id;
    public $created_at;
    public $updated_at;

    // Related data for display
    public $branch_name;
    public $customer_name;
    public $creator_name;
    public $job_card_number_display; // For linking
    public $items = []; // Array of InvoiceItem objects or arrays

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    private function generateInvoiceNumber(int $branchId): string {
        $datePart = date('Ymd');
        // This needs to be robust to avoid collisions, possibly using a sequence or checking last ID.
        // For now, simple random part.
        $randomPart = strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));
        return "INV-B{$branchId}-{$datePart}-{$randomPart}";
    }

    public function calculateTotals(array $items, string $discountType = null, float $discountValue = 0, float $taxRatePercentage = 0): array {
        $subTotal = 0;
        foreach ($items as $item) {
            // Each item in $items should have ['quantity', 'unit_price']
            // It might also have its own discount/tax if item-level calculation is needed, but for now global.
            $itemSubTotal = (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
            // Optional: Apply item-specific discount/tax here if structure supports it
            // $itemTotal = $itemSubTotal - ($item['discount_amount'] ?? 0) + ($item['tax_amount'] ?? 0);
            $subTotal += $itemSubTotal; // Using pre-tax, pre-discount item subtotal for overall subtotal
        }

        $discountAmount = 0;
        if ($discountType === 'percentage' && $discountValue > 0) {
            $discountAmount = $subTotal * ($discountValue / 100);
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            $discountAmount = $discountValue;
        }
        // Ensure discount doesn't exceed subtotal
        $discountAmount = min($discountAmount, $subTotal);

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
        if (empty($data['branch_id']) || empty($data['customer_id']) || empty($data['date_issued']) || empty($data['created_by_user_id'])) {
            error_log("Invoice creation: Missing required fields (branch, customer, date_issued, created_by).");
            return null;
        }

        $this->pdo->beginTransaction();

        try {
            $invoiceNumber = $this->generateInvoiceNumber((int)$data['branch_id']);

            $calculatedTotals = $this->calculateTotals(
                $data['items'] ?? [],
                $data['discount_type'] ?? null,
                (float)($data['discount_value'] ?? 0),
                (float)($data['tax_rate_percentage'] ?? 0)
            );

            $sql = "INSERT INTO invoices (invoice_number, job_card_id, quotation_id, branch_id, customer_id, date_issued, date_due,
                                       status, sub_total, discount_type, discount_value, discount_amount, tax_rate_percentage, tax_amount, total_amount, amount_paid,
                                       payment_terms, notes_to_customer, internal_notes, created_by_user_id)
                    VALUES (:invoice_number, :job_card_id, :quotation_id, :branch_id, :customer_id, :date_issued, :date_due,
                            :status, :sub_total, :discount_type, :discount_value, :discount_amount, :tax_rate_percentage, :tax_amount, :total_amount, :amount_paid,
                            :payment_terms, :notes_to_customer, :internal_notes, :created_by_user_id)";

            $stmt = $this->pdo->prepare($sql);

            $stmt->bindValue(':invoice_number', $invoiceNumber);
            $stmt->bindValue(':job_card_id', !empty($data['job_card_id']) ? (int)$data['job_card_id'] : null, PDO::PARAM_INT_OR_NULL);
            $stmt->bindValue(':quotation_id', !empty($data['quotation_id']) ? (int)$data['quotation_id'] : null, PDO::PARAM_INT_OR_NULL);
            $stmt->bindValue(':branch_id', (int)$data['branch_id'], PDO::PARAM_INT);
            $stmt->bindValue(':customer_id', (int)$data['customer_id'], PDO::PARAM_INT);
            $stmt->bindValue(':date_issued', $data['date_issued']);
            $stmt->bindValue(':date_due', !empty($data['date_due']) ? $data['date_due'] : null);
            $stmt->bindValue(':status', $data['status'] ?? 'draft');

            $stmt->bindValue(':sub_total', $calculatedTotals['sub_total']);
            $stmt->bindValue(':discount_type', $data['discount_type'] ?? null);
            $stmt->bindValue(':discount_value', (float)($data['discount_value'] ?? 0));
            $stmt->bindValue(':discount_amount', $calculatedTotals['discount_amount']);
            $stmt->bindValue(':tax_rate_percentage', (float)($data['tax_rate_percentage'] ?? 0));
            $stmt->bindValue(':tax_amount', $calculatedTotals['tax_amount']);
            $stmt->bindValue(':total_amount', $calculatedTotals['total_amount']);
            $stmt->bindValue(':amount_paid', (float)($data['amount_paid'] ?? 0));

            $stmt->bindValue(':payment_terms', $data['payment_terms'] ?? null);
            $stmt->bindValue(':notes_to_customer', $data['notes_to_customer'] ?? null);
            $stmt->bindValue(':internal_notes', $data['internal_notes'] ?? null);
            $stmt->bindValue(':created_by_user_id', (int)$data['created_by_user_id'], PDO::PARAM_INT);

            $stmt->execute();
            $invoiceId = (int)$this->pdo->lastInsertId();

            // Add Invoice Items
            if (!empty($data['items']) && is_array($data['items'])) {
                $itemSql = "INSERT INTO invoice_items (invoice_id, item_type, item_id, description, quantity, unit_price, sub_total, discount_amount, tax_amount, total_price)
                            VALUES (:invoice_id, :item_type, :item_id, :description, :quantity, :unit_price, :sub_total, :discount_amount, :tax_amount, :total_price)";
                $itemStmt = $this->pdo->prepare($itemSql);
                foreach ($data['items'] as $item) {
                    // Required: item_type, description, quantity, unit_price
                    if (empty($item['item_type']) || empty($item['description']) || !isset($item['quantity']) || !isset($item['unit_price'])) continue;

                    $item_id_val = null; // For service_id or inventory_item_id
                    if ($item['item_type'] === 'service' && !empty($item['service_id'])) $item_id_val = (int)$item['service_id'];
                    elseif ($item['item_type'] === 'part' && !empty($item['inventory_item_id'])) $item_id_val = (int)$item['inventory_item_id'];

                    $itemSubTotal = (float)$item['quantity'] * (float)$item['unit_price'];
                    // Item-level discount/tax could be calculated here if applicable
                    $itemDiscount = (float)($item['discount_amount'] ?? 0);
                    $itemTax = (float)($item['tax_amount'] ?? 0);
                    $itemTotal = $itemSubTotal - $itemDiscount + $itemTax;

                    $itemStmt->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
                    $itemStmt->bindValue(':item_type', $item['item_type']);
                    $itemStmt->bindValue(':item_id', $item_id_val, PDO::PARAM_INT_OR_NULL);
                    $itemStmt->bindValue(':description', $item['description']);
                    $itemStmt->bindValue(':quantity', $item['quantity']);
                    $itemStmt->bindValue(':unit_price', $item['unit_price']);
                    $itemStmt->bindValue(':sub_total', $itemSubTotal);
                    $itemStmt->bindValue(':discount_amount', $itemDiscount);
                    $itemStmt->bindValue(':tax_amount', $itemTax);
                    $itemStmt->bindValue(':total_price', $itemTotal);
                    $itemStmt->execute();
                }
            }

            // If created from job card, update job card status to 'invoiced'
            if (!empty($data['job_card_id'])) {
                $jcModel = new JobCard();
                $jcModel->updateStatus((int)$data['job_card_id'], 'invoiced');
                // Also update job card's actual_cost if not already set
                $updateJcCostStmt = $this->pdo->prepare("UPDATE job_cards SET actual_cost = :actual_cost WHERE id = :jc_id AND actual_cost IS NULL");
                $updateJcCostStmt->bindValue(':actual_cost', $calculatedTotals['total_amount']);
                $updateJcCostStmt->bindValue(':jc_id', (int)$data['job_card_id']);
                $updateJcCostStmt->execute();
            }


            $this->pdo->commit();
            return $invoiceId;

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log("Invoice creation transaction failed: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
            return null;
        }
    }

    public function findById(int $id): ?self {
        $sql = "SELECT i.*,
                       b.name as branch_name,
                       c.full_name as customer_name, c.email as customer_email, c.phone as customer_phone, c.company_name as customer_company, c.tin_number as customer_tin, c.vrn_number as customer_vrn,
                       u.full_name as creator_name,
                       jc.job_card_number as job_card_number_display
                FROM invoices i
                JOIN branches b ON i.branch_id = b.id
                JOIN customers c ON i.customer_id = c.id
                JOIN users u ON i.created_by_user_id = u.id
                LEFT JOIN job_cards jc ON i.job_card_id = jc.id
                WHERE i.id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $invoice = new self();
            foreach ($data as $key => $value) {
                if (property_exists($invoice, $key)) {
                    $invoice->$key = $value;
                }
            }
            $invoice->branch_name = $data['branch_name'];
            $invoice->customer_name = $data['customer_name']; // For easy access
            $invoice->creator_name = $data['creator_name'];
            $invoice->job_card_number_display = $data['job_card_number_display'];


            // Fetch associated items
            $itemStmt = $this->pdo->prepare(
                "SELECT ii.* FROM invoice_items ii WHERE ii.invoice_id = :invoice_id ORDER BY ii.id ASC"
            );
            $itemStmt->bindParam(':invoice_id', $id, PDO::PARAM_INT);
            $itemStmt->execute();
            $invoice->items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            return $invoice;
        }
        return null;
    }

    public function getAll(int $limit = 25, int $offset = 0, ?int $branchIdFilter = null): array {
        $sql = "SELECT i.id, i.invoice_number, i.status, i.date_issued, i.total_amount, i.balance_due,
                       c.full_name as customer_name,
                       b.name as branch_name
                FROM invoices i
                JOIN customers c ON i.customer_id = c.id
                JOIN branches b ON i.branch_id = b.id";

        $params = [];
        if ($branchIdFilter !== null) {
            $sql .= " WHERE i.branch_id = :branch_id_filter";
            $params[':branch_id_filter'] = $branchIdFilter;
        }

        $sql .= " ORDER BY i.date_issued DESC, i.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $invoiceId, string $newStatus): bool {
        // Add validation for allowed status transitions
        $allowedStatuses = ['draft', 'sent', 'paid', 'partially_paid', 'overdue', 'cancelled', 'void'];
         if (!in_array($newStatus, $allowedStatuses)) {
            error_log("Invalid status '{$newStatus}' for invoice ID {$invoiceId}.");
            return false;
        }
        $sql = "UPDATE invoices SET status = :status WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $invoiceId, PDO::PARAM_INT);
        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Failed to update status for invoice ID {$invoiceId}: " . $e->getMessage());
            return false;
        }
    }

    public function recordPayment(int $invoiceId, float $amount, string $paymentDate, string $paymentMethod, ?string $referenceNumber, int $processedByUserId, ?string $notes = null): bool {
        $this->pdo->beginTransaction();
        try {
            // 1. Add to payments table
            $sqlPay = "INSERT INTO payments (invoice_id, payment_date, amount_paid, payment_method, reference_number, notes, processed_by_user_id)
                       VALUES (:invoice_id, :payment_date, :amount_paid, :payment_method, :reference_number, :notes, :processed_by_user_id)";
            $stmtPay = $this->pdo->prepare($sqlPay);
            $stmtPay->bindParam(':invoice_id', $invoiceId, PDO::PARAM_INT);
            $stmtPay->bindParam(':payment_date', $paymentDate);
            $stmtPay->bindParam(':amount_paid', $amount);
            $stmtPay->bindParam(':payment_method', $paymentMethod);
            $stmtPay->bindParam(':reference_number', $referenceNumber);
            $stmtPay->bindParam(':notes', $notes);
            $stmtPay->bindParam(':processed_by_user_id', $processedByUserId, PDO::PARAM_INT);
            $stmtPay->execute();

            // 2. Update invoice amount_paid and status
            $invoice = $this->findById($invoiceId); // Fetch current invoice details
            if (!$invoice) {
                $this->pdo->rollBack();
                error_log("Cannot record payment: Invoice ID {$invoiceId} not found.");
                return false;
            }

            $newAmountPaid = (float)$invoice->amount_paid + $amount;
            $newBalanceDue = (float)$invoice->total_amount - $newAmountPaid;

            $newStatus = $invoice->status;
            if ($newBalanceDue <= 0.005) { // Using a small epsilon for float comparison
                $newStatus = 'paid';
            } elseif ($newAmountPaid > 0) {
                $newStatus = 'partially_paid';
            }

            $sqlInv = "UPDATE invoices SET amount_paid = :amount_paid, status = :status WHERE id = :id";
            $stmtInv = $this->pdo->prepare($sqlInv);
            $stmtInv->bindParam(':amount_paid', $newAmountPaid);
            $stmtInv->bindParam(':status', $newStatus);
            $stmtInv->bindParam(':id', $invoiceId, PDO::PARAM_INT);
            $stmtInv->execute();

            // If invoice is now fully paid, update related job card status to 'paid'
            if ($newStatus === 'paid' && $invoice->job_card_id) {
                $jcModel = new JobCard();
                $jcModel->updateStatus((int)$invoice->job_card_id, 'paid');
            }

            $this->pdo->commit();
            return true;

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log("Failed to record payment for invoice ID {$invoiceId}: " . $e->getMessage());
            return false;
        }
    }
    // Update, Delete methods would be similar to Quotation, handling items and recalculations.

    public function findByJobCardId(int $jobCardId): ?self {
        $sql = "SELECT * FROM invoices WHERE job_card_id = :job_card_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':job_card_id', $jobCardId, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            // Basic hydration, not fetching items here for this specific check
            $invoice = new self();
            foreach ($data as $key => $value) {
                if (property_exists($invoice, $key)) {
                    $invoice->$key = $value;
                }
            }
            return $invoice;
        }
        return null;
    }

    public function findByQuotationId(int $quotationId): ?self {
        // Find invoice linked directly to a quote (not via a job card from that quote)
        $sql = "SELECT * FROM invoices WHERE quotation_id = :quotation_id AND job_card_id IS NULL LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':quotation_id', $quotationId, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
         if ($data) {
            $invoice = new self();
            foreach ($data as $key => $value) {
                if (property_exists($invoice, $key)) {
                    $invoice->$key = $value;
                }
            }
            return $invoice;
        }
        return null;
    }

    /**
     * Expose PDO instance for specific cases if needed by controllers, though direct model methods are preferred.
     * @return PDO
     */
    public function getPdo(): PDO {
        return $this->pdo;
    }
}
?>
