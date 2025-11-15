<?php
/**
 * Page for managing computer assets.
 *
 * This is the core file for asset management. It handles:
 * - Security: Restricting 'add', 'edit', 'delete', 'checkout', 'checkin' to Admins.
 * Users can only view the main list (though this file is Admin-only by default in index.php).
 * - CRUD: Add, Edit, Delete computers.
 * - Check-in/Check-out: Dedicated forms and handlers for changing asset status and assignment.
 * - Bulk Actions: Deleting or updating the status of multiple assets at once.
 * - Image Uploads: Handles image validation, resizing (to thumbnail), and storage.
 * - Logging: Logs all C/U/D, check-in/out, and bulk actions to the 'asset_log'.
 * - CSV Export: Generates a CSV file based on the current filters.
 * - AJAX Integration: Uses helper files to fetch and render the computer list dynamically.
 *
 * @global PDO $pdo The database connection object.
 * @global string $role The role of the currently logged-in user.
 * @global string $csp_nonce The Content Security Policy nonce.
 */

// $pdo, $role, $csp_nonce are available from index.php

// --- Constants ---
define('UPLOAD_DIR', 'uploads/'); // Directory for full-size images
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']); // Allowed image types

// --- Helper Files ---
// These files contain functions for database queries, HTML rendering, and image processing.
require 'ajax_query_helpers.php';
require 'ajax_render_helpers.php';
require 'image_helper.php';

// Determine the current action (e.g., 'list', 'add', 'edit')
$action = $_GET['action'] ?? 'list';
// Get the admin's user ID for logging purposes
$admin_user_id = $_SESSION['user_id'];

// *** UPDATED: CSV EXPORT HANDLER ***
// This block intercepts the 'list' action if 'export=csv' is in the URL.
if ($action == 'list' && isset($_GET['export']) && $_GET['export'] == 'csv') {

    // We use the *exact same* query helper as the main list.
    // This ensures the export matches the filters applied on the page.
    $data = fetchComputersData($pdo, $_GET);
    $results = $data['results'];

    // Set HTTP headers to force a file download
    $filename = 'computers_export_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Open 'php://output' as a file stream
    $output = fopen('php://output', 'w');

    // Write the CSV header row
    fputcsv($output, [
        'Asset Tag', 'Category', 'Model', 'Serial Number', 'Status',
        'Assigned To (Name)', 'Assigned To (Username)',
        'Supplier', 'Purchase Date', 'Warranty Expiry'
    ]);

    // Loop through the query results and write each row to the CSV
    foreach ($results as $row) {
        $csv_row = [
            $row['asset_tag'],
            $row['category_name'],
            $row['model'],
            $row['serial_number'],
            $row['status'],
            $row['assigned_to_full_name'],
            $row['assigned_to_username'],
            $row['supplier_name'],
            $row['purchase_date'],
            $row['warranty_expiry']
        ];
        fputcsv($output, $csv_row);
    }

    fclose($output);
    exit; // Stop script execution after generating the CSV
}

// ... (ALL POST, CHECK-IN, CHECK-OUT, DELETE, and BULK ACTION handlers remain exactly the same) ...
// ... (No changes to 'add', 'edit', 'checkout' cases in the switch statement) ...

