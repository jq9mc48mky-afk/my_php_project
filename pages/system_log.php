<?php
// $pdo, $role, $csp_nonce are available from index.php

// Security: Only Super Admins can access this page
if ($role != 'Super Admin') {
    $_SESSION['error'] = 'Access Denied.';
    header('Location: index.php?page=dashboard');
    exit;
}

// Pagination settings
$results_per_page = 25;
$current_page = $_GET['p'] ?? 1;
if ($current_page < 1) { $current_page = 1; }
$offset = ($current_page - 1) * $results_per_page;

try {
    // Get total number of logs for pagination
    $total_results = $pdo->query('SELECT COUNT(*) FROM system_log')->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);

    // Fetch the logs for the current page
    $stmt = $pdo->prepare('
        SELECT l.*, u.username, u.full_name
        FROM system_log l
        LEFT JOIN users u ON l.admin_user_id = u.id
        ORDER BY l.timestamp DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->bindValue(1, $results_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error fetching system log: ' . $e->getMessage();
    $logs = [];
    $total_pages = 0;
}
?>

<h1 class="mb-4">Global System Audit Log</h1>
<p class="lead">This page shows all administrative actions taken across the system.</p>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Admin User</th>
                        <th>Action Type</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No system log entries found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                <td>
                                    <?php if ($log['full_name']): ?>
                                        <?php echo htmlspecialchars($log['full_name']); ?>
                                        <br>
                                        <small class="text-muted">(User #<?php echo htmlspecialchars($log['username']); ?>)</small>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown/System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($log['action_type']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <!-- Previous -->
                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=system_log&p=<?php echo $current_page - 1; ?>">Previous</a>
                    </li>
                    <!-- Pages -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=system_log&p=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <!-- Next -->
                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=system_log&p=<?php echo $current_page + 1; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
</div>