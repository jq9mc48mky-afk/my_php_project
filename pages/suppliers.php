<?php
/**
 * Page for managing suppliers (Admin-only).
 *
 * This file handles:
 * - Security: Restricting access to Admins.
 * - CRUD: Adding, editing, and deleting suppliers via a modal.
 * - Data Validation: Prevents deletion of suppliers linked to computers.
 * - Logging: Logs all creation, update, and deletion actions.
 * - AJAX Integration: Uses helper files to fetch and render the supplier list dynamically.
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
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'];
    $contact_person = $_POST['contact_person'] ?: null; // Allow empty
    $phone = $_POST['phone'] ?: null; // Allow empty
    $email = $_POST['email'] ?: null; // Allow empty
    $admin_user_id = $_SESSION['user_id']; // For logging

    try {
        if ($id) {
            // --- UPDATE (EDIT) LOGIC ---

            // 1. Log what's being changed (fetch old data first)
            $stmt_old = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch();
            // --- End log ---

            // 2. Perform the update
            $stmt = $pdo->prepare('UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ? WHERE id = ?');
            $stmt->execute([$name, $contact_person, $phone, $email, $id]);
            $_SESSION['success'] = 'Supplier updated successfully.';

            // 3. Log Action (build a detailed message)
            $details = "Supplier (ID: $id) updated.\n";
            if ($old_data['name'] != $name) {
                $details .= "Name changed from '{$old_data['name']}' to '$name'.\n";
            }
            if ($old_data['contact_person'] != $contact_person) {
                $details .= "Contact changed from '{$old_data['contact_person']}' to '$contact_person'.\n";
            }
            if ($old_data['phone'] != $phone) {
                $details .= "Phone changed from '{$old_data['phone']}' to '$phone'.\n";
            }
            if ($old_data['email'] != $email) {
                $details .= "Email changed from '{$old_data['email']}' to '$email'.\n";
            }
            log_system_change($pdo, $admin_user_id, 'Supplier', $details);
            // --- End Log ---

        } else {
            // --- INSERT (ADD) LOGIC ---

            // 1. Perform the insert
            $stmt = $pdo->prepare('INSERT INTO suppliers (name, contact_person, phone, email) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $contact_person, $phone, $email]);
            $new_id = $pdo->lastInsertId(); // Get new ID for logging
            $_SESSION['success'] = 'Supplier added successfully.';

            // 2. Log Action
            $details = "Supplier created (ID: $new_id, Name: $name).";
            log_system_change($pdo, $admin_user_id, 'Supplier', $details);
            // --- End Log ---
        }
        // Redirect back to the main suppliers page
        header('Location: index.php?page=suppliers');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: index.php?page=suppliers');
        exit;
    }
}

// Handle Delete
if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $admin_user_id = $_SESSION['user_id'];

    try {
        // --- Get data before deleting for log ---
        $stmt_get = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
        $stmt_get->execute([$delete_id]);
        $supplier_to_delete = $stmt_get->fetch();
        // --- End log ---

        // Business Logic: Check if any computers are linked to this supplier.
        // This prevents orphaned data.
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM computers WHERE supplier_id = ?');
        $stmt->execute([$delete_id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'Cannot delete supplier. It is linked to one or more computers.';
        } else {
            // Safe to delete
            $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = ?');
            $stmt->execute([$delete_id]);
            $_SESSION['success'] = 'Supplier deleted successfully.';

            // --- Log Action ---
            if ($supplier_to_delete) {
                $details = "Supplier deleted (ID: $delete_id, Name: {$supplier_to_delete['name']}).";
                log_system_change($pdo, $admin_user_id, 'Supplier', $details);
            }
            // --- End Log ---
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
    // Redirect back to the main suppliers page
    header('Location: index.php?page=suppliers');
    exit;
}

// --- MODIFIED: LIST DISPLAY LOGIC ---
// This now uses the helper function 'fetchSuppliersData' for the initial page load
$search_term = $_GET['search'] ?? '';

// fetchSuppliersData is defined in 'ajax_query_helpers.php'
$data = fetchSuppliersData($pdo, $_GET);
$suppliers = $data['results'];
$total_pages = $data['total_pages'];
$current_page = $data['current_page'];

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Suppliers</h1>
    <!-- This button triggers the '#supplierModal' -->
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal">
        <i class="bi bi-plus-lg"></i> Add New Supplier
    </button>
</div>

<!-- 
    Filter Form 
    - id="ajax-filter-form": Used by global JS (footer.php) to attach an AJAX submit handler.
    - data-type="suppliers": Tells the AJAX handler which render function to use for the response.
-->
<div class="card shadow-sm rounded-3 mb-4">
    <div class="card-body bg-light">
        <form method="GET" action="index.php" id="ajax-filter-form" data-type="suppliers">
            <input type="hidden" name="page" value="suppliers">
            <div class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Name, Contact, Phone, Email..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-2 d-flex">
                    <button type="submit" class="btn btn-primary me-2 w-100">Search</button>
                    <a href="index.php?page=suppliers" class="btn btn-secondary w-100" id="clear-filters-btn">Clear</a>
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
    <!-- Loading Overlay: Shown/hidden by global AJAX JS -->
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
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <!-- 
                    Data Table Body
                    - id="data-table-body": This element's content is replaced by the AJAX response.
                -->
                <tbody id="data-table-body">
                    <?php
                    // renderSuppliersTableBody is defined in 'ajax_render_helpers.php'
                    echo renderSuppliersTableBody($suppliers, csrf_input());
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
            echo renderPagination($current_page, $total_pages, $_GET);
?>
        </div>

    </div>
</div>

<!-- Add/Edit Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- This form submits to the 'if (isset($_POST['save']))' block -->
            <form id="supplierForm" method="POST" action="index.php?page=suppliers">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierModalLabel">Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo csrf_input(); ?>
                    <!-- This hidden 'id' field determines if it's an 'Add' or 'Edit' -->
                    <input type="hidden" name="id" id="supplierId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="supplierName" class="form-label">Supplier Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="supplierName" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="supplierContact" class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="supplierContact" name="contact_person">
                        </div>
                        <div class="col-md-6">
                            <label for="supplierPhone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="supplierPhone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="supplierEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="supplierEmail" name="email">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save" class="btn btn-primary">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
/**
 * Executes when the DOM is fully loaded.
 * Attaches an event listener to the supplier modal.
 */
