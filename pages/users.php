<?php
// $pdo, $role, $csp_nonce are available from index.php
if ($role != 'Super Admin') {
    $_SESSION['error'] = 'Access Denied.';
    header('Location: index.php?page=dashboard');
    exit;
}

$admin_user_id = $_SESSION['user_id'];

// *** UPDATED: Handle Add/Edit ***
if (isset($_POST['save'])) {
    $id = $_POST['id'] ?? null;
    $username = $_POST['username'];
    $full_name = $_POST['full_name'];
    $password = $_POST['password'];
    $user_role = $_POST['role'];

    // (Validation logic is unchanged)
    $is_valid_username = false;
    $error_message = '';
    if ($user_role == 'User') {
        if (preg_match('/^\d{3}$/', $username)) {
            $is_valid_username = true;
        } else {
            $error_message = 'For the "User" role, the username must be a 3-digit employee number.';
        }
    } elseif ($user_role == 'Admin' || $user_role == 'Super Admin') {
        if (preg_match('/^[a-zA-Z0-9]{3,20}$/', $username)) {
            $is_valid_username = true;
        } else {
            $error_message = 'For "Admin" roles, the username must be 3-20 alphanumeric characters (letters and numbers only).';
        }
    } else {
        $error_message = 'Invalid role selected.';
    }
    if (!$is_valid_username) {
        $_SESSION['error'] = $error_message;
        header('Location: index.php?page=users');
        exit;
    }

    try {
        if ($id) {
            // --- LOCKOUT PREVENTION 1 & 2 (SELF-DEMOTION & LAST ADMIN) ---
            $is_self_edit = ($id == $admin_user_id);
            $is_demotion = ($user_role != 'Super Admin'); // The new role is NOT Super Admin

            if ($is_self_edit && $is_demotion) {
                $_SESSION['error'] = 'Error: You cannot demote your own account.';
                header('Location: index.php?page=users' . (isset($_GET['show_inactive']) ? '&show_inactive=1' : ''));
                exit;
            }

            // Check if this action is demoting a Super Admin
            if ($is_demotion) {
                // Find out what the user's old role was
                $stmt_old_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt_old_role->execute([$id]);
                $old_role = $stmt_old_role->fetchColumn();

                // If their old role was Super Admin, check if they are the last one
                if ($old_role == 'Super Admin') {
                    $stmt_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Super Admin' AND is_active = 1");
                    if ($stmt_count->fetchColumn() <= 1) {
                        $_SESSION['error'] = 'Cannot demote the last active Super Admin account.';
                        header('Location: index.php?page=users' . (isset($_GET['show_inactive']) ? '&show_inactive=1' : ''));
                        exit;
                    }
                }
            }
            // --- END LOCKOUT PREVENTION ---

            // --- Log what's being changed ---
            $stmt_old = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch();
            // --- End log ---

            // Update user
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET username = ?, full_name = ?, password = ?, role = ? WHERE id = ?');
                $stmt->execute([$username, $full_name, $hashed_password, $user_role, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username = ?, full_name = ?, role = ? WHERE id = ?');
                $stmt->execute([$username, $full_name, $user_role, $id]);
            }
            $_SESSION['success'] = 'User updated successfully.';

            // --- Log Action ---
            $details = "User (ID: $id, Username: $username) updated.\n";
            if ($old_data['username'] != $username) $details .= "Username changed from '{$old_data['username']}' to '$username'.\n";
            if ($old_data['full_name'] != $full_name) $details .= "Full Name changed from '{$old_data['full_name']}' to '$full_name'.\n";
            if ($old_data['role'] != $user_role) $details .= "Role changed from '{$old_data['role']}' to '$user_role'.\n";
            if (!empty($password)) $details .= "Password updated.\n";
            log_system_change($pdo, $admin_user_id, 'User Management', $details);
            // --- End Log ---

        } else {
            // Add new user
            if (empty($password)) {
                $_SESSION['error'] = 'Password is required for new users.';
                header('Location: index.php?page=users');
                exit;
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, full_name, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $full_name, $hashed_password, $user_role]);
            $new_id = $pdo->lastInsertId();
            $_SESSION['success'] = 'User added successfully.';

            // --- Log Action ---
            $details = "User created (ID: $new_id, Username: $username, Role: $user_role).";
            log_system_change($pdo, $admin_user_id, 'User Management', $details);
            // --- End Log ---
        }
        header('Location: index.php?page=users' . (isset($_GET['show_inactive']) ? '&show_inactive=1' : ''));
        exit;

    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            $_SESSION['error'] = 'Error: This username (employee number) is already taken.';
        } else {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
        header('Location: index.php?page=users' . (isset($_GET['show_inactive']) ? '&show_inactive=1' : ''));
        exit;
    }
}

