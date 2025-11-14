<?php
// $pdo and $role are available from index.php
// Security: Only Admins can access this page
if ($role == 'User') {
    $_SESSION['error'] = 'Access Denied.';
    header('Location: index.php?page=dashboard');
    exit;
}

// --- CSV EXPORT HANDLER ---
// This logic is triggered by index.php *before* header.php is included
if (isset($_GET['export'])) {
    
    $export_type = $_GET['export'];
    $results = [];
    $headers = [];
    $filename = 'report_' . date('Y-m-d') . '.csv';

    try {
        switch ($export_type) {
            case 'status':
                $filename = 'report_by_status_' . date('Y-m-d') . '.csv';
                $headers = ['Status', 'Count'];
                $results = $pdo->query('
                    SELECT status, COUNT(*) as count 
                    FROM computers 
                    GROUP BY status ORDER BY status
                ')->fetchAll();
                break;

            case 'category':
                $filename = 'report_by_category_' . date('Y-m-d') . '.csv';
                $headers = ['Category', 'Count'];
                $results = $pdo->query('
                    SELECT cat.name, COUNT(c.id) as count 
                    FROM categories cat
                    LEFT JOIN computers c ON cat.id = c.category_id
                    GROUP BY cat.name ORDER BY cat.name
                ')->fetchAll();
                break;

            case 'supplier':
                $filename = 'report_by_supplier_' . date('Y-m-d') . '.csv';
                $headers = ['Supplier', 'Count'];
                $results = $pdo->query('
                    SELECT s.name, COUNT(c.id) as count 
                    FROM suppliers s
                    LEFT JOIN computers c ON s.id = c.supplier_id
                    GROUP BY s.name ORDER BY s.name
                ')->fetchAll();
                break;
        }

    } catch (PDOException $e) {
        // Handle error - in a real app, you might redirect with an error
        die('Database error generating export: ' . $e->getMessage());
    }

    if (!empty($headers) && !empty($results)) {
        // --- Send CSV Headers ---
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // --- Write CSV Data ---
        $output = fopen('php://output', 'w');
        
        // Write header row
        fputcsv($output, $headers);

        // Write data rows
        foreach ($results as $row) {
            fputcsv($output, $row); // $row is already indexed numerically or assoc.
        }
        
        fclose($output);
        exit; // Stop script execution
    }
    
    // If we got here, export type was invalid, just fall through to HTML page
}
// --- END CSV EXPORT HANDLER ---


// --- HTML Page Logic ---
try {
    // Report 1: Computers by Status
    $status_report = $pdo->query('
        SELECT status, COUNT(*) as count 
        FROM computers 
        GROUP BY status
        ORDER BY status
    ')->fetchAll();

    // Report 2: Computers by Category
    $category_report = $pdo->query('
        SELECT cat.name, COUNT(c.id) as count 
        FROM categories cat
        LEFT JOIN computers c ON cat.id = c.category_id
        GROUP BY cat.name
        ORDER BY cat.name
    ')->fetchAll();

    // Report 3: Computers by Supplier
    $supplier_report = $pdo->query('
        SELECT s.name, COUNT(c.id) as count 
        FROM suppliers s
        LEFT JOIN computers c ON s.id = c.supplier_id
        GROUP BY s.name
        ORDER BY s.name
    ')->fetchAll();
    
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error generating reports: ' . $e->getMessage() . '</div>';
    $status_report = $category_report = $supplier_report = []; // Ensure arrays
}
?>

<h1 class="mb-4">Reports</h1>

<div class="row g-4">
    <!-- Report 1: By Status -->
    <div class="col-md-6">
        <div class="card shadow-sm rounded-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Computers by Status</h5>
                <a href="index.php?page=reports&export=status" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-download"></i> Export
                </a>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status_report as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td><?php echo $row['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Report 2: By Category -->
    <div class="col-md-6">
        <div class="card shadow-sm rounded-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Computers by Category</h5>
                <a href="index.php?page=reports&export=category" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-download"></i> Export
                </a>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Category</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category_report as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo $row['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Report 3: By Supplier -->
    <div class="col-md-6">
        <div class="card shadow-sm rounded-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Computers by Supplier</h5>
                <a href="index.php?page=reports&export=supplier" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-download"></i> Export
                </a>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Supplier</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($supplier_report as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo $row['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
?>