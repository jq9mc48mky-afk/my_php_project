<?php
// $pdo and $role variables are available from index.php

define('UPLOAD_DIR_DASH', 'uploads/');

// --- Admin & Super Admin Dashboard ---
if ($role == 'Admin' || $role == 'Super Admin') {
    // (No changes to any of the Admin dashboard queries or HTML)
    try {
        $total_computers = $pdo->query('SELECT COUNT(*) FROM computers')->fetchColumn();
        $assigned = $pdo->query("SELECT COUNT(*) FROM computers WHERE status = 'Assigned'")->fetchColumn();
        $in_stock = $pdo->query("SELECT COUNT(*) FROM computers WHERE status = 'In Stock'")->fetchColumn();
        $in_repair = $pdo->query("SELECT COUNT(*) FROM computers WHERE status = 'In Repair'")->fetchColumn();
        $total_users = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $total_suppliers = $pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();

        $expiring_warranties_stmt = $pdo->prepare("
            SELECT id, asset_tag, model, warranty_expiry 
            FROM computers 
            WHERE warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY warranty_expiry ASC
        ");
        $expiring_warranties_stmt->execute();
        $expiring_warranties = $expiring_warranties_stmt->fetchAll();

        $stale_repairs_stmt = $pdo->prepare("
            SELECT c.id, c.asset_tag, c.model, mrl.timestamp AS last_repair_date
            FROM computers c
            JOIN (
                SELECT computer_id, MAX(timestamp) AS timestamp
                FROM asset_log
                WHERE details LIKE '%to \'In Repair\'%'
                GROUP BY computer_id
            ) AS mrl ON c.id = mrl.computer_id
            WHERE c.status = 'In Repair' AND mrl.timestamp < DATE_SUB(NOW(), INTERVAL 14 DAY)
        ");
        $stale_repairs_stmt->execute();
        $stale_repairs = $stale_repairs_stmt->fetchAll();
        
        $upcoming_maint_stmt = $pdo->prepare("
            SELECT m.id, m.scheduled_date, c.asset_tag, c.id as computer_id
            FROM maintenance_schedule m
            JOIN computers c ON m.computer_id = c.id
            WHERE m.completed_date IS NULL
              AND m.scheduled_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY m.scheduled_date ASC
            LIMIT 10
        ");
        $upcoming_maint_stmt->execute();
        $upcoming_maint = $upcoming_maint_stmt->fetchAll();
        
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Could not fetch stats: ' . $e->getMessage() . '</div>';
        $expiring_warranties = [];
        $stale_repairs = [];
        $upcoming_maint = [];
    }
?>
    <h1 class="mb-4">Admin Dashboard</h1>
    <div class="row g-4">
        <div class="col-md-6 col-lg-3">
            <div class="card text-white bg-primary shadow-sm h-100 rounded-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Computers</h5>
                            <h2 class="display-6"><?php echo $total_computers; ?></h2>
                        </div>
                        <i class="bi bi-laptop fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card text-white bg-success shadow-sm h-100 rounded-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Assigned</h5>
                            <h2 class="display-6"><?php echo $assigned; ?></h2>
                        </div>
                        <i class="bi bi-person-check fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card text-dark bg-light shadow-sm h-100 rounded-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">In Stock</h5>
                            <h2 class="display-6"><?php echo $in_stock; ?></h2>
                        </div>
                        <i class="bi bi-box-seam fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card text-white bg-warning shadow-sm h-100 rounded-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">In Repair</h5>
                            <h2 class="display-6"><?php echo $in_repair; ?></h2>
                        </div>
                        <i class="bi bi-tools fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card text-dark bg-info shadow-sm h-100 rounded-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Users</h5>
                            <h2 class="display-6"><?php echo $total_users; ?></h2>
                        </div>
                        <i class="bi bi-people fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card text-white bg-secondary shadow-sm h-100 rounded-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Suppliers</h5>
                            <h2 class="display-6"><?php echo $total_suppliers; ?></h2>
                        </div>
                        <i class="bi bi-truck fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <h2 class="mt-5 mb-3">Notifications</h2>
    <div class="row g-4">
    
        <div class="col-lg-6">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-info text-dark">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-check"></i> 
                        Upcoming & Overdue Maintenance
                        <span class="badge bg-dark ms-2"><?php echo count($upcoming_maint); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_maint)): ?>
                        <p class="mb-0">No maintenance tasks are due soon.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($upcoming_maint as $task): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="index.php?page=computer_history&id=<?php echo $task['computer_id']; ?>" class="fw-bold"><?php echo htmlspecialchars($task['asset_tag']); ?></a>
                                    </div>
                                    <span class="<?php echo (date('Y-m-d') > $task['scheduled_date']) ? 'text-danger' : ''; ?>">
                                        Due: <?php echo htmlspecialchars($task['scheduled_date']); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-tools"></i> 
                        Stale Repairs (In Repair > 14 Days)
                        <span class="badge bg-dark ms-2"><?php echo count($stale_repairs); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($stale_repairs)): ?>
                        <p class="mb-0">No computers have been in repair for more than 14 days.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($stale_repairs as $computer): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="index.php?page=computer_history&id=<?php echo $computer['id']; ?>" class="fw-bold"><?php echo htmlspecialchars($computer['asset_tag']); ?></a>
                                        (<?php echo htmlspecialchars($computer['model']); ?>)
                                    </div>
                                    <span class="text-muted">
                                        Since: <?php echo htmlspecialchars(date('Y-m-d', strtotime($computer['last_repair_date']))); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php
// --- User Dashboard ---
} else {
    try {
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare('
            SELECT c.*, cat.name as category_name
            FROM computers c
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE c.assigned_to_user_id = ?
        ');
        $stmt->execute([$user_id]);
        $my_computers = $stmt->fetchAll();
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Could not fetch assigned computers: ' . $e->getMessage() . '</div>';
    }
?>
    <h1 class="mb-4">My Dashboard</h1>
    <p class="lead">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>. Here are the assets assigned to you.</p>

    <div class="card shadow-sm rounded-3">
        <div class="card-header">
            <h5 class="mb-0">My Assigned Computers & Assets</h5>
        </div>
        <div class="card-body">
            <?php if (empty($my_computers)): ?>
                <p>You do not have any computers or assets assigned to you.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px;">Image</th>
                                <th>Asset Tag</th>
                                <th>Category</th>
                                <th>Model</th>
                                <th>Serial Number</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_computers as $computer): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        $thumb_path = 'uploads/placeholder.png'; // Default
                                        if (!empty($computer['image_filename'])) {
                                            $thumb_filename = preg_replace('/(\.[^.]+)$/', '_thumb$1', $computer['image_filename']);
                                            $potential_path = UPLOAD_DIR . $thumb_filename;
                                            if (file_exists($potential_path)) {
                                                $thumb_path = $potential_path;
                                            }
                                        }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($thumb_path); ?>" alt="Asset" 
                                             class="img-thumbnail list-asset-img">
                                    </td>
                                    <td><?php echo htmlspecialchars($computer['asset_tag']); ?></td>
                                    <td><?php echo htmlspecialchars($computer['category_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($computer['model']); ?></td>
                                    <td><?php echo htmlspecialchars($computer['serial_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php if ($computer['status'] == 'Assigned') echo 'bg-success';
                                                  elseif ($computer['status'] == 'In Repair') echo 'bg-warning text-dark';
                                                  else echo 'bg-secondary'; ?>
                                        ">
                                            <?php echo htmlspecialchars($computer['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php } 
?>