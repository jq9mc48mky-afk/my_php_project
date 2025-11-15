<?php
/**
 * Page for managing asset categories (Admin-only).
 *
 * This file handles:
 * - Security: Restricting access to Admins.
 * - CRUD: Adding, editing, and deleting categories via a modal.
 * - Data Validation: Prevents deletion of categories linked to computers.
 * - Logging: Logs all creation, update, and deletion actions.
 * - AJAX Integration: Uses helper files to fetch and render the category list dynamically.
 *
 * @global PDO $pdo The database connection object.
 * @global string $role The role of the currently logged-in user.
 * @global string $csp_nonce The Content Security Policy nonce.
 */

// $pdo, $role, $csp_nonce are available from index.php
// Security: Only Admins can access this page
if ($role == 'User') {
    $_SESSION['error'] = 'Access Denied.';
    header('Location: index.php?page=dashboard');
    exit;
}

// *** NEW: Require the helper files ***
// These files contain the functions that perform the actual database queries
// and render the HTML for the table body and pagination, allowing for AJAX updates.
require 'ajax_query_helpers.php';
require 'ajax_render_helpers.php';

// Handle Add/Edit (from modal)
if (isset($_POST['save'])) {
    // Determine if this is an 'Add' or 'Edit' operation
    // If 'id' is present, it's an 'Edit'. Otherwise, it's 'Add'.
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'];
    $description = $_POST['description'] ?: null; // Allow empty description
    $admin_user_id = $_SESSION['user_id']; // For logging

    try {
        if ($id) {
            // --- Log what's being changed ---
            // For updates, we fetch the current state *before* updating
            // This allows us to create a detailed log of what changed.
            $stmt_old = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch();
            // --- End log ---

            // Perform the update
            $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
            $stmt->execute([$name, $description, $id]);
            $_SESSION['success'] = 'Category updated successfully.';

            // --- Log Action ---
            // Build a dynamic details string for the log
            $details = "Category (ID: $id) updated.\n";
            if ($old_data['name'] != $name) {
                $details .= "Name changed from '{$old_data['name']}' to '$name'.\n";
            }
            if ($old_data['description'] != $description) {
                $details .= "Description changed.\n";
            }
            log_system_change($pdo, $admin_user_id, 'Category', $details);
            // --- End Log ---

        } else {
            // This is an 'Add' operation
            $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $stmt->execute([$name, $description]);
            $new_id = $pdo->lastInsertId(); // Get the ID of the new category for logging
            $_SESSION['success'] = 'Category added successfully.';

            // --- Log Action ---
            $details = "Category created (ID: $new_id, Name: $name).";
            log_system_change($pdo, $admin_user_id, 'Category', $details);
            // --- End Log ---
        }
        // Redirect back to the main categories page after save
        header('Location: index.php?page=categories');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: index.php?page=categories');
        exit;
    }
}

// Handle Delete
if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $admin_user_id = $_SESSION['user_id'];

    try {
        // --- Get data before deleting for log ---
        $stmt_get = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt_get->execute([$delete_id]);
        $category_to_delete = $stmt_get->fetch();
        // --- End log ---

        // Business Logic: Check if any computers are using this category.
        // This prevents orphaned data (computers with a non-existent category_id).
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM computers WHERE category_id = ?');
        $stmt->execute([$delete_id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'Cannot delete category. It is linked to one or more computers.';
        } else {
            // Safe to delete
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$delete_id]);
            $_SESSION['success'] = 'Category deleted successfully.';

            // --- Log Action ---
            if ($category_to_delete) {
                $details = "Category deleted (ID: $delete_id, Name: {$category_to_delete['name']}).";
                log_system_change($pdo, $admin_user_id, 'Category', $details);
            }
            // --- End Log ---
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
    // Redirect back to the main categories page
    header('Location: index.php?page=categories');
    exit;
}

// --- MODIFIED: LIST DISPLAY LOGIC ---
// This now uses the helper function for the initial page load
$search_term = $_GET['search'] ?? '';

// fetchCategoriesData is defined in 'ajax_query_helpers.php'
// It handles search, pagination, and returns the results and page info.
$data = fetchCategoriesData($pdo, $_GET);
$categories = $data['results'];
$total_pages = $data['total_pages'];
$current_page = $data['current_page'];

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Categories</h1>
    <!-- This button triggers the '#categoryModal' -->
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
        <i class="bi bi-plus-lg"></i> Add New Category
    </button>
