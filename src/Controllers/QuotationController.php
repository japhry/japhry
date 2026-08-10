<?php
namespace App\Controllers;

use App\Models\Quotation;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Service;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Branch;
use App\Controllers\AuthController;

class QuotationController {
    private $quotationModel;
    private $customerModel;
    private $vehicleModel;
    private $serviceModel;
    private $inventoryItemModel;
    private $userModel;
    private $branchModel;
    private $authController;

    public function __construct() {
        $this->quotationModel = new Quotation();
        $this->customerModel = new Customer();
        $this->vehicleModel = new Vehicle();
        $this->serviceModel = new Service();
        $this->inventoryItemModel = new InventoryItem();
        $this->userModel = new User();
        $this->branchModel = new Branch();
        $this->authController = new AuthController();
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']);
    }

    public function index() {
        $currentUserRole = $this->authController->getCurrentUserRole();
        $currentBranchId = $_SESSION['branch_id'] ?? null;

        $branchFilter = null;
        if ($currentUserRole !== 'system_admin' && $currentBranchId) {
            $branchFilter = $currentBranchId;
        }

        $quotations = $this->quotationModel->getAll(25, 0, $branchFilter);

        echo "<h1>Quotations</h1>";
        if (isset($_SESSION['message'])) { echo "<p style='color:green;'>".htmlspecialchars($_SESSION['message'])."</p>"; unset($_SESSION['message']); }
        if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }

        echo "<a href='/quotations/create'>Create New Quotation</a>";

        if (!empty($quotations)) {
            echo "<table border='1'><tr><th>Quote #</th><th>Date Issued</th><th>Customer</th><th>Branch</th><th>Total</th><th>Status</th><th>Actions</th></tr>";
            foreach ($quotations as $q) {
                echo "<tr>";
                echo "<td><a href='/quotations/view?id={$q['id']}'>" . htmlspecialchars($q['quotation_number']) . "</a></td>";
                echo "<td>" . htmlspecialchars($q['date_issued']) . "</td>";
                echo "<td>" . htmlspecialchars($q['customer_name']) . "</td>";
                echo "<td>" . htmlspecialchars($q['branch_name']) . "</td>";
                echo "<td>" . htmlspecialchars(number_format($q['total_amount'], 2)) . "</td>";
                echo "<td>" . htmlspecialchars(ucwords(str_replace('_', ' ', $q['status']))) . "</td>";
                echo "<td><a href='/quotations/view?id={$q['id']}'>View</a>";
                if ($q['status'] === 'draft' || $q['status'] === 'sent') { // Allow editing drafts/sent
                     echo " | <a href='/quotations/edit?id={$q['id']}'>Edit</a>"; // Edit to be implemented
                }
                if ($q['status'] === 'accepted' && empty($q['job_card_id'])) {
                    echo " | <form action='/quotations/convert-to-jobcard' method='POST' style='display:inline;'>
                                <input type='hidden' name='quotation_id' value='{$q['id']}'>
                                <button type='submit'>Convert to Job Card</button>
                              </form>";
                }
                // Add options to change status (e.g., mark as sent, accepted, rejected)
                echo "</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No quotations found.</p>";
        }
        echo '<p><a href="/dashboard">Back to Dashboard</a></p>';
    }

    public function view() {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) { $_SESSION['error_message'] = "Invalid Quotation ID."; header('Location: /quotations'); exit; }

        $quotation = $this->quotationModel->findById($id);
        if (!$quotation) { $_SESSION['error_message'] = "Quotation not found."; header('Location: /quotations'); exit; }

        $currentUserRole = $this->authController->getCurrentUserRole();
        $currentBranchId = $_SESSION['branch_id'] ?? null;
        if ($currentUserRole !== 'system_admin' && $quotation->branch_id != $currentBranchId) {
            $_SESSION['access_error'] = "You do not have permission to view this quotation.";
            header('Location: /quotations'); exit;
        }

