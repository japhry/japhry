<?php
namespace App\Controllers;

use App\Models\Vehicle;
use App\Models\Customer; // To fetch customer names for forms/lists
use App\Controllers\AuthController;

class VehicleController {
    private $vehicleModel;
    private $customerModel;
    private $authController;

    public function __construct() {
        $this->vehicleModel = new Vehicle();
        $this->customerModel = new Customer();
        $this->authController = new AuthController();
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff', 'mechanic']);
    }

    // List vehicles, optionally filtered by customer_id
    public function index() {
        $customerId = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);
        $format = filter_input(INPUT_GET, 'format', FILTER_SANITIZE_STRING);

        $pageTitle = "All Vehicles";
        $customerName = null;
        $vehicles = [];

        if ($customerId) {
            $vehicles = $this->vehicleModel->getAllByCustomer($customerId);
            $customer = $this->customerModel->findById($customerId);
            if ($customer) {
                $customerName = $customer->full_name;
                $pageTitle = "Vehicles for " . htmlspecialchars($customerName);
            } elseif ($format !== 'json') { // Don't set error if JSON is requested for a non-existent customer, just return empty
                 $_SESSION['error_message'] = "Customer not found for the provided ID.";
            }
        } else {
            $vehicles = $this->vehicleModel->getAll();
        }

        if ($format === 'json') {
            header('Content-Type: application/json');
            // For vehicle dropdown, we just need basic info
            $simpleVehicles = array_map(function($v) {
                return [
                    'id' => $v['id'],
                    'make' => $v['make'],
                    'model' => $v['model'],
                    'license_plate' => $v['license_plate'],
                    'vin' => $v['vin']
                ];
            }, $vehicles);
            echo json_encode($simpleVehicles); // Simpler structure for dropdown
            exit;
        }

        // HTML Output
        echo "<h1>{$pageTitle}</h1>";
        if (isset($_SESSION['message'])) {
            echo "<p style='color:green;'>" . htmlspecialchars($_SESSION['message']) . "</p>";
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
            unset($_SESSION['error_message']);
        }

        $createLink = "/vehicles/create";
        if ($customerId) {
            $createLink .= "?customer_id=" . $customerId;
        }
        echo "<a href='{$createLink}'>Add New Vehicle</a>";

        if ($customerId && $customerName === null && !isset($_SESSION['error_message'])) {
             echo "<p>No customer found for ID {$customerId}, showing all vehicles instead.</p>";
        }

