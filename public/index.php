<?php
// Advanced Garage Management System - Front Controller

// Basic routing (will be expanded)
$request_uri = explode('?', $_SERVER['REQUEST_URI'], 2)[0];
$route = trim($request_uri, '/');

// Autoloader for classes (very basic, consider Composer for real projects)
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $class_name) . '.php';
    if (file_exists($file)) {
        require $file;
        return true;
    }
    return false;
});

// Load configuration
$config = require __DIR__ . '/../config/database.php';

// Database connection (example using PDO)
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['user'],
        $config['password'],
        $config['options']
    );
} catch (\PDOException $e) {
    // For now, just die. In a real app, show a user-friendly error page.
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please check server logs. We are working to fix it!");
}

// Basic session start (if not already started)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Instantiate AuthController for login/logout checks
$authController = new App\Controllers\AuthController();


// Simple routing logic
switch ($route) {
    case '':
    case 'dashboard':
        $authController->requireLogin(); // Protect this route
        echo "<h1>Welcome, " . htmlspecialchars($_SESSION['username'] ?? 'Guest') . "!</h1>";
        echo "<p>Your Role: " . htmlspecialchars($_SESSION['user_role'] ?? 'N/A') . "</p>";
        echo "<p>Branch ID: " . htmlspecialchars($_SESSION['branch_id'] ?? 'N/A') . "</p>";
        echo "<p>Dashboard (coming soon)</p>";
        echo '<p><a href="/logout">Logout</a></p>';
        // Example: include __DIR__ . '/../templates/dashboard.php';
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->login();
        } else {
            $authController->showLoginForm();
        }
        break;

    case 'logout':
        $authController->logout();
        break;

    case 'job-cards':
        $authController->requireLogin(['system_admin', 'branch_admin', 'mechanic', 'staff']);
        // Example: $jobCardController = new App\Controllers\JobCardController($pdo);
        // $jobCardController->index();
        echo "<h1>Job Cards List</h1>"; // Placeholder
        echo '<p><a href="/dashboard">Back to Dashboard</a></p>';
        break;

    case 'job-cards/new':
        $authController->requireLogin(['system_admin', 'branch_admin', 'staff']);
        echo "<h1>New Job Card</h1>";
        // For now, let's try to render the sample job card HTML
        // This is a temporary measure to test the visual output
        // We'll create this file in the next step.
        $jobCardTemplatePath = __DIR__ . '/../templates/job_card_sample.html';
        if (file_exists($jobCardTemplatePath)) {
            readfile($jobCardTemplatePath);
        } else {
            echo "<p>Job card template ('templates/job_card_sample.html') not found. We will create it.</p>";
        }
        echo '<p><a href="/dashboard">Back to Dashboard</a></p>';
        break;

    // Branch Management Routes
    case 'branches':
        $branchController = new App\Controllers\BranchController();
        $branchController->index();
        break;
    case 'branches/create':
        $branchController = new App\Controllers\BranchController();
        $branchController->create();
        break;
    case 'branches/edit': // Expects ?id=X
        $branchController = new App\Controllers\BranchController();
        $branchController->edit();
        break;
    case 'branches/delete': // Expects POST with id
        $branchController = new App\Controllers\BranchController();
        $branchController->delete();
        break;

    // User Management Routes (using AuthController for now)
    case 'users': // List users
        $authController->listUsers(); // Method to be added to AuthController
        break;
    case 'users/create': // Show create form or handle POST
        $authController->createUser(); // Method to be added
        break;
    case 'users/edit': // Show edit form or handle POST (expects ?id=X)
        $authController->editUser(); // Method to be added
        break;
    // case 'users/delete': // Handle POST with id (use with caution)
    //     $authController->deleteUser(); // Method to be added
    //     break;

    // Customer Management Routes
    case 'customers': // List customers
        $customerController = new App\Controllers\CustomerController();
        $customerController->index();
        break;
    case 'customers/create':
        $customerController = new App\Controllers\CustomerController();
        $customerController->create();
        break;
    case 'customers/edit': // Expects ?id=X
        $customerController = new App\Controllers\CustomerController();
        $customerController->edit();
        break;
    case 'customers/delete': // Expects POST with id
        $customerController = new App\Controllers\CustomerController();
        $customerController->delete();
        break;
    case 'customers/search': // AJAX search, expects ?term=XYZ
        $customerController = new App\Controllers\CustomerController();
        $customerController->search();
        break;

    // Vehicle Management Routes
    case 'vehicles': // List vehicles, optionally ?customer_id=X
        $vehicleController = new App\Controllers\VehicleController();
        $vehicleController->index();
        break;
    case 'vehicles/create': // Optionally ?customer_id=X to pre-fill
        $vehicleController = new App\Controllers\VehicleController();
        $vehicleController->create();
        break;
    case 'vehicles/edit': // Expects ?id=X
        $vehicleController = new App\Controllers\VehicleController();
        $vehicleController->edit();
        break;
    case 'vehicles/delete': // Expects POST with id
        $vehicleController = new App\Controllers\VehicleController();
        $vehicleController->delete();
        break;
    case 'vehicles/search': // AJAX search, expects ?term=XYZ
        $vehicleController = new App\Controllers\VehicleController();
        $vehicleController->search();
        break;

    // Service Management Routes
    case 'services': // List services
        $serviceController = new App\Controllers\ServiceController();
        $serviceController->index();
        break;
    case 'services/create':
        $serviceController = new App\Controllers\ServiceController();
        $serviceController->create();
        break;
    case 'services/edit': // Expects ?id=X
        $serviceController = new App\Controllers\ServiceController();
        $serviceController->edit();
        break;
    case 'services/delete': // Expects POST with id
        $serviceController = new App\Controllers\ServiceController();
        $serviceController->delete();
        break;
    case 'services/search': // AJAX search, expects ?term=XYZ
        $serviceController = new App\Controllers\ServiceController();
        $serviceController->search();
        break;

    // Inventory Management Routes
    case 'inventory': // Main inventory page (lists items)
        $inventoryController = new App\Controllers\InventoryController();
        $inventoryController->index();
        break;
    case 'inventory/categories': // List categories
        $inventoryController = new App\Controllers\InventoryController();
        $inventoryController->listCategories();
        break;
    case 'inventory/categories/create':
        $inventoryController = new App\Controllers\InventoryController();
        $inventoryController->createCategory();
        break;
    case 'inventory/categories/edit': // ?id=X
        $inventoryController = new App\Controllers\InventoryController();
        $inventoryController->editCategory();
        break;
    case 'inventory/categories/delete': // POST with id
        $inventoryController = new App\Controllers\InventoryController();
        $inventoryController->deleteCategory();
        break;
    case 'inventory/items/create':
        $inventoryController = new App\Controllers\InventoryController();
        $inventoryController->createItem();
        break;
    case 'inventory/items/edit': // ?id=X
        $inventoryController = new App\Controllers\InventoryController();
        $inventoryController->editItem();
        break;
    case 'inventory/items/delete': // POST with id
        $inventoryController = new App\Controllers\InventoryController();
        $inventoryController->deleteItem();
        break;
    case 'inventory/items/search': // AJAX search ?term=XYZ
        $inventoryController = new App\Controllers\InventoryController();
        $inventoryController->searchItems();
        break;

    // Job Card Management Routes
    case 'job-cards': // List Job Cards
        $jobCardController = new App\Controllers\JobCardController();
        $jobCardController->index();
        break;
    case 'job-cards/create':
        $jobCardController = new App\Controllers\JobCardController();
        $jobCardController->create();
        break;
    case 'job-cards/view': // Expects ?id=X
        $jobCardController = new App\Controllers\JobCardController();
        $jobCardController->view();
        break;
    case 'job-cards/edit': // Expects ?id=X
        $jobCardController = new App\Controllers\JobCardController();
        $jobCardController->edit(); // Placeholder for now
        break;
    // Add more job card actions like update status, assign mechanic etc.

    // Quotation Management Routes
    case 'quotations': // List Quotations
        $quotationController = new App\Controllers\QuotationController();
        $quotationController->index();
        break;
    case 'quotations/create':
        $quotationController = new App\Controllers\QuotationController();
        $quotationController->create();
        break;
    case 'quotations/view': // Expects ?id=X
        $quotationController = new App\Controllers\QuotationController();
        $quotationController->view();
        break;
    case 'quotations/edit': // Expects ?id=X
        $quotationController = new App\Controllers\QuotationController();
        $quotationController->edit(); // Placeholder
        break;
    case 'quotations/convert-to-jobcard': // POST with quotation_id
        $quotationController = new App\Controllers\QuotationController();
        $quotationController->convertToJobCard();
        break;
    // Add routes for updating quotation status etc.

    // Invoice Management Routes
    case 'invoices': // List Invoices
        $invoiceController = new App\Controllers\InvoiceController();
        $invoiceController->index();
        break;
    case 'invoices/create': // Optionally ?job_card_id=X or ?quotation_id=X
        $invoiceController = new App\Controllers\InvoiceController();
        $invoiceController->create();
        break;
    case 'invoices/view': // Expects ?id=X
        $invoiceController = new App\Controllers\InvoiceController();
        $invoiceController->view();
        break;
    case 'invoices/edit': // Expects ?id=X
        $invoiceController = new App\Controllers\InvoiceController();
        $invoiceController->edit(); // Placeholder
        break;
    case 'invoices/record-payment': // Expects ?id=X
        $invoiceController = new App\Controllers\InvoiceController();
        $invoiceController->recordPayment();
        break;
    // Add routes for updating invoice status etc.


    // Add more routes here
    default:
        http_response_code(404);
        // include __DIR__ . '/../templates/404.php';
        echo "<h1>404 - Page Not Found</h1>";
        echo '<p><a href="/dashboard">Go to Dashboard</a></p>';
        break;
}

?>