        // Adapt job_card_sample.html for quotation view
        // This is highly simplified. A dedicated quotation template is better.
        $templatePath = __DIR__ . '/../../templates/job_card_sample.html'; // REUSE FOR NOW
        if (file_exists($templatePath)) {
            $templateContent = file_get_contents($templatePath);

            // General changes for "Quotation"
            $templateContent = str_replace('GARAGE TANZANIA LTD', htmlspecialchars($quotation->branch_name), $templateContent);
            $templateContent = str_replace('>Internal Invoice<', '>Quotation<', $templateContent);
            $templateContent = str_replace('>Job Card<', '>Quotation<', $templateContent); // If already changed by JC view
            $templateContent = str_replace('GARAGE TANZANIA', htmlspecialchars($quotation->branch_name . " - QUOTATION"), $templateContent); // Watermark
            $templateContent = str_replace('Internal Job Card: {{JOB_CARD_ID}}', 'Quotation #: '.htmlspecialchars($quotation->quotation_number), $templateContent);
            $templateContent = str_replace('Mechanic Assigned: {{MECHANIC_NAME}}', 'Status: '.htmlspecialchars(ucwords(str_replace('_', ' ',$quotation->status))), $templateContent);


            // Header section (Branch/Quotation Info)
            $templateContent = str_replace('GARAGE TANZANIA LTD - MAIN BRANCH', htmlspecialchars($quotation->branch_name), $templateContent);
            $templateContent = str_replace('JC-2025-00123', htmlspecialchars($quotation->quotation_number), $templateContent);
            $templateContent = str_replace('Service Advisor', 'Prepared By', $templateContent);
            $templateContent = str_replace('zmbmando', htmlspecialchars($quotation->creator_name ?? 'N/A'), $templateContent);
            $templateContent = str_replace('Date Received', 'Date Issued', $templateContent);
            $templateContent = str_replace('16/05/2025', htmlspecialchars(date('d/m/Y', strtotime($quotation->date_issued))), $templateContent);
            $templateContent = str_replace('Promised Delivery', 'Valid Until', $templateContent);
            $templateContent = str_replace('18/05/2025', $quotation->valid_until_date ? htmlspecialchars(date('d/m/Y', strtotime($quotation->valid_until_date))) : 'N/A', $templateContent);

            // Customer & Vehicle Details
            $templateContent = str_replace('T/I Automark Repairs', htmlspecialchars($quotation->customer_name), $templateContent);
            $vehicleDisplay = $quotation->vehicle_details_display ?? 'N/A (No specific vehicle)';
            $templateContent = str_replace('Toyota Hilux', $vehicleDisplay, $templateContent);
            // Other customer/vehicle fields might not be directly on quotation model, fetch if needed or use placeholders.
            $customerDetails = $this->customerModel->findById($quotation->customer_id);
            $templateContent = str_replace('+255 710 123 456', htmlspecialchars($customerDetails->phone ?? 'N/A'), $templateContent);
            if ($quotation->vehicle_id) {
                $vehicle = $this->vehicleModel->findById($quotation->vehicle_id);
                $templateContent = str_replace('T123 XYZ', htmlspecialchars($vehicle->license_plate ?? 'N/A'), $templateContent);
                $templateContent = str_replace('VN1234567890ABCDEF', htmlspecialchars($vehicle->vin ?? 'N/A'), $templateContent);
            } else {
                 $templateContent = str_replace('T123 XYZ', 'N/A', $templateContent);
                 $templateContent = str_replace('VN1234567890ABCDEF', 'N/A', $templateContent);
            }
            // $templateContent = str_replace('Kij Building, Sokoine Drive', htmlspecialchars($customerDetails->address ?? 'N/A'), $templateContent);


            // Remove "Customer Complaints" & "Mechanic Findings" sections or adapt
            $templateContent = preg_replace('/<div class="highlight-info" style="background: #fff3cd;.*?<\/div>/s', '<div class="highlight-info"><strong>Notes:</strong><p>'.nl2br(htmlspecialchars($quotation->notes ?? 'No additional notes.')).'</p></div>', $templateContent, 1);
            $templateContent = preg_replace('/<div class="detail-box" style="margin-bottom: 25px;">.*?<\/div>/s', '', $templateContent, 1); // Remove mechanic findings


            // Items Table
            $itemsHtml = "";
            foreach($quotation->items as $item) {
                // Fetch service/part name if item_id is present and description is generic
                $itemName = $item['description']; // Use overridden description first
                if (empty($itemName)) { // Or if you want to always show original name + override
                    if ($item['item_type'] === 'service' && $item['item_id']) {
                        $svc = $this->serviceModel->findById($item['item_id']);
                        if ($svc) $itemName = $svc->name;
                    } elseif ($item['item_type'] === 'part' && $item['item_id']) {
                        $part = $this->inventoryItemModel->findById($item['item_id']); // This returns array
                        if ($part) $itemName = $part['name'] . ($part['sku'] ? " (SKU: ".$part['sku'].")" : "");
                    }
                }
                $itemPrefix = ucfirst($item['item_type']);
                $itemsHtml .= "<tr><td><strong>{$itemPrefix}:</strong> ".htmlspecialchars($itemName)."</td>
                               <td class='qty'>".htmlspecialchars($item['quantity'])."</td>
                               <td class='price'>".htmlspecialchars(number_format($item['unit_price'],2))."</td>
                               <td class='total'>".htmlspecialchars(number_format($item['total_price'],2))."</td></tr>";
            }
            $templateContent = preg_replace('/<table class="items-table">.*?<tbody>.*?<\/tbody>.*?<\/table>/s', '<table class="items-table"><thead><tr><th>Description of Goods / Services</th><th class="qty">Qty</th><th class="price">Unit Price</th><th class="total">Net Total</th></tr></thead><tbody>'.$itemsHtml.'</tbody></table>', $templateContent, 1);

            // Totals Section
            $totalsSectionHtml = "<div class='total-box'>
                <div class='total-row'><div class='total-label'>Subtotal</div><div class='total-value'>".number_format($quotation->sub_total,2)."</div></div>
                <div class='total-row'><div class='total-label'>Discount</div><div class='total-value'>".number_format($quotation->discount_amount,2)."</div></div>
                <div class='total-row'><div class='total-label'>Tax Amount</div><div class='total-value'>".number_format($quotation->tax_amount,2)."</div></div>
                <div class='total-row highlight'><div class='total-label'>Total Amount</div><div class='total-value'>".number_format($quotation->total_amount,2)."</div></div>
            </div>";
            $templateContent = preg_replace('/<div class="total-section">.*?<\/div>\s*<\/div>\s*<div class="payment-info">/s', '<div class="total-section">'.$totalsSectionHtml.'</div></div><div class="payment-info">', $templateContent, 1);
            $templateContent = preg_replace('/<div class="total-section">.*?<\/div>\s*(?!<\/div>\s*<div class="payment-info">)/s', '<div class="total-section">'.$totalsSectionHtml.'</div>', $templateContent, 1);


            // Payment Info / Terms
            $templateContent = preg_replace('/<div class="payment-info">.*?<\/div>/s', '<div class="payment-info" style="background: #e9ecef; border-left-color: #adb5bd; color: #495057;"><svg width="24" height="24" viewBox="0 0 24 24" fill="#495057"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z"/></svg><div><strong>Terms & Conditions:</strong><p>'.nl2br(htmlspecialchars($quotation->terms_and_conditions ?? 'Standard terms apply.')).'</p></div></div>', $templateContent, 1);

            // Signature area - adapt or remove for quotation
            $templateContent = preg_replace('/<div class="signature-label">Service Advisor Signature<\/div>/s', '<div class="signature-label">Prepared By</div>', $templateContent, 1);
            // $templateContent = preg_replace('/<div class="signature-area">.*?<\/div>/s', '', $templateContent, 1); // Or remove entirely


            // Barcode & Microprint
            $templateContent = str_replace('*JC-2025-00123*', '*'.htmlspecialchars($quotation->quotation_number).'*', $templateContent);
            $templateContent = str_replace('DOCUMENT ID: JC-2025-00123', 'DOCUMENT ID: '.htmlspecialchars($quotation->quotation_number), $templateContent);
            $templateContent = str_replace('JOB CARD', 'QUOTATION', $templateContent);
            $templateContent = str_replace('ISSUED: 16/05/2025', 'ISSUED: '.htmlspecialchars(date('d/m/Y', strtotime($quotation->date_issued))), $templateContent);
            $templateContent = str_replace('THIS DOCUMENT IS FOR INTERNAL USE AND CUSTOMER AUTHORIZATION. ESTIMATES ARE SUBJECT TO CHANGE.', 'THIS QUOTATION IS VALID UNTIL '. ($quotation->valid_until_date ? date('d/m/Y', strtotime($quotation->valid_until_date)) : 'N/A') .'. PRICES ARE SUBJECT TO CHANGE AFTER VALIDITY PERIOD.', $templateContent);

            echo $templateContent;

        } else {
            echo "Error: Quotation template file (job_card_sample.html) not found.";
        }
        echo "<hr><p><a href='/quotations'>Back to Quotations List</a></p>";
        echo "<p><button onclick='window.print()'>Print Quotation</button></p>";
    }


    public function create() {
        $currentUserRole = $this->authController->getCurrentUserRole();
        $userBranchId = $_SESSION['branch_id'] ?? null;
        $userId = $_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'branch_id' => $_POST['branch_id'] ?? $userBranchId,
                'customer_id' => filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT),
                'vehicle_id' => filter_input(INPUT_POST, 'vehicle_id', FILTER_VALIDATE_INT) ?: null,
                'date_issued' => $_POST['date_issued'] ?? date('Y-m-d'),
                'valid_until_date' => !empty($_POST['valid_until_date']) ? $_POST['valid_until_date'] : null,
                'terms_and_conditions' => $_POST['terms_and_conditions'] ?? 'Standard quotation terms apply. Prices valid for specified period only.',
                'notes' => $_POST['notes'] ?? null,
                'status' => 'draft', // Initial status
                'created_by_user_id' => $userId,
                'items' => [],
                'discount_percentage' => filter_input(INPUT_POST, 'discount_percentage', FILTER_VALIDATE_FLOAT) ?: 0,
                'tax_rate_percentage' => filter_input(INPUT_POST, 'tax_rate_percentage', FILTER_VALIDATE_FLOAT) ?: 0, // Example: 18 for 18% VAT. Store this globally?
            ];

            if (empty($data['branch_id']) || empty($data['customer_id']) || empty($data['date_issued'])) {
                $_SESSION['error_message'] = "Branch, Customer, and Date Issued are required.";
                header('Location: /quotations/create'); exit;
            }
            // Collect items (services/parts) - similar to JobCardController
            // Services
            if (!empty($_POST['selected_services']) && is_array($_POST['selected_services'])) {
                foreach($_POST['selected_services'] as $idx => $service_id_form) {
                    $service_id = filter_var($service_id_form, FILTER_VALIDATE_INT);
                    $qty = filter_var($_POST['service_quantity'][$idx] ?? 1, FILTER_VALIDATE_FLOAT);
                    $price = filter_var($_POST['service_price'][$idx] ?? 0, FILTER_VALIDATE_FLOAT);
                    $desc = $_POST['service_description_override'][$idx] ?? ''; // Original desc if override empty

                    if ($service_id && $qty > 0 && $price >= 0) {
                        if(empty($desc)) { // Fetch original if override is empty
                            $origService = $this->serviceModel->findById($service_id);
                            $desc = $origService ? $origService->name : 'Service ID '.$service_id;
                        }
                        $data['items'][] = ['item_type' => 'service', 'service_id' => $service_id, 'description' => $desc, 'quantity' => $qty, 'unit_price' => $price];
                    }
                }
            }
            // Parts
            if (!empty($_POST['selected_parts']) && is_array($_POST['selected_parts'])) {
                foreach($_POST['selected_parts'] as $idx => $item_id_form) {
                    $item_id = filter_var($item_id_form, FILTER_VALIDATE_INT);
                    $qty = filter_var($_POST['part_quantity'][$idx] ?? 1, FILTER_VALIDATE_INT);
                    $price = filter_var($_POST['part_price'][$idx] ?? 0, FILTER_VALIDATE_FLOAT);
                    $desc = $_POST['part_description_override'][$idx] ?? '';
                    if ($item_id && $qty > 0 && $price >= 0) {
                         if(empty($desc)) {
                            $origPart = $this->inventoryItemModel->findById($item_id); // Returns array
                            $desc = $origPart ? $origPart['name'] : 'Part ID '.$item_id;
                        }
                        $data['items'][] = ['item_type' => 'part', 'inventory_item_id' => $item_id, 'description' => $desc, 'quantity' => $qty, 'unit_price' => $price];
                    }
                }
            }
             // Misc Items (if any)
            if (!empty($_POST['misc_item_description']) && is_array($_POST['misc_item_description'])) {
                foreach ($_POST['misc_item_description'] as $idx => $desc) {
                    if (!empty($desc)) {
                        $qty = filter_var($_POST['misc_item_quantity'][$idx] ?? 1, FILTER_VALIDATE_FLOAT);
                        $price = filter_var($_POST['misc_item_price'][$idx] ?? 0, FILTER_VALIDATE_FLOAT);
                        if ($qty > 0 && $price >= 0) {
                             $data['items'][] = ['item_type' => 'misc', 'description' => $desc, 'quantity' => $qty, 'unit_price' => $price];
                        }
                    }
                }
            }


            if (empty($data['items'])) {
                 $_SESSION['error_message'] = "At least one item (service, part, or misc) must be added to the quotation.";
                 header('Location: /quotations/create'); exit;
            }

            $quotationId = $this->quotationModel->create($data);

            if ($quotationId) {
                $_SESSION['message'] = "Quotation #{$this->quotationModel->findById($quotationId)->quotation_number} created successfully!";
                header("Location: /quotations/view?id={$quotationId}");
            } else {
                $_SESSION['error_message'] = "Failed to create Quotation.";
                header('Location: /quotations/create');
            }
            exit;

        } else {
            echo "<h1>Create New Quotation</h1>";
            if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }

            $branches = ($currentUserRole === 'system_admin') ? $this->branchModel->getAll() : [$this->branchModel->findById($userBranchId)];
            // Form similar to Job Card create, but with fields for valid_until, terms, discount, tax.
            // JavaScript for item selection will be nearly identical.
            ?>
            <style> /* Same as JobCard form for now */
                .form-section { margin-bottom: 20px; padding: 15px; border: 1px solid #eee; border-radius: 5px; }
                .form-section h3 { margin-top: 0; }
                #services_list .service-item, #parts_list .part-item, #misc_items_list .misc-item { margin-bottom: 10px; padding:10px; border:1px dashed #ddd;}
            </style>
            <form action="/quotations/create" method="POST">
                <div class="form-section">
                    <h3>Details</h3>
                    <p><label>Branch: <select name="branch_id" required>
                        <?php foreach($branches as $branch): if(!$branch) continue; ?>
                        <option value="<?= htmlspecialchars($branch->id ?? $branch['id']) ?>" <?= (($branch->id ?? $branch['id']) == $userBranchId) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($branch->name ?? $branch['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select></label></p>
                    <p><label>Date Issued: <input type="date" name="date_issued" value="<?= date('Y-m-d') ?>" required></label></p>
                    <p><label>Valid Until: <input type="date" name="valid_until_date"></label></p>
                </div>

                <div class="form-section">
                    <h3>Customer & Vehicle (Optional for Vehicle)</h3>
                    <p><label>Customer: <input type="text" id="customer_search" placeholder="Search Name/Phone..."> <select name="customer_id" id="customer_id_select" required><option value="">-- Select --</option></select> <a href="/customers/create?redirect_to=/quotations/create" target="_blank">New</a></label></p>
                    <p><label>Vehicle: <select name="vehicle_id" id="vehicle_id_select"><option value="">-- Optional --</option></select> <a href="#" id="new_vehicle_link" style="display:none;" target="_blank">New Vehicle</a></label></p>
                </div>

                <div class="form-section">
                    <h3>Services</h3>
                    <p><label>Search Service: <input type="text" id="service_search"> <button type="button" id="add_service_btn">Add</button></label></p>
                    <div id="service_search_results"></div><div id="services_list"></div>
                </div>

                <div class="form-section">
                    <h3>Parts</h3>
                    <p><label>Search Part: <input type="text" id="part_search"> <button type="button" id="add_part_btn">Add</button></label></p>
                    <div id="part_search_results"></div><div id="parts_list"></div>
                </div>

                <div class="form-section">
                    <h3>Miscellaneous Items</h3>
                    <div id="misc_items_list"></div>
                    <button type="button" id="add_misc_item_btn">Add Miscellaneous Item</button>
                </div>

                <div class="form-section">
                    <h3>Totals & Terms</h3>
                    <p><label>Discount (%): <input type="number" name="discount_percentage" value="0" min="0" max="100" step="0.01"></label></p>
                    <p><label>Tax Rate (%): <input type="number" name="tax_rate_percentage" value="0" min="0" max="100" step="0.01"></label> (e.g., 18 for 18% VAT)</p>
                    <p><label>Terms & Conditions: <textarea name="terms_and_conditions" rows="3" style="width:100%;">Prices quoted are valid for 14 days. Payment terms: Net 30 upon acceptance.</textarea></label></p>
                    <p><label>Additional Notes: <textarea name="notes" rows="2" style="width:100%;"></textarea></label></p>
                </div>

                <button type="submit">Create Quotation (as Draft)</button>
            </form>
            <script>
                // JS will be very similar to Job Card create form - customer, vehicle, service, part search & add
                // Differences: no mechanic, different status, add discount/tax fields.
                // Add Misc Item JS
                let miscItemCounter = 0;
                document.getElementById('add_misc_item_btn').addEventListener('click', function() {
                    miscItemCounter++;
                    const itemHtml = `<div class="misc-item" id="misc_item_${miscItemCounter}">
                        <label>Description: <input type="text" name="misc_item_description[]" required style="width:40%;"></label>
                        <label>Qty: <input type="number" name="misc_item_quantity[]" value="1" min="0.1" step="0.1" style="width:60px;" onchange="updateTotal(this)"></label>
                        <label>Unit Price: <input type="number" name="misc_item_price[]" value="0" min="0" step="0.01" style="width:80px;" onchange="updateTotal(this)"></label>
                        <button type="button" onclick="this.parentElement.remove()">Remove</button>
                        <span class="total-price">Total: 0.00</span>
                    </div>`;
                    document.getElementById('misc_items_list').insertAdjacentHTML('beforeend', itemHtml);
                });

                // Placeholder for other JS functions (customer search, vehicle population, service/part add)
                // These would be copied and adapted from JobCardController's create view JS.
                // For brevity, not repeating them verbatim here but they are essential.
                const customerSearchInput = document.getElementById('customer_search');
                const customerSelect = document.getElementById('customer_id_select');
                const vehicleSelect = document.getElementById('vehicle_id_select');
                const newVehicleLink = document.getElementById('new_vehicle_link');

                customerSearchInput.addEventListener('keyup', function() {
                    const term = this.value;
                    if (term.length < 2) { customerSelect.innerHTML = '<option value=\"\">-- Select Customer --</option>'; vehicleSelect.innerHTML = '<option value=\"\">-- Select Vehicle --</option>'; newVehicleLink.style.display='none'; return; }
                    fetch(`/customers/search?term=${encodeURIComponent(term)}`)
                        .then(response => response.json())
                        .then(data => {
                            let options = '<option value=\"\">-- Select Customer --</option>';
                            data.forEach(cust => {
                                options += `<option value="${cust.id}">${cust.full_name} (${cust.phone || cust.email || cust.company_name || 'ID: '+cust.id})</option>`;
                            });
                            customerSelect.innerHTML = options;
                        });
                });
                 customerSelect.addEventListener('change', function() {
                    const customerId = this.value;
                    vehicleSelect.innerHTML = '<option value=\"\">-- Loading Vehicles --</option>';
                    newVehicleLink.style.display='none';
                    if (!customerId) { vehicleSelect.innerHTML = '<option value=\"\">-- Select Vehicle --</option>'; return;}

                    newVehicleLink.href = `/vehicles/create?customer_id=${customerId}&redirect_to=/quotations/create`;
                    newVehicleLink.style.display='inline';

                    fetch(`/vehicles?customer_id=${customerId}&format=json`)
                        .then(response => response.json())
                        .then(data => {
                             let options = '<option value=\"\">-- Select Vehicle (Optional) --</option>';
                             if (data && data.length > 0) {
                                 data.forEach(veh => {
                                    options += `<option value="${veh.id}">${veh.make} ${veh.model} (${veh.license_plate || veh.vin})</option>`;
                                });
                             } else {
                                options = '<option value=\"\">-- No vehicles for this customer --</option>';
                             }
                            vehicleSelect.innerHTML = options;
                        })
                        .catch(err => { vehicleSelect.innerHTML = '<option value=\"\">-- Error --</option>'; });
                });

                // Service Search & Add (Simplified)
                const serviceSearchInput = document.getElementById('service_search');
                const serviceSearchResultsDiv = document.getElementById('service_search_results');
                const addServiceBtn = document.getElementById('add_service_btn');
                const servicesListDiv = document.getElementById('services_list');
                let serviceCounter = 0;

                serviceSearchInput.addEventListener('keyup', function() { /* ... same as job card ... */ });
                serviceSearchResultsDiv.addEventListener('click', function(e){ /* ... same as job card ... */ });
                addServiceBtn.addEventListener('click', function(){ /* ... same as job card, ensure field names match form ... */ });


                // Part Search & Add (Similar to Service)
                const partSearchInput = document.getElementById('part_search');
                const partSearchResultsDiv = document.getElementById('part_search_results');
                const addPartBtn = document.getElementById('add_part_btn');
                const partsListDiv = document.getElementById('parts_list');
                let partCounter = 0;

                partSearchInput.addEventListener('keyup', function() { /* ... same as job card ... */ });
                partSearchResultsDiv.addEventListener('click', function(e){ /* ... same as job card ... */ });
                addPartBtn.addEventListener('click', function(){ /* ... same as job card, ensure field names match form ... */ });

                // Common functions (need to be defined or included)
                // function updateTotal(inputElement){ /* ... */ }
                // function htmlspecialchars(str) { /* ... */ }
                 function updateTotal(inputElement){
                    const itemDiv = inputElement.closest('.service-item, .part-item, .misc-item');
                    const qtyInput = itemDiv.querySelector('input[name$="quantity[]"]');
                    const priceInput = itemDiv.querySelector('input[name$="price[]"]');
                    const totalSpan = itemDiv.querySelector('.total-price');

                    const qty = parseFloat(qtyInput.value) || 0;
                    const price = parseFloat(priceInput.value) || 0;
                    totalSpan.textContent = "Total: " + (qty * price).toFixed(2);
                }
                function htmlspecialchars(str) {
                     return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                }
                 // Copy paste JS from JobCard for service/part search and add here...
                 // Service Search & Add (Simplified)
                serviceSearchInput.addEventListener('keyup', function() {
                    const term = this.value;
                    if (term.length < 2) { serviceSearchResultsDiv.innerHTML = ''; return; }
                    fetch(`/services/search?term=${encodeURIComponent(term)}`)
                        .then(response => response.json())
                        .then(data => {
                            let html = '<ul>';
                            data.forEach(svc => {
                                html += `<li data-id="${svc.id}" data-name="${htmlspecialchars(svc.name)}" data-price="${svc.default_price}" style="cursor:pointer;">${htmlspecialchars(svc.name)} - ${svc.default_price}</li>`;
                            });
                            html += '</ul>';
                            serviceSearchResultsDiv.innerHTML = html;
                        });
                });
                serviceSearchResultsDiv.addEventListener('click', function(e){
                    if(e.target && e.target.tagName === 'LI'){
                        serviceSearchInput.value = e.target.dataset.name;
                        serviceSearchInput.dataset.selectedId = e.target.dataset.id;
                        serviceSearchInput.dataset.selectedPrice = e.target.dataset.price;
                        serviceSearchResultsDiv.innerHTML = '';
                    }
                });
                addServiceBtn.addEventListener('click', function(){
                    const id = serviceSearchInput.dataset.selectedId;
                    const name = serviceSearchInput.value;
                    const price = serviceSearchInput.dataset.selectedPrice;
                    if(!id || !name) { alert('Please select a service from search results.'); return; }

                    serviceCounter++;
                    const itemHtml = `<div class="service-item" id="svc_item_${serviceCounter}">
                        <input type="hidden" name="selected_services[]" value="${id}">
                        <strong>${htmlspecialchars(name)}</strong> (Price: ${price})<br>
                        <label>Qty: <input type="number" name="service_quantity[]" value="1" min="0.1" step="0.1" style="width:60px;" data-price="${price}" onchange="updateTotal(this)"></label>
                        <label>Actual Price: <input type="number" name="service_price[]" value="${price}" min="0" step="0.01" style="width:80px;" onchange="updateTotal(this)"></label><br>
                        <label>Desc. Override: <input type="text" name="service_description_override[]" placeholder="${htmlspecialchars(name)}" style="width:80%;"></label>
                        <button type="button" onclick="this.parentElement.remove()">Remove</button>
                        <span class="total-price">Total: ${price}</span>
                    </div>`;
                    servicesListDiv.insertAdjacentHTML('beforeend', itemHtml);
                    serviceSearchInput.value = ''; serviceSearchInput.dataset.selectedId = '';serviceSearchInput.dataset.selectedPrice = '';
                });

                // Part Search & Add (Similar to Service)
                partSearchInput.addEventListener('keyup', function() {
                    const term = this.value;
                    if (term.length < 2) { partSearchResultsDiv.innerHTML = ''; return; }
                    fetch(`/inventory/items/search?term=${encodeURIComponent(term)}`)
                        .then(response => response.json())
                        .then(data => {
                            let html = '<ul>';
                            data.forEach(item => {
                                html += `<li data-id="${item.id}" data-name="${htmlspecialchars(item.name)}" data-sku="${htmlspecialchars(item.sku)}" data-price="${item.unit_price}" data-qty="${item.quantity_on_hand}" style="cursor:pointer;">${htmlspecialchars(item.name)} (SKU: ${htmlspecialchars(item.sku)}) - Price: ${item.unit_price} (Qty: ${item.quantity_on_hand})</li>`;
                            });
                            html += '</ul>';
                            partSearchResultsDiv.innerHTML = html;
                        });
                });
                 partSearchResultsDiv.addEventListener('click', function(e){
                    if(e.target && e.target.tagName === 'LI'){
                        partSearchInput.value = e.target.dataset.name + " (SKU: " + e.target.dataset.sku + ")";
                        partSearchInput.dataset.selectedId = e.target.dataset.id;
                        partSearchInput.dataset.selectedPrice = e.target.dataset.price;
                        partSearchInput.dataset.selectedMaxQty = e.target.dataset.qty;
                        partSearchResultsDiv.innerHTML = '';
                    }
                });
                addPartBtn.addEventListener('click', function(){
                    const id = partSearchInput.dataset.selectedId;
                    const name = partSearchInput.value;
                    const price = partSearchInput.dataset.selectedPrice;
                    const maxQty = parseInt(partSearchInput.dataset.selectedMaxQty || 0);

                    if(!id || !name) { alert('Please select a part from search results.'); return; }

                    partCounter++;
                    const itemHtml = `<div class="part-item" id="part_item_${partCounter}">
                        <input type="hidden" name="selected_parts[]" value="${id}">
                        <strong>${htmlspecialchars(name)}</strong> (Unit Price: ${price})<br>
                        <label>Qty Used: <input type="number" name="part_quantity[]" value="1" min="1" step="1" style="width:60px;" data-price="${price}" onchange="updateTotal(this)"></label>
                        <label>Actual Price: <input type="number" name="part_price[]" value="${price}" min="0" step="0.01" style="width:80px;" onchange="updateTotal(this)"></label><br>
                        <label>Desc. Override: <input type="text" name="part_description_override[]" placeholder="${htmlspecialchars(name.split('(SKU:')[0].trim())}" style="width:80%;"></label>
                        <button type="button" onclick="this.parentElement.remove()">Remove</button>
                        <span class="total-price">Total: ${price}</span>
                    </div>`;
                    partsListDiv.insertAdjacentHTML('beforeend', itemHtml);
                    partSearchInput.value = ''; partSearchInput.dataset.selectedId = ''; partSearchInput.dataset.selectedPrice = ''; partSearchInput.dataset.selectedMaxQty = '';
                });

            </script>
            <?php
            echo '<p><a href="/quotations">Back to Quotations List</a></p>';
        }
    }

    public function edit() {
        // Placeholder for edit functionality
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        echo "<h1>Edit Quotation ID: {$id} (Not Implemented Yet)</h1>";
        echo "<p>This would involve loading existing quotation data, including items, into a form similar to the create form, and handling updates.</p>";
        echo '<p><a href="/quotations/view?id='.$id.'">View Quotation</a> | <a href="/quotations">Back to List</a></p>';
    }

    public function convertToJobCard() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /quotations'); exit;
        }
        $quotationId = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);
        $userId = $_SESSION['user_id'];

        if (!$quotationId || !$userId) {
            $_SESSION['error_message'] = "Invalid request for converting quotation.";
            header('Location: /quotations'); exit;
        }

        $newJobCardId = $this->quotationModel->convertToJobCard($quotationId, $userId);

        if ($newJobCardId) {
            $_SESSION['message'] = "Quotation successfully converted to Job Card #{$this->jobCardModel->findById($newJobCardId)->job_card_number}."; // Assumes JobCardModel is accessible or get JCN from QuotationModel
            header("Location: /job-cards/view?id={$newJobCardId}");
        } else {
            $_SESSION['error_message'] = "Failed to convert quotation to Job Card. It might not be 'Accepted' or already converted.";
            header("Location: /quotations/view?id={$quotationId}");
        }
        exit;
    }

    // TODO: Methods for updateStatus (e.g. mark as 'sent', 'accepted', 'rejected')
}
?>