// *** MODIFIED: Handle Deactivate ***
if (isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];

    if ($delete_id == $admin_user_id) {
        $_SESSION['error'] = 'You cannot deactivate your own account.';
    } else {
        try {
            // --- LOCKOUT PREVENTION 2: LAST ADMIN CHECK ---
            $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt_role->execute([$delete_id]);
            $user_role = $stmt_role->fetchColumn();

            if ($user_role == 'Super Admin') {
                $stmt_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Super Admin' AND is_active = 1");
                if ($stmt_count->fetchColumn() <= 1) {
                    $_SESSION['error'] = 'Cannot deactivate the last active Super Admin account.';
                    header('Location: index.php?page=users' . (isset($_GET['show_inactive']) ? '&show_inactive=1' : ''));
                    exit;
                }
            }
            // --- END LOCKOUT PREVENTION ---

            // --- Get data before deleting for log ---
            $stmt_get = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt_get->execute([$delete_id]);
            $user_to_delete = $stmt_get->fetch();
            // --- End log ---

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM computers WHERE assigned_to_user_id = ?');
            $stmt->execute([$delete_id]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = 'Cannot deactivate user. They have computers assigned to them. Please re-assign their assets first.';
            } else {
                // *** THIS IS THE "SOFT DELETE" ***
                $stmt = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?');
                $stmt->execute([$delete_id]);
                $_SESSION['success'] = 'User deactivated successfully.';

                // --- Log Action ---
                if ($user_to_delete) {
                    $details = "User deactivated (ID: $delete_id, Username: {$user_to_delete['username']}).";
                    log_system_change($pdo, $admin_user_id, 'User Management', $details);
                }
                // --- End Log ---
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
    }
    header('Location: index.php?page=users' . (isset($_GET['show_inactive']) ? '&show_inactive=1' : ''));
    exit;
}

// *** NEW: Handle Reactivate ***
if (isset($_POST['reactivate_id'])) {
    $reactivate_id = (int)$_POST['reactivate_id'];
    
    try {
        $stmt = $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?');
        $stmt->execute([$reactivate_id]);
        $_SESSION['success'] = 'User reactivated successfully.';

        // --- Log Action ---
        $stmt_get = $pdo->prepare('SELECT username FROM users WHERE id = ?');
        $stmt_get->execute([$reactivate_id]);
        $username = $stmt_get->fetchColumn();
        $details = "User reactivated (ID: $reactivate_id, Username: $username).";
        log_system_change($pdo, $admin_user_id, 'User Management', $details);
        // --- End Log ---

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
    
    header('Location: index.php?page=users' . (isset($_GET['show_inactive']) ? '&show_inactive=1' : ''));
    exit;
}


// *** MODIFIED: LIST DISPLAY LOGIC (Handles filtering) ***
$show_inactive = isset($_GET['show_inactive']);
$sql = "SELECT 
            u.id, u.username, u.full_name, u.role, u.is_active,
            (SELECT COUNT(*) FROM computers c WHERE c.assigned_to_user_id = u.id) as asset_count
        FROM users u";
if (!$show_inactive) {
    $sql .= " WHERE is_active = 1";
}
$sql .= " ORDER BY is_active DESC, full_name ASC";
$users = $pdo->query($sql)->fetchAll();

$roles = ['Super Admin', 'Admin', 'User'];

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Manage Users</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
        <i class="bi bi-plus-lg"></i> Add New User
    </button>
</div>

<div class="card shadow-sm rounded-3">
    <!-- *** NEW: Card Header w/ Filter *** -->
    <div class="card-header bg-light d-flex justify-content-end">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="showInactive" 
                   <?php echo $show_inactive ? 'checked' : ''; ?>>
            <label class="form-check-label" for="showInactive">Show Inactive Users</label>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Assets</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <!-- *** MODIFIED: Add class for inactive rows *** -->
                    <tr class="<?php echo $user['is_active'] ? '' : 'opacity-50 text-muted'; ?>">
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td>
                            <span class="badge 
                                <?php if ($user['role'] == 'Super Admin') echo 'bg-danger';
                                      elseif ($user['role'] == 'Admin') echo 'bg-warning text-dark';
                                      else echo 'bg-success'; ?>
                            ">
                                <?php echo htmlspecialchars($user['role']); ?>
                            </span>
                        </td>
                        <!-- *** NEW: Status Cell *** -->
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['asset_count'] > 0): ?>
                                <a href="index.php?page=computers&assigned_user_id=<?php echo $user['id']; ?>" 
                                   class="badge bg-primary text-decoration-none" 
                                   title="View <?php echo $user['asset_count']; ?> assigned assets">
                                    <?php echo $user['asset_count']; ?>
                                </a>
                            <?php else: ?>
                                <span class="badge bg-light text-dark">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- *** MODIFIED: Conditional Actions *** -->
                            <?php if ($user['is_active']): ?>
                                <!-- Can only edit active users -->
                                <button type="button" class="btn btn-sm btn-outline-primary edit-btn"
                                        data-bs-toggle="modal" data-bs-target="#userModal"
                                        data-id="<?php echo $user['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                        data-full_name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                        data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                        title="Edit">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>

                                <button type_button" class="btn btn-sm btn-outline-info reset-btn"
                                        data-bs-toggle="modal" data-bs-target="#resetLinkModal"
                                        data-id="<?php echo $user['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                        title="Send Reset Link">
                                    <i class="bi bi-key-fill"></i>
                                </button>
                                
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" action="index.php?page=users" style="display:inline-block;" class="form-confirm-delete" 
                                    data-confirm-message="Are you sure you want to <strong>deactivate</strong> this user? <p>They will no longer be able to log in.</p> (Note: The system will prevent deactivation if this user is currently assigned to any computers.)">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="delete_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Deactivate">
                                        <i class="bi bi-person-x-fill"></i> <!-- Changed Icon -->
                                    </button>
                                </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Show Reactivate button for inactive users -->
                                <form method="POST" action="index.php?page=users<?php echo $show_inactive ? '&show_inactive=1' : ''; ?>" style="display:inline-block;" class="form-confirm-action"
                                    data-confirm-title="Confirm Reactivation"
                                    data-confirm-message="Are you sure you want to <strong>reactivate</strong> this user?"
                                    data-confirm-button-text="Reactivate"
                                    data-confirm-button-class="btn-success">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="reactivate_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success" title="Reactivate">
                                        <i class="bi bi-person-check-fill"></i> Reactivate
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- *** MODAL IS UNCHANGED *** -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="userForm" method="POST" action="index.php?page=users<?php echo $show_inactive ? '&show_inactive=1' : ''; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="id" id="userId">
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <div id="usernameHelp" class="form-text"></div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" aria-describedby="passwordHelp">
                        <div id="passwordHelp" class="form-text">Leave blank to keep current password (on edit). Required for new users.</div>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r; ?>"><?php echo $r; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="resetLinkModal" tabindex="-1" aria-labelledby="resetLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetLinkModalLabel">Generate Password Reset Link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>You are about to generate a new, single-use password reset link for <strong id="resetUserName"></strong>.</p>
                <p>The user's current password will **not** be changed until they use the link.</p>
                
                <input type="hidden" id="resetUserId">
                
                <div id="resetLinkResult" style="display: none;">
                    <label for="generatedLink" class="form-label">Send this link to the user (it expires in 30 minutes):</label>
                    <textarea class="form-control" id="generatedLink" rows="3" readonly></textarea>
                </div>
                
                <div id="resetSpinner" class="text-center" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Generating...</span>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="resetCloseButton">Close</button>
                <button type="button" class="btn btn-primary" id="generateLinkButton">Generate Link</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
document.addEventListener('DOMContentLoaded', function() {
    
    // *** Show/Hide Inactive Checkbox Handler ***
    const showInactiveCheckbox = document.getElementById('showInactive');
    if (showInactiveCheckbox) {
        showInactiveCheckbox.addEventListener('change', function() {
            const baseUrl = 'index.php?page=users';
            if (this.checked) {
                window.location.href = baseUrl + '&show_inactive=1';
            } else {
                window.location.href = baseUrl;
            }
        });
    }

    // --- Modal Logic ---
    const userModal = document.getElementById('userModal');
    const roleSelect = userModal.querySelector('#role');
    const usernameInput = userModal.querySelector('#username');
    const passwordInput = userModal.querySelector('#password');
    const passwordHelp = userModal.querySelector('#passwordHelp');
    const usernameHelp = userModal.querySelector('#usernameHelp');
    const fullNameInput = userModal.querySelector('#full_name');
    
    function updateUsernameValidation() {
        const selectedRole = roleSelect.value;
        
        if (selectedRole === 'User') {
            usernameInput.pattern = '\\d{3}';
            usernameInput.title = 'Must be a 3-digit number.';
            usernameInput.maxLength = 3;
            usernameHelp.textContent = 'For "User" role, must be a 3-digit employee number.';
        } else if (selectedRole === 'Admin' || selectedRole === 'Super Admin') {
            usernameInput.pattern = '[a-zA-Z0-9]{3,20}';
            usernameInput.title = 'Must be 3-20 alphanumeric characters.';
            usernameInput.maxLength = 20;
            usernameHelp.textContent = 'For "Admin" roles, must be 3-20 alphanumeric characters.';
        }
    }

    roleSelect.addEventListener('change', updateUsernameValidation);
    
    if (userModal) {
        userModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            // *** Check if button exists (can be null if triggered by JS)
            if (!button) return; 

            const modalTitle = userModal.querySelector('.modal-title');
            const form = userModal.querySelector('form');
            
            const userId = button.getAttribute('data-id');
            const username = button.getAttribute('data-username');
            const userRole = button.getAttribute('data-role');
            const fullName = button.getAttribute('data-full_name'); // <-- ADDED

            // --- ADD THIS BLOCK ---
            const currentUserId = <?php echo (int)$_SESSION['user_id']; ?>;
            const roleHelpText = document.getElementById('roleHelp'); // We will add this
            
            if (!roleHelpText) {
                // Create the help text element if it doesn't exist
                const helpEl = document.createElement('div');
                helpEl.id = 'roleHelp';
                helpEl.className = 'form-text text-danger';
                roleSelect.after(helpEl);
            }
            // --- END ADDED BLOCK ---


            if (userId) {
                // Edit Mode
                modalTitle.textContent = 'Edit User';
                form.querySelector('#userId').value = userId;
                usernameInput.value = username;
                roleSelect.value = userRole;
                fullNameInput.value = fullName; // <-- ADDED
                
                passwordInput.required = false;
                passwordHelp.textContent = 'Leave blank to keep the current password.';

                // --- ADD THIS LOGIC ---
                if (userId == currentUserId) {
                    roleSelect.disabled = true;
                    document.getElementById('roleHelp').textContent = 'You cannot change your own role.';
                } else {
                    roleSelect.disabled = false;
                    document.getElementById('roleHelp').textContent = '';
                }
                // --- END ADDED LOGIC ---

            } else {
                // Add Mode
                modalTitle.textContent = 'Add New User';
                form.reset();
                form.querySelector('#userId').value = '';
                
                passwordInput.required = true;
                passwordHelp.textContent = 'Password is required for new users.';

                // --- ADD THIS LOGIC ---
                roleSelect.disabled = false; // Ensure it's enabled for "Add"
                if (document.getElementById('roleHelp')) {
                    document.getElementById('roleHelp').textContent = ''; // Ensure it's clear
                }
                // --- END ADDED LOGIC ---
            }
            
            updateUsernameValidation();
        });
    }

    // *** Reset Link Modal Logic ***
    const resetModal = document.getElementById('resetLinkModal');
    if (resetModal) {
        const userNameEl = resetModal.querySelector('#resetUserName');
        const userIdInput = resetModal.querySelector('#resetUserId');
        const generateBtn = resetModal.querySelector('#generateLinkButton');
        const closeBtn = resetModal.querySelector('#resetCloseButton');
        const resultDiv = resetModal.querySelector('#resetLinkResult');
        const linkTextarea = resetModal.querySelector('#generatedLink');
        const spinner = resetModal.querySelector('#resetSpinner');

        resetModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-id');
            const userName = button.getAttribute('data-name');

            // 1. Set initial state
            userNameEl.textContent = userName;
            userIdInput.value = userId;
            
            // 2. Reset modal to its "ready" state
            resultDiv.style.display = 'none';
            spinner.style.display = 'none';
            linkTextarea.value = '';
            generateBtn.style.display = 'block'; // Show generate button
            generateBtn.disabled = false;
        });

        generateBtn.addEventListener('click', async function() {
            const userId = userIdInput.value;
            
            // 3. Show loading state
            spinner.style.display = 'block';
            generateBtn.disabled = true;

            try {
                // 4. Call the API
                const response = await fetch(`api.php?type=generate-reset-token&user_id=${userId}`);
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Unknown error occurred.');
                }
                
                // 5. Build the full URL
                const baseUrl = window.location.origin + window.location.pathname.replace(/index\.php$/, '');
                const resetUrl = `${baseUrl}reset.php?token=${data.token}`;

                // 6. Show the result
                linkTextarea.value = resetUrl;
                resultDiv.style.display = 'block';
                spinner.style.display = 'none';
                generateBtn.style.display = 'none'; // Hide generate button
                
                // Automatically select the text
                linkTextarea.select();

            } catch (err) {
                // 7. Show error
                spinner.style.display = 'none';
                linkTextarea.value = 'Error: ' + err.message;
                resultDiv.style.display = 'block';
                generateBtn.disabled = false;
            }
        });
    }
});
</script>

<?php
?>