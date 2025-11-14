<?php
// $pdo, $role, $csp_nonce are available from index.php
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// *** NEW: Require the helper files ***
// These files now contain the query and render logic
require 'ajax_query_helpers.php';
require 'ajax_render_helpers.php';
require 'image_helper.php';

$action = $_GET['action'] ?? 'list';
$admin_user_id = $_SESSION['user_id'];

// *** UPDATED: CSV EXPORT HANDLER ***
// This now uses the shared query helper
if ($action == 'list' && isset($_GET['export']) && $_GET['export'] == 'csv') {
    
    // We pass all GET params to the helper
    $data = fetchComputersData($pdo, $_GET);
    $results = $data['results'];

    $filename = 'computers_export_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'Asset Tag', 'Category', 'Model', 'Serial Number', 'Status', 
        'Assigned To (Name)', 'Assigned To (Username)',
        'Supplier', 'Purchase Date', 'Warranty Expiry'
    ]);

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
    exit;
}

// ... (ALL POST, CHECK-IN, CHECK-OUT, DELETE, and BULK ACTION handlers remain exactly the same) ...
// ... (No changes to 'add', 'edit', 'checkout' cases in the switch statement) ...

if (isset($_POST['save'])) {
    if ($role == 'User') {
        $_SESSION['error'] = 'Access Denied.';
        header('Location: index.php?page=dashboard');
        exit;
    }
    $id = $_POST['id'] ?? null;
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
    // Rule 1: If a user is assigned, status MUST be 'Assigned'.
    if (!empty($assigned_to_user_id) && $status !== 'Assigned') {
        $_SESSION['error'] = "Validation Error: An asset cannot be assigned to a user unless its status is 'Assigned'.";
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    // Rule 2: If status is 'Assigned', a user MUST be selected.
    if ($status === 'Assigned' && empty($assigned_to_user_id)) {
        $_SESSION['error'] = "Validation Error: An asset with status 'Assigned' must be assigned to a user.";
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    // *** END MODIFIED VALIDATION ***

    $image_to_save = $_POST['current_image'] ?? null;
    $old_image_filename = $_POST['current_image'] ?? null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // Our new function does validation, resize, and save all at once
        $upload_result = process_and_save_image($_FILES['image']);
        
        if (is_array($upload_result)) { // Failure
            $_SESSION['error'] = implode('<br>', $upload_result);
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else { // Success
            $image_to_save = $upload_result;
        }
    }
    
    try {
        if ($id) {
            $stmt_old = $pdo->prepare('SELECT * FROM computers WHERE id = ?');
            $stmt_old->execute([$id]);
            $old_computer = $stmt_old->fetch();
            
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
            
            if ($image_to_save != $old_image_filename && $old_image_filename) {
                if (file_exists(UPLOAD_DIR . $old_image_filename)) {
                    unlink(UPLOAD_DIR . $old_image_filename);
                }
                $old_thumb = preg_replace('/(\.[^.]+)$/', '_thumb$1', $old_image_filename);
                if (file_exists(UPLOAD_DIR . $old_thumb)) {
                    unlink(UPLOAD_DIR . $old_thumb);
                }
            }
            
            $log_details = [];
            if ($old_computer['asset_tag'] != $asset_tag) { $log_details[] = "Asset Tag changed to '$asset_tag'."; }
            if ($old_computer['image_filename'] != $image_to_save) { $log_details[] = "Image updated."; }
            if ($old_computer['category_id'] != $category_id) { $log_details[] = "Category ID changed to '$category_id'."; }
            if ($old_computer['supplier_id'] != $supplier_id) { $log_details[] = "Supplier ID changed to '$supplier_id'."; }
            if ($old_computer['model'] != $model) { $log_details[] = "Model changed to '$model'."; }
            if ($old_computer['serial_number'] != $serial_number) { $log_details[] = "Serial # changed to '$serial_number'."; }
            if ($old_computer['purchase_date'] != $purchase_date) { $log_details[] = "Purchase Date changed to '$purchase_date'."; }
            if ($old_computer['warranty_expiry'] != $warranty_expiry) { $log_details[] = "Warranty changed to '$warranty_expiry'."; }
            if ($old_computer['assigned_to_user_id'] != $assigned_to_user_id) { 
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
            log_asset_change($pdo, $id, $admin_user_id, 'Updated', $details);

        } else {
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

            $new_computer_id = $pdo->lastInsertId();
            $details = "Asset created with tag '$asset_tag' and status '$status'.";
            log_asset_change($pdo, $new_computer_id, $admin_user_id, 'Created', $details);
        }
        header('Location: index.php?page=computers');
        exit;

    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            $_SESSION['error'] = 'Error: An asset with this tag already exists.';
        } else {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
        if ($new_image_filename && file_exists(UPLOAD_DIR . $new_image_filename)) {
            unlink(UPLOAD_DIR . $new_image_filename);
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}
if (isset($_POST['check_out'])) {
    if ($role == 'User') {
        $_SESSION['error'] = 'Access Denied.';
        header('Location: index.php?page=dashboard');
        exit;
    }
    $computer_id = $_POST['computer_id'];
    $assigned_to_user_id = $_POST['assigned_to_user_id'];
    if (empty($assigned_to_user_id)) {
        $_SESSION['error'] = 'You must select a user to check out the asset to.';
        header('Location: index.php?page=computers&action=checkout&id=' . $computer_id);
        exit;
    }
    try {
        $user_stmt = $pdo->prepare('SELECT username, full_name FROM users WHERE id = ?');
        $user_stmt->execute([$assigned_to_user_id]);
        $user = $user_stmt->fetch();
        $stmt = $pdo->prepare('UPDATE computers SET status = ?, assigned_to_user_id = ? WHERE id = ?');
        $stmt->execute(['Assigned', $assigned_to_user_id, $computer_id]);
        $details = "Asset checked out to {$user['full_name']} (User #{$user['username']}).";
        log_asset_change($pdo, $computer_id, $admin_user_id, 'Checked Out', $details);
        $_SESSION['success'] = "Asset successfully checked out to {$user['full_name']}.";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error during check-out: ' . $e->getMessage();
    }
    header('Location: index.php?page=computers');
    exit;
}
if (isset($_POST['check_in'])) {
    if ($role == 'User') {
        $_SESSION['error'] = 'Access Denied.';
        header('Location: index.php?page=dashboard');
        exit;
    }
    $computer_id = $_POST['computer_id'];
    try {
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
        $stmt = $pdo->prepare('UPDATE computers SET status = ?, assigned_to_user_id = NULL WHERE id = ?');
        $stmt->execute(['In Stock', $computer_id]);
        $details = "Asset returned from $full_name (User #$username).";
        log_asset_change($pdo, $computer_id, $admin_user_id, 'Checked In', $details);
        $_SESSION['success'] = 'Asset successfully checked in and marked as "In Stock".';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error during check-in: ' . $e->getMessage();
    }
    header('Location: index.php?page=computers');
    exit;
}
if ($action == 'delete' && isset($_POST['id'])) {
    if ($role == 'User') {
        $_SESSION['error'] = 'Access Denied.';
        header('Location: index.php?page=dashboard');
        exit;
    }
    $computer_id_to_delete = (int)$_POST['id'];
    try {
        $stmt_get = $pdo->prepare('SELECT asset_tag, image_filename FROM computers WHERE id = ?');
        $stmt_get->execute([$computer_id_to_delete]);
        $computer_info = $stmt_get->fetch();
        $asset_tag = $computer_info['asset_tag'] ?? 'N/A';
        $image_to_delete = $computer_info['image_filename'] ?? null;
        
        $stmt = $pdo->prepare('DELETE FROM computers WHERE id = ?');
        $stmt->execute([$computer_id_to_delete]);
        $_SESSION['success'] = 'Computer deleted successfully.';

        if ($image_to_delete && file_exists(UPLOAD_DIR . $image_to_delete)) {
            unlink(UPLOAD_DIR . $image_to_delete);
            $thumb_to_delete = preg_replace('/(\.[^.]+)$/', '_thumb$1', $image_to_delete);
            if (file_exists(UPLOAD_DIR . $thumb_to_delete)) {
                unlink(UPLOAD_DIR . $thumb_to_delete);
            }
        }
        
        $details = "Asset deleted (was tag: '$asset_tag').";
        log_asset_change($pdo, $computer_id_to_delete, $admin_user_id, 'Deleted', $details);

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
    header('Location: index.php?page=computers');
    exit;
}
if (isset($_POST['bulk_apply'])) {
    if ($role == 'User') {
        $_SESSION['error'] = 'Access Denied.';
        header('Location: index.php?page=dashboard');
        exit;
    }
    $bulk_action = $_POST['bulk_action'];
    if (empty($_POST['computer_ids']) || !is_array($_POST['computer_ids'])) {
        $_SESSION['error'] = 'No items were selected.';
        header('Location: index.php?page=computers');
        exit;
    }
    $ids = array_map('intval', $_POST['computer_ids']);
    if (empty($ids)) {
        $_SESSION['error'] = 'No valid items were selected.';
        header('Location: index.php?page=computers');
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $pdo->beginTransaction();
        switch ($bulk_action) {
            case 'delete':
                $stmt_get = $pdo->prepare("SELECT id, asset_tag, image_filename FROM computers WHERE id IN ($placeholders)");
                $stmt_get->execute($ids);
                $computers_to_delete = $stmt_get->fetchAll();
                $stmt_del = $pdo->prepare("DELETE FROM computers WHERE id IN ($placeholders)");
                $stmt_del->execute($ids);
                $deleted_count = $stmt_del->rowCount();
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
                $stmt_get = $pdo->prepare("SELECT id, asset_tag, status FROM computers WHERE id IN ($placeholders)");
                $stmt_get->execute($ids);
                $computers_to_update = $stmt_get->fetchAll();
                $stmt_update = $pdo->prepare("UPDATE computers SET status = ?, assigned_to_user_id = NULL WHERE id IN ($placeholders)");
                $stmt_update->execute(array_merge([$new_status], $ids));
                $updated_count = $stmt_update->rowCount();
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
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Database error during bulk action: ' . $e->getMessage();
    }
    header('Location: index.php?page=computers');
    exit;
}


// --- Display pages based on action ---

switch ($action) {
    case 'add':
    case 'edit':
        if ($role == 'User') {
            $_SESSION['error'] = 'Access Denied.';
            header('Location: index.php?page=computers');
            exit;
        }
        $computer = null;
        if ($action == 'edit' && isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM computers WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $computer = $stmt->fetch();
            if (!$computer) {
                $_SESSION['error'] = 'Computer not found.';
                header('Location: index.php?page=computers');
                exit;
            }
        }
        $categories = $pdo->query('SELECT id, name FROM categories')->fetchAll();
        $suppliers = $pdo->query('SELECT id, name FROM suppliers')->fetchAll();
        $users = $pdo->query('SELECT id, username, full_name FROM users WHERE is_active = 1 ORDER BY full_name')->fetchAll();
        // Data for new "Assigned To" filter
        $users = $pdo->query('SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name')->fetchAll();
        $assigned_user_filter = $_GET['assigned_user_id'] ?? '';
        
        $statuses = ['In Stock', 'Assigned', 'In Repair', 'Retired'];
        
        ?>
        <h1 class="mb-4"><?php echo $action == 'add' ? 'Add New' : 'Edit'; ?> Computer</h1>
        <div class="card shadow-sm rounded-3">
            <div class="card-body">
                <form method="POST" action="index.php?page=computers&action=<?php echo $action; ?>" enctype="multipart/form-data">
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
                        event.preventDefault();
                        
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
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.initTomSelect === 'function') {
                // These selects are on the main page, not in a modal
                window.initTomSelect('#category_id');
                window.initTomSelect('#supplier_id');
                window.initTomSelect('#assigned_to_user_id');

                // *** NEW: Initialize Flatpickr ***
                const cspNonce = '<?php echo $csp_nonce; ?>';
                window.initFlatpickr('#purchase_date', {}, cspNonce);
                window.initFlatpickr('#warranty_expiry', {}, cspNonce);
            }
        });
        </script>

        <?php
        break;

    case 'checkout':
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
        $stmt = $pdo->prepare('SELECT * FROM computers WHERE id = ?');
        $stmt->execute([$computer_id]);
        $computer = $stmt->fetch();
        if (!$computer) {
            $_SESSION['error'] = 'Computer not found.';
            header('Location: index.php?page=computers');
            exit;
        }
        if ($computer['status'] != 'In Stock') {
            $_SESSION['error'] = 'This asset is not "In Stock" and cannot be checked out.';
            header('Location: index.php?page=computers');
            exit;
        }
        $users = $pdo->query('SELECT id, username, full_name FROM users ORDER BY full_name')->fetchAll();
        ?>
        <h1 class="mb-4">Check Out Asset</h1>
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm rounded-3">
                    <div class="card-body">
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
        // *** MODIFIED: This block is now much simpler ***
        // It just calls the helper functions for the initial page load.
        
        $search_term = $_GET['search'] ?? '';
        $status_filter = $_GET['status_filter'] ?? '';
        $category_filter = $_GET['category_filter'] ?? '';
        
        // Data for filters
        $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
        $statuses = ['In Stock', 'Assigned', 'In Repair', 'Retired'];
        
        // Initial data load using the helper
        $data = fetchComputersData($pdo, $_GET);
        $computers = $data['results'];
        $total_pages = $data['total_pages'];
        $current_page = $data['current_page'];
        
        ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Computers</h1>
            <div class="d-flex">
                <?php
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
        
        <!-- *** MODIFIED: Added id="ajax-filter-form" and data-type="computers" *** -->
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
                            <select class="form-select" id="assigned_user_id" name="assigned_user_id">
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
        
        <!-- *** MODIFIED: Added id="data-table-container" for loading overlay *** -->
        <div class="card shadow-sm rounded-3" id="data-table-container">
            <!-- *** NEW: Loading Overlay *** -->
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
                        <!-- *** MODIFIED: Added id="data-table-body" *** -->
                        <tbody id="data-table-body">
                            <?php 
                            // Render initial table body using the helper
                            echo renderComputersTableBody($computers, $role, csrf_input());
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- *** MODIFIED: Added id="pagination-controls" *** -->
                <div id="pagination-controls">
                    <?php
                    // Render initial pagination using the helper
                    echo renderPagination($current_page, $total_pages, $_GET);
                    ?>
                </div>

            </div>
            
            <?php if ($role != 'User'): ?>
            <!-- *** MODIFIED: Always render footer, but hide if no computers *** -->
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
        
<!-- *** REMOVED: Style block moved to header.php *** -->

<!-- *** MODIFIED: Bulk action script. Event listeners are now delegated in footer. *** -->
<script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Select All Checkbox Logic ---
    // This must be attached to a static parent since the checkboxes are dynamic
    const tableContainer = document.getElementById('data-table-container');
    if (tableContainer) {
        // Listen for "Select All"
        tableContainer.addEventListener('change', function(e) {
            if (e.target.id === 'selectAllCheckbox') {
                const itemCheckboxes = tableContainer.querySelectorAll('.item-checkbox');
                itemCheckboxes.forEach(checkbox => {
                    checkbox.checked = e.target.checked;
                });
            }
        });

        // Listen for individual item clicks
        tableContainer.addEventListener('change', function(e) {
            if (e.target.classList.contains('item-checkbox')) {
                const selectAllCheckbox = document.getElementById('selectAllCheckbox');
                if (!selectAllCheckbox) return;

                if (!e.target.checked) {
                    selectAllCheckbox.checked = false;
                } else {
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
            e.preventDefault(); 
            
            const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            if (checkedBoxes.length === 0) {
                // This now works because showToast is global
                window.showToast('Please select at least one item to apply an action.', 'error');
                return;
            }

            const actionSelect = document.getElementById('bulkActionSelect');
            const actionValue = actionSelect.value;
            const actionText = actionSelect.options[actionSelect.selectedIndex].text;
            
            const finalizeSubmit = () => {
                checkedBoxes.forEach(checkbox => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'computer_ids[]';
                    hiddenInput.value = checkbox.value;
                    this.appendChild(hiddenInput);
                });
                const bulkApplyInput = document.createElement('input');
                bulkApplyInput.type = 'hidden';
                bulkApplyInput.name = 'bulk_apply';
                bulkApplyInput.value = 'true';
                this.appendChild(bulkApplyInput);
                this.submit();
            };

            if (actionValue === 'delete') {
                const customMessage = `Are you sure you want to <strong>permanently "${actionText}"</strong> ${checkedBoxes.length} selected item(s)? All its associated history will be lost. <p><strong>This action cannot be undone.</strong></p>`;
                const modalElement = document.getElementById('confirmDeleteModal');
                const modalBody = document.getElementById('confirmDeleteModalBody');
                const confirmButton = document.getElementById('confirmDeleteButton');
                // We use getOrCreateInstance because the modal JS is now in footer.php
                const confirmModal = bootstrap.Modal.getOrCreateInstance(modalElement);
                
                modalBody.innerHTML = customMessage;
                // We must remove any old listeners before adding a new one
                confirmButton.replaceWith(confirmButton.cloneNode(true));
                document.getElementById('confirmDeleteButton').addEventListener('click', finalizeSubmit, { once: true });
                
                confirmModal.show();
            } else {
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