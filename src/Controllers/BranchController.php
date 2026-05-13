<?php
namespace App\Controllers;

use App\Models\Branch;
use App\Controllers\AuthController;

class BranchController {
    private $branchModel;
    private $authController;

    public function __construct() {
        $this->branchModel = new Branch();
        $this->authController = new AuthController();
        // Ensure user is logged in and has appropriate role for branch management
        $this->authController->requireLogin(['system_admin', 'branch_admin']); // Allow branch_admin for viewing own branch? TBD
    }

    // List all branches
    public function index() {
        // Only system_admin should see all branches and manage them
        if ($this->authController->getCurrentUserRole() !== 'system_admin') {
            $_SESSION['access_error'] = "You do not have permission to manage all branches.";
            header('Location: /dashboard');
            exit;
        }

        $branches = $this->branchModel->getAll();

        // Basic HTML output for now. Replace with a template view later.
        echo "<h1>Branch Management</h1>";
        if (isset($_SESSION['message'])) {
            echo "<p style='color:green;'>" . htmlspecialchars($_SESSION['message']) . "</p>";
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
            unset($_SESSION['error_message']);
        }

        echo "<a href='/branches/create'>Create New Branch</a>";
        if (!empty($branches)) {
            echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Actions</th></tr>";
            foreach ($branches as $branch) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($branch['id']) . "</td>";
                echo "<td>" . htmlspecialchars($branch['name']) . "</td>";
                echo "<td>" . htmlspecialchars($branch['phone'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($branch['email'] ?? 'N/A') . "</td>";
                echo "<td>
                        <a href='/branches/edit?id=" . $branch['id'] . "'>Edit</a> |
                        <form action='/branches/delete' method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to delete this branch?\");'>
                            <input type='hidden' name='id' value='" . $branch['id'] . "'>
                            <button type='submit'>Delete</button>
                        </form>
                      </td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No branches found.</p>";
        }
        echo '<p><a href="/dashboard">Back to Dashboard</a></p>';
    }

    // Show form to create a new branch or handle creation
    public function create() {
        if ($this->authController->getCurrentUserRole() !== 'system_admin') {
            $_SESSION['access_error'] = "You do not have permission to create branches.";
            header('Location: /dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'address' => $_POST['address'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? ''
            ];

            if (empty($data['name'])) {
                $_SESSION['error_message'] = "Branch name is required.";
                // Redirect back to form with error (or re-render form with error)
                header('Location: /branches/create');
                exit;
            }

            $branchId = $this->branchModel->create($data);

            if ($branchId) {
                $_SESSION['message'] = "Branch created successfully!";
                header('Location: /branches');
            } else {
                $_SESSION['error_message'] = "Failed to create branch. Name might already exist or data is invalid.";
                header('Location: /branches/create'); // Show form again with error
            }
            exit;
        } else {
            // Display creation form
            echo "<h1>Create New Branch</h1>";
            if (isset($_SESSION['error_message'])) {
                echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
                unset($_SESSION['error_message']);
            }
            echo "<form action='/branches/create' method='POST'>
                    <div><label>Name: <input type='text' name='name' required></label></div>
                    <div><label>Address: <textarea name='address'></textarea></label></div>
                    <div><label>Phone: <input type='text' name='phone'></label></div>
                    <div><label>Email: <input type='email' name='email'></label></div>
                    <button type='submit'>Create Branch</button>
                  </form>";
            echo '<p><a href="/branches">Back to Branches List</a></p>';
        }
    }

    // Show form to edit an existing branch or handle update
    public function edit() {
        if ($this->authController->getCurrentUserRole() !== 'system_admin') {
            $_SESSION['access_error'] = "You do not have permission to edit branches.";
            header('Location: /dashboard');
            exit;
        }

        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $_SESSION['error_message'] = "Invalid Branch ID.";
            header('Location: /branches');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'address' => $_POST['address'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? ''
            ];

            $postedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$postedId || $postedId !== $id) {
                 $_SESSION['error_message'] = "Branch ID mismatch.";
                 header('Location: /branches');
                 exit;
            }


            if (empty($data['name'])) {
                $_SESSION['error_message'] = "Branch name is required.";
                header("Location: /branches/edit?id={$id}"); // Re-render form with error
                exit;
            }

            if ($this->branchModel->update($id, $data)) {
                $_SESSION['message'] = "Branch updated successfully!";
                header('Location: /branches');
            } else {
                $_SESSION['error_message'] = "Failed to update branch. Name might already exist or data invalid.";
                header("Location: /branches/edit?id={$id}");
            }
            exit;
        } else {
            $branch = $this->branchModel->findById($id);
            if (!$branch) {
                $_SESSION['error_message'] = "Branch not found.";
                header('Location: /branches');
                exit;
            }

            // Display edit form
            echo "<h1>Edit Branch: " . htmlspecialchars($branch->name) . "</h1>";
             if (isset($_SESSION['error_message'])) {
                echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
                unset($_SESSION['error_message']);
            }
            echo "<form action='/branches/edit?id={$branch->id}' method='POST'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($branch->id) . "'>
                    <div><label>Name: <input type='text' name='name' value='" . htmlspecialchars($branch->name) . "' required></label></div>
                    <div><label>Address: <textarea name='address'>" . htmlspecialchars($branch->address ?? '') . "</textarea></label></div>
                    <div><label>Phone: <input type='text' name='phone' value='" . htmlspecialchars($branch->phone ?? '') . "'></label></div>
                    <div><label>Email: <input type='email' name='email' value='" . htmlspecialchars($branch->email ?? '') . "'></label></div>
                    <button type='submit'>Update Branch</button>
                  </form>";
            echo '<p><a href="/branches">Back to Branches List</a></p>';
        }
    }

    // Handle branch deletion
    public function delete() {
        if ($this->authController->getCurrentUserRole() !== 'system_admin') {
            $_SESSION['access_error'] = "You do not have permission to delete branches.";
            header('Location: /dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                $_SESSION['error_message'] = "Invalid Branch ID for deletion.";
                header('Location: /branches');
                exit;
            }

            // Add check here: are there users, job cards, etc., associated that prevent deletion?
            // For now, we rely on DB constraints or simple delete.
            // A more robust check would query related tables.

            if ($this->branchModel->delete($id)) {
                $_SESSION['message'] = "Branch deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to delete branch. It might be in use or an error occurred.";
            }
            header('Location: /branches');
            exit;
        } else {
            // Deletion should only be via POST
            header('Location: /branches');
            exit;
        }
    }
}
?>