</div>

<!-- 
    Filter Form 
    - id="ajax-filter-form": Used by global JS (footer.php) to attach an AJAX submit handler.
    - data-type="categories": Tells the AJAX handler which render function to use for the response.
-->
<div class="card shadow-sm rounded-3 mb-4">
    <div class="card-body bg-light">
        <form method="GET" action="index.php" id="ajax-filter-form" data-type="categories">
            <input type="hidden" name="page" value="categories">
            <div class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Name or Description..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-2 d-flex">
                    <button type="submit" class="btn btn-primary me-2 w-100">Search</button>
                    <!-- This button clears the filter form fields and reloads the page -->
                    <a href="index.php?page=categories" class="btn btn-secondary w-100" id="clear-filters-btn">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 
    Data Table Container
    - id="data-table-container": Used as the wrapper for the loading overlay.
-->
<div class="card shadow-sm rounded-3" id="data-table-container">
    <!-- 
        Loading Overlay 
        - This is shown/hidden by the global AJAX JS (footer.php) during requests.
    -->
    <div class="loading-overlay" id="loading-overlay" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <!-- 
                    Data Table Body
                    - id="data-table-body": This element's content is replaced by the AJAX response.
                -->
                <tbody id="data-table-body">
                    <?php
                    // renderCategoriesTableBody is defined in 'ajax_render_helpers.php'
                    // It generates the <tr> rows for the initial page load.
                    echo renderCategoriesTableBody($categories, csrf_input());
?>
                </tbody>
            </table>
        </div>

        <!-- 
            Pagination Controls
            - id="pagination-controls": This element's content is replaced by the AJAX response.
        -->
        <div id="pagination-controls">
            <?php
            // renderPagination is defined in 'ajax_render_helpers.php'
            // It generates the pagination links for the initial page load.
            echo renderPagination($current_page, $total_pages, $_GET);
?>
        </div>

    </div>
</div>

<!-- Add/Edit Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- This form submits to the same page, handled by the 'if (isset($_POST['save']))' block -->
            <form id="categoryForm" method="POST" action="index.php?page=categories">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalLabel">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo csrf_input(); // Security: CSRF token?>
                    <!-- This hidden 'id' field determines if it's an 'Add' (empty) or 'Edit' (has ID) -->
                    <input type="hidden" name="id" id="categoryId">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
/**
 * Executes when the DOM is fully loaded.
 * Attaches an event listener to the category modal.
 */
document.addEventListener('DOMContentLoaded', function() {
    const categoryModal = document.getElementById('categoryModal');
    if (categoryModal) {
        /**
         * Listens for the 'show.bs.modal' event.
         * This event fires just before the modal is shown.
         * Its purpose is to dynamically populate the form for either
         * adding a new category or editing an existing one.
         *
         * @param {Event} event The modal event.
         */
        categoryModal.addEventListener('show.bs.modal', function(event) {
            // 'event.relatedTarget' is the button that triggered the modal
            const button = event.relatedTarget;
            
            // *** ADDED: Check if button exists (can be null if triggered by JS)
            if (!button) return; 

            // Get data attributes from the 'Edit' button
            const categoryId = button.getAttribute('data-id');
            const categoryName = button.getAttribute('data-name');
            const categoryDescription = button.getAttribute('data-description');

            // Get modal elements
            const modalTitle = categoryModal.querySelector('.modal-title');
            const form = categoryModal.querySelector('form');
            const idInput = categoryModal.querySelector('#categoryId');
            const nameInput = categoryModal.querySelector('#categoryName');
            const descriptionInput = categoryModal.querySelector('#categoryDescription');

            if (categoryId) {
                // --- Edit Mode ---
                // If a categoryId was passed, populate the form with existing data
                modalTitle.textContent = 'Edit Category';
                idInput.value = categoryId;
                nameInput.value = categoryName;
                descriptionInput.value = categoryDescription;
            } else {
                // --- Add Mode ---
                // If no categoryId, reset the form for a new entry
                modalTitle.textContent = 'Add New Category';
                form.reset();
                idInput.value = '';
            }
        });
    }
});
</script>

<?php
?>