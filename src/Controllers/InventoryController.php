<?php
namespace App\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryCategory;
use App\Models\Branch; // To get branch list for filtering/assignment
use App\Controllers\AuthController;

class InventoryController {
    private $itemModel;
    private $categoryModel;
    private $branchModel;
    private $authController;

    public function __construct() {
        $this->itemModel = new InventoryItem();
        $this->categoryModel = new InventoryCategory();
        $this->branchModel = new Branch();
        $this->authController = new AuthController();
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff', 'mechanic']);
    }

    // Combined list for items and categories, or could be separate pages
    public function index() {
        // For now, just list items. Category management can be a sub-section or separate.
        $this->listItems();
    }

    // CATEGORY MANAGEMENT
    public function listCategories() {
        $this->authController->requireLogin(['system_admin', 'branch_admin']);
        $categories = $this->categoryModel->getAll();

        echo "<h1>Inventory Categories</h1>";
         if (isset($_SESSION['message'])) {
            echo "<p style='color:green;'>" . htmlspecialchars($_SESSION['message']) . "</p>";
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo "<p style='color:red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
            unset($_SESSION['error_message']);
        }
        echo "<a href='/inventory/categories/create'>Create New Category</a>";
        if (!empty($categories)) {
            echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Description</th><th>Actions</th></tr>";
            foreach ($categories as $cat) {
                echo "<tr><td>{$cat['id']}</td><td>".htmlspecialchars($cat['name'])."</td><td>".htmlspecialchars($cat['description'] ?? '')."</td>";
                echo "<td><a href='/inventory/categories/edit?id={$cat['id']}'>Edit</a> |
                          <form action='/inventory/categories/delete' method='POST' style='display:inline;' onsubmit='return confirm(\"Deleting category will set items in it to NULL category. Proceed?\");'>
                              <input type='hidden' name='id' value='{$cat['id']}'>
                              <button type='submit'>Delete</button>
                          </form></td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No categories found.</p>";
        }
        echo "<p><a href='/inventory'>Back to Inventory</a> | <a href='/dashboard'>Dashboard</a></p>";
    }

    public function createCategory() {
        $this->authController->requireLogin(['system_admin', 'branch_admin']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = ['name' => $_POST['name'] ?? '', 'description' => $_POST['description'] ?? ''];
            if (empty($data['name'])) {
                $_SESSION['error_message'] = "Category name is required.";
                header('Location: /inventory/categories/create'); exit;
            }
            if ($this->categoryModel->findByName($data['name'])) {
                 $_SESSION['error_message'] = "Category name already exists.";
                header('Location: /inventory/categories/create'); exit;
            }
            if ($this->categoryModel->create($data)) {
                $_SESSION['message'] = "Category created.";
                header('Location: /inventory/categories');
            } else {
                $_SESSION['error_message'] = "Failed to create category.";
                header('Location: /inventory/categories/create');
            }
            exit;
        }
        echo "<h1>Create Inventory Category</h1>";
        if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }
        echo "<form method='POST'><label>Name: <input type='text' name='name' required></label><br>
              <label>Description: <textarea name='description'></textarea></label><br>
              <button type='submit'>Create</button></form>";
        echo "<p><a href='/inventory/categories'>Cancel</a></p>";
    }

    public function editCategory() {
        $this->authController->requireLogin(['system_admin', 'branch_admin']);
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) { header('Location: /inventory/categories'); exit; }
        $category = $this->categoryModel->findById($id);
        if (!$category) { $_SESSION['error_message'] = "Category not found."; header('Location: /inventory/categories'); exit; }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = ['name' => $_POST['name'] ?? '', 'description' => $_POST['description'] ?? ''];
            $postedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($postedId !== $id) { $_SESSION['error_message'] = "ID mismatch."; header('Location: /inventory/categories'); exit;}

