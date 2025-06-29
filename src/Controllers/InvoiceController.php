<?php
namespace App\Controllers;

use App\Models\Invoice;
use App\Models\JobCard; // To fetch data for invoicing
use App\Models\Quotation; // To fetch data for invoicing
use App\Models\Customer;
use App\Models\Branch;
use App\Controllers\AuthController;

class InvoiceController {
    private $invoiceModel;
    private $jobCardModel;
    private $quotationModel;
    private $customerModel;
    private $branchModel;
    private $authController;

    public function __construct() {
        $this->invoiceModel = new Invoice();
        $this->jobCardModel = new JobCard(); // Needed for creating invoice from JC
        $this->quotationModel = new Quotation(); // Needed for creating invoice from Quote
        $this->customerModel = new Customer();
        $this->branchModel = new Branch();
        $this->authController = new AuthController();
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']); // Staff can manage invoices
    }

    public function index() {
        $currentUserRole = $this->authController->getCurrentUserRole();
        $currentBranchId = $_SESSION['branch_id'] ?? null;

        $branchFilter = null;
        if ($currentUserRole !== 'system_admin' && $currentBranchId) {
            $branchFilter = $currentBranchId;
        }

        $invoices = $this->invoiceModel->getAll(25, 0, $branchFilter);

        echo "<h1>Invoices</h1>";
        if (isset($_SESSION['message'])) { echo "<p style='color:green;'>".htmlspecialchars($_SESSION['message'])."</p>"; unset($_SESSION['message']); }
        if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }

        // Links to create invoice from Job Card or Quotation (or standalone - less common for garage)
        echo "<p><a href='/job-cards'>Select Job Card to Invoice</a> | <a href='/quotations'>Select Quotation to Invoice</a></p>";
        // echo "<a href='/invoices/create'>Create Standalone Invoice</a>"; // If needed

        if (!empty($invoices)) {
            echo "<table border='1'><tr><th>Invoice #</th><th>Date Issued</th><th>Customer</th><th>Branch</th><th>Total</th><th>Balance Due</th><th>Status</th><th>Actions</th></tr>";
            foreach ($invoices as $inv) {
                echo "<tr>";
                echo "<td><a href='/invoices/view?id={$inv['id']}'>" . htmlspecialchars($inv['invoice_number']) . "</a></td>";
                echo "<td>" . htmlspecialchars($inv['date_issued']) . "</td>";
                echo "<td>" . htmlspecialchars($inv['customer_name']) . "</td>";
                echo "<td>" . htmlspecialchars($inv['branch_name']) . "</td>";
                echo "<td>" . htmlspecialchars(number_format($inv['total_amount'], 2)) . "</td>";
                echo "<td>" . htmlspecialchars(number_format($inv['balance_due'], 2)) . "</td>";
                echo "<td>" . htmlspecialchars(ucwords(str_replace('_', ' ', $inv['status']))) . "</td>";
                echo "<td><a href='/invoices/view?id={$inv['id']}'>View</a>";
                 if ($inv['status'] === 'draft' || $inv['status'] === 'sent') {
                     echo " | <a href='/invoices/edit?id={$inv['id']}'>Edit</a>"; // Edit placeholder
                 }
                 if ($inv['balance_due'] > 0 && ($inv['status'] === 'sent' || $inv['status'] === 'partially_paid' || $inv['status'] === 'overdue')) {
                     echo " | <a href='/invoices/record-payment?id={$inv['id']}'>Record Payment</a>";
                 }
                echo "</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No invoices found.</p>";
        }
        echo '<p><a href="/dashboard">Back to Dashboard</a></p>';
    }

    public function view() {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) { $_SESSION['error_message'] = "Invalid Invoice ID."; header('Location: /invoices'); exit; }

        $invoice = $this->invoiceModel->findById($id);
        if (!$invoice) { $_SESSION['error_message'] = "Invoice not found."; header('Location: /invoices'); exit; }

        $currentUserRole = $this->authController->getCurrentUserRole();
        $currentBranchId = $_SESSION['branch_id'] ?? null;
        if ($currentUserRole !== 'system_admin' && $invoice->branch_id != $currentBranchId) {
            $_SESSION['access_error'] = "You do not have permission to view this invoice.";
            header('Location: /invoices'); exit;
        }

