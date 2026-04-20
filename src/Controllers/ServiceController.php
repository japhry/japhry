<?php
namespace App\Controllers;

use App\Models\Service;
use App\Controllers\AuthController;

class ServiceController {
    private $serviceModel;
    private $authController;

    public function __construct() {
        $this->serviceModel = new Service();
        $this->authController = new AuthController();
        // Service management typically for admins or specific staff roles
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']);
    }

    public function index() {
        $services = $this->serviceModel->getAll(); // Get all, including inactive for management view

        echo "<h1>Garage Service Management</h1>";
        if (isset($_SESSION['message'])) {
            echo "<p style='color:green;'>" . htmlspecialchars($_SESSION['message']) . "</p>";
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
            unset($_SESSION['error_message']);
        }

        echo "<a href='/services/create'>Add New Service</a>";
        if (!empty($services)) {
            echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Default Price</th><th>Est. Hours</th><th>Active</th><th>Actions</th></tr>";
            foreach ($services as $service) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($service['id']) . "</td>";
                echo "<td>" . htmlspecialchars($service['name']) . "</td>";
                echo "<td>" . htmlspecialchars(number_format($service['default_price'], 2)) . "</td>";
                echo "<td>" . htmlspecialchars($service['estimated_time_hours'] ?? 'N/A') . "</td>";
                echo "<td>" . ($service['is_active'] ? 'Yes' : 'No') . "</td>";
                echo "<td>
                        <a href='/services/edit?id=" . $service['id'] . "'>Edit</a> |
                        <form action='/services/delete' method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure? If this service is on job cards, deletion might fail. Consider deactivating instead.\");'>
                            <input type='hidden' name='id' value='" . $service['id'] . "'>
                            <button type='submit'>Delete</button>
                        </form>
                      </td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No services defined yet.</p>";
        }
        echo '<p><a href="/dashboard">Back to Dashboard</a></p>';
    }

    public function create() {
        $this->authController->requireLogin(['system_admin', 'branch_admin']); // Stricter for creation/editing

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'default_price' => $_POST['default_price'] ?? '',
                'estimated_time_hours' => $_POST['estimated_time_hours'] ?? null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];

            if (empty($data['name']) || !isset($data['default_price']) || $data['default_price'] === '') {
                $_SESSION['error_message'] = "Service name and default price are required.";
                header('Location: /services/create');
                exit;
            }
            if (!is_numeric($data['default_price']) || $data['default_price'] < 0) {
                 $_SESSION['error_message'] = "Default price must be a valid non-negative number.";
                header('Location: /services/create');
                exit;
            }
             if ($data['estimated_time_hours'] !== null && $data['estimated_time_hours'] !== '' && (!is_numeric($data['estimated_time_hours']) || $data['estimated_time_hours'] < 0)) {
                 $_SESSION['error_message'] = "Estimated time, if provided, must be a valid non-negative number.";
                header('Location: /services/create');
                exit;
            }
            if ($data['estimated_time_hours'] === '') $data['estimated_time_hours'] = null;


            // Check if service name already exists
            if ($this->serviceModel->findByName($data['name'])) {
                $_SESSION['error_message'] = "A service with this name already exists.";
                header('Location: /services/create');
                exit;
            }

            $serviceId = $this->serviceModel->create($data);

