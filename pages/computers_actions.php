<?php
/**
 * Computers Page - Command/Action Handler.
 *
 * This file is included by 'computers.php' and handles all state-changing
 * POST requests (Commands) for computer assets.
 *
 * It assumes that $pdo, $role, $admin_user_id, and $action are
 * already defined by 'computers.php'.
 *
 * All handlers in this file are expected to redirect and `exit;`
 * to prevent the 'computers.php' view logic from executing.
 *
 * @global PDO $pdo The database connection object.
 * @global string $role The role of the currently logged-in user.
 * @global int $admin_user_id The ID of the logged-in admin.
 * @global string $action The current action (used for delete).
 */

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
?>