        // Use job_card_sample.html as a base template for invoice display (very simplified)
        $templatePath = __DIR__ . '/../../templates/job_card_sample.html';
        if (file_exists($templatePath)) {
            $templateContent = file_get_contents($templatePath);

            // General changes for "Invoice"
            $templateContent = str_replace('GARAGE TANZANIA LTD', htmlspecialchars($invoice->branch_name), $templateContent);
            $templateContent = str_replace('>Internal Invoice<', '>Tax Invoice<', $templateContent);
            $templateContent = str_replace('>Job Card<', '>Tax Invoice<', $templateContent);
            $templateContent = str_replace('>Quotation<', '>Tax Invoice<', $templateContent);
            $templateContent = str_replace('GARAGE TANZANIA', htmlspecialchars($invoice->branch_name . " - INVOICE"), $templateContent); // Watermark

            $footerVerification = "Invoice #: ".htmlspecialchars($invoice->invoice_number);
            if($invoice->job_card_number_display) {
                $footerVerification .= " | Job Card: " . htmlspecialchars($invoice->job_card_number_display);
            }
            $templateContent = str_replace('Internal Job Card: {{JOB_CARD_ID}}', $footerVerification, $templateContent);
            $templateContent = str_replace('Mechanic Assigned: {{MECHANIC_NAME}}', 'Status: '.htmlspecialchars(ucwords(str_replace('_', ' ',$invoice->status))), $templateContent);


            // Header section (Branch/Invoice Info)
            $templateContent = str_replace('GARAGE TANZANIA LTD - MAIN BRANCH', htmlspecialchars($invoice->branch_name), $templateContent); // Invoice From company
            $templateContent = str_replace('JC-2025-00123', htmlspecialchars($invoice->invoice_number), $templateContent); // Invoice Number
            $templateContent = str_replace('Service Advisor', 'Prepared By', $templateContent);
            $templateContent = str_replace('zmbmando', htmlspecialchars($invoice->creator_name ?? 'N/A'), $templateContent);
            $templateContent = str_replace('Date Received', 'Invoice Date', $templateContent);
            $templateContent = str_replace('16/05/2025', htmlspecialchars(date('d/m/Y', strtotime($invoice->date_issued))), $templateContent);
            $templateContent = str_replace('Promised Delivery', 'Due Date', $templateContent);
            $templateContent = str_replace('18/05/2025', $invoice->date_due ? htmlspecialchars(date('d/m/Y', strtotime($invoice->date_due))) : 'N/A', $templateContent);

            // Customer & Vehicle Details (Invoice To)
            $customer = $this->customerModel->findById($invoice->customer_id); // Fetch full customer for details
            $templateContent = str_replace('T/I Automark Repairs', htmlspecialchars($customer->full_name), $templateContent);
            $templateContent = str_replace('Kij Building, Sokoine Drive', htmlspecialchars($customer->address ?? 'N/A'), $templateContent);
            $templateContent = str_replace('Dar es Salaam', '', $templateContent); // City - if customer has it
            $templateContent = str_replace('100-146-304', htmlspecialchars($customer->tin_number ?? 'N/A'), $templateContent); // Customer TIN
            // For "Order Number" placeholder in template, could use Job Card # or Quote # if applicable
            $orderNumDisplay = $invoice->job_card_number_display ?? ($this->quotationModel->findById($invoice->quotation_id)->quotation_number ?? 'N/A');
            $templateContent = str_replace('<span>48131</span>', '<span>'.$orderNumDisplay.'</span>', $templateContent);


            // Remove "Customer Complaints" & "Mechanic Findings"
            $templateContent = preg_replace('/<div class="highlight-info" style="background: #fff3cd;.*?<\/div>/s', '', $templateContent, 1);
            $templateContent = preg_replace('/<div class="detail-box" style="margin-bottom: 25px;">.*?<\/div>/s', '', $templateContent, 1);

            // Highlight Info (Payment Terms)
            $paymentTermsText = $invoice->payment_terms ?? "Payment due by " . ($invoice->date_due ? date('d/m/Y', strtotime($invoice->date_due)) : "date of issue") . ".";
            $templateContent = preg_replace('/<div class="highlight-info">.*?<\/div>/s', '<div class="highlight-info"><strong>Payment Terms:</strong> '.htmlspecialchars($paymentTermsText).'</div>', $templateContent, 1);


            // Items Table
            $itemsHtml = "";
            foreach($invoice->items as $item) {
                $itemsHtml .= "<tr><td>".htmlspecialchars($item['description'])."</td>
                               <td class='qty'>".htmlspecialchars($item['quantity'])."</td>
                               <td class='price'>".htmlspecialchars(number_format($item['unit_price'],2))."</td>
                               <td class='total'>".htmlspecialchars(number_format($item['sub_total'],2))."</td></tr>"; // Using item sub_total before overall discount/tax
            }
             $templateContent = preg_replace('/<table class="items-table">.*?<tbody>.*?<\/tbody>.*?<\/table>/s', '<table class="items-table"><thead><tr><th>Description of Goods / Services</th><th class="qty">Qty</th><th class="price">Unit Price</th><th class="total">Net Total</th></tr></thead><tbody>'.$itemsHtml.'</tbody></table>', $templateContent, 1);


            // Totals Section
            $totalsSectionHtml = "<div class='total-box'>
                <div class='total-row'><div class='total-label'>Subtotal</div><div class='total-value'>".number_format($invoice->sub_total,2)."</div></div>
                <div class='total-row'><div class='total-label'>Discount (".($invoice->discount_type === 'percentage' ? (float)$invoice->discount_value.'%' : 'Fixed').")</div><div class='total-value'>".number_format($invoice->discount_amount,2)."</div></div>
                <div class='total-row'><div class='total-label'>V.A.T (".(float)$invoice->tax_rate_percentage."%)</div><div class='total-value'>".number_format($invoice->tax_amount,2)."</div></div>
                <div class='total-row highlight'><div class='total-label'>Total Amount</div><div class='total-value'>".number_format($invoice->total_amount,2)."</div></div>
                <div class='total-row'><div class='total-label'>Amount Paid</div><div class='total-value'>".number_format($invoice->amount_paid,2)."</div></div>
                <div class='total-row highlight'><div class='total-label'>Balance Due</div><div class='total-value'>".number_format($invoice->balance_due,2)."</div></div>
            </div>";
            $templateContent = preg_replace('/<div class="total-section">.*?<\/div>\s*<\/div>\s*<div class="payment-info">/s', '<div class="total-section">'.$totalsSectionHtml.'</div></div><div class="payment-info">', $templateContent, 1);
             // Fallback if payment-info div is not directly after total-section wrapper
            $templateContent = preg_replace('/<div class="total-section">.*?<\/div>(?!\s*<\/div>\s*<div class="payment-info">)/s', '<div class="total-section">'.$totalsSectionHtml.'</div>', $templateContent, 1);


            // Payment Info
            $paymentInfoText = $invoice->notes_to_customer ?? 'Please include invoice number in payment reference. For assistance, contact accounts@GARAGE-tz.co.tz';
            $templateContent = preg_replace('/<div class="payment-info">.*?<\/div>/s', '<div class="payment-info"><svg width="24" height="24" viewBox="0 0 24 24" fill="#e6a000"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg><div><strong>Payment Instructions:</strong> '.htmlspecialchars($paymentInfoText).'</div></div>', $templateContent, 1);


            // Barcode & Microprint
            $templateContent = str_replace('*TZ-TT-48131-2025-31979776*', '*'.htmlspecialchars($invoice->invoice_number).'*', $templateContent);
            $templateContent = str_replace('DOCUMENT ID: TT-2025-48131', 'DOCUMENT ID: '.htmlspecialchars($invoice->invoice_number), $templateContent);
            $templateContent = str_replace('INTERNAL INVOICE', 'TAX INVOICE', $templateContent);
            $templateContent = str_replace('ISSUED: 16/05/2025', 'ISSUED: '.htmlspecialchars(date('d/m/Y', strtotime($invoice->date_issued))), $templateContent);

            echo $templateContent;

        } else {
            echo "Error: Invoice template file (job_card_sample.html) not found.";
        }
        echo "<hr><p><a href='/invoices'>Back to Invoices List</a></p>";
        echo "<p><button onclick='window.print()'>Print Invoice</button></p>";
    }

    // Create invoice (can be from Job Card, Quotation, or standalone)
    public function create() {
        $jobCardId = filter_input(INPUT_GET, 'job_card_id', FILTER_VALIDATE_INT);
        $quotationId = filter_input(INPUT_GET, 'quotation_id', FILTER_VALIDATE_INT);

        $currentUserRole = $this->authController->getCurrentUserRole();
        $userBranchId = $_SESSION['branch_id'] ?? null; // Current user's branch
        $userId = $_SESSION['user_id']; // Current user ID

        $sourceData = null; // To hold data from JC or Quote
        $sourceType = null;

        if ($jobCardId) {
            $sourceData = $this->jobCardModel->findById($jobCardId);
            $sourceType = 'job_card';
            if (!$sourceData) { $_SESSION['error_message'] = "Job Card not found to create invoice from."; header('Location: /job-cards'); exit; }
            if ($sourceData->status !== 'completed' && $sourceData->status !== 'invoiced' && $sourceData->status !== 'paid' && $sourceData->status !== 'partially_paid') {
                 $_SESSION['error_message'] = "Invoice can only be created from a completed (or already invoiced/paid) Job Card. Current status: {$sourceData->status}";
                 header('Location: /job-cards/view?id='.$jobCardId); exit;
            }
            // Check if already invoiced
            $existingInvoice = $this->invoiceModel->findByJobCardId($jobCardId); // Needs implementation in InvoiceModel
            if ($existingInvoice) {
                 $_SESSION['message'] = "This Job Card has already been invoiced (Invoice #{$existingInvoice->invoice_number}). Viewing existing invoice.";
                 header('Location: /invoices/view?id='.$existingInvoice->id); exit;
            }

        } elseif ($quotationId) {
            $sourceData = $this->quotationModel->findById($quotationId);
            $sourceType = 'quotation';
            if (!$sourceData) { $_SESSION['error_message'] = "Quotation not found."; header('Location: /quotations'); exit; }
            if ($sourceData->status !== 'accepted') {
                 $_SESSION['error_message'] = "Invoice can only be created from an 'Accepted' Quotation.";
                 header('Location: /quotations/view?id='.$quotationId); exit;
            }
             // Check if already invoiced (via job card or directly)
            if ($sourceData->job_card_id) {
                $existingInvoice = $this->invoiceModel->findByJobCardId($sourceData->job_card_id);
                 if ($existingInvoice) {
                     $_SESSION['message'] = "This Quotation's related Job Card has already been invoiced (Invoice #{$existingInvoice->invoice_number}). Viewing existing invoice.";
                     header('Location: /invoices/view?id='.$existingInvoice->id); exit;
                 }
            } else {
                // Check if quote directly invoiced (if your system allows that without JC)
                $existingInvoice = $this->invoiceModel->findByQuotationId($quotationId); // Needs implementation
                if ($existingInvoice) {
                     $_SESSION['message'] = "This Quotation has already been invoiced directly (Invoice #{$existingInvoice->invoice_number}). Viewing existing invoice.";
                     header('Location: /invoices/view?id='.$existingInvoice->id); exit;
                }
            }
        }
        // Else, it's a standalone invoice (form will be mostly empty)

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Collect data from POST, similar to QuotationController::create()
            // but also including job_card_id/quotation_id if applicable
            $data = [
                'job_card_id' => filter_input(INPUT_POST, 'source_job_card_id', FILTER_VALIDATE_INT) ?: null,
                'quotation_id' => filter_input(INPUT_POST, 'source_quotation_id', FILTER_VALIDATE_INT) ?: null,
                'branch_id' => filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT) ?: $userBranchId,
                'customer_id' => filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT),
                'date_issued' => $_POST['date_issued'] ?? date('Y-m-d'),
                'date_due' => !empty($_POST['date_due']) ? $_POST['date_due'] : null,
                'status' => $_POST['status'] ?? 'draft', // Default to draft
                'discount_type' => $_POST['discount_type'] ?? null,
                'discount_value' => (float)($_POST['discount_value'] ?? 0),
                'tax_rate_percentage' => (float)($_POST['tax_rate_percentage'] ?? 0), // Default VAT rate from settings?
                'payment_terms' => $_POST['payment_terms'] ?? 'Payment due upon receipt.',
                'notes_to_customer' => $_POST['notes_to_customer'] ?? null,
                'internal_notes' => $_POST['internal_notes'] ?? null,
                'created_by_user_id' => $userId,
                'items' => [],
            ];

            // Basic validation
             if (empty($data['branch_id']) || empty($data['customer_id']) || empty($data['date_issued'])) {
                $_SESSION['error_message'] = "Branch, Customer, and Date Issued are required.";
                // Re-render form with error and previous values
                // This simplified redirect loses POST data, real app needs better handling
                $redirectUrl = '/invoices/create';
                if ($data['job_card_id']) $redirectUrl .= '?job_card_id='.$data['job_card_id'];
                elseif ($data['quotation_id']) $redirectUrl .= '?quotation_id='.$data['quotation_id'];
                header("Location: {$redirectUrl}");
                exit;
            }

            // Collect items (services, parts, misc) - similar to JobCard/Quotation create
            // Services
            if (!empty($_POST['selected_services']) && is_array($_POST['selected_services'])) {
                foreach($_POST['selected_services'] as $idx => $service_id_form) { /* ... copy from Quotation create ... */
                    $service_id = filter_var($service_id_form, FILTER_VALIDATE_INT);
                    $qty = filter_var($_POST['service_quantity'][$idx] ?? 1, FILTER_VALIDATE_FLOAT);
                    $price = filter_var($_POST['service_price'][$idx] ?? 0, FILTER_VALIDATE_FLOAT);
                    $desc = $_POST['service_description_override'][$idx] ?? '';
                    if ($service_id && $qty > 0 && $price >= 0) {
                        if(empty($desc)) { $origService = $this->serviceModel->findById($service_id); $desc = $origService ? $origService->name : 'Service ID '.$service_id; }
                        $data['items'][] = ['item_type' => 'service', 'service_id' => $service_id, 'description' => $desc, 'quantity' => $qty, 'unit_price' => $price];
                    }
                }
            }
            // Parts
            if (!empty($_POST['selected_parts']) && is_array($_POST['selected_parts'])) {
                foreach($_POST['selected_parts'] as $idx => $item_id_form) { /* ... copy from Quotation create ... */
                    $item_id = filter_var($item_id_form, FILTER_VALIDATE_INT);
                    $qty = filter_var($_POST['part_quantity'][$idx] ?? 1, FILTER_VALIDATE_INT);
                    $price = filter_var($_POST['part_price'][$idx] ?? 0, FILTER_VALIDATE_FLOAT);
                    $desc = $_POST['part_description_override'][$idx] ?? '';
                    if ($item_id && $qty > 0 && $price >= 0) {
                         if(empty($desc)) { $origPart = $this->inventoryItemModel->findById($item_id); $desc = $origPart ? $origPart['name'] : 'Part ID '.$item_id; }
                        $data['items'][] = ['item_type' => 'part', 'inventory_item_id' => $item_id, 'description' => $desc, 'quantity' => $qty, 'unit_price' => $price];
                    }
                }
            }
             // Misc Items
            if (!empty($_POST['misc_item_description']) && is_array($_POST['misc_item_description'])) { /* ... copy from Quotation create ... */
                 foreach ($_POST['misc_item_description'] as $idx => $desc) {
                    if (!empty($desc)) {
                        $qty = filter_var($_POST['misc_item_quantity'][$idx] ?? 1, FILTER_VALIDATE_FLOAT);
                        $price = filter_var($_POST['misc_item_price'][$idx] ?? 0, FILTER_VALIDATE_FLOAT);
                        if ($qty > 0 && $price >= 0) { $data['items'][] = ['item_type' => 'misc', 'description' => $desc, 'quantity' => $qty, 'unit_price' => $price]; }
                    }
                }
            }


            if (empty($data['items'])) {
                 $_SESSION['error_message'] = "At least one item must be added to the invoice.";
                 // Re-render form
                 $redirectUrl = '/invoices/create';
                 if ($data['job_card_id']) $redirectUrl .= '?job_card_id='.$data['job_card_id'];
                 elseif ($data['quotation_id']) $redirectUrl .= '?quotation_id='.$data['quotation_id'];
                 header("Location: {$redirectUrl}");
                 exit;
            }

            $invoiceId = $this->invoiceModel->create($data);

            if ($invoiceId) {
                $_SESSION['message'] = "Invoice #{$this->invoiceModel->findById($invoiceId)->invoice_number} created successfully!";
                header("Location: /invoices/view?id={$invoiceId}");
            } else {
                $_SESSION['error_message'] = "Failed to create Invoice.";
                 $redirectUrl = '/invoices/create';
                 if ($data['job_card_id']) $redirectUrl .= '?job_card_id='.$data['job_card_id'];
                 elseif ($data['quotation_id']) $redirectUrl .= '?quotation_id='.$data['quotation_id'];
                 header("Location: {$redirectUrl}");
            }
            exit;

        } else {
            // --- DISPLAY INVOICE CREATION FORM ---
            $pageTitle = "Create New Invoice";
            $formData = ['items' => [], 'branch_id' => $userBranchId, 'customer_id' => null, 'vehicle_id' => null, 'job_card_id' => null, 'quotation_id' => null];

            if ($sourceType === 'job_card' && $sourceData) {
                $pageTitle = "Create Invoice from Job Card #{$sourceData->job_card_number}";
                $formData['branch_id'] = $sourceData->branch_id;
                $formData['customer_id'] = $sourceData->customer_id;
                $formData['vehicle_id'] = $sourceData->vehicle_id; // For display/info
                $formData['job_card_id'] = $sourceData->id;
                // Populate items from job card services and parts
                foreach ($sourceData->services as $svc) {
                    $formData['items'][] = ['item_type' => 'service', 'service_id' => $svc['service_id'], 'description' => $svc['service_name'] . ($svc['description_override'] ? ' - '.$svc['description_override'] : ''), 'quantity' => $svc['quantity'], 'unit_price' => $svc['unit_price']];
                }
                foreach ($sourceData->parts as $part) {
                    $formData['items'][] = ['item_type' => 'part', 'inventory_item_id' => $part['inventory_item_id'], 'description' => $part['item_name'] . ($part['description_override'] ? ' - '.$part['description_override'] : ''), 'quantity' => $part['quantity_used'], 'unit_price' => $part['unit_price']];
                }
                // Default payment terms might come from branch settings or customer
                $branchDetails = $this->branchModel->findById($sourceData->branch_id);
                // $formData['payment_terms'] = $branchDetails->default_payment_terms ?? 'Payment due upon receipt.';

            } elseif ($sourceType === 'quotation' && $sourceData) {
                $pageTitle = "Create Invoice from Quotation #{$sourceData->quotation_number}";
                $formData['branch_id'] = $sourceData->branch_id;
                $formData['customer_id'] = $sourceData->customer_id;
                $formData['vehicle_id'] = $sourceData->vehicle_id;
                $formData['quotation_id'] = $sourceData->id;
                $formData['payment_terms'] = $sourceData->terms_and_conditions;
                // Populate items from quotation
                foreach ($sourceData->items as $item) {
                     $formData['items'][] = [
                        'item_type' => $item['item_type'],
                        'service_id' => ($item['item_type'] == 'service' ? $item['item_id'] : null),
                        'inventory_item_id' => ($item['item_type'] == 'part' ? $item['item_id'] : null),
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price']
                    ];
                }
            }

            echo "<h1>{$pageTitle}</h1>";
            if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }

            $branches = ($currentUserRole === 'system_admin') ? $this->branchModel->getAll() : [$this->branchModel->findById($userBranchId)];
            $customerForForm = $formData['customer_id'] ? $this->customerModel->findById($formData['customer_id']) : null;

            // Form (similar to Quotation create, pre-filled if from JC/Quote)
            ?>
            <style> /* ... same style as quote/jc form ... */
                .form-section { margin-bottom: 20px; padding: 15px; border: 1px solid #eee; border-radius: 5px; }
                .form-section h3 { margin-top: 0; }
                #services_list .service-item, #parts_list .part-item, #misc_items_list .misc-item { margin-bottom: 10px; padding:10px; border:1px dashed #ddd;}
            </style>
            <form action="/invoices/create" method="POST">
                <?php if ($formData['job_card_id']): ?> <input type="hidden" name="source_job_card_id" value="<?= $formData['job_card_id'] ?>"> <?php endif; ?>
                <?php if ($formData['quotation_id']): ?> <input type="hidden" name="source_quotation_id" value="<?= $formData['quotation_id'] ?>"> <?php endif; ?>

                <div class="form-section">
                    <h3>Invoice Details</h3>
                    <p><label>Branch: <select name="branch_id" required>
                        <?php foreach($branches as $branch): if(!$branch) continue; $b_id = $branch->id ?? $branch['id']; $b_name = $branch->name ?? $branch['name'];?>
                        <option value="<?= htmlspecialchars($b_id) ?>" <?= ($b_id == $formData['branch_id']) ? 'selected' : '' ?>><?= htmlspecialchars($b_name) ?></option>
                        <?php endforeach; ?>
                    </select></label></p>
                    <p><label>Date Issued: <input type="date" name="date_issued" value="<?= date('Y-m-d') ?>" required></label></p>
                    <p><label>Date Due: <input type="date" name="date_due"></label></p>
                </div>
                <div class="form-section">
                    <h3>Customer</h3>
                    <?php if ($customerForForm): ?>
                        <p><strong>Customer:</strong> <?= htmlspecialchars($customerForForm->full_name) ?> (ID: <?= $customerForForm->id ?>)</p>
                        <input type="hidden" name="customer_id" value="<?= $customerForForm->id ?>">
                        <?php if ($formData['vehicle_id'] && ($vehicleForForm = $this->vehicleModel->findById($formData['vehicle_id']))): ?>
                             <p><strong>Vehicle:</strong> <?= htmlspecialchars($vehicleForForm->make . ' ' . $vehicleForForm->model . ' (' . ($vehicleForForm->license_plate ?: $vehicleForForm->vin) . ')') ?></p>
                        <?php endif; ?>
                    <?php else: // Standalone invoice - allow customer search ?>
                         <p><label>Customer: <input type="text" id="customer_search" placeholder="Search..."> <select name="customer_id" id="customer_id_select" required><option value="">-- Select --</option></select></label></p>
                    <?php endif; ?>
                </div>

                <div class="form-section">
                    <h3>Invoice Items</h3>
                    <!-- Pre-fill items if $formData['items'] is populated -->
                    <div id="services_list">
                        <?php foreach($formData['items'] as $idx => $item): if($item['item_type'] !== 'service') continue; ?>
                        <div class="service-item">
                            <input type="hidden" name="selected_services[]" value="<?= $item['service_id'] ?>">
                            <strong>Service: <?= htmlspecialchars($item['description']) ?></strong><br>
                            <label>Qty: <input type="number" name="service_quantity[]" value="<?= htmlspecialchars($item['quantity']) ?>" min="0.1" step="0.1"></label>
                            <label>Price: <input type="number" name="service_price[]" value="<?= htmlspecialchars($item['unit_price']) ?>" min="0" step="0.01"></label>
                            <input type="hidden" name="service_description_override[]" value="<?= htmlspecialchars($item['description']) ?>">
                             <button type="button" onclick="this.parentElement.remove()">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="parts_list">
                         <?php foreach($formData['items'] as $idx => $item): if($item['item_type'] !== 'part') continue; ?>
                        <div class="part-item">
                            <input type="hidden" name="selected_parts[]" value="<?= $item['inventory_item_id'] ?>">
                            <strong>Part: <?= htmlspecialchars($item['description']) ?></strong><br>
                            <label>Qty: <input type="number" name="part_quantity[]" value="<?= htmlspecialchars($item['quantity']) ?>" min="1" step="1"></label>
                            <label>Price: <input type="number" name="part_price[]" value="<?= htmlspecialchars($item['unit_price']) ?>" min="0" step="0.01"></label>
                             <input type="hidden" name="part_description_override[]" value="<?= htmlspecialchars($item['description']) ?>">
                             <button type="button" onclick="this.parentElement.remove()">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="misc_items_list">
                        <?php foreach($formData['items'] as $idx => $item): if($item['item_type'] !== 'misc') continue; ?>
                        <div class="misc-item">
                             <strong>Misc: <?= htmlspecialchars($item['description']) ?></strong><br>
                            <input type="hidden" name="misc_item_description[]" value="<?= htmlspecialchars($item['description']) ?>">
                            <label>Qty: <input type="number" name="misc_item_quantity[]" value="<?= htmlspecialchars($item['quantity']) ?>" min="0.1" step="0.1"></label>
                            <label>Price: <input type="number" name="misc_item_price[]" value="<?= htmlspecialchars($item['unit_price']) ?>" min="0" step="0.01"></label>
                            <button type="button" onclick="this.parentElement.remove()">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                     <hr>
                    <p><label>Add Service: <input type="text" id="service_search_inv"> <button type="button" id="add_service_btn_inv">Add</button></label><div id="service_search_results_inv"></div></p>
                    <p><label>Add Part: <input type="text" id="part_search_inv"> <button type="button" id="add_part_btn_inv">Add</button></label><div id="part_search_results_inv"></div></p>
                    <p><button type="button" id="add_misc_item_btn_inv">Add Miscellaneous Item</button></p>
                </div>

                 <div class="form-section">
                    <h3>Totals & Terms</h3>
                    <p><label>Discount Type: <select name="discount_type"><option value="">None</option><option value="percentage">Percentage</option><option value="fixed">Fixed Amount</option></select></label>
                       <label>Discount Value: <input type="number" name="discount_value" value="0" min="0" step="0.01"></label></p>
                    <p><label>Tax Rate (%): <input type="number" name="tax_rate_percentage" value="18" min="0" max="100" step="0.01"></label> (e.g., 18 for 18% VAT)</p>
                    <p><label>Payment Terms: <textarea name="payment_terms" rows="2" style="width:100%;"><?= htmlspecialchars($formData['payment_terms'] ?? 'Payment due upon receipt.') ?></textarea></label></p>
                    <p><label>Notes to Customer: <textarea name="notes_to_customer" rows="2" style="width:100%;"></textarea></label></p>
                    <p><label>Internal Notes (Invoice): <textarea name="internal_notes" rows="2" style="width:100%;"></textarea></label></p>
                     <p><label>Initial Status: <select name="status"><option value="draft" selected>Draft</option><option value="sent">Sent</option></select></label></p>
                </div>
                <button type="submit">Create Invoice</button>
            </form>
            <script>
                // JS for item addition (service_search_inv, part_search_inv, add_misc_item_btn_inv)
                // would be identical to Quotation/JobCard form, just different element IDs.
                // For brevity, not repeated here.
            </script>
            <?php
            $backLink = $sourceType === 'job_card' ? "/job-cards/view?id={$jobCardId}" : ($sourceType === 'quotation' ? "/quotations/view?id={$quotationId}" : "/invoices");
            echo '<p><a href="'.$backLink.'">Cancel / Back</a></p>';
        }
    }

    public function edit() { /* ... Placeholder ... */ echo "Edit Invoice - Not Implemented"; }

    public function recordPayment() {
        $invoiceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$invoiceId) { $_SESSION['error_message'] = "Invalid Invoice ID."; header('Location: /invoices'); exit; }

        $invoice = $this->invoiceModel->findById($invoiceId);
        if (!$invoice) { $_SESSION['error_message'] = "Invoice not found."; header('Location: /invoices'); exit; }
        if ($invoice->balance_due <= 0) { $_SESSION['message'] = "Invoice #{$invoice->invoice_number} is already fully paid."; header("Location: /invoices/view?id={$invoiceId}"); exit;}


        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $amount = filter_input(INPUT_POST, 'amount_paid', FILTER_VALIDATE_FLOAT);
            $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
            $paymentMethod = $_POST['payment_method'] ?? '';
            $refNum = $_POST['reference_number'] ?? null;
            $notes = $_POST['payment_notes'] ?? null;
            $processedBy = $_SESSION['user_id'];

            if ($amount === false || $amount <= 0 || empty($paymentMethod)) {
                $_SESSION['error_message'] = "Valid amount and payment method are required.";
                header("Location: /invoices/record-payment?id={$invoiceId}"); exit;
            }
            if ($amount > $invoice->balance_due + 0.005) { // Allow for small rounding diff
                 $_SESSION['error_message'] = "Payment amount cannot exceed balance due (Balance: {$invoice->balance_due}).";
                 header("Location: /invoices/record-payment?id={$invoiceId}"); exit;
            }

            if ($this->invoiceModel->recordPayment($invoiceId, $amount, $paymentDate, $paymentMethod, $refNum, $processedBy, $notes)) {
                $_SESSION['message'] = "Payment of {$amount} recorded for Invoice #{$invoice->invoice_number}.";
                header("Location: /invoices/view?id={$invoiceId}");
            } else {
                $_SESSION['error_message'] = "Failed to record payment.";
                header("Location: /invoices/record-payment?id={$invoiceId}");
            }
            exit;

        } else {
            echo "<h1>Record Payment for Invoice #".htmlspecialchars($invoice->invoice_number)."</h1>";
            echo "<p>Total Amount: ".htmlspecialchars(number_format($invoice->total_amount,2))."</p>";
            echo "<p>Amount Paid: ".htmlspecialchars(number_format($invoice->amount_paid,2))."</p>";
            echo "<p><strong>Balance Due: ".htmlspecialchars(number_format($invoice->balance_due,2))."</strong></p>";

            if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }

            ?>
            <form method="POST">
                <p><label>Payment Amount: <input type="number" name="amount_paid" step="0.01" min="0.01" max="<?= $invoice->balance_due ?>" value="<?= $invoice->balance_due ?>" required></label></p>
                <p><label>Payment Date: <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required></label></p>
                <p><label>Payment Method:
                    <select name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="other">Other</option>
                    </select>
                </label></p>
                <p><label>Reference Number: <input type="text" name="reference_number"></label></p>
                <p><label>Payment Notes: <textarea name="payment_notes"></textarea></label></p>
                <button type="submit">Record Payment</button>
            </form>
            <?php
            echo "<p><a href='/invoices/view?id={$invoiceId}'>Cancel</a></p>";
        }
    }
     // Method to find invoice by Job Card ID (used in create method)
    private function findByJobCardId(int $jobCardId): ?object { // Assuming InvoiceModel doesn't have this yet
        $stmt = $this->invoiceModel->getPdo()->prepare("SELECT * FROM invoices WHERE job_card_id = :job_card_id LIMIT 1");
        $stmt->bindParam(':job_card_id', $jobCardId, \PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($data) {
            // Basic object conversion, not full model hydration here for simplicity
            return (object)$data;
        }
        return null;
    }
     private function findByQuotationId(int $quotationId): ?object {
        $stmt = $this->invoiceModel->getPdo()->prepare("SELECT * FROM invoices WHERE quotation_id = :quotation_id AND job_card_id IS NULL LIMIT 1");
        $stmt->bindParam(':quotation_id', $quotationId, \PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($data) {
            return (object)$data;
        }
        return null;
    }


    // Add method to InvoiceModel to expose PDO for the above private methods.
    // Or, better, move findByJobCardId and findByQuotationId to InvoiceModel.
    // These have been moved to InvoiceModel now. The private methods below can be removed.
}

// Remove these as they are now in InvoiceModel
// private function findByJobCardId(int $jobCardId): ?object { ... }
// private function findByQuotationId(int $quotationId): ?object { ... }
?>