            if ($serviceId) {
                $_SESSION['message'] = "Service created successfully!";
                header('Location: /services');
            } else {
                $_SESSION['error_message'] = "Failed to create service. Name might already exist or data is invalid.";
                header('Location: /services/create');
            }
            exit;
        } else {
            echo "<h1>Add New Garage Service</h1>";
             if (isset($_SESSION['error_message'])) {
                echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
                unset($_SESSION['error_message']);
            }
            echo "<form action='/services/create' method='POST'>
                    <div><label>Service Name: <input type='text' name='name' required></label></div>
                    <div><label>Description: <textarea name='description'></textarea></label></div>
                    <div><label>Default Price: <input type='number' name='default_price' step='0.01' min='0' required></label></div>
                    <div><label>Est. Time (Hours): <input type='number' name='estimated_time_hours' step='0.1' min='0'></label></div>
                    <div><label><input type='checkbox' name='is_active' value='1' checked> Active</label></div>
                    <button type='submit'>Add Service</button>
                  </form>";
            echo '<p><a href="/services">Back to Services List</a></p>';
        }
    }

    public function edit() {
        $this->authController->requireLogin(['system_admin', 'branch_admin']);
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $_SESSION['error_message'] = "Invalid Service ID.";
            header('Location: /services');
            exit;
        }

        $service = $this->serviceModel->findById($id);
        if (!$service) {
            $_SESSION['error_message'] = "Service not found.";
            header('Location: /services');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'default_price' => $_POST['default_price'] ?? '',
                'estimated_time_hours' => $_POST['estimated_time_hours'] ?? null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            $postedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
             if (!$postedId || $postedId !== $id) {
                 $_SESSION['error_message'] = "Service ID mismatch.";
                 header('Location: /services');
                 exit;
            }


            if (empty($data['name']) || !isset($data['default_price']) || $data['default_price'] === '') {
                $_SESSION['error_message'] = "Service name and default price are required.";
                header("Location: /services/edit?id={$id}");
                exit;
            }
            if (!is_numeric($data['default_price']) || $data['default_price'] < 0) {
                 $_SESSION['error_message'] = "Default price must be a valid non-negative number.";
                header("Location: /services/edit?id={$id}");
                exit;
            }
            if ($data['estimated_time_hours'] !== null && $data['estimated_time_hours'] !== '' && (!is_numeric($data['estimated_time_hours']) || $data['estimated_time_hours'] < 0)) {
                 $_SESSION['error_message'] = "Estimated time, if provided, must be a valid non-negative number.";
                 header("Location: /services/edit?id={$id}");
                exit;
            }
             if ($data['estimated_time_hours'] === '') $data['estimated_time_hours'] = null;

            // Check if name is being changed to one that already exists for another service
            if ($data['name'] !== $service->name) {
                $existingServiceByName = $this->serviceModel->findByName($data['name']);
                if ($existingServiceByName && $existingServiceByName->id !== $id) {
                    $_SESSION['error_message'] = "Another service with this name already exists.";
                    header("Location: /services/edit?id={$id}");
                    exit;
                }
            }

            if ($this->serviceModel->update($id, $data)) {
                $_SESSION['message'] = "Service details updated successfully!";
                header('Location: /services');
            } else {
                $_SESSION['error_message'] = "Failed to update service. Name might conflict or data invalid.";
                header("Location: /services/edit?id={$id}");
            }
            exit;
        } else {
            echo "<h1>Edit Service: " . htmlspecialchars($service->name) . "</h1>";
            if (isset($_SESSION['error_message'])) {
                echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
                unset($_SESSION['error_message']);
            }
            echo "<form action='/services/edit?id={$service->id}' method='POST'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($service->id) . "'>
                    <div><label>Service Name: <input type='text' name='name' value='" . htmlspecialchars($service->name) . "' required></label></div>
                    <div><label>Description: <textarea name='description'>" . htmlspecialchars($service->description ?? '') . "</textarea></label></div>
                    <div><label>Default Price: <input type='number' name='default_price' step='0.01' min='0' value='" . htmlspecialchars($service->default_price) . "' required></label></div>
                    <div><label>Est. Time (Hours): <input type='number' name='estimated_time_hours' step='0.1' min='0' value='" . htmlspecialchars($service->estimated_time_hours ?? '') . "'></label></div>
                    <div><label><input type='checkbox' name='is_active' value='1' " . ($service->is_active ? 'checked' : '') . "> Active</label></div>
                    <button type='submit'>Update Service</button>
                  </form>";
            echo '<p><a href="/services">Back to Services List</a></p>';
        }
    }

    public function delete() {
        $this->authController->requireLogin(['system_admin', 'branch_admin']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                $_SESSION['error_message'] = "Invalid Service ID for deletion.";
                header('Location: /services');
                exit;
            }

            if ($this->serviceModel->delete($id)) {
                $_SESSION['message'] = "Service deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to delete service. It might be in use on job cards (try deactivating it instead) or another error occurred.";
            }
            header('Location: /services');
            exit;
        } else {
            header('Location: /services');
            exit;
        }
    }

    // AJAX search for services
    public function search() {
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff', 'mechanic']);
        $searchTerm = $_GET['term'] ?? '';

        if (strlen($searchTerm) < 2) {
            echo json_encode([]);
            exit;
        }

        $services = $this->serviceModel->searchByName($searchTerm, true); // Only search active services
        header('Content-Type: application/json');
        echo json_encode($services);
        exit;
    }
}
?>