        if (!empty($vehicles)) {
            echo "<table border='1'><tr><th>ID</th><th>Owner</th><th>Make</th><th>Model</th><th>Year</th><th>VIN</th><th>License Plate</th><th>Actions</th></tr>";
            foreach ($vehicles as $vehicle) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($vehicle['id']) . "</td>";
                echo "<td><a href='/customers/edit?id=" . htmlspecialchars($vehicle['customer_id']) . "'>" . htmlspecialchars($vehicle['customer_full_name']) . "</a></td>";
                echo "<td>" . htmlspecialchars($vehicle['make'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($vehicle['model'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($vehicle['year'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($vehicle['vin'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($vehicle['license_plate'] ?? '') . "</td>";
                echo "<td>
                        <a href='/vehicles/edit?id=" . $vehicle['id'] . "'>Edit</a> |
                        <form action='/vehicles/delete' method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure? This might affect related job cards.\");'>
                            <input type='hidden' name='id' value='" . $vehicle['id'] . "'>
                            <button type='submit'>Delete</button>
                        </form>
                      </td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            if ($customerId && $customerName) {
                 echo "<p>No vehicles found for " . htmlspecialchars($customerName) . ".</p>";
            } else {
                echo "<p>No vehicles found in the system.</p>";
            }
        }
        if ($customerId) {
            echo '<p><a href="/customers/edit?id='.$customerId.'">Back to Customer</a> | <a href="/vehicles">View All Vehicles</a></p>';
        }
        echo '<p><a href="/dashboard">Back to Dashboard</a></p>';
    }

    public function create() {
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']);
        $preselectedCustomerId = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);
        $customer = null;
        if ($preselectedCustomerId) {
            $customer = $this->customerModel->findById($preselectedCustomerId);
            if (!$customer) {
                $_SESSION['error_message'] = "Customer with ID {$preselectedCustomerId} not found. Please select a valid customer.";
                $preselectedCustomerId = null; // Unset if not found
            }
        }


        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $customerId = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
            if (!$customerId) {
                $_SESSION['error_message'] = "A customer must be selected or specified.";
                header('Location: /vehicles/create' . ($preselectedCustomerId ? '?customer_id='.$preselectedCustomerId : ''));
                exit;
            }

            // Ensure the selected customer exists
            $selectedCustomer = $this->customerModel->findById($customerId);
            if (!$selectedCustomer) {
                 $_SESSION['error_message'] = "Invalid customer selected.";
                 header('Location: /vehicles/create' . ($preselectedCustomerId ? '?customer_id='.$preselectedCustomerId : ''));
                 exit;
            }

            $data = [
                'customer_id' => $customerId,
                'make' => $_POST['make'] ?? '',
                'model' => $_POST['model'] ?? '',
                'year' => $_POST['year'] ?? '',
                'vin' => $_POST['vin'] ?? '',
                'license_plate' => $_POST['license_plate'] ?? '',
                'color' => $_POST['color'] ?? '',
                'notes' => $_POST['notes'] ?? '',
            ];

            if (empty($data['vin']) && empty($data['license_plate'])) {
                $_SESSION['error_message'] = "Either VIN or License Plate is required.";
                header('Location: /vehicles/create?customer_id='.$customerId);
                exit;
            }

            // Check for duplicate VIN or License Plate if provided
            if (!empty($data['vin'])) {
                $existingByVin = $this->vehicleModel->findByVin($data['vin']);
                if ($existingByVin) {
                    $_SESSION['error_message'] = "A vehicle with this VIN (".$data['vin'].") already exists (ID: ".$existingByVin->id.").";
                    header('Location: /vehicles/create?customer_id='.$customerId);
                    exit;
                }
            }
            if (!empty($data['license_plate'])) {
                 $existingByPlate = $this->vehicleModel->findByLicensePlate($data['license_plate']);
                 if ($existingByPlate) {
                    $_SESSION['error_message'] = "A vehicle with this License Plate (".$data['license_plate'].") already exists (ID: ".$existingByPlate->id.").";
                    header('Location: /vehicles/create?customer_id='.$customerId);
                    exit;
                }
            }


            $vehicleId = $this->vehicleModel->create($data);

            if ($vehicleId) {
                $_SESSION['message'] = "Vehicle added successfully for " . htmlspecialchars($selectedCustomer->full_name) . "!";
                header('Location: /vehicles?customer_id=' . $customerId);
            } else {
                $_SESSION['error_message'] = "Failed to add vehicle. VIN or License Plate might already exist, or data is invalid.";
                header('Location: /vehicles/create?customer_id=' . $customerId);
            }
            exit;
        } else {
            // Display vehicle creation form
            echo "<h1>Add New Vehicle</h1>";
            if (isset($_SESSION['error_message'])) {
                echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
                unset($_SESSION['error_message']);
            }

            $customers = $this->customerModel->getAll(); // Fetch all customers for dropdown

            echo "<form action='/vehicles/create' method='POST'>";
            echo "<div><label>Customer: <select name='customer_id' required>";
            if ($preselectedCustomerId && $customer) {
                 echo "<option value='".htmlspecialchars($customer->id)."' selected>".htmlspecialchars($customer->full_name)." (ID: ".$customer->id.")</option>";
            } else {
                echo "<option value=''>-- Select Customer --</option>";
            }
            foreach ($customers as $cust) {
                // Avoid re-listing pre-selected customer
                if ($preselectedCustomerId && $cust['id'] == $preselectedCustomerId) continue;
                echo "<option value='".htmlspecialchars($cust['id'])."'>".htmlspecialchars($cust['full_name'])." (ID: ".$cust['id'].")</option>";
            }
            echo "</select></label> Or <a href='/customers/create?redirect_to=/vehicles/create'>Register New Customer</a></div>";

            echo "<div><label>Make: <input type='text' name='make'></label></div>";
            echo "<div><label>Model: <input type='text' name='model'></label></div>";
            echo "<div><label>Year: <input type='number' name='year' min='1900' max='".(date('Y')+1)."'></label></div>";
            echo "<div><label>VIN: <input type='text' name='vin'></label></div>";
            echo "<div><label>License Plate: <input type='text' name='license_plate'></label></div>";
            echo "<div><label>Color: <input type='text' name='color'></label></div>";
            echo "<div><label>Notes: <textarea name='notes'></textarea></label></div>";
            echo "<button type='submit'>Add Vehicle</button>";
            echo "</form>";
            if ($preselectedCustomerId) {
                 echo '<p><a href="/vehicles?customer_id='.$preselectedCustomerId.'">Back to Customer Vehicles</a></p>';
            } else {
                echo '<p><a href="/vehicles">Back to All Vehicles</a></p>';
            }
        }
    }

    public function edit() {
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']);
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $_SESSION['error_message'] = "Invalid Vehicle ID.";
            header('Location: /vehicles');
            exit;
        }

        $vehicle = $this->vehicleModel->findById($id);
        if (!$vehicle) {
            $_SESSION['error_message'] = "Vehicle not found.";
            header('Location: /vehicles');
            exit;
        }

        $ownerCustomer = $this->customerModel->findById($vehicle->customer_id);


        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $customerId = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
             if (!$customerId) {
                $_SESSION['error_message'] = "Customer ID is required.";
                header("Location: /vehicles/edit?id={$id}");
                exit;
            }
            // Ensure the selected customer exists
            $selectedCustomer = $this->customerModel->findById($customerId);
            if (!$selectedCustomer) {
                 $_SESSION['error_message'] = "Invalid customer selected for vehicle.";
                 header("Location: /vehicles/edit?id={$id}");
                 exit;
            }

            $data = [
                'customer_id' => $customerId,
                'make' => $_POST['make'] ?? '',
                'model' => $_POST['model'] ?? '',
                'year' => $_POST['year'] ?? '',
                'vin' => $_POST['vin'] ?? '',
                'license_plate' => $_POST['license_plate'] ?? '',
                'color' => $_POST['color'] ?? '',
                'notes' => $_POST['notes'] ?? '',
            ];
             $postedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
             if (!$postedId || $postedId !== $id) {
                 $_SESSION['error_message'] = "Vehicle ID mismatch.";
                 header('Location: /vehicles');
                 exit;
            }

            if (empty($data['vin']) && empty($data['license_plate'])) {
                $_SESSION['error_message'] = "Either VIN or License Plate is required.";
                header("Location: /vehicles/edit?id={$id}");
                exit;
            }

            // Check for duplicate VIN or License Plate if changed
            if (!empty($data['vin']) && $data['vin'] !== $vehicle->vin) {
                $existingByVin = $this->vehicleModel->findByVin($data['vin']);
                if ($existingByVin && $existingByVin->id !== $id) {
                    $_SESSION['error_message'] = "Another vehicle with this VIN (".$data['vin'].") already exists.";
                    header("Location: /vehicles/edit?id={$id}");
                    exit;
                }
            }
            if (!empty($data['license_plate']) && $data['license_plate'] !== $vehicle->license_plate) {
                 $existingByPlate = $this->vehicleModel->findByLicensePlate($data['license_plate']);
                 if ($existingByPlate && $existingByPlate->id !== $id) {
                    $_SESSION['error_message'] = "Another vehicle with this License Plate (".$data['license_plate'].") already exists.";
                    header("Location: /vehicles/edit?id={$id}");
                    exit;
                }
            }


            if ($this->vehicleModel->update($id, $data)) {
                $_SESSION['message'] = "Vehicle details updated successfully!";
                header('Location: /vehicles?customer_id=' . $data['customer_id']);
            } else {
                $_SESSION['error_message'] = "Failed to update vehicle. VIN or License Plate might conflict, or data invalid.";
                header("Location: /vehicles/edit?id={$id}");
            }
            exit;
        } else {
            echo "<h1>Edit Vehicle: " . htmlspecialchars($vehicle->make . ' ' . $vehicle->model) . "</h1>";
            if ($ownerCustomer) {
                echo "<p>Owner: <a href='/customers/edit?id=".$ownerCustomer->id."'>" . htmlspecialchars($ownerCustomer->full_name) . "</a></p>";
            }
             if (isset($_SESSION['error_message'])) {
                echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
                unset($_SESSION['error_message']);
            }

            $customers = $this->customerModel->getAll();

            echo "<form action='/vehicles/edit?id={$vehicle->id}' method='POST'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($vehicle->id) . "'>
                    <div><label>Customer: <select name='customer_id' required>";
            foreach ($customers as $cust) {
                $selected = ($cust['id'] == $vehicle->customer_id) ? 'selected' : '';
                echo "<option value='".htmlspecialchars($cust['id'])."' $selected>".htmlspecialchars($cust['full_name'])."</option>";
            }
            echo "</select></label></div>";

            echo "<div><label>Make: <input type='text' name='make' value='" . htmlspecialchars($vehicle->make ?? '') . "'></label></div>";
            echo "<div><label>Model: <input type='text' name='model' value='" . htmlspecialchars($vehicle->model ?? '') . "'></label></div>";
            echo "<div><label>Year: <input type='number' name='year' min='1900' max='".(date('Y')+1)."' value='" . htmlspecialchars($vehicle->year ?? '') . "'></label></div>";
            echo "<div><label>VIN: <input type='text' name='vin' value='" . htmlspecialchars($vehicle->vin ?? '') . "'></label></div>";
            echo "<div><label>License Plate: <input type='text' name='license_plate' value='" . htmlspecialchars($vehicle->license_plate ?? '') . "'></label></div>";
            echo "<div><label>Color: <input type='text' name='color' value='" . htmlspecialchars($vehicle->color ?? '') . "'></label></div>";
            echo "<div><label>Notes: <textarea name='notes'>" . htmlspecialchars($vehicle->notes ?? '') . "</textarea></label></div>";
            echo "<button type='submit'>Update Vehicle</button>";
            echo "</form>";
            echo '<p><a href="/vehicles?customer_id='.$vehicle->customer_id.'">Back to Owner\'s Vehicles List</a></p>';
        }
    }

    public function delete() {
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']); // Staff can delete if necessary? Or only admins?
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                $_SESSION['error_message'] = "Invalid Vehicle ID for deletion.";
                header('Location: /vehicles');
                exit;
            }

            $vehicle = $this->vehicleModel->findById($id);
            if (!$vehicle) {
                 $_SESSION['error_message'] = "Vehicle not found for deletion.";
                header('Location: /vehicles');
                exit;
            }
            $customerId = $vehicle->customer_id; // To redirect back to customer's vehicle list

            if ($this->vehicleModel->delete($id)) {
                $_SESSION['message'] = "Vehicle deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to delete vehicle. It might have related job cards or other records preventing deletion.";
            }
            header('Location: /vehicles' . ($customerId ? '?customer_id='.$customerId : ''));
            exit;
        } else {
            header('Location: /vehicles');
            exit;
        }
    }

    // AJAX search for vehicles
    public function search() {
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff', 'mechanic']);
        $searchTerm = $_GET['term'] ?? '';

        if (strlen($searchTerm) < 2) {
            echo json_encode([]);
            exit;
        }

        $vehicles = $this->vehicleModel->searchByPlateOrVIN($searchTerm);
        header('Content-Type: application/json');
        echo json_encode($vehicles);
        exit;
    }
}
?>