// --- POST HANDLER: ADD/EDIT COMPUTER ---
if (isset($_POST['save'])) {
    // Security: Only Admins can save
    if ($role == 'User') {
        $_SESSION['error'] = 'Access Denied.';
        header('Location: index.php?page=dashboard');
        exit;
    }
    // Collect form data
    $id = $_POST['id'] ?? null; // 'id' will be present for 'Edit', null for 'Add'
    $asset_tag = $_POST['asset_tag'];
    $category_id = $_POST['category_id'] ?: null;
    $supplier_id = $_POST['supplier_id'] ?: null;
    $model = $_POST['model'];
    $serial_number = $_POST['serial_number'] ?: null;
    $purchase_date = $_POST['purchase_date'] ?: null;
    $warranty_expiry = $_POST['warranty_expiry'] ?: null;
    $assigned_to_user_id = $_POST['assigned_to_user_id'] ?: null;
    $status = $_POST['status'];

    // *** MODIFIED: Server-side validation as a security fallback ***
    // This duplicates the client-side JS validation to ensure data integrity
    // even if the user bypasses the JavaScript.

    // Rule 1: If a user is assigned, status MUST be 'Assigned'.
    // An asset can't be 'In Stock' and also assigned to someone.
    if (!empty($assigned_to_user_id) && $status !== 'Assigned') {
        $_SESSION['error'] = "Validation Error: An asset cannot be assigned to a user unless its status is 'Assigned'.";
        header('Location: ' . $_SERVER['REQUEST_URI']); // Reload the form
        exit;
    }
    // Rule 2: If status is 'Assigned', a user MUST be selected.
    // An asset can't be 'Assigned' to nobody.
    if ($status === 'Assigned' && empty($assigned_to_user_id)) {
        $_SESSION['error'] = "Validation Error: An asset with status 'Assigned' must be assigned to a user.";
        header('Location: ' . $_SERVER['REQUEST_URI']); // Reload the form
        exit;
    }
    // *** END MODIFIED VALIDATION ***

    $image_to_save = $_POST['current_image'] ?? null; // Start with the existing image (if any)
    $old_image_filename = $_POST['current_image'] ?? null;

    // Check if a *new* image was uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // 'process_and_save_image' is a custom function from 'image_helper.php'
        // It handles validation, resizing, and saving both original and thumbnail.
        $upload_result = process_and_save_image($_FILES['image']);

        if (is_array($upload_result)) { // Failure (returns an array of errors)
            $_SESSION['error'] = implode('<br>', $upload_result);
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else { // Success (returns the new filename)
            $image_to_save = $upload_result;
        }
    }

    try {
        if ($id) {
            // --- UPDATE (EDIT) LOGIC ---

            // 1. Get the *old* computer data for logging purposes
            $stmt_old = $pdo->prepare('SELECT * FROM computers WHERE id = ?');
            $stmt_old->execute([$id]);
            $old_computer = $stmt_old->fetch();

            // 2. Perform the update
            $stmt = $pdo->prepare('
                UPDATE computers SET asset_tag = ?, category_id = ?, supplier_id = ?, model = ?, 
                image_filename = ?, serial_number = ?, purchase_date = ?, warranty_expiry = ?, 
                assigned_to_user_id = ?, status = ?
                WHERE id = ?
            ');
            $stmt->execute([
                $asset_tag, $category_id, $supplier_id, $model, $image_to_save, $serial_number,
                $purchase_date, $warranty_expiry, $assigned_to_user_id, $status, $id
            ]);
            $_SESSION['success'] = 'Computer updated successfully.';

            // 3. Clean up old image files if a new one was uploaded
            if ($image_to_save != $old_image_filename && $old_image_filename) {
                if (file_exists(UPLOAD_DIR . $old_image_filename)) {
                    unlink(UPLOAD_DIR . $old_image_filename);
                }
                $old_thumb = preg_replace('/(\.[^.]+)$/', '_thumb$1', $old_image_filename);
                if (file_exists(UPLOAD_DIR . $old_thumb)) {
                    unlink(UPLOAD_DIR . $old_thumb);
                }
            }

            // 4. Build a detailed log message
            $log_details = [];
            if ($old_computer['asset_tag'] != $asset_tag) {
                $log_details[] = "Asset Tag changed to '$asset_tag'.";
            }
            if ($old_computer['image_filename'] != $image_to_save) {
                $log_details[] = "Image updated.";
            }
            // ... (other fields) ...
            if ($old_computer['assigned_to_user_id'] != $assigned_to_user_id) {
                // For user assignment, we fetch names to make the log more readable
                $old_name = 'Unassigned';
                if ($old_computer['assigned_to_user_id']) {
                    $stmt_name = $pdo->prepare('SELECT full_name, username FROM users WHERE id = ?');
                    $stmt_name->execute([$old_computer['assigned_to_user_id']]);
                    if ($user_info = $stmt_name->fetch()) {
                        $old_name = "{$user_info['full_name']} (User #{$user_info['username']})";
                    }
                }
                $new_name = 'Unassigned';
                if ($assigned_to_user_id) {
                    $stmt_name = $pdo->prepare('SELECT full_name, username FROM users WHERE id = ?');
                    $stmt_name->execute([$assigned_to_user_id]);
                    if ($user_info = $stmt_name->fetch()) {
                        $new_name = "{$user_info['full_name']} (User #{$user_info['username']})";
                    }
                }
                $log_details[] = "Assignment changed from '$old_name' to '$new_name'.";
            }
            if ($old_computer['status'] != $status) {
                $log_details[] = "Status changed from '{$old_computer['status']}' to '$status'.";
            }

            $details = empty($log_details) ? 'Asset details re-saved with no changes.' : implode("\n", $log_details);
            // 'log_asset_change' is a global helper function
            log_asset_change($pdo, $id, $admin_user_id, 'Updated', $details);

        } else {
            // --- INSERT (ADD) LOGIC ---

            // 1. Perform the insert
            $stmt = $pdo->prepare('
                INSERT INTO computers (asset_tag, category_id, supplier_id, model, image_filename, 
                serial_number, purchase_date, warranty_expiry, assigned_to_user_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $asset_tag, $category_id, $supplier_id, $model, $image_to_save, $serial_number,
                $purchase_date, $warranty_expiry, $assigned_to_user_id, $status
            ]);
            $_SESSION['success'] = 'Computer added successfully.';

            // 2. Get the new ID and create a log entry
            $new_computer_id = $pdo->lastInsertId();
            $details = "Asset created with tag '$asset_tag' and status '$status'.";
            log_asset_change($pdo, $new_computer_id, $admin_user_id, 'Created', $details);
        }
        header('Location: index.php?page=computers'); // Redirect to list view
        exit;

    } catch (PDOException $e) {
        // Handle database errors
        if ($e->errorInfo[1] == 1062) {
            // 1062 is the MySQL error code for 'Duplicate entry'
            $_SESSION['error'] = 'Error: An asset with this tag already exists.';
        } else {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
        // If a new image was uploaded but the DB insert failed, delete the orphaned image
        if ($image_to_save != $old_image_filename && file_exists(UPLOAD_DIR . $image_to_save)) {
            unlink(UPLOAD_DIR . $image_to_save);
            $thumb = preg_replace('/(\.[^.]+)$/', '_thumb$1', $image_to_save);
            if (file_exists(UPLOAD_DIR . $thumb)) {
                unlink(UPLOAD_DIR . $thumb);
            }
        }
        header('Location: ' . $_SERVER['REQUEST_URI']); // Reload the form
        exit;
    }
}

// --- POST HANDLER: CHECK-OUT ---
if (isset($_POST['check_out'])) {
    if ($role == 'User') {
        $_SESSION['error'] = 'Access Denied.';
        header('Location: index.php?page=dashboard');
        exit;
    }
    $computer_id = $_POST['computer_id'];
    $assigned_to_user_id = $_POST['assigned_to_user_id'];

    // Validation
    if (empty($assigned_to_user_id)) {
        $_SESSION['error'] = 'You must select a user to check out the asset to.';
        header('Location: index.php?page=computers&action=checkout&id=' . $computer_id);
        exit;
    }
    try {
        // 1. Get user info for the log
        $user_stmt = $pdo->prepare('SELECT username, full_name FROM users WHERE id = ?');
        $user_stmt->execute([$assigned_to_user_id]);
        $user = $user_stmt->fetch();

        // 2. Update the computer's status and assigned user
        $stmt = $pdo->prepare('UPDATE computers SET status = ?, assigned_to_user_id = ? WHERE id = ?');
        $stmt->execute(['Assigned', $assigned_to_user_id, $computer_id]);

        // 3. Log the action
        $details = "Asset checked out to {$user['full_name']} (User #{$user['username']}).";
        log_asset_change($pdo, $computer_id, $admin_user_id, 'Checked Out', $details);

        $_SESSION['success'] = "Asset successfully checked out to {$user['full_name']}.";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error during check-out: ' . $e->getMessage();
    }
    header('Location: index.php?page=computers');
    exit;
}

// --- POST HANDLER: CHECK-IN ---
if (isset($_POST['check_in'])) {
    if ($role == 'User') {
        $_SESSION['error'] = 'Access Denied.';
        header('Location: index.php?page=dashboard');
        exit;
    }
    $computer_id = $_POST['computer_id'];
    try {
        // 1. Get computer/user info for the log
        $comp_stmt = $pdo->prepare('
            SELECT c.asset_tag, u.username, u.full_name 
            FROM computers c 
            LEFT JOIN users u ON c.assigned_to_user_id = u.id 
            WHERE c.id = ?
        ');
        $comp_stmt->execute([$computer_id]);
        $computer_info = $comp_stmt->fetch();
        $username = $computer_info['username'] ?? 'unknown';
        $full_name = $computer_info['full_name'] ?? 'Unknown User';

        // 2. Update the computer's status to 'In Stock' and remove assignment
        $stmt = $pdo->prepare('UPDATE computers SET status = ?, assigned_to_user_id = NULL WHERE id = ?');
        $stmt->execute(['In Stock', $computer_id]);

        // 3. Log the action
        $details = "Asset returned from $full_name (User #$username).";
        log_asset_change($pdo, $computer_id, $admin_user_id, 'Checked In', $details);

        $_SESSION['success'] = 'Asset successfully checked in and marked as "In Stock".';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error during check-in: ' . $e->getMessage();
    }
    header('Location: index.php?page=computers');
    exit;
}

// --- POST HANDLER: DELETE ---
if ($action == 'delete' && isset($_POST['id'])) {
    if ($role == 'User') {
        $_SESSION['error'] = 'Access Denied.';
        header('Location: index.php?page=dashboard');
        exit;
    }
    $computer_id_to_delete = (int)$_POST['id'];
    try {
        // 1. Get info for logging and image deletion *before* deleting
        $stmt_get = $pdo->prepare('SELECT asset_tag, image_filename FROM computers WHERE id = ?');
        $stmt_get->execute([$computer_id_to_delete]);
        $computer_info = $stmt_get->fetch();
        $asset_tag = $computer_info['asset_tag'] ?? 'N/A';
        $image_to_delete = $computer_info['image_filename'] ?? null;

        // 2. Delete the computer from the database
        // Note: Related logs (asset_log, maintenance) are kept for historical
        //       records, as they don't have foreign key constraints that cascade.
        //       This is a design choice.
        $stmt = $pdo->prepare('DELETE FROM computers WHERE id = ?');
        $stmt->execute([$computer_id_to_delete]);
        $_SESSION['success'] = 'Computer deleted successfully.';

        // 3. Delete the associated image files
        if ($image_to_delete && file_exists(UPLOAD_DIR . $image_to_delete)) {
            unlink(UPLOAD_DIR . $image_to_delete);
            $thumb_to_delete = preg_replace('/(\.[^.]+)$/', '_thumb$1', $image_to_delete);
            if (file_exists(UPLOAD_DIR . $thumb_to_delete)) {
                unlink(UPLOAD_DIR . $thumb_to_delete);
            }
        }

        // 4. Log the deletion
        $details = "Asset deleted (was tag: '$asset_tag').";
        log_asset_change($pdo, $computer_id_to_delete, $admin_user_id, 'Deleted', $details);

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
    header('Location: index.php?page=computers');
    exit;
}

// --- POST HANDLER: BULK ACTIONS ---
if (isset($_POST['bulk_apply'])) {
    if ($role == 'User') {
        $_SESSION['error'] = 'Access Denied.';
        header('Location: index.php?page=dashboard');
        exit;
    }
    $bulk_action = $_POST['bulk_action'];

    // Validation: Check if any items were actually selected
    if (empty($_POST['computer_ids']) || !is_array($_POST['computer_ids'])) {
        $_SESSION['error'] = 'No items were selected.';
        header('Location: index.php?page=computers');
        exit;
    }

    // Sanitize all input IDs to integers
    $ids = array_map('intval', $_POST['computer_ids']);
    if (empty($ids)) {
        $_SESSION['error'] = 'No valid items were selected.';
        header('Location: index.php?page=computers');
        exit;
    }

    // Create a string of '?' placeholders for the 'IN' clause (e.g., "?,?,?")
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        // Use a transaction to ensure all or nothing is processed
        $pdo->beginTransaction();

        switch ($bulk_action) {
            case 'delete':
                // 1. Get info for logging/image deletion *before* deleting
                $stmt_get = $pdo->prepare("SELECT id, asset_tag, image_filename FROM computers WHERE id IN ($placeholders)");
                $stmt_get->execute($ids);
                $computers_to_delete = $stmt_get->fetchAll();

                // 2. Delete the computers
                $stmt_del = $pdo->prepare("DELETE FROM computers WHERE id IN ($placeholders)");
                $stmt_del->execute($ids);
                $deleted_count = $stmt_del->rowCount();

                // 3. Log and delete images for *each* item
                foreach ($computers_to_delete as $computer) {
                    $details = "Asset deleted (was tag: '{$computer['asset_tag']}').";
                    log_asset_change($pdo, $computer['id'], $admin_user_id, 'Deleted', $details);

                    if ($computer['image_filename'] && file_exists(UPLOAD_DIR . $computer['image_filename'])) {
                        unlink(UPLOAD_DIR . $computer['image_filename']);
                        $thumb_to_delete = preg_replace('/(\.[^.]+)$/', '_thumb$1', $computer['image_filename']);
                        if (file_exists(UPLOAD_DIR . $thumb_to_delete)) {
                            unlink(UPLOAD_DIR . $thumb_to_delete);
                        }
                    }
                }
                $_SESSION['success'] = "Successfully deleted $deleted_count item(s).";
                break;

            case 'set_retired':
            case 'set_repair':
                $new_status = ($bulk_action == 'set_retired') ? 'Retired' : 'In Repair';

                // 1. Get old status for logging
                $stmt_get = $pdo->prepare("SELECT id, asset_tag, status FROM computers WHERE id IN ($placeholders)");
                $stmt_get->execute($ids);
                $computers_to_update = $stmt_get->fetchAll();

                // 2. Update status and un-assign user (assets in repair/retired can't be assigned)
                $stmt_update = $pdo->prepare("UPDATE computers SET status = ?, assigned_to_user_id = NULL WHERE id IN ($placeholders)");
                // Merge the $new_status with the array of $ids for execute()
                $stmt_update->execute(array_merge([$new_status], $ids));
                $updated_count = $stmt_update->rowCount();

                // 3. Log the change for *each* item
                foreach ($computers_to_update as $computer) {
                    if ($computer['status'] != $new_status) {
                        $details = "Status changed from '{$computer['status']}' to '$new_status'.";
                        log_asset_change($pdo, $computer['id'], $admin_user_id, 'Updated', $details);
                    }
                }
                $_SESSION['success'] = "Successfully updated $updated_count item(s) to '$new_status'.";
                break;

            default:
                $_SESSION['error'] = 'Invalid bulk action selected.';
        }

        // If all operations were successful, commit the transaction
        $pdo->commit();
    } catch (PDOException $e) {
        // If any operation failed, roll back the entire transaction
        $pdo->rollBack();
        $_SESSION['error'] = 'Database error during bulk action: ' . $e->getMessage();
    }
    header('Location: index.php?page=computers');
    exit;
}


// --- Display pages based on action ---
// This switch statement controls which view (list, add, edit, checkout) is displayed.

switch ($action) {
    case 'add':
    case 'edit':
        // --- VIEW: ADD/EDIT FORM ---
        if ($role == 'User') {
            $_SESSION['error'] = 'Access Denied.';
            header('Location: index.php?page=computers');
            exit;
        }
        $computer = null;
        if ($action == 'edit' && isset($_GET['id'])) {
            // If 'edit', fetch the computer's data to pre-fill the form
            $stmt = $pdo->prepare('SELECT * FROM computers WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $computer = $stmt->fetch();
            if (!$computer) {
                $_SESSION['error'] = 'Computer not found.';
                header('Location: index.php?page=computers');
                exit;
            }
        }

        // Fetch data for dropdown menus
        $categories = $pdo->query('SELECT id, name FROM categories')->fetchAll();
        $suppliers = $pdo->query('SELECT id, name FROM suppliers')->fetchAll();
        $users = $pdo->query('SELECT id, username, full_name FROM users WHERE is_active = 1 ORDER BY full_name')->fetchAll();
        $assigned_user_filter = $_GET['assigned_user_id'] ?? '';
        $statuses = ['In Stock', 'Assigned', 'In Repair', 'Retired'];

        ?>
        <h1 class="mb-4"><?php echo $action == 'add' ? 'Add New' : 'Edit'; ?> Computer</h1>
        <div class="card shadow-sm rounded-3">
            <div class="card-body">
                <!-- The form submits to this same page, handled by the 'if (isset($_POST['save']))' block -->
                <form method="POST" action="index.php?page=computers&action=<?php echo $action; ?>" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>
                    <?php if ($action == 'edit'): ?>
                        <!-- For 'edit', we include the computer's ID -->
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($computer['id']); ?>">
                    <?php endif; ?>
                    <!-- This hidden field holds the name of the current image -->
                    <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($computer['image_filename'] ?? ''); ?>">
                    
                    <div class="row g-3">
                        <!-- Main form fields -->
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
                        <!-- Image Upload section -->
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

        <!-- *** MODIFIED: Client-side validation script for this form *** -->
        <script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
        /**
         * Attaches a submit event listener to the Add/Edit computer form
         * to perform client-side validation *before* submission.
         * This prevents invalid status/user combinations.
         */
        document.addEventListener('DOMContentLoaded', function() {
            const computerForm = document.querySelector('form[action="index.php?page=computers&action=<?php echo $action; ?>"]');
            if (computerForm) {
                const statusSelect = document.getElementById('status');
                const userSelect = document.getElementById('assigned_to_user_id');

                computerForm.addEventListener('submit', function(event) {
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

        <!-- *** ADDED: Tom Select Initializer Script *** -->
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

        <?php
        break;

    case 'checkout':
        // --- VIEW: CHECK-OUT FORM ---
        if ($role == 'User') {
            $_SESSION['error'] = 'Access Denied.';
            header('Location: index.php?page=computers');
            exit;
        }
        $computer_id = $_GET['id'] ?? null;
        if (!$computer_id) {
            $_SESSION['error'] = 'Invalid computer ID.';
            header('Location: index.php?page=computers');
            exit;
        }

        // 1. Fetch computer details
        $stmt = $pdo->prepare('SELECT * FROM computers WHERE id = ?');
        $stmt->execute([$computer_id]);
        $computer = $stmt->fetch();
        if (!$computer) {
            $_SESSION['error'] = 'Computer not found.';
            header('Location: index.php?page=computers');
            exit;
        }

        // 2. Business Logic: Can only check out items that are 'In Stock'
        if ($computer['status'] != 'In Stock') {
            $_SESSION['error'] = 'This asset is not "In Stock" and cannot be checked out.';
            header('Location: index.php?page=computers');
            exit;
        }

        // 3. Fetch users for the dropdown
        $users = $pdo->query('SELECT id, username, full_name FROM users ORDER BY full_name')->fetchAll();
        ?>
        <h1 class="mb-4">Check Out Asset</h1>
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm rounded-3">
                    <div class="card-body">
                        <!-- This form submits to the 'if (isset($_POST['check_out']))' block -->
                        <form method="POST" action="index.php?page=computers">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="computer_id" value="<?php echo htmlspecialchars($computer['id']); ?>">
                            <div class="mb-3">
                                <label class="form-label">Asset Tag</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($computer['asset_tag']); ?>" readonly disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($computer['model']); ?>" readonly disabled>
                            </div>
                            <div class="mb-3">
                                <label for="assigned_to_user_id" class="form-label">Assign To User <span class="text-danger">*</span></label>
                                <select class="form-select" id="assigned_to_user_id" name="assigned_to_user_id" required>
                                    <option value="" selected disabled>Select a user</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['full_name']); ?> (User #<?php echo htmlspecialchars($user['username']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <hr class="my-4">
                            <div class="d-flex justify-content-end">
                                <a href="index.php?page=computers" class="btn btn-secondary me-2">Cancel</a>
                                <button type="submit" name="check_out" class="btn btn-success">
                                    <i class="bi bi-box-arrow-up-right"></i> Complete Check-out
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <!-- Helper text box -->
                <div class="alert alert-info">
                    <h5><i class="bi bi-info-circle-fill"></i> What This Does:</h5>
                    <p>Checking out this asset will:</p>
                    <ul>
                        <li>Set its status to "Assigned".</li>
                        <li>Assign it to the selected user.</li>
                        <li>Create a "Checked Out" entry in the asset's history log.</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- *** ADDED: Tom Select Initializer Script for Checkout page *** -->
        <script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.initTomSelect === 'function') {
                window.initTomSelect('#assigned_to_user_id');
            }
        });
        </script>
        <?php
        break;

    case 'list':
    default:
        // --- VIEW: LIST (DEFAULT) ---

        // Get filter values from URL
        $search_term = $_GET['search'] ?? '';
        $status_filter = $_GET['status_filter'] ?? '';
        $category_filter = $_GET['category_filter'] ?? '';
        $assigned_user_filter = $_GET['assigned_user_id'] ?? '';

        // Fetch data for filter dropdowns
        $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
        $statuses = ['In Stock', 'Assigned', 'In Repair', 'Retired'];
        $users = $pdo->query('SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name')->fetchAll();

        // Initial data load using the helper function from 'ajax_query_helpers.php'
        $data = fetchComputersData($pdo, $_GET);
        $computers = $data['results'];
        $total_pages = $data['total_pages'];
        $current_page = $data['current_page'];

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
                <!-- This link triggers the CSV export handler at the top of the file -->
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
        
        <!-- 
            Filter Form
            - id="ajax-filter-form": Used by global JS (footer.php) to attach an AJAX submit handler.
            - data-type="computers": Tells the AJAX handler which render function to use for the response.
        -->
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
        
        <!-- 
            Data Table Container
            - id="data-table-container": Wrapper for the table and loading overlay.
        -->
        <div class="card shadow-sm rounded-3" id="data-table-container">
            <!-- Loading overlay, shown during AJAX requests -->
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
                                    <!-- 'Select All' checkbox for bulk actions -->
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
                        <!-- 
                            Data Table Body
                            - id="data-table-body": This element's content is replaced by the AJAX response.
                        -->
                        <tbody id="data-table-body">
                            <?php
                    // renderComputersTableBody is from 'ajax_render_helpers.php'
                    echo renderComputersTableBody($computers, $role, csrf_input());
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
                    // renderPagination is from 'ajax_render_helpers.php'
                    echo renderPagination($current_page, $total_pages, $_GET);
        ?>
                </div>

            </div>
            
            <?php if ($role != 'User'): ?>
            <!-- 
                Bulk Action Footer
                - This form is handled by the 'if (isset($_POST['bulk_apply']))' block
            -->
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

        <?php
        break;
}
?>