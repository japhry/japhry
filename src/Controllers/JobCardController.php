<?php
namespace App\Controllers;

use App\Models\JobCard;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Service;
use App\Models\InventoryItem;
use App\Models\User; // To list mechanics
use App\Models\Branch; // To list branches
use App\Controllers\AuthController;

class JobCardController {
    private $jobCardModel;
    private $customerModel;
    private $vehicleModel;
    private $serviceModel;
    private $inventoryItemModel;
    private $userModel;
    private $branchModel;
    private $authController;

    public function __construct() {
        $this->jobCardModel = new JobCard();
        $this->customerModel = new Customer();
        $this->vehicleModel = new Vehicle();
        $this->serviceModel = new Service();
        $this->inventoryItemModel = new InventoryItem();
        $this->userModel = new User();
        $this->branchModel = new Branch();
        $this->authController = new AuthController();
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff', 'mechanic']);
    }

    public function index() {
        // List job cards
        $currentUserRole = $this->authController->getCurrentUserRole();
        $currentBranchId = $_SESSION['branch_id'] ?? null;

        $branchFilter = null;
        if ($currentUserRole !== 'system_admin' && $currentBranchId) {
            $branchFilter = $currentBranchId;
        } // System admin sees all unless a filter is applied via GET

        $jobCards = $this->jobCardModel->getAll(25, 0, $branchFilter);

        echo "<h1>Job Cards</h1>";
        if (isset($_SESSION['message'])) { echo "<p style='color:green;'>".htmlspecialchars($_SESSION['message'])."</p>"; unset($_SESSION['message']); }
        if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }

        if (in_array($currentUserRole, ['system_admin', 'branch_admin', 'staff'])) {
            echo "<a href='/job-cards/create'>Create New Job Card</a>";
        }

