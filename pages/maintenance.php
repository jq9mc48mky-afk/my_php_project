<?php
// $pdo, $role, $csp_nonce are available from index.php

// Security: Only Admins can access this page
if ($role == 'User') {
    $_SESSION['error'] = 'Access Denied.';
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'list';
$current_user_id = $_SESSION['user_id'];

// Handle POST actions (Add, Mark Complete, Delete)
try {
    // Handle Add New Task (from modal)
    if (isset($_POST['save'])) {
        $computer_id = $_POST['computer_id'];
        $title = $_POST['title'];
        $scheduled_date = $_POST['scheduled_date'];
        $notes = $_POST['notes'] ?: null;

        $stmt = $pdo->prepare('
            INSERT INTO maintenance_schedule (computer_id, created_by_user_id, title, scheduled_date, notes) 
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$computer_id, $current_user_id, $title, $scheduled_date, $notes]);
        
        // Log the system change
        $details = "Scheduled maintenance '$title' for computer ID $computer_id.";
        log_system_change($pdo, $current_user_id, 'Maintenance', $details);

        $_SESSION['success'] = 'Maintenance task scheduled successfully.';
        header('Location: index.php?page=maintenance');
        exit;
    }

    // Handle Mark as Complete
    if ($action == 'complete' && isset($_POST['id'])) {
        $stmt = $pdo->prepare('UPDATE maintenance_schedule SET completed_date = CURDATE() WHERE id = ?');
        $stmt->execute([$_POST['id']]);
        
        // Log
        log_system_change($pdo, $current_user_id, 'Maintenance', "Marked task ID {$_POST['id']} as complete.");

        $_SESSION['success'] = 'Task marked as complete.';
        header('Location: index.php?page=maintenance');
        exit;
    }

    // Handle Delete
    if ($action == 'delete' && isset($_POST['id'])) {
        $stmt = $pdo->prepare('DELETE FROM maintenance_schedule WHERE id = ?');
        $stmt->execute([$_POST['id']]);
        
        // Log
        log_system_change($pdo, $current_user_id, 'Maintenance', "Deleted task ID {$_POST['id']}.");

        $_SESSION['success'] = 'Task deleted successfully.';
        header('Location: index.php?page=maintenance');
        exit;
    }

} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header('Location: index.php?page=maintenance');
    exit;
}

// --- Fetch data for the main page ---

// 1. Fetch pending and overdue tasks for the list
$stmt = $pdo->prepare('
    SELECT m.*, c.asset_tag, c.model
    FROM maintenance_schedule m
    LEFT JOIN computers c ON m.computer_id = c.id
    WHERE m.completed_date IS NULL
    ORDER BY m.scheduled_date ASC
');
$stmt->execute();
$tasks = $stmt->fetchAll();

// 2. Fetch all computers for the "Add Task" modal dropdown
$computers = $pdo->query('SELECT id, asset_tag, model FROM computers ORDER BY asset_tag')->fetchAll();

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Maintenance Schedule</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
        <i class="bi bi-plus-lg"></i> Schedule New Task
    </button>
</div>

<div class="card shadow-sm rounded-3">
    <div class="card-header">
        <h5 class="mb-0">Pending & Overdue Tasks</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Status</th>
                        <th>Scheduled Date</th>
                        <th>Asset</th>
                        <th>Task</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No pending maintenance tasks.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <?php if (date('Y-m-d') > $task['scheduled_date']): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($task['scheduled_date']); ?></td>
                                <td>
                                    <a href="index.php?page=computer_history&id=<?php echo $task['computer_id']; ?>">
                                        <?php echo htmlspecialchars($task['asset_tag']); ?>
                                    </a>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($task['model']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                <td>
                                    <form method="POST" action="index.php?page=maintenance&action=complete" style="display:inline-block;" class="form-confirm-action"
                                        data-confirm-title="Confirm Task Completion"
                                        data-confirm-message="Are you sure you want to mark this task as <strong>complete</strong>?"
                                        data-confirm-button-text="Mark Complete"
                                        data-confirm-button-class="btn-success">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="Mark Complete">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="index.php?page=maintenance&action=delete" style="display:inline-block;" class="form-confirm-delete" 
                                        data-confirm-message="Are you sure you want to <strong>permanently delete</strong> this scheduled task? <p><strong>This action cannot be undone.</strong></p>">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- *** ADDED: Add Task Modal *** -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="addTaskForm" method="POST" action="index.php?page=maintenance">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTaskModalLabel">Schedule New Maintenance Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo csrf_input(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="computer_id" class="form-label">Computer / Asset <span class="text-danger">*</span></label>
                            <!-- *** IMPORTANT: We need the empty option value="" for the placeholder to work correctly as a 'blank' state *** -->
                            <select class="form-select" id="computer_id" name="computer_id" required>
                                <option value="" selected disabled>Select an asset</option>
                                <?php foreach ($computers as $computer): ?>
                                    <option value="<?php echo $computer['id']; ?>">
                                        <?php echo htmlspecialchars($computer['asset_tag'] . ' (' . $computer['model'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="scheduled_date" class="form-label">Scheduled Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" required>
                        </div>
                        <div class="col-md-12">
                            <label for="title" class="form-label">Title / Task <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" placeholder="e.g., Quarterly Audit, Virus Scan..." required>
                        </div>
                        <div class="col-md-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save" class="btn btn-primary">Schedule Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- *** UPDATED: Initialization Script *** -->
<script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
document.addEventListener('app:loaded', function() {
    const addTaskModal = document.getElementById('addTaskModal');
    if (addTaskModal) {
        const form = addTaskModal.querySelector('#addTaskForm');
        
        // Initialize Tom Select when the modal is SHOWN to ensure dimensions are correct
        addTaskModal.addEventListener('shown.bs.modal', function() {
            const selectEl = document.getElementById('computer_id');
            // Check if we haven't initialized it yet
            if (selectEl && !selectEl.tomselect) {
                if (typeof window.initTomSelect === 'function') {
                    window.initTomSelect('#computer_id', {
                        dropdownParent: 'body', // This attaches the dropdown to the body to avoid clipping
                        allowEmptyOption: false
                    });
                }
            }
        });

        // *** MODIFIED: Pass the CSP Nonce ***
        const cspNonce = '<?php echo $csp_nonce; ?>';
        window.initFlatpickr('#scheduled_date', {
            minDate: "today"
        }, cspNonce);

        // Reset logic
        addTaskModal.addEventListener('hidden.bs.modal', function() {
            form.reset();
            // We should also clear the Tom Select if it exists
            const selectEl = document.getElementById('computer_id');
            if (selectEl && selectEl.tomselect) {
                selectEl.tomselect.clear();
            }
        });
    }
});
</script>

<?php
?>