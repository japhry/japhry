<?php
namespace App\Controllers;

use App\Models\User;

class AuthController {

    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function showLoginForm() {
        // In a real app, you'd render a template
        // For now, just a simple form
        if ($this->isLoggedIn()) {
            header('Location: /dashboard'); // Or wherever your main app page is
            exit;
        }

        $output = '<form action="/login" method="post">
            <h2>Login</h2>';

        if (isset($_SESSION['login_error'])) {
            $output .= '<p style="color:red;">' . htmlspecialchars($_SESSION['login_error']) . '</p>';
            unset($_SESSION['login_error']);
        }

        $output .= '<div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <p>Try with: sysadmin / adminpassword</p>
        <p>Or: mainadmin / branchadminpass</p>
        <p>Or: mech01 / mechanicpass</p>';
        echo $output; // In a real app, use a templating engine
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Username and password are required.';
            header('Location: /login');
            exit;
        }

        $userModel = new User();
        $user = $userModel->findByUsername($username);

        if ($user && $user->is_active && $user->verifyPassword($password)) {
            // Password is correct, user is active
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;
            $_SESSION['user_role'] = $user->role;
            $_SESSION['branch_id'] = $user->branch_id; // Store branch_id if available

            $userModel->updateLastLogin($user->id); // Update last login timestamp

            // Regenerate session ID for security
            session_regenerate_id(true);

            header('Location: /dashboard'); // Redirect to a protected page
            exit;
        } else {
            $_SESSION['login_error'] = 'Invalid username or password, or account inactive.';
            header('Location: /login');
            exit;
        }
    }

    public function logout() {
        // Unset all session variables
        $_SESSION = [];

        // If it's desired to kill the session, also delete the session cookie.
        // Note: This will destroy the session, and not just the session data!
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Finally, destroy the session.
        session_destroy();

        header('Location: /login'); // Redirect to login page
        exit;
    }

    public function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public function getCurrentUserRole(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    public function requireLogin(array $allowedRoles = []) {
        if (!$this->isLoggedIn()) {
            $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
            header('Location: /login');
            exit;
        }
        if (!empty($allowedRoles)) {
            $userRole = $this->getCurrentUserRole();
            if (!in_array($userRole, $allowedRoles)) {
                // User is logged in but doesn't have the required role
                $_SESSION['access_error'] = "You (" . htmlspecialchars($userRole) . ") do not have permission to access this page. Required: " . implode(', ', $allowedRoles);
                header('Location: /dashboard'); // Or an error page
                exit;
            }
        }
    }

    // USER MANAGEMENT METHODS START HERE

    public function listUsers() {
        $this->requireLogin(['system_admin', 'branch_admin']);
        $userModel = new User();
        $currentUserRole = $this->getCurrentUserRole();
        $currentBranchId = $_SESSION['branch_id'] ?? null;

        // System admin sees all users. Branch admin sees users in their branch.
        if ($currentUserRole === 'system_admin') {
            $users = $userModel->getAllUsers(); // Needs to be implemented in UserModel
        } elseif ($currentUserRole === 'branch_admin' && $currentBranchId) {
            $users = $userModel->getUsersByBranch($currentBranchId); // Needs to be implemented
        } else {
            $_SESSION['access_error'] = "You do not have permission to view this list or your branch is not set.";
            header('Location: /dashboard');
            exit;
        }

        echo "<h1>User Management</h1>";
        if (isset($_SESSION['message'])) {
            echo "<p style='color:green;'>" . htmlspecialchars($_SESSION['message']) . "</p>";
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
            unset($_SESSION['error_message']);
        }
        echo "<a href='/users/create'>Create New User</a>";
        if (!empty($users)) {
            echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Branch ID</th><th>Active</th><th>Actions</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['full_name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "<td>" . htmlspecialchars($user['branch_id'] ?? 'Global') . "</td>";
                echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
                echo "<td><a href='/users/edit?id=" . $user['id'] . "'>Edit</a></td>"; // Delete action to be added carefully
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No users found.</p>";
        }
        echo '<p><a href="/dashboard">Back to Dashboard</a></p>';
    }

    public function createUser() {
        $this->requireLogin(['system_admin', 'branch_admin']);
        $userModel = new User();
        $currentUserRole = $this->getCurrentUserRole();
        $currentBranchId = $_SESSION['branch_id'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'username' => $_POST['username'] ?? '',
                'password' => $_POST['password'] ?? '',
                'email' => $_POST['email'] ?? '',
                'full_name' => $_POST['full_name'] ?? '',
                'role' => $_POST['role'] ?? '',
                'branch_id' => $_POST['branch_id'] ?? null, // System admin can set branch
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];

            // Validation
            if (empty($data['username']) || empty($data['password']) || empty($data['email']) || empty($data['role'])) {
                $_SESSION['error_message'] = 'Username, password, email, and role are required.';
                // Ideally, re-render form with old values and error message
                header('Location: /users/create');
                exit;
            }

            // Role assignment security: Branch admin cannot create system_admin or branch_admin for other branches
            if ($currentUserRole === 'branch_admin') {
                if ($data['role'] === 'system_admin' || ($data['role'] === 'branch_admin' && $data['branch_id'] != $currentBranchId)) {
                    $_SESSION['error_message'] = 'You do not have permission to assign this role or branch.';
                    header('Location: /users/create');
                    exit;
                }
                // Force branch_id to current user's branch if they are a branch_admin
                $data['branch_id'] = $currentBranchId;
            }


            $userId = $userModel->create($data);

            if ($userId) {
                $_SESSION['message'] = "User created successfully!";
                header('Location: /users');
            } else {
                $_SESSION['error_message'] = "Failed to create user. Username or email might already exist.";
                header('Location: /users/create');
            }
            exit;
        } else {
            // Display user creation form
            // Fetch branches for system_admin to select from
            $branches = [];
            if ($currentUserRole === 'system_admin') {
                $branchModel = new \App\Models\Branch();
                $branches = $branchModel->getAll();
            }

            echo "<h1>Create New User</h1>";
            // Form HTML (simplified)
            echo "<form action='/users/create' method='POST'>";
            // Fields: username, password, email, full_name, role, branch_id (if sysadmin), is_active
            echo "<div><label>Username: <input type='text' name='username' required></label></div>";
            echo "<div><label>Password: <input type='password' name='password' required></label></div>";
            echo "<div><label>Email: <input type='email' name='email' required></label></div>";
            echo "<div><label>Full Name: <input type='text' name='full_name'></label></div>";

            // Role selection
            $availableRoles = ['staff', 'mechanic']; // Base roles
            if ($currentUserRole === 'system_admin') {
                $availableRoles = ['system_admin', 'branch_admin', 'mechanic', 'staff', 'customer'];
            } elseif ($currentUserRole === 'branch_admin') {
                 $availableRoles = ['branch_admin', 'mechanic', 'staff', 'customer']; // BA can create another BA for their own branch.
            }
            echo "<div><label>Role: <select name='role' required>";
            foreach($availableRoles as $role) {
                 // Branch admin can only create branch_admin for their own branch.
                if ($currentUserRole === 'branch_admin' && $role === 'branch_admin' && $currentBranchId === null) continue;
                echo "<option value='".htmlspecialchars($role)."'>".ucfirst($role)."</option>";
            }
            echo "</select></label></div>";

            // Branch selection (only for system_admin, or fixed for branch_admin)
            if ($currentUserRole === 'system_admin') {
                echo "<div><label>Branch: <select name='branch_id'><option value=''>Global (None)</option>";
                foreach ($branches as $branch) {
                    echo "<option value='".htmlspecialchars($branch['id'])."'>".htmlspecialchars($branch['name'])."</option>";
                }
                echo "</select></label></div>";
            } elseif ($currentUserRole === 'branch_admin' && $currentBranchId) {
                echo "<input type='hidden' name='branch_id' value='".htmlspecialchars($currentBranchId)."'>";
                // Display branch name for confirmation
                $branchModel = new \App\Models\Branch();
                $currentBranchDetails = $branchModel->findById($currentBranchId);
                if ($currentBranchDetails) {
                     echo "<div><strong>Branch: " . htmlspecialchars($currentBranchDetails->name) . "</strong> (auto-assigned)</div>";
                }
            }


            echo "<div><label><input type='checkbox' name='is_active' value='1' checked> Active</label></div>";
            echo "<button type='submit'>Create User</button>";
            echo "</form>";
            echo '<p><a href="/users">Back to Users List</a></p>';
        }
    }

    public function editUser() {
        $this->requireLogin(['system_admin', 'branch_admin']);
        $userModel = new User();
        $currentUserRole = $this->getCurrentUserRole();
        $currentUserId = $this->getCurrentUserId();
        $currentBranchId = $_SESSION['branch_id'] ?? null;

        $userIdToEdit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$userIdToEdit) {
            $_SESSION['error_message'] = "Invalid User ID.";
            header('Location: /users');
            exit;
        }

        $userToEdit = $userModel->findById($userIdToEdit); // Needs to be implemented in UserModel
        if (!$userToEdit) {
            $_SESSION['error_message'] = "User not found.";
            header('Location: /users');
            exit;
        }

        // Security: Branch admin can only edit users in their own branch
        // And cannot edit system_admins or escalate privileges beyond their own.
        if ($currentUserRole === 'branch_admin') {
            if ($userToEdit['branch_id'] != $currentBranchId && $userToEdit['role'] !== 'customer') { // Allow editing global customers?
                $_SESSION['access_error'] = "You can only edit users within your own branch.";
                header('Location: /users');
                exit;
            }
            if ($userToEdit['role'] === 'system_admin') {
                 $_SESSION['access_error'] = "Branch admins cannot edit System Administrators.";
                header('Location: /users');
                exit;
            }
        }
        // Prevent users from editing themselves through this interface (use a separate profile page)
        // if ($userIdToEdit === $currentUserId) {
        //     $_SESSION['error_message'] = "Please use the profile page to edit your own details.";
        //     header('Location: /users');
        //     exit;
        // }


        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'email' => $_POST['email'] ?? '',
                'full_name' => $_POST['full_name'] ?? '',
                'role' => $_POST['role'] ?? '',
                'branch_id' => $_POST['branch_id'] ?? null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            // Password update is optional
            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }

            // Validation
             if (empty($data['email']) || empty($data['role'])) {
                $_SESSION['error_message'] = 'Email and role are required.';
                header("Location: /users/edit?id={$userIdToEdit}");
                exit;
            }

            // Role assignment security for branch_admin
            if ($currentUserRole === 'branch_admin') {
                if ($data['role'] === 'system_admin' || ($data['role'] === 'branch_admin' && $data['branch_id'] != $currentBranchId)) {
                    $_SESSION['error_message'] = 'You do not have permission to assign this role or branch.';
                    header("Location: /users/edit?id={$userIdToEdit}");
                    exit;
                }
                // Force branch_id for non-system_admin roles if user is branch_admin
                if ($data['role'] !== 'system_admin') { // Should not happen due to above check, but defensive
                    $data['branch_id'] = $currentBranchId;
                }
                 // Branch admin cannot change another branch admin of their branch to system_admin
                if ($userToEdit['role'] === 'branch_admin' && $userToEdit['branch_id'] == $currentBranchId && $data['role'] === 'system_admin') {
                    $_SESSION['error_message'] = 'Branch admins cannot escalate other branch admins to system admins.';
                    header("Location: /users/edit?id={$userIdToEdit}");
                    exit;
                }
            }

            // Prevent role escalation by branch admin for users in their branch
            if ($currentUserRole === 'branch_admin' && $userToEdit['branch_id'] == $currentBranchId) {
                // Can't change role to system_admin
                if ($data['role'] === 'system_admin') {
                    $_SESSION['error_message'] = "You cannot change a user's role to System Admin.";
                    header("Location: /users/edit?id={$userIdToEdit}");
                    exit;
                }
                // Can't change another branch_admin's role if they are not the one editing.
                if ($userToEdit['role'] === 'branch_admin' && $userToEdit['id'] !== $currentUserId && $data['role'] !== 'branch_admin') {
                     $_SESSION['error_message'] = "You cannot change another Branch Admin's role.";
                    // Revert role to original if attempted change, or just show error. For now, error.
                    // $data['role'] = $userToEdit['role'];
                    header("Location: /users/edit?id={$userIdToEdit}");
                    exit;
                }
            }


            if ($userModel->updateUser($userIdToEdit, $data)) { // Needs to be implemented in UserModel
                $_SESSION['message'] = "User updated successfully!";
                header('Location: /users');
            } else {
                $_SESSION['error_message'] = "Failed to update user. Email might already exist for another user.";
                header("Location: /users/edit?id={$userIdToEdit}");
            }
            exit;
        } else {
            // Display user edit form
            $branches = [];
            if ($currentUserRole === 'system_admin') {
                $branchModel = new \App\Models\Branch();
                $branches = $branchModel->getAll();
            }

            echo "<h1>Edit User: " . htmlspecialchars($userToEdit['username']) . "</h1>";
            // Form HTML (simplified)
            echo "<form action='/users/edit?id={$userIdToEdit}' method='POST'>";
            echo "<div>Username: <strong>" . htmlspecialchars($userToEdit['username']) . "</strong> (cannot be changed)</div>";
            echo "<div><label>Password: <input type='password' name='password'> (leave blank to keep current)</label></div>";
            echo "<div><label>Email: <input type='email' name='email' value='" . htmlspecialchars($userToEdit['email']) . "' required></label></div>";
            echo "<div><label>Full Name: <input type='text' name='full_name' value='" . htmlspecialchars($userToEdit['full_name'] ?? '') . "'></label></div>";

            // Role selection
            $availableRoles = ['staff', 'mechanic', 'customer'];
            if ($currentUserRole === 'system_admin') {
                 $availableRoles = ['system_admin', 'branch_admin', 'mechanic', 'staff', 'customer'];
            } elseif ($currentUserRole === 'branch_admin') {
                // BA can edit users in their branch to staff, mechanic, customer.
                // BA can edit themselves (role will be branch_admin).
                // BA can edit other BAs in their branch (role will be branch_admin).
                if ($userToEdit['id'] === $currentUserId || $userToEdit['role'] === 'branch_admin') {
                    $availableRoles = ['branch_admin', 'mechanic', 'staff', 'customer'];
                } else {
                    $availableRoles = ['mechanic', 'staff', 'customer'];
                }
            }

            echo "<div><label>Role: <select name='role' required>";
            foreach($availableRoles as $role) {
                // A branch_admin cannot set another user to system_admin.
                if ($currentUserRole === 'branch_admin' && $role === 'system_admin') continue;
                // A branch_admin cannot change another branch_admin's role if that user is not themselves.
                if ($currentUserRole === 'branch_admin' && $userToEdit['role'] === 'branch_admin' && $userToEdit['id'] !== $currentUserId && $role !== 'branch_admin') continue;


                $selected = ($userToEdit['role'] === $role) ? 'selected' : '';
                echo "<option value='".htmlspecialchars($role)."' $selected>".ucfirst($role)."</option>";
            }
            echo "</select></label></div>";

            // Branch selection
            if ($currentUserRole === 'system_admin') {
                echo "<div><label>Branch: <select name='branch_id'><option value=''>Global (None)</option>";
                foreach ($branches as $branch) {
                    $selected = ($userToEdit['branch_id'] == $branch['id']) ? 'selected' : '';
                    echo "<option value='".htmlspecialchars($branch['id'])."' $selected>".htmlspecialchars($branch['name'])."</option>";
                }
                echo "</select></label></div>";
            } elseif ($userToEdit['branch_id']) { // If user has a branch and editor is branch_admin
                echo "<input type='hidden' name='branch_id' value='".htmlspecialchars($userToEdit['branch_id'])."'>";
                 $branchModel = new \App\Models\Branch();
                $currentBranchDetails = $branchModel->findById($userToEdit['branch_id']);
                if ($currentBranchDetails) {
                     echo "<div><strong>Branch: " . htmlspecialchars($currentBranchDetails->name) . "</strong></div>";
                }
            }


            $isActiveChecked = $userToEdit['is_active'] ? 'checked' : '';
            echo "<div><label><input type='checkbox' name='is_active' value='1' $isActiveChecked> Active</label></div>";
            echo "<button type='submit'>Update User</button>";
            echo "</form>";
            echo '<p><a href="/users">Back to Users List</a></p>';
        }
    }
}
?>