        if (!empty($jobCards)) {
            echo "<table border='1'><tr><th>JC Number</th><th>Date Recv.</th><th>Customer</th><th>Vehicle (Plate)</th><th>Branch</th><th>Mechanic</th><th>Status</th><th>Actions</th></tr>";
            foreach ($jobCards as $jc) {
                echo "<tr>";
                echo "<td><a href='/job-cards/view?id={$jc['id']}'>" . htmlspecialchars($jc['job_card_number']) . "</a></td>";
                echo "<td>" . htmlspecialchars($jc['date_received']) . "</td>";
                echo "<td>" . htmlspecialchars($jc['customer_name']) . "</td>";
                echo "<td>" . htmlspecialchars($jc['vehicle_make'].' '.$jc['vehicle_model'].' ('.$jc['vehicle_license_plate'].')') . "</td>";
                echo "<td>" . htmlspecialchars($jc['branch_name']) . "</td>";
                echo "<td>" . htmlspecialchars($jc['mechanic_name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars(ucwords(str_replace('_', ' ', $jc['status']))) . "</td>";
                echo "<td><a href='/job-cards/view?id={$jc['id']}'>View</a>";
                if (in_array($currentUserRole, ['system_admin', 'branch_admin', 'staff'])) { // Edit permissions
                     echo " | <a href='/job-cards/edit?id={$jc['id']}'>Edit</a>"; // Edit to be implemented
                }
                echo "</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No job cards found.</p>";
        }
        echo '<p><a href="/dashboard">Back to Dashboard</a></p>';
    }

    public function view() {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $_SESSION['error_message'] = "Invalid Job Card ID.";
            header('Location: /job-cards');
            exit;
        }

        $jobCard = $this->jobCardModel->findById($id);
        if (!$jobCard) {
            $_SESSION['error_message'] = "Job Card not found.";
            header('Location: /job-cards');
            exit;
        }

        // Authorization: User must be system_admin or belong to the job card's branch
        $currentUserRole = $this->authController->getCurrentUserRole();
        $currentBranchId = $_SESSION['branch_id'] ?? null;
        if ($currentUserRole !== 'system_admin' && $jobCard->branch_id != $currentBranchId) {
            $_SESSION['access_error'] = "You do not have permission to view this job card.";
            header('Location: /job-cards');
            exit;
        }

        // For now, we use the static HTML template and inject data.
        // A proper templating engine would be much better.
        $templatePath = __DIR__ . '/../../templates/job_card_sample.html';
        if (file_exists($templatePath)) {
            $templateContent = file_get_contents($templatePath);

            // Replace placeholders (this is very basic and error-prone)
            // Company Info (usually static, but could be dynamic per branch if needed)
            $templateContent = str_replace('GARAGE TANZANIA LTD', htmlspecialchars($jobCard->branch_name), $templateContent);
            // Document Type
            $templateContent = str_replace('>Internal Invoice<', '>Job Card<', $templateContent);
            $templateContent = str_replace('GARAGE TANZANIA', htmlspecialchars($jobCard->branch_name), $templateContent); // Watermark

            // Branch / Service Center section
            $templateContent = str_replace('GARAGE TANZANIA LTD - MAIN BRANCH', htmlspecialchars($jobCard->branch_name), $templateContent);
            $templateContent = str_replace('JC-2025-00123', htmlspecialchars($jobCard->job_card_number), $templateContent);
            // Assuming branch address is part of $jobCard->branch_details or fetched separately
            // $templateContent = str_replace('Nyerere Road, P.O. Box 9060', htmlspecialchars($jobCard->branch_address_placeholder), $templateContent);
            $templateContent = str_replace('zmbmando', htmlspecialchars($jobCard->creator_name ?? 'N/A'), $templateContent); // Service Advisor/Creator
            $templateContent = str_replace('16/05/2025', htmlspecialchars(date('d/m/Y', strtotime($jobCard->date_received))), $templateContent);
            $templateContent = str_replace('18/05/2025', $jobCard->date_promised_completion ? htmlspecialchars(date('d/m/Y', strtotime($jobCard->date_promised_completion))) : 'N/A', $templateContent);

            // Customer & Vehicle Details
            $templateContent = str_replace('T/I Automark Repairs', htmlspecialchars($jobCard->customer_details['name']), $templateContent);
            $templateContent = str_replace('Toyota Hilux', htmlspecialchars($jobCard->vehicle_details['make'] . ' ' . $jobCard->vehicle_details['model']), $templateContent);
            $templateContent = str_replace('+255 710 123 456', htmlspecialchars($jobCard->customer_details['phone'] ?? 'N/A'), $templateContent);
            $templateContent = str_replace('T123 XYZ', htmlspecialchars($jobCard->vehicle_details['license_plate'] ?? 'N/A'), $templateContent);
            // $templateContent = str_replace('Kij Building, Sokoine Drive', htmlspecialchars($jobCard->customer_details['address_placeholder']), $templateContent);
            $templateContent = str_replace('VN1234567890ABCDEF', htmlspecialchars($jobCard->vehicle_details['vin'] ?? 'N/A'), $templateContent);

            // Customer Complaints
            $complaintsHtml = nl2br(htmlspecialchars($jobCard->customer_complaints));
            // This replacement is tricky due to HTML structure. Simpler to replace a placeholder.
            // For now, let's assume there's a placeholder like {{CUSTOMER_COMPLAINTS_P_TAGS}}
            $templateContent = preg_replace('/<strong>Customer Complaints \/ Requests:<\/strong>.*?<p>.*?<\/p>/s', '<strong>Customer Complaints / Requests:</strong><p>'.$complaintsHtml.'</p>', $templateContent, 1);


            // Items Table (Services and Parts)
            $itemsHtml = "";
            $estSubtotalServices = 0;
            $estSubtotalParts = 0;
            foreach($jobCard->services as $svc) {
                $itemsHtml .= "<tr><td><strong>Service:</strong> ".htmlspecialchars($svc['service_name'])."<br><small>".htmlspecialchars($svc['description_override'] ?? '')."</small></td>
                               <td class='qty'>".htmlspecialchars($svc['quantity'])."</td>
                               <td class='price'>".htmlspecialchars(number_format($svc['unit_price'],2))."</td>
                               <td class='total'>".htmlspecialchars(number_format($svc['total_price'],2))."</td></tr>";
                $estSubtotalServices += $svc['total_price'];
            }
            foreach($jobCard->parts as $part) {
                $itemsHtml .= "<tr><td><em>Part:</em> ".htmlspecialchars($part['item_name'])." (SKU: ".htmlspecialchars($part['item_sku']).")<br><small>".htmlspecialchars($part['description_override'] ?? '')."</small></td>
                               <td class='qty'>".htmlspecialchars($part['quantity_used'])."</td>
                               <td class='price'>".htmlspecialchars(number_format($part['unit_price'],2))."</td>
                               <td class='total'>".htmlspecialchars(number_format($part['total_price'],2))."</td></tr>";
                $estSubtotalParts += $part['total_price'];
            }
            // Replace the entire tbody content
            $templateContent = preg_replace('/<table class="items-table">.*?<tbody>.*?<\/tbody>.*?<\/table>/s', '<table class="items-table"><thead><tr><th>Description of Services / Parts</th><th class="qty">Qty</th><th class="price">Unit Price</th><th class="total">Estimated Total</th></tr></thead><tbody>'.$itemsHtml.'</tbody></table>', $templateContent, 1);

            // Totals
            // This is also very brittle. Ideally, placeholders for each value.
            $totalEstimated = $estSubtotalServices + $estSubtotalParts;
            // Assuming 0 VAT for job card estimate, or calculate if applicable
            $vatAmount = 0; // $totalEstimated * 0.18 if VAT applies
            $grandTotal = $totalEstimated + $vatAmount;

            $templateContent = str_replace('>225,000.00<', '>'.number_format($estSubtotalServices,2).'<', $templateContent);
            $templateContent = str_replace('>115,000.00<', '>'.number_format($estSubtotalParts,2).'<', $templateContent);
            // $templateContent = str_replace('>61,200.00<', '>'.number_format($vatAmount,2).'<', $templateContent); // VAT
            $templateContent = preg_replace('/<div class="total-label">VAT \(18%\)<\/div>\s*<div class="total-value">.*?<\/div>/s', '<div class="total-label">VAT (0%)</div><div class="total-value">'.number_format($vatAmount,2).'</div>', $templateContent);

            $templateContent = str_replace('>401,200.00<', '>'.number_format($grandTotal,2).'<', $templateContent);


            // Mechanic's Findings
            $findingsHtml = nl2br(htmlspecialchars($jobCard->mechanic_findings ?? 'Not yet recorded.'));
            $templateContent = preg_replace('/<div style="font-size: 14px; line-height: 1.6;">.*?<\/div>/s', '<div style="font-size: 14px; line-height: 1.6;"><p>'.$findingsHtml.'</p></div>', $templateContent, 1);

            // Barcode & Microprint
            $templateContent = str_replace('*JC-2025-00123*', '*'.htmlspecialchars($jobCard->job_card_number).'*', $templateContent);
            $templateContent = str_replace('DOCUMENT ID: JC-2025-00123', 'DOCUMENT ID: '.htmlspecialchars($jobCard->job_card_number), $templateContent);
            $templateContent = str_replace('ISSUED: 16/05/2025', 'ISSUED: '.htmlspecialchars(date('d/m/Y', strtotime($jobCard->created_at))), $templateContent);

            // Footer
            $templateContent = str_replace('{{JOB_CARD_ID}}', htmlspecialchars($jobCard->job_card_number), $templateContent);
            $templateContent = str_replace('{{MECHANIC_NAME}}', htmlspecialchars($jobCard->mechanic_name ?? 'Not Assigned'), $templateContent);

            // QR Codes - update text
            $templateContent = str_replace('https://GARAGE-tz.com/jobcard/" + jobCardId', '"https://YOUR_DOMAIN.com/job-cards/view?id='.$jobCard->id.'"', $templateContent); // Adjust domain
            // $templateContent = str_replace('https://verify.tra.go.tz/placeholder_for_jobcard_verification_if_any', '"SOME_VERIFICATION_LINK_IF_NEEDED"', $templateContent);


            echo $templateContent;

        } else {
            echo "Error: Job card template file not found.";
        }
        echo "<hr><p><a href='/job-cards'>Back to Job Cards List</a></p>";
        echo "<p><button onclick='window.print()'>Print Job Card</button></p>";

    }

    public function create() {
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']);
        $currentUserRole = $this->authController->getCurrentUserRole();
        $userBranchId = $_SESSION['branch_id'] ?? null;
        $userId = $_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // --- DATA COLLECTION ---
            $data = [
                'branch_id' => $_POST['branch_id'] ?? $userBranchId, // Default to user's branch
                'customer_id' => filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT),
                'vehicle_id' => filter_input(INPUT_POST, 'vehicle_id', FILTER_VALIDATE_INT),
                'assigned_mechanic_id' => filter_input(INPUT_POST, 'assigned_mechanic_id', FILTER_VALIDATE_INT) ?: null,
                'date_received' => $_POST['date_received'] ?? date('Y-m-d'),
                'date_promised_completion' => !empty($_POST['date_promised_completion']) ? $_POST['date_promised_completion'] : null,
                'customer_complaints' => $_POST['customer_complaints'] ?? '',
                'mechanic_findings' => $_POST['mechanic_findings'] ?? null, // Typically filled later
                'internal_notes' => $_POST['internal_notes'] ?? null,
                'status' => $_POST['status'] ?? 'pending_approval', // Or 'approved' if creating directly
                'created_by_user_id' => $userId,
                'services' => [],
                'parts' => [],
                // estimated_cost will be calculated or entered
            ];

            // --- VALIDATION ---
            if (empty($data['branch_id']) || empty($data['customer_id']) || empty($data['vehicle_id']) || empty($data['customer_complaints'])) {
                $_SESSION['error_message'] = "Branch, Customer, Vehicle, and Customer Complaints are required.";
                // Re-render form with errors and previously entered data (complex part not fully done here)
                header('Location: /job-cards/create'); // Simplified redirect
                exit;
            }
            // Ensure vehicle belongs to customer (basic check)
            $vehicleCheck = $this->vehicleModel->findById($data['vehicle_id']);
            if (!$vehicleCheck || $vehicleCheck->customer_id != $data['customer_id']) {
                 $_SESSION['error_message'] = "Selected vehicle does not belong to the selected customer.";
                 header('Location: /job-cards/create'); exit;
            }


            // Collect services
            if (!empty($_POST['selected_services']) && is_array($_POST['selected_services'])) {
                foreach($_POST['selected_services'] as $idx => $service_id) {
                    $service_id = filter_var($service_id, FILTER_VALIDATE_INT);
                    $qty = filter_var($_POST['service_quantity'][$idx] ?? 1, FILTER_VALIDATE_FLOAT);
                    $price = filter_var($_POST['service_price'][$idx] ?? 0, FILTER_VALIDATE_FLOAT); // Price might be overridden
                    $desc_override = $_POST['service_description_override'][$idx] ?? null;
                    $notes = $_POST['service_notes'][$idx] ?? null;
                    if ($service_id && $qty > 0 && $price >= 0) {
                        $data['services'][] = [
                            'service_id' => $service_id,
                            'quantity' => $qty,
                            'unit_price' => $price,
                            'description_override' => $desc_override,
                            'notes' => $notes
                        ];
                    }
                }
            }
            // Collect parts
             if (!empty($_POST['selected_parts']) && is_array($_POST['selected_parts'])) {
                foreach($_POST['selected_parts'] as $idx => $item_id) {
                    $item_id = filter_var($item_id, FILTER_VALIDATE_INT);
                    $qty = filter_var($_POST['part_quantity'][$idx] ?? 1, FILTER_VALIDATE_INT);
                    $price = filter_var($_POST['part_price'][$idx] ?? 0, FILTER_VALIDATE_FLOAT); // Price might be overridden
                    $desc_override = $_POST['part_description_override'][$idx] ?? null;
                    $notes = $_POST['part_notes'][$idx] ?? null;

                    if ($item_id && $qty > 0 && $price >= 0) {
                        // Check stock availability (basic check, model does more)
                        $partInfo = $this->inventoryItemModel->findById($item_id);
                        if ($partInfo && $partInfo['quantity_on_hand'] < $qty) {
                             $_SESSION['warning_message'] = "Warning: Insufficient stock for item '{$partInfo['name']}'. Available: {$partInfo['quantity_on_hand']}, Requested: {$qty}. Job card created, but stock needs attention.";
                             // Don't block creation, but flag it. Or block if strict.
                        }
                        $data['parts'][] = [
                            'inventory_item_id' => $item_id,
                            'quantity_used' => $qty,
                            'unit_price' => $price,
                            'description_override' => $desc_override,
                            'notes' => $notes
                        ];
                    }
                }
            }

            // --- CREATION ---
            $jobCardId = $this->jobCardModel->create($data);

            if ($jobCardId) {
                $_SESSION['message'] = "Job Card #{$this->jobCardModel->findById($jobCardId)->job_card_number} created successfully!";
                 if(isset($_SESSION['warning_message'])) { // Append warning if exists
                    $_SESSION['message'] .= " " . $_SESSION['warning_message'];
                    unset($_SESSION['warning_message']);
                }
                header("Location: /job-cards/view?id={$jobCardId}");
            } else {
                $_SESSION['error_message'] = "Failed to create Job Card. Please check all inputs.";
                header('Location: /job-cards/create'); // Simplified redirect
            }
            exit;

        } else {
            // --- DISPLAY FORM ---
            echo "<h1>Create New Job Card</h1>";
            if (isset($_SESSION['message'])) { echo "<p style='color:green;'>".htmlspecialchars($_SESSION['message'])."</p>"; unset($_SESSION['message']); }
            if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }
            if (isset($_SESSION['warning_message'])) { echo "<p style='color:orange;'>".htmlspecialchars($_SESSION['warning_message'])."</p>"; unset($_SESSION['warning_message']); }