            if (empty($data['name'])) {
                $_SESSION['error_message'] = "Category name is required.";
                header("Location: /inventory/categories/edit?id={$id}"); exit;
            }
            $existing = $this->categoryModel->findByName($data['name']);
            if ($existing && $existing->id !== $id) {
                 $_SESSION['error_message'] = "Another category with this name already exists.";
                 header("Location: /inventory/categories/edit?id={$id}"); exit;
            }
            if ($this->categoryModel->update($id, $data)) {
                $_SESSION['message'] = "Category updated.";
                header('Location: /inventory/categories');
            } else {
                $_SESSION['error_message'] = "Failed to update category.";
                header("Location: /inventory/categories/edit?id={$id}");
            }
            exit;
        }
        echo "<h1>Edit Inventory Category: ".htmlspecialchars($category->name)."</h1>";
        if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }
        echo "<form method='POST'><input type='hidden' name='id' value='{$id}'>
              <label>Name: <input type='text' name='name' value='".htmlspecialchars($category->name)."' required></label><br>
              <label>Description: <textarea name='description'>".htmlspecialchars($category->description ?? '')."</textarea></label><br>
              <button type='submit'>Update</button></form>";
        echo "<p><a href='/inventory/categories'>Cancel</a></p>";
    }

    public function deleteCategory() {
        $this->authController->requireLogin(['system_admin', 'branch_admin']);
         if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /inventory/categories'); exit; }
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) { $_SESSION['error_message'] = "Invalid ID."; header('Location: /inventory/categories'); exit; }

        if ($this->categoryModel->delete($id)) {
            $_SESSION['message'] = "Category deleted. Items within it are now uncategorized.";
        } else {
            $_SESSION['error_message'] = "Failed to delete category.";
        }
        header('Location: /inventory/categories');
        exit;
    }


    // ITEM MANAGEMENT
    public function listItems() {
        $currentUserRole = $this->authController->getCurrentUserRole();
        $currentBranchId = null;
        if ($currentUserRole === 'branch_admin' || $currentUserRole === 'staff' || $currentUserRole === 'mechanic') {
            $currentBranchId = $_SESSION['branch_id'] ?? null;
             if ($currentBranchId === null && $currentUserRole !== 'system_admin') {
                // If a branch-specific user has no branch_id, they might only see global items.
                // Or deny access. For now, let model handle it by showing global if $currentBranchId is null after check.
            }
        }
        // System admin sees all, others see their branch + global items.
        $items = $this->itemModel->getAll(100, 0, $currentBranchId);

        echo "<h1>Inventory Items</h1>";
        if (isset($_SESSION['message'])) { echo "<p style='color:green;'>".htmlspecialchars($_SESSION['message'])."</p>"; unset($_SESSION['message']); }
        if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }

        echo "<a href='/inventory/items/create'>Add New Item</a> | <a href='/inventory/categories'>Manage Categories</a>";
        if (!empty($items)) {
            echo "<table border='1'><tr><th>ID</th><th>SKU</th><th>Name</th><th>Category</th><th>Branch</th><th>Qty</th><th>Price</th><th>Cost</th><th>Active</th><th>Actions</th></tr>";
            foreach ($items as $item) {
                echo "<tr><td>{$item['id']}</td><td>".htmlspecialchars($item['sku'])."</td><td>".htmlspecialchars($item['name'])."</td>
                      <td>".htmlspecialchars($item['category_name'] ?? 'N/A')."</td>
                      <td>".htmlspecialchars($item['branch_name'] ?? 'Global')."</td>
                      <td>".htmlspecialchars($item['quantity_on_hand'])."</td>
                      <td>".htmlspecialchars(number_format($item['unit_price'],2))."</td>
                      <td>".htmlspecialchars(isset($item['cost_price']) ? number_format($item['cost_price'],2) : 'N/A')."</td>
                      <td>".($item['is_active'] ? 'Yes' : 'No')."</td>";
                echo "<td><a href='/inventory/items/edit?id={$item['id']}'>Edit</a> |
                          <form action='/inventory/items/delete' method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure? Deletion may fail if item is on job cards.\");'>
                              <input type='hidden' name='id' value='{$item['id']}'>
                              <button type='submit'>Delete</button>
                          </form></td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No inventory items found.</p>";
        }
        echo '<p><a href="/dashboard">Back to Dashboard</a></p>';
    }

    public function createItem() {
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']);
        $currentUserRole = $this->authController->getCurrentUserRole();
        $currentBranchId = $_SESSION['branch_id'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'sku' => $_POST['sku'] ?? '',
                'description' => $_POST['description'] ?? '',
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'branch_id' => !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null,
                'quantity_on_hand' => $_POST['quantity_on_hand'] ?? 0,
                'unit_price' => $_POST['unit_price'] ?? '',
                'cost_price' => $_POST['cost_price'] ?? null,
                'reorder_level' => $_POST['reorder_level'] ?? 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];

            if (empty($data['name']) || empty($data['sku']) || $data['unit_price'] === '' || $data['quantity_on_hand'] === '') {
                $_SESSION['error_message'] = "Name, SKU, Unit Price, and Quantity are required.";
                header('Location: /inventory/items/create'); exit;
            }
            // Further validation (numeric, etc.) is in the model.

            // If user is branch admin/staff, force branch_id to their branch if they try to set it to something else or leave it empty for a new branch-specific item
            if (($currentUserRole === 'branch_admin' || $currentUserRole === 'staff') && $currentBranchId) {
                 if (empty($data['branch_id']) || $data['branch_id'] != $currentBranchId) {
                    // Allow staff to create global items if data['branch_id'] is explicitly empty and they don't set it.
                    // But if they *do* set it, it must be their own branch.
                    if (!empty($data['branch_id']) && $data['branch_id'] != $currentBranchId) {
                         $_SESSION['error_message'] = "You can only assign items to your own branch.";
                         header('Location: /inventory/items/create'); exit;
                    }
                    // If they didn't set a branch_id, but are branch specific, assign their branch.
                    // This logic might need refinement: do we want branch staff to create global items?
                    // For now, if they are branch_staff and don't specify branch, it becomes their branch.
                    if(empty($data['branch_id'])) $data['branch_id'] = $currentBranchId;
                 }
            } else if ($currentUserRole === 'system_admin') {
                // Sys admin can choose specific branch or global (null)
                 $data['branch_id'] = !empty($_POST['branch_id_select']) ? (int)$_POST['branch_id_select'] : null;
            }


            if ($this->itemModel->findBySkuAndBranch($data['sku'], $data['branch_id'])) {
                $_SESSION['error_message'] = "SKU '{$data['sku']}' already exists for this branch (or globally if no branch selected).";
                header('Location: /inventory/items/create'); exit;
            }

            if ($this->itemModel->create($data)) {
                $_SESSION['message'] = "Inventory item created.";
                header('Location: /inventory');
            } else {
                $_SESSION['error_message'] = "Failed to create item. Check SKU uniqueness per branch and data validity.";
                header('Location: /inventory/items/create');
            }
            exit;
        }

        $categories = $this->categoryModel->getAll();
        $branches = ($currentUserRole === 'system_admin') ? $this->branchModel->getAll() : [];


        echo "<h1>Create Inventory Item</h1>";
        if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }
        echo "<form method='POST'>";
        echo "<div><label>Name: <input type='text' name='name' required></label></div>";
        echo "<div><label>SKU: <input type='text' name='sku' required></label></div>";
        echo "<div><label>Description: <textarea name='description'></textarea></label></div>";
        echo "<div><label>Category: <select name='category_id'><option value=''>None</option>";
        foreach($categories as $cat) echo "<option value='{$cat['id']}'>".htmlspecialchars($cat['name'])."</option>";
        echo "</select></label></div>";

        if ($currentUserRole === 'system_admin') {
            echo "<div><label>Branch (for branch-specific stock, leave empty for global): <select name='branch_id_select'><option value=''>Global/None</option>";
            foreach($branches as $branch) echo "<option value='{$branch['id']}'>".htmlspecialchars($branch['name'])."</option>";
            echo "</select></label></div>";
        } else if ($currentBranchId) {
            $branchInfo = $this->branchModel->findById($currentBranchId);
            echo "<div><strong>Branch: ".htmlspecialchars($branchInfo->name ?? 'Your Branch')."</strong> (auto-assigned for new items) <input type='hidden' name='branch_id' value='{$currentBranchId}'></div>";
            echo "<p><small>To create a global item, ask a System Administrator.</small></p>";
        }


        echo "<div><label>Quantity on Hand: <input type='number' name='quantity_on_hand' value='0' min='0' required></label></div>";
        echo "<div><label>Unit Price (Selling): <input type='number' name='unit_price' step='0.01' min='0' required></label></div>";
        echo "<div><label>Cost Price (Buying): <input type='number' name='cost_price' step='0.01' min='0'></label></div>";
        echo "<div><label>Reorder Level: <input type='number' name='reorder_level' value='0' min='0'></label></div>";
        echo "<div><label><input type='checkbox' name='is_active' value='1' checked> Active</label></div>";
        echo "<button type='submit'>Create Item</button></form>";
        echo "<p><a href='/inventory'>Cancel</a></p>";
    }

    public function editItem() {
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff']);
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) { header('Location: /inventory'); exit; }

        $item = $this->itemModel->findById($id); // Returns array
        if (!$item) { $_SESSION['error_message'] = "Item not found."; header('Location: /inventory'); exit; }

        $currentUserRole = $this->authController->getCurrentUserRole();
        $currentBranchId = $_SESSION['branch_id'] ?? null;

        // Authorization: System admin can edit anything. Branch admin/staff can only edit items in their branch or global items.
        if (($currentUserRole === 'branch_admin' || $currentUserRole === 'staff') && $item['branch_id'] !== null && $item['branch_id'] != $currentBranchId) {
            $_SESSION['error_message'] = "You do not have permission to edit this item from another branch.";
            header('Location: /inventory'); exit;
        }


        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'sku' => $_POST['sku'] ?? '', // SKU might be non-editable for existing items depending on policy
                'description' => $_POST['description'] ?? '',
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'branch_id' => !empty($_POST['branch_id_select']) ? (int)$_POST['branch_id_select'] : null, // From select for sysadmin
                'quantity_on_hand' => $_POST['quantity_on_hand'] ?? 0,
                'unit_price' => $_POST['unit_price'] ?? '',
                'cost_price' => $_POST['cost_price'] ?? null,
                'reorder_level' => $_POST['reorder_level'] ?? 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            $postedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($postedId !== $id) { $_SESSION['error_message'] = "ID mismatch."; header('Location: /inventory'); exit; }

            // SKU and Branch might be restricted from editing for non-sysadmins or once set.
            // For now, allow sysadmin to change branch. Others cannot change branch from non-null to something else.
             if ($currentUserRole !== 'system_admin') {
                $data['branch_id'] = $item['branch_id']; // Preserve original branch unless sysadmin
                $data['sku'] = $item['sku']; // Preserve original SKU unless sysadmin
            } else {
                 $data['branch_id'] = !empty($_POST['branch_id_select']) ? (int)$_POST['branch_id_select'] : null;
            }


            if (empty($data['name']) || $data['unit_price'] === '' || $data['quantity_on_hand'] === '' || empty($data['sku'])) {
                $_SESSION['error_message'] = "Name, SKU, Unit Price, and Quantity are required.";
                header("Location: /inventory/items/edit?id={$id}"); exit;
            }

            $existingBySku = $this->itemModel->findBySkuAndBranch($data['sku'], $data['branch_id']);
            if ($existingBySku && $existingBySku['id'] !== $id) {
                 $_SESSION['error_message'] = "SKU '{$data['sku']}' already exists for the selected branch/global context.";
                 header("Location: /inventory/items/edit?id={$id}"); exit;
            }


            if ($this->itemModel->update($id, $data)) {
                $_SESSION['message'] = "Item updated.";
                header('Location: /inventory');
            } else {
                $_SESSION['error_message'] = "Failed to update item.";
                header("Location: /inventory/items/edit?id={$id}");
            }
            exit;
        }

        $categories = $this->categoryModel->getAll();
        $branches = ($currentUserRole === 'system_admin') ? $this->branchModel->getAll() : [];

        echo "<h1>Edit Item: ".htmlspecialchars($item['name'])." (SKU: ".htmlspecialchars($item['sku']).")</h1>";
        if (isset($_SESSION['error_message'])) { echo "<p style='color:red;'>".htmlspecialchars($_SESSION['error_message'])."</p>"; unset($_SESSION['error_message']); }
        echo "<form method='POST'><input type='hidden' name='id' value='{$id}'>";
        echo "<div><label>Name: <input type='text' name='name' value='".htmlspecialchars($item['name'])."' required></label></div>";

        if ($currentUserRole === 'system_admin') {
            echo "<div><label>SKU: <input type='text' name='sku' value='".htmlspecialchars($item['sku'])."' required></label></div>";
        } else {
            echo "<div>SKU: <strong>".htmlspecialchars($item['sku'])."</strong> (cannot be changed by your role) <input type='hidden' name='sku' value='".htmlspecialchars($item['sku'])."'></div>";
        }

        echo "<div><label>Description: <textarea name='description'>".htmlspecialchars($item['description'] ?? '')."</textarea></label></div>";
        echo "<div><label>Category: <select name='category_id'><option value=''>None</option>";
        foreach($categories as $cat) {
            $selected = ($item['category_id'] == $cat['id']) ? 'selected' : '';
            echo "<option value='{$cat['id']}' $selected>".htmlspecialchars($cat['name'])."</option>";
        }
        echo "</select></label></div>";

        if ($currentUserRole === 'system_admin') {
            echo "<div><label>Branch: <select name='branch_id_select'><option value=''>Global/None</option>";
            foreach($branches as $branch) {
                $selected = ($item['branch_id'] == $branch['id']) ? 'selected' : '';
                echo "<option value='{$branch['id']}' $selected>".htmlspecialchars($branch['name'])."</option>";
            }
            echo "</select></label></div>";
        } else {
            $itemBranchName = $item['branch_name'] ?? 'Global/None';
            echo "<div>Branch: <strong>".htmlspecialchars($itemBranchName)."</strong> (cannot be changed by your role)</div>";
        }


        echo "<div><label>Quantity on Hand: <input type='number' name='quantity_on_hand' value='".htmlspecialchars($item['quantity_on_hand'])."' min='0' required></label></div>";
        echo "<div><label>Unit Price (Selling): <input type='number' name='unit_price' value='".htmlspecialchars($item['unit_price'])."' step='0.01' min='0' required></label></div>";
        echo "<div><label>Cost Price (Buying): <input type='number' name='cost_price' value='".htmlspecialchars($item['cost_price'] ?? '')."' step='0.01' min='0'></label></div>";
        echo "<div><label>Reorder Level: <input type='number' name='reorder_level' value='".htmlspecialchars($item['reorder_level'] ?? 0)."' min='0'></label></div>";
        $checked = $item['is_active'] ? 'checked' : '';
        echo "<div><label><input type='checkbox' name='is_active' value='1' $checked> Active</label></div>";
        echo "<button type='submit'>Update Item</button></form>";
        echo "<p><a href='/inventory'>Cancel</a></p>";
    }

    public function deleteItem() {
        $this->authController->requireLogin(['system_admin', 'branch_admin']); // Stricter for delete
         if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /inventory'); exit; }
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) { $_SESSION['error_message'] = "Invalid ID."; header('Location: /inventory'); exit; }

        $item = $this->itemModel->findById($id);
        if (!$item) { $_SESSION['error_message'] = "Item not found."; header('Location: /inventory'); exit; }

        // Authorization: System admin can delete anything. Branch admin can only delete items in their branch or global items.
        $currentUserRole = $this->authController->getCurrentUserRole();
        $currentBranchId = $_SESSION['branch_id'] ?? null;
        if ($currentUserRole === 'branch_admin' && $item['branch_id'] !== null && $item['branch_id'] != $currentBranchId) {
            $_SESSION['error_message'] = "You do not have permission to delete this item from another branch.";
            header('Location: /inventory'); exit;
        }

        if ($this->itemModel->delete($id)) {
            $_SESSION['message'] = "Item deleted.";
        } else {
            $_SESSION['error_message'] = "Failed to delete item. It might be in use on job cards (try deactivating).";
        }
        header('Location: /inventory');
        exit;
    }

    // AJAX search for inventory items
    public function searchItems() {
        $this->authController->requireLogin(['system_admin', 'branch_admin', 'staff', 'mechanic']);
        $searchTerm = $_GET['term'] ?? '';
        $branchId = $_SESSION['branch_id'] ?? null; // Search within user's branch context + global
        // System admin can search all branches if a specific filter isn't applied by the UI
        if ($this->authController->getCurrentUserRole() === 'system_admin' && isset($_GET['filter_branch_id'])) {
            $branchId = $_GET['filter_branch_id'] === 'global' ? null : (int)$_GET['filter_branch_id'];
        }


        if (strlen($searchTerm) < 2) {
            echo json_encode([]);
            exit;
        }

        $items = $this->itemModel->searchByNameOrSku($searchTerm, $branchId, true); // Active items only
        header('Content-Type: application/json');
        echo json_encode($items);
        exit;
    }
}
?>
