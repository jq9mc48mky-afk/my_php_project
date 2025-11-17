<?php
/**
 * Partial View: Computer Add/Edit Form
 *
 * This file is included by 'computers.php' (case 'add' / 'edit')
 * It renders the main form for creating and editing a computer asset.
 *
 * @global string $action The current action ('add' or 'edit').
 * @global array|null $computer The computer data (null for 'add', array for 'edit').
 * @global array $categories List of all categories for the dropdown.
 * @global array $suppliers List of all suppliers for the dropdown.
 * @global array $users List of all active users for the dropdown.
 * @global array $statuses List of all statuses for the dropdown.
 * @global string $csp_nonce The Content Security Policy nonce.
 */
?>
<h1 class="mb-4"><?php echo $action == 'add' ? 'Add New' : 'Edit'; ?> Computer</h1>
        <div class="card shadow-sm rounded-3">
            <div class="card-body">
                <form method="POST" action="index.php?page=computers" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>
                    <?php if ($action == 'edit'): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($computer['id']); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($computer['image_filename'] ?? ''); ?>">
                    
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="asset_tag" class="form-label">Asset Tag <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="asset_tag" name="asset_tag" 
                                           value="<?php echo htmlspecialchars($computer['asset_tag'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="model" class="form-label">Model <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="model" name="model" 
                                           value="<?php echo htmlspecialchars($computer['model'] ?? ''); ?>" required>
                                </div>
                                 <div class="col-md-6">
                                    <label for="serial_number" class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                           value="<?php echo htmlspecialchars($computer['serial_number'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?php echo $status; ?>" <?php echo (isset($computer) && $computer['status'] == $status) ? 'selected' : ''; ?>>
                                                <?php echo $status; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">"In Stock" and "Assigned" are best managed via the Check-in/Check-out buttons.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo (isset($computer) && $computer['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="supplier_id" class="form-label">Supplier</label>
                                    <select class="form-select" id="supplier_id" name="supplier_id">
                                        <option value="">Select Supplier</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier['id']; ?>" <?php echo (isset($computer) && $computer['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="purchase_date" class="form-label">Purchase Date</label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                           value="<?php echo htmlspecialchars($computer['purchase_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="warranty_expiry" class="form-label">Warranty Expiry</label>
                                    <input type="date" class="form-control" id="warranty_expiry" name="warranty_expiry" 
                                           value="<?php echo htmlspecialchars($computer['warranty_expiry'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="assigned_to_user_id" class="form-label">Assigned To</label>
                                    <select class="form-select" id="assigned_to_user_id" name="assigned_to_user_id">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo (isset($computer) && $computer['assigned_to_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['full_name']); ?> (User #<?php echo htmlspecialchars($user['username']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <label for="image" class="form-label">Asset Image</label>
                            <div class="card">
                                <div class="card-body text-center">
                                    <?php
                                    // Image display logic: Use placeholder if no image or file not found
                                    $image_path = UPLOAD_DIR . ($computer['image_filename'] ?? 'default.png');
        if (!file_exists($image_path) || empty($computer['image_filename'])) {
            $image_path = 'uploads/placeholder.png';
        }
        ?>
                                    <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                         alt="Asset Image" 
                                         class="img-fluid rounded mb-3 form-asset-img">
                                    <input class="form-control" type="file" id="image" name="image" accept="image/png, image/jpeg, image/gif">
                                    <?php if ($action == 'edit' && !empty($computer['image_filename'])): ?>
                                        <div class="form-text mt-2">Uploading a new image will replace the current one.</div>
                                    <?php else: ?>
                                        <div class="form-text mt-2">Max 5MB. (JPG, PNG, GIF)</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end">
                        <a href="index.php?page=computers" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" name="save" class="btn btn-primary">Save Computer</button>
                    </div>
                </form>
            </div>
        </div>

        <script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
        /**
         * Attaches a submit event listener to the Add/Edit computer form
         * to perform client-side validation *before* submission.
         * This prevents invalid status/user combinations.
         */
        document.addEventListener('DOMContentLoaded', function() {
            // Note: The form's action URL is now just 'index.php?page=computers'
            const computerForm = document.querySelector('form[action="index.php?page=computers"]');
            if (computerForm) {
                const statusSelect = document.getElementById('status');
                const userSelect = document.getElementById('assigned_to_user_id');

                computerForm.addEventListener('submit', function(event) {
                    // Only run this validation if the 'save' button was clicked
                    if (!document.activeElement || document.activeElement.name !== 'save') {
                        return;
                    }

                    const selectedStatus = statusSelect.value;
                    const selectedUser = userSelect.value;
                    let validationMessage = '';

                    // Rule 1: A user is selected, but status is NOT 'Assigned'.
                    if (selectedUser && selectedStatus !== 'Assigned') {
                        validationMessage = `<strong>Invalid Combination:</strong><br>
                            An asset cannot be assigned to a user if its status is 
                            '${selectedStatus}'.<br><br>
                            Please set the status to 'Assigned' or set 'Assigned To' to 'Unassigned'.`;
                    }
                    // Rule 2: Status is 'Assigned', but NO user is selected.
                    else if (selectedStatus === 'Assigned' && !selectedUser) {
                        validationMessage = `<strong>Invalid Combination:</strong><br>
                            An asset with the status 'Assigned' must be assigned to a user.<br><br>
                            Please select a user from the 'Assigned To' list or change the status.`;
                    }

                    // If we have any validation message, stop the form and show the modal
                    if (validationMessage) {
                        event.preventDefault(); // Stop the form submission
                        
                        // 'showValidationModal' is a global function defined in footer.php
                        if (window.showValidationModal) {
                            window.showValidationModal(validationMessage);
                        } else {
                            // Fallback if modal function isn't ready
                            alert(validationMessage.replace(/<br>|<strong>|<\/strong>/g, ' '));
                        }
                    }
                });
            }
        });
        </script>

        <script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
        /**
         * Initializes TomSelect and Flatpickr for the form fields
         * after the DOM is loaded.
         */
        document.addEventListener('DOMContentLoaded', function() {
            // 'initTomSelect' is a global helper function (from footer.php)
            if (typeof window.initTomSelect === 'function') {
                // These selects are on the main page, not in a modal
                window.initTomSelect('#category_id');
                window.initTomSelect('#supplier_id');
                window.initTomSelect('#assigned_to_user_id');

                // *** NEW: Initialize Flatpickr ***
                // 'initFlatpickr' is also a global helper (from footer.php)
                const cspNonce = '<?php echo $csp_nonce; ?>';
                window.initFlatpickr('#purchase_date', {}, cspNonce);
                window.initFlatpickr('#warranty_expiry', {}, cspNonce);
            }
        });
        </script>