            $branches = ($currentUserRole === 'system_admin') ? $this->branchModel->getAll() : [$this->branchModel->findById($userBranchId)];
            // Mechanics: Users with role 'mechanic', optionally filtered by current user's branch
            $mechanics = $this->userModel->getUsersByRoleAndBranch('mechanic', ($currentUserRole !== 'system_admin' ? $userBranchId : null));


            // This form will be complex and require JavaScript for good UX (searching customers, vehicles, services, parts dynamically)
            // For now, a very basic structure.
            ?>
            <style>
                .form-section { margin-bottom: 20px; padding: 15px; border: 1px solid #eee; border-radius: 5px; }
                .form-section h3 { margin-top: 0; }
                #services_list .service-item, #parts_list .part-item { margin-bottom: 10px; padding:10px; border:1px dashed #ddd;}
            </style>
            <form action="/job-cards/create" method="POST">
                <div class="form-section">
                    <h3>Branch & Dates</h3>
                    <p>
                        <label>Branch:
                            <select name="branch_id" required>
                                <?php foreach($branches as $branch): if(!$branch) continue; ?>
                                <option value="<?= htmlspecialchars($branch->id ?? $branch['id']) ?>" <?= (($branch->id ?? $branch['id']) == $userBranchId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch->name ?? $branch['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </p>
                    <p><label>Date Received: <input type="date" name="date_received" value="<?= date('Y-m-d') ?>" required></label></p>
                    <p><label>Date Promised Completion: <input type="date" name="date_promised_completion"></label></p>
                </div>

                <div class="form-section">
                    <h3>Customer & Vehicle</h3>
                    <p>
                        <label>Customer:
                            <input type="text" id="customer_search" placeholder="Search Name/Phone/Email...">
                            <select name="customer_id" id="customer_id_select" required>
                                <option value="">-- Select Customer --</option>
                            </select>
                            <a href="/customers/create?redirect_to=/job-cards/create" target="_blank">New Customer</a>
                        </label>
                    </p>
                    <p>
                        <label>Vehicle:
                            <select name="vehicle_id" id="vehicle_id_select" required>
                                <option value="">-- Select Vehicle (after selecting customer) --</option>
                            </select>
                             <a href="#" id="new_vehicle_link" style="display:none;" target="_blank">New Vehicle for this Customer</a>
                        </label>
                    </p>
                </div>

                <div class="form-section">
                    <h3>Complaints & Notes</h3>
                    <p><label>Customer Complaints/Requests: <textarea name="customer_complaints" rows="4" style="width:100%;" required></textarea></label></p>
                    <p><label>Internal Notes: <textarea name="internal_notes" rows="3" style="width:100%;"></textarea></label></p>
                </div>

                <div class="form-section">
                    <h3>Services</h3>
                    <p><label>Search Service: <input type="text" id="service_search" placeholder="Search service name..."></label> <button type="button" id="add_service_btn">Add Selected Service</button></p>
                    <div id="service_search_results"></div>
                    <div id="services_list"></div>
                </div>

                <div class="form-section">
                    <h3>Parts</h3>
                     <p><label>Search Part: <input type="text" id="part_search" placeholder="Search part name/SKU..."></label> <button type="button" id="add_part_btn">Add Selected Part</button></p>
                    <div id="part_search_results"></div>
                    <div id="parts_list"></div>
                </div>

                <div class="form-section">
                    <h3>Assignment & Status</h3>
                    <p>
                        <label>Assign Mechanic:
                            <select name="assigned_mechanic_id">
                                <option value="">-- Not Assigned --</option>
                                <?php foreach($mechanics as $mechanic): ?>
                                <option value="<?= htmlspecialchars($mechanic['id']) ?>"><?= htmlspecialchars($mechanic['full_name'] . ' ('.$mechanic['username'].')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </p>
                    <p>
                        <label>Initial Status:
                            <select name="status">
                                <option value="pending_approval" selected>Pending Approval</option>
                                <option value="approved">Approved (Work Authorized)</option>
                                <option value="in_progress">In Progress</option>
                            </select>
                        </label>
                    </p>
                </div>

                <button type="submit">Create Job Card</button>
            </form>
            <script>
                // Basic JS for dynamic search and add (conceptual)
                // Customer Search
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

                    newVehicleLink.href = `/vehicles/create?customer_id=${customerId}&redirect_to=/job-cards/create`; // Adjust redirect if needed
                    newVehicleLink.style.display='inline';

                    fetch(`/vehicles?customer_id=${customerId}&format=json`) // Need to make vehicle controller output JSON for this
                        .then(response => response.json()) // This requires VehicleController to output JSON
                        .then(data => {
                             let options = '<option value=\"\">-- Select Vehicle --</option>';
                             if (data && data.vehicles && data.vehicles.length > 0) { // Assuming controller returns {vehicles: [...]}
                                data.vehicles.forEach(veh => {
                                    options += `<option value="${veh.id}">${veh.make} ${veh.model} (${veh.license_plate || veh.vin})</option>`;
                                });
                             } else if (data && data.length > 0) { // If controller returns array directly
                                 data.forEach(veh => {
                                    options += `<option value="${veh.id}">${veh.make} ${veh.model} (${veh.license_plate || veh.vin})</option>`;
                                });
                             } else {
                                options = '<option value=\"\">-- No vehicles found for this customer --</option>';
                             }
                            vehicleSelect.innerHTML = options;
                        })
                        .catch(err => {
                            console.error("Error fetching vehicles:", err);
                            vehicleSelect.innerHTML = '<option value=\"\">-- Error loading vehicles --</option>';
                        });
                });

                // Service Search & Add (Simplified)
                const serviceSearchInput = document.getElementById('service_search');
                const serviceSearchResultsDiv = document.getElementById('service_search_results');
                const addServiceBtn = document.getElementById('add_service_btn');
                const servicesListDiv = document.getElementById('services_list');
                let serviceCounter = 0;

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
                serviceSearchResultsDiv.addEventListener('click', function(e){ // Select item
                    if(e.target && e.target.tagName === 'LI'){
                        serviceSearchInput.value = e.target.dataset.name;
                        serviceSearchInput.dataset.selectedId = e.target.dataset.id;
                        serviceSearchInput.dataset.selectedPrice = e.target.dataset.price;
                        serviceSearchResultsDiv.innerHTML = '';
                    }
                });
                addServiceBtn.addEventListener('click', function(){
                    const id = serviceSearchInput.dataset.selectedId;
                    const name = serviceSearchInput.value; // Name from input, might be different if user typed
                    const price = serviceSearchInput.dataset.selectedPrice;
                    if(!id || !name) { alert('Please select a service from search results.'); return; }

                    serviceCounter++;
                    const itemHtml = `<div class="service-item" id="svc_item_${serviceCounter}">
                        <input type="hidden" name="selected_services[]" value="${id}">
                        <strong>${htmlspecialchars(name)}</strong> (Price: ${price})<br>
                        <label>Qty: <input type="number" name="service_quantity[]" value="1" min="0.1" step="0.1" style="width:60px;" data-price="${price}" onchange="updateTotal(this)"></label>
                        <label>Actual Price: <input type="number" name="service_price[]" value="${price}" min="0" step="0.01" style="width:80px;" onchange="updateTotal(this)"></label><br>
                        <label>Desc. Override: <input type="text" name="service_description_override[]" style="width:80%;"></label><br>
                        <label>Notes: <input type="text" name="service_notes[]" style="width:80%;"></label>
                        <button type="button" onclick="this.parentElement.remove()">Remove</button>
                        <span class="total-price">Total: ${price}</span>
                    </div>`;
                    servicesListDiv.insertAdjacentHTML('beforeend', itemHtml);
                    serviceSearchInput.value = ''; serviceSearchInput.dataset.selectedId = '';serviceSearchInput.dataset.selectedPrice = '';
                });

                // Part Search & Add (Similar to Service)
                const partSearchInput = document.getElementById('part_search');
                const partSearchResultsDiv = document.getElementById('part_search_results');
                const addPartBtn = document.getElementById('add_part_btn');
                const partsListDiv = document.getElementById('parts_list');
                let partCounter = 0;

                partSearchInput.addEventListener('keyup', function() {
                    const term = this.value;
                    if (term.length < 2) { partSearchResultsDiv.innerHTML = ''; return; }
                    fetch(`/inventory/items/search?term=${encodeURIComponent(term)}`) // Assumes current user's branch context
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
                        <strong>${htmlspecialchars(name)}</strong> (Unit Price: ${price}) (Avail: ${maxQty})<br>
                        <label>Qty Used: <input type="number" name="part_quantity[]" value="1" min="1" max="${maxQty}" step="1" style="width:60px;" data-price="${price}" onchange="updateTotal(this)"></label>
                        <label>Actual Price: <input type="number" name="part_price[]" value="${price}" min="0" step="0.01" style="width:80px;" onchange="updateTotal(this)"></label><br>
                        <label>Desc. Override: <input type="text" name="part_description_override[]" style="width:80%;"></label><br>
                        <label>Notes: <input type="text" name="part_notes[]" style="width:80%;"></label>
                        <button type="button" onclick="this.parentElement.remove()">Remove</button>
                        <span class="total-price">Total: ${price}</span>
                    </div>`;
                    partsListDiv.insertAdjacentHTML('beforeend', itemHtml);
                    partSearchInput.value = ''; partSearchInput.dataset.selectedId = ''; partSearchInput.dataset.selectedPrice = ''; partSearchInput.dataset.selectedMaxQty = '';
                });

                function updateTotal(inputElement){
                    const itemDiv = inputElement.closest('.service-item, .part-item');
                    const qtyInput = itemDiv.querySelector('input[name^="service_quantity"], input[name^="part_quantity"]');
                    const priceInput = itemDiv.querySelector('input[name^="service_price"], input[name^="part_price"]');
                    const totalSpan = itemDiv.querySelector('.total-price');

                    const qty = parseFloat(qtyInput.value) || 0;
                    const price = parseFloat(priceInput.value) || 0;
                    totalSpan.textContent = "Total: " + (qty * price).toFixed(2);
                }
                function htmlspecialchars(str) { // Basic JS equivalent for display
                     return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                }

            </script>
            <?php
            echo '<p><a href="/job-cards">Back to Job Cards List</a></p>';
        }
    }

    // Placeholder for edit function
    public function edit() {
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']);
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $_SESSION['error_message'] = "Invalid Job Card ID.";
            header('Location: /job-cards');
            exit;
        }
        // TODO: Implement full edit functionality similar to create, loading existing data.
        // This will involve fetching the job card, its services, and parts, and populating the form.
        // Submission would involve updating the main job card record, and then potentially deleting all
        // existing job_card_services and job_card_parts for that job card and re-inserting the new set.
        // Stock adjustments for parts would need careful handling (e.g., reverting old quantities, applying new).
        echo "<h1>Edit Job Card ID: {$id} (Not Implemented Yet)</h1>";
        echo "<p>This functionality requires loading existing data into a form similar to the create form, handling updates to services/parts (which might involve deleting old and inserting new), and careful stock management for parts changes.</p>";
        echo '<p><a href="/job-cards/view?id='.$id.'">View Job Card</a> | <a href="/job-cards">Back to List</a></p>';

    }


    // Other methods: updateStatus, addNotes, assignMechanic etc. would go here.
}
?>
