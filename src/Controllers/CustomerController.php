<?php
namespace App\Controllers;

use App\Models\Customer;
use App\Controllers\AuthController;

class CustomerController {
    private $customerModel;
    private $authController;

    public function __construct() {
        $this->customerModel = new Customer();
        $this->authController = new AuthController();
        // All customer management actions require login
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']);
    }

    public function index() {
        // Any logged-in staff/admin can view customers
        $customers = $this->customerModel->getAll();

        echo "<h1>Customer Management</h1>";
        if (isset($_SESSION['message'])) {
            echo "<p style='color:green;'>" . htmlspecialchars($_SESSION['message']) . "</p>";
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
            unset($_SESSION['error_message']);
        }

        echo "<a href='/customers/create'>Register New Customer</a>";
        if (!empty($customers)) {
            echo "<table border='1'><tr><th>ID</th><th>Full Name</th><th>Company</th><th>Phone</th><th>Email</th><th>Actions</th></tr>";
            foreach ($customers as $customer) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($customer['id']) . "</td>";
                echo "<td>" . htmlspecialchars($customer['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($customer['company_name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($customer['phone'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($customer['email'] ?? 'N/A') . "</td>";
                echo "<td>
                        <a href='/customers/edit?id=" . $customer['id'] . "'>Edit</a> |
                        <a href='/vehicles?customer_id=" . $customer['id'] . "'>Vehicles</a> |
                        <form action='/customers/delete' method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure? Deleting a customer will also delete all their vehicles and related job cards/invoices might be affected!\");'>
                            <input type='hidden' name='id' value='" . $customer['id'] . "'>
                            <button type='submit'>Delete</button>
                        </form>
                      </td>"; // Link to view customer's vehicles
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No customers found.</p>";
        }
        echo '<p><a href="/dashboard">Back to Dashboard</a></p>';
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'full_name' => $_POST['full_name'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? '',
                'address' => $_POST['address'] ?? '',
                'company_name' => $_POST['company_name'] ?? '',
                'tin_number' => $_POST['tin_number'] ?? '',
                'vrn_number' => $_POST['vrn_number'] ?? '',
                // 'user_id' can be linked later if a customer gets portal access
            ];

            if (empty($data['full_name'])) {
                $_SESSION['error_message'] = "Customer full name is required.";
                // Re-render form, ideally with previous values
                header('Location: /customers/create');
                exit;
            }
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = "Invalid email format.";
                header('Location: /customers/create');
                exit;
            }

            // Check if email already exists
            if (!empty($data['email']) && $this->customerModel->findByEmail($data['email'])) {
                $_SESSION['error_message'] = "A customer with this email already exists.";
                header('Location: /customers/create');
                exit;
            }


            $customerId = $this->customerModel->create($data);

            if ($customerId) {
                $_SESSION['message'] = "Customer registered successfully!";
                header('Location: /customers'); // Or to customer details page: /customers/view?id=$customerId
            } else {
                $_SESSION['error_message'] = "Failed to register customer. Email might already exist or data is invalid.";
                header('Location: /customers/create');
            }
            exit;
        } else {
            echo "<h1>Register New Customer</h1>";
             if (isset($_SESSION['error_message'])) {
                echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
                unset($_SESSION['error_message']);
            }
            // Basic form
            echo "<form action='/customers/create' method='POST'>
                    <div><label>Full Name: <input type='text' name='full_name' required></label></div>
                    <div><label>Phone: <input type='tel' name='phone'></label></div>
                    <div><label>Email: <input type='email' name='email'></label></div>
                    <div><label>Address: <textarea name='address'></textarea></label></div>
                    <div><label>Company Name: <input type='text' name='company_name'></label></div>
                    <div><label>TIN Number: <input type='text' name='tin_number'></label></div>
                    <div><label>VRN Number: <input type='text' name='vrn_number'></label></div>
                    <button type='submit'>Register Customer</button>
                  </form>";
            echo '<p><a href="/customers">Back to Customers List</a></p>';
        }
    }

    public function edit() {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $_SESSION['error_message'] = "Invalid Customer ID.";
            header('Location: /customers');
            exit;
        }

        $customer = $this->customerModel->findById($id);
        if (!$customer) {
            $_SESSION['error_message'] = "Customer not found.";
            header('Location: /customers');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'full_name' => $_POST['full_name'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? '',
                'address' => $_POST['address'] ?? '',
                'company_name' => $_POST['company_name'] ?? '',
                'tin_number' => $_POST['tin_number'] ?? '',
                'vrn_number' => $_POST['vrn_number'] ?? '',
            ];
            $postedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
             if (!$postedId || $postedId !== $id) {
                 $_SESSION['error_message'] = "Customer ID mismatch.";
                 header('Location: /customers');
                 exit;
            }


            if (empty($data['full_name'])) {
                $_SESSION['error_message'] = "Full name is required.";
                header("Location: /customers/edit?id={$id}");
                exit;
            }
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = "Invalid email format.";
                 header("Location: /customers/edit?id={$id}");
                exit;
            }

            // Check if email is being changed to one that already exists for another customer
            if (!empty($data['email']) && $data['email'] !== $customer->email) {
                $existingCustomerByEmail = $this->customerModel->findByEmail($data['email']);
                if ($existingCustomerByEmail && $existingCustomerByEmail->id !== $id) {
                    $_SESSION['error_message'] = "Another customer with this email already exists.";
                    header("Location: /customers/edit?id={$id}");
                    exit;
                }
            }


            if ($this->customerModel->update($id, $data)) {
                $_SESSION['message'] = "Customer details updated successfully!";
                header('Location: /customers');
            } else {
                $_SESSION['error_message'] = "Failed to update customer details. Email might already exist or data invalid.";
                header("Location: /customers/edit?id={$id}");
            }
            exit;
        } else {
            echo "<h1>Edit Customer: " . htmlspecialchars($customer->full_name) . "</h1>";
             if (isset($_SESSION['error_message'])) {
                echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
                unset($_SESSION['error_message']);
            }
            echo "<form action='/customers/edit?id={$customer->id}' method='POST'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($customer->id) . "'>
                    <div><label>Full Name: <input type='text' name='full_name' value='" . htmlspecialchars($customer->full_name) . "' required></label></div>
                    <div><label>Phone: <input type='tel' name='phone' value='" . htmlspecialchars($customer->phone ?? '') . "'></label></div>
                    <div><label>Email: <input type='email' name='email' value='" . htmlspecialchars($customer->email ?? '') . "'></label></div>
                    <div><label>Address: <textarea name='address'>" . htmlspecialchars($customer->address ?? '') . "</textarea></label></div>
                    <div><label>Company Name: <input type='text' name='company_name' value='" . htmlspecialchars($customer->company_name ?? '') . "'></label></div>
                    <div><label>TIN Number: <input type='text' name='tin_number' value='" . htmlspecialchars($customer->tin_number ?? '') . "'></label></div>
                    <div><label>VRN Number: <input type='text' name='vrn_number' value='" . htmlspecialchars($customer->vrn_number ?? '') . "'></label></div>
                    <button type='submit'>Update Customer</button>
                  </form>";
            echo '<p><a href="/customers">Back to Customers List</a></p>';
        }
    }

    public function delete() {
        // Deletion only allowed for admins for now, due to cascading potential
        $this->authController->requireLogin(['system_admin', 'branch_admin']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                $_SESSION['error_message'] = "Invalid Customer ID for deletion.";
                header('Location: /customers');
                exit;
            }

            // Add more checks here if needed, e.g., if customer has outstanding invoices.
            // The DB schema currently cascades vehicle deletions.

            if ($this->customerModel->delete($id)) {
                $_SESSION['message'] = "Customer (and their associated vehicles) deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to delete customer. They might have records in other parts of the system preventing deletion (e.g., invoices, payments) or an error occurred.";
            }
            header('Location: /customers');
            exit;
        } else {
            header('Location: /customers'); // Should be POST
            exit;
        }
    }

    // AJAX endpoint for searching customers (e.g., for adding to a job card)
    public function search() {
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']);
        $searchTerm = $_GET['term'] ?? '';

        if (strlen($searchTerm) < 2) { // Minimum search term length
            echo json_encode([]);
            exit;
        }

        $customers = $this->customerModel->searchByNameOrPhone($searchTerm);
        header('Content-Type: application/json');
        echo json_encode($customers);
        exit;
    }
}
?>
