<?php
if ($role == 'User') {
    $_SESSION['error'] = 'Access Denied.';
    header('Location: index.php?page=dashboard');
    exit;
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid computer ID.';
    header('Location: index.php?page=computers');
    exit;
}

$computer_id = (int)$_GET['id'];
define('UPLOAD_DIR_HISTORY', 'uploads/');

try {
    $stmt = $pdo->prepare('SELECT asset_tag, model, image_filename FROM computers WHERE id = ?');
    $stmt->execute([$computer_id]);
    $computer = $stmt->fetch();
    if (!$computer) {
        $_SESSION['error'] = 'Computer not found.';
        header('Location: index.php?page=computers');
        exit;
    }

    // *** UPDATED: Query 1 (Asset Log) ***
    $log_stmt = $pdo->prepare('
        SELECT l.*, u.username as admin_username, u.full_name as admin_full_name
        FROM asset_log l
        LEFT JOIN users u ON l.admin_user_id = u.id
        WHERE l.computer_id = ?
        ORDER BY l.timestamp DESC
    ');
    $log_stmt->execute([$computer_id]);
    $logs = $log_stmt->fetchAll();
    
    // *** UPDATED: Query 2 (Maintenance Log) ***
    $maint_stmt = $pdo->prepare('
        SELECT m.*, u.username as created_by_username, u.full_name as created_by_full_name
        FROM maintenance_schedule m
        LEFT JOIN users u ON m.created_by_user_id = u.id
        WHERE m.computer_id = ?
        ORDER BY m.scheduled_date DESC
    ');
    $maint_stmt->execute([$computer_id]);
    $maintenance_logs = $maint_stmt->fetchAll();

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error fetching history: ' . $e->getMessage() . '</div>';
    $logs = [];
    $maintenance_logs = [];
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <?php
        $thumb_path = 'uploads/placeholder.png'; // Default
        if (!empty($computer['image_filename'])) {
            $thumb_filename = preg_replace('/(\.[^.]+)$/', '_thumb$1', $computer['image_filename']);
            $potential_path = UPLOAD_DIR_HISTORY . $thumb_filename;
            if (file_exists($potential_path)) {
                $thumb_path = $potential_path;
            }
        }
        ?>
        <img src="<?php echo htmlspecialchars($thumb_path); ?>" alt="Asset" 
             class="img-thumbnail me-3" style="width: 80px; height: 80px; object-fit: cover;">
        <div>
            <h1 class="mb-0">Asset History</h1>
            <p class="lead text-muted mb-0">
                <?php echo htmlspecialchars($computer['asset_tag']); ?> / 
                <?php echo htmlspecialchars($computer['model']); ?>
            </p>
        </div>
    </div>
    <a href="index.php?page=computers" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back to Computers List
    </a>
</div>
<ul class="nav nav-tabs" id="historyTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="action-log-tab" data-bs-toggle="tab" data-bs-target="#action-log" type="button" role="tab" aria-controls="action-log" aria-selected="true">
            <i class="bi bi-list-ul"></i> Action Log
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab" aria-controls="maintenance" aria-selected="false">
            <i class="bi bi-calendar-check"></i> Maintenance Schedule
        </button>
    </li>
</ul>

<div class="tab-content" id="historyTabContent">
    
    <div class="tab-pane fade show active" id="action-log" role="tabpanel" aria-labelledby="action-log-tab">
        <div class="card shadow-sm rounded-bottom rounded-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Admin User</th> <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No action history found for this asset.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                        <td>
                                            <?php if ($log['admin_full_name']): ?>
                                                <?php echo htmlspecialchars($log['admin_full_name']); ?>
                                                <br>
                                                <small class="text-muted">(User #<?php echo htmlspecialchars($log['admin_username']); ?>)</small>
                                            <?php else: ?>
                                                Unknown User
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php if ($log['action'] == 'Created') echo 'bg-success'; elseif ($log['action'] == 'Updated') echo 'bg-primary'; elseif ($log['action'] == 'Deleted') echo 'bg-danger'; elseif ($log['action'] == 'Checked Out') echo 'bg-info text-dark'; elseif ($log['action'] == 'Checked In') echo 'bg-secondary'; else echo 'bg-dark'; ?>">
                                                <?php echo htmlspecialchars($log['action']); ?>
                                            </span>
                                        </td>
                                        <td style="white-space: pre-wrap;"><?php echo htmlspecialchars($log['details']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="tab-pane fade" id="maintenance" role="tabpanel" aria-labelledby="maintenance-tab">
        <div class="card shadow-sm rounded-bottom rounded-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Status</th>
                                <th>Scheduled</th>
                                <th>Completed</th>
                                <th>Task</th>
                                <th>Notes</th>
                                <th>Scheduled By</th> </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($maintenance_logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No maintenance tasks scheduled for this asset.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($maintenance_logs as $task): ?>
                                    <tr>
                                        <td>
                                            <?php if ($task['completed_date']): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php elseif (date('Y-m-d') > $task['scheduled_date']): ?>
                                                <span class="badge bg-danger">Overdue</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($task['scheduled_date']); ?></td>
                                        <td><?php echo htmlspecialchars($task['completed_date'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                                        <td><?php echo htmlspecialchars($task['notes'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($task['created_by_full_name']): ?>
                                                <?php echo htmlspecialchars($task['created_by_full_name']); ?>
                                                <br>
                                                <small class="text-muted">(User #<?php echo htmlspecialchars($task['created_by_username']); ?>)</small>
                                            <?php else: ?>
                                                Unknown
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
?>