document.addEventListener('DOMContentLoaded', function() {
    const supplierModal = document.getElementById('supplierModal');
    if (supplierModal) {
        /**
         * Listens for the 'show.bs.modal' event.
         * This event fires just before the modal is shown.
         * Its purpose is to dynamically populate the form for either
         * adding a new supplier or editing an existing one.
         *
         * @param {Event} event The modal event.
         */
        supplierModal.addEventListener('show.bs.modal', function(event) {
            // 'event.relatedTarget' is the button that triggered the modal
            const button = event.relatedTarget;
            
            if (!button) return; // Exit if modal was triggered by JS (not a button)

            // Get data attributes from the 'Edit' button
            const supplierId = button.getAttribute('data-id');
            
            // Get modal elements
            const modalTitle = supplierModal.querySelector('.modal-title');
            const form = supplierModal.querySelector('form');
            const idInput = supplierModal.querySelector('#supplierId');
            const nameInput = supplierModal.querySelector('#supplierName');
            const contactInput = supplierModal.querySelector('#supplierContact');
            const phoneInput = supplierModal.querySelector('#supplierPhone');
            const emailInput = supplierModal.querySelector('#supplierEmail');

            if (supplierId) {
                // --- Edit Mode ---
                // Populate the form with data from the button's attributes
                modalTitle.textContent = 'Edit Supplier';
                idInput.value = supplierId;
                nameInput.value = button.getAttribute('data-name');
                contactInput.value = button.getAttribute('data-contact');
                phoneInput.value = button.getAttribute('data-phone');
                emailInput.value = button.getAttribute('data-email');
            } else {
                // --- Add Mode ---
                // Reset the form to be empty
                modalTitle.textContent = 'Add New Supplier';
                form.reset();
                idInput.value = '';
            }
        });
    }
});
</script>

<?php
?>