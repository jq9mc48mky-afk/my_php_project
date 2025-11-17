<?php
/**
 * Partial View: Computers List
 *
 * This file is included by 'computers.php' (case 'list')
 * It renders the main filterable, paginated list of computers.
 *
 * @global PDO $pdo The database connection object.
 * @global string $role The role of the currently logged-in user.
 * @global string $csp_nonce The Content Security Policy nonce.
 *
 * @global string $search_term Current search term.
 * @global string $status_filter Current status filter.
 * @global string $category_filter Current category filter.
 * @global string $assigned_user_filter Current user filter.
 * @global array $categories List of all categories for the dropdown.
 * @global array $statuses List of all statuses for the dropdown.
 * @global array $users List of all active users for the dropdown.
 *
 * @global array $computers The paginated array of computer data from fetchComputersData().
 * @global int $total_pages Total number of pages for pagination.
 * @global int $current_page The current active page.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Computers</h1>
            <div class="d-flex">
                <?php
                // Build the export URL, preserving all current filters *except* pagination ('p')
                $export_params = $_GET;
        unset($export_params['p']);
        $export_params['export'] = 'csv';
        ?>
                <a href="index.php?<?php echo http_build_query($export_params); ?>" class="btn btn-outline-success me-2">
                    <i class="bi bi-download"></i> Export Filtered CSV
                </a>
                <?php if ($role != 'User'): ?>
                    <a href="index.php?page=computers&action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add New Computer
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card shadow-sm rounded-3 mb-4">
            <div class="card-body bg-light">
                <form method="GET" action="index.php" id="ajax-filter-form" data-type="computers">
                    <input type="hidden" name="page" value="computers">
                    
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Asset Tag, Model, Serial..." 
                                   value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="category_filter" class="form-label">Category</label>
                            <select class="form-select" id="category_filter" name="category_filter">
                                <option value="">-- All Categories --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($category_filter == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status_filter" class="form-label">Status</label>
                            <select class="form-select" id="status_filter" name="status_filter">
                                <option value="">-- All Statuses --</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo ($status_filter == $status) ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="assigned_user_id" class="form-label">Assigned To</label>
                            <select class="form-select" id="assigned_user_filter" name="assigned_user_id">
                                <option value="">-- All Users --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($assigned_user_filter == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex">
                            <button type="submit" class="btn btn-primary me-2 w-100">Filter</button>
                            <a href="index.php?page=computers" class="btn btn-secondary w-100" id="clear-filters-btn">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card shadow-sm rounded-3" id="data-table-container">
            <div class="loading-overlay" id="loading-overlay" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <?php if ($role != 'User'): ?>
                                    <th style="width: 10px;">
                                        <input class="form-check-input" type="checkbox" id="selectAllCheckbox" title="Select All">
                                    </th>
                                <?php endif; ?>
                                <th style="width: 70px;">Image</th>
                                <th>Asset Tag</th>
                                <th>Category</th>
                                <th>Model</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <?php if ($role != 'User'): ?>
                                    <th style="width: 250px;">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="data-table-body">
                            <?php
                    // renderComputersTableBody is from 'ajax_render_helpers.php'
                    echo renderComputersTableBody($computers, $role, csrf_input());
        ?>
                        </tbody>
                    </table>
                </div>
                
                <div id="pagination-controls">
                    <?php
                    // renderPagination is from 'ajax_render_helpers.php'
                    echo renderPagination($current_page, $total_pages, $_GET);
        ?>
                </div>

            </div>
            
            <?php if ($role != 'User'): ?>
            <div class="card-footer bg-light" id="bulk-action-footer" style="<?php echo empty($computers) ? 'display: none;' : ''; ?>">
                <form id="bulkActionForm" method="POST" action="index.php?page=computers">
                    <?php echo csrf_input(); ?>
                    <div class="d-flex align-items-center" style="width: 400px;">
                        <label for="bulkActionSelect" class="form-label me-2 mb-0">With selected:</label>
                        <select class="form-select me-2" name="bulk_action" id="bulkActionSelect" required>
                            <option value="" disabled selected>Choose action...</option>
                            <option value="delete">Delete</option>
                            <option value="set_retired">Set Status to 'Retired'</option>
                            <option value="set_repair">Set Status to 'In Repair'</option>
                        </select>
                        <button type="submit" name="bulk_apply" class="btn btn-primary">Apply</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
        
<script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
/**
 * Attaches event listeners for the list view, including
 * TomSelect initialization, 'Select All' logic, and bulk action handling.
 */
document.addEventListener('DOMContentLoaded', function() {
    
    if (typeof window.initTomSelect === 'function') {
        // By setting dropdownParent: null, we override the global
        // 'dropdownParent: body' setting from footer.php.
        // This forces these filter dropdowns to render in their
        // default wrapper, which fixes a layout-breaking bug where they
        // would be "stuck" in the card header.
        const filterOptions = { dropdownParent: null };
        
        window.initTomSelect('#category_filter', filterOptions);
        window.initTomSelect('#status_filter', filterOptions);
        window.initTomSelect('#assigned_user_filter', filterOptions);
    }

    // --- Select All Checkbox Logic ---
    // We attach the listener to the static parent '#data-table-container'
    // This is necessary because the checkboxes in the table body are
    // dynamically replaced via AJAX. This is called "event delegation".
    const tableContainer = document.getElementById('data-table-container');
    if (tableContainer) {
        
        // Listen for "Select All"
        tableContainer.addEventListener('change', function(e) {
            // If the changed element is the 'selectAllCheckbox'
            if (e.target.id === 'selectAllCheckbox') {
                const itemCheckboxes = tableContainer.querySelectorAll('.item-checkbox');
                itemCheckboxes.forEach(checkbox => {
                    checkbox.checked = e.target.checked;
                });
            }
        });

        // Listen for individual item clicks
        tableContainer.addEventListener('change', function(e) {
            // If the changed element is one of the '.item-checkbox'
            if (e.target.classList.contains('item-checkbox')) {
                const selectAllCheckbox = document.getElementById('selectAllCheckbox');
                if (!selectAllCheckbox) return;

                if (!e.target.checked) {
                    // If *any* item is unchecked, uncheck "Select All"
                    selectAllCheckbox.checked = false;
                } else {
                    // If this item was checked, check if *all* items are now checked
                    const itemCheckboxes = tableContainer.querySelectorAll('.item-checkbox');
                    const checkedItems = tableContainer.querySelectorAll('.item-checkbox:checked');
                    if (checkedItems.length === itemCheckboxes.length) {
                        selectAllCheckbox.checked = true;
                    }
                }
            }
        });
    }

    // --- Bulk Action Form Logic ---
    const bulkActionForm = document.getElementById('bulkActionForm');
    if (bulkActionForm) {
        
        bulkActionForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Stop normal form submission
            
            const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            if (checkedBoxes.length === 0) {
                // 'showToast' is a global function from footer.php
                window.showToast('Please select at least one item to apply an action.', 'error');
                return;
            }

            const actionSelect = document.getElementById('bulkActionSelect');
            const actionValue = actionSelect.value;
            const actionText = actionSelect.options[actionSelect.selectedIndex].text;
            
            /**
             * This function dynamically adds the selected checkbox values
             * as hidden inputs to the form and then submits it.
             */
            const finalizeSubmit = () => {
                // Add all checked IDs to the form
                checkedBoxes.forEach(checkbox => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'computer_ids[]';
                    hiddenInput.value = checkbox.value;
                    this.appendChild(hiddenInput);
                });
                // Add the 'bulk_apply' flag to trigger the POST handler
                const bulkApplyInput = document.createElement('input');
                bulkApplyInput.type = 'hidden';
                bulkApplyInput.name = 'bulk_apply';
                bulkApplyInput.value = 'true';
                this.appendChild(bulkApplyInput);
                
                // Submit the form (no longer prevented)
                this.submit();
            };

            // Business Logic: If the action is 'delete', show a confirmation modal first.
            if (actionValue === 'delete') {
                // 'confirmDeleteModal' and related elements are global (in footer.php)
                const customMessage = `Are you sure you want to <strong>permanently "${actionText}"</strong> ${checkedBoxes.length} selected item(s)? All its associated history will be lost. <p><strong>This action cannot be undone.</strong></p>`;
                const modalElement = document.getElementById('confirmDeleteModal');
                const modalBody = document.getElementById('confirmDeleteModalBody');
                const confirmButton = document.getElementById('confirmDeleteButton');
                const confirmModal = bootstrap.Modal.getOrCreateInstance(modalElement);
                
                modalBody.innerHTML = customMessage;
                
                // We must remove any old listeners before adding a new one to prevent
                // accidental multiple submissions.
                confirmButton.replaceWith(confirmButton.cloneNode(true));
                document.getElementById('confirmDeleteButton').addEventListener('click', finalizeSubmit, { once: true });
                
                confirmModal.show();
            } else {
                // For other actions (like status change), submit immediately
                finalizeSubmit();
            }
        });
    }
});
</script>