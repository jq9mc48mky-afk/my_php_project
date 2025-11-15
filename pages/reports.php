<?php
/**
 * Page for displaying summary reports (Admin-only).
 *
 * This file has two modes:
 * 1. CSV Export Mode: If `?export=...` is in the URL, this script will be
 * triggered *before* any HTML is sent. It generates a CSV file based
 * on the report type and then exits.
 * 2. HTML Display Mode: If no `export` param is set, it queries the database
 * for all report summaries and displays them in tables on the page.
 *
 * @global PDO $pdo The database connection object.
 * @global string $role The role of the currently logged-in user.
 */

// $pdo and $role are available from index.php
// Security: Only Admins can access this page
if ($role == 'User') {
    $_SESSION['error'] = 'Access Denied.';
    header('Location: index.php?page=dashboard');
    exit;
}

// --- CSV EXPORT HANDLER ---
// This logic is triggered by index.php *before* header.php is included,
// which is why it can set HTTP headers without issue.
if (isset($_GET['export'])) {

    $export_type = $_GET['export'];
    $results = [];
    $headers = [];
    $filename = 'report_' . date('Y-m-d') . '.csv';

    try {
        // Use a switch to determine which query to run for the export
        switch ($export_type) {
            case 'status':
                $filename = 'report_by_status_' . date('Y-m-d') . '.csv';
                $headers = ['Status', 'Count'];
                // Query: Count of computers grouped by their 'status'
                $results = $pdo->query('
                    SELECT status, COUNT(*) as count 
                    FROM computers 
                    GROUP BY status ORDER BY status
                ')->fetchAll();
                break;

            case 'category':
                $filename = 'report_by_category_' . date('Y-m-d') . '.csv';
                $headers = ['Category', 'Count'];
                // Query: Count of computers grouped by category name.
                // LEFT JOIN ensures categories with 0 computers are also shown.
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
                // Query: Count of computers grouped by supplier name.
                // LEFT JOIN ensures suppliers with 0 computers are also shown.
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

    // Proceed only if we have valid headers and results
    if (!empty($headers) && !empty($results)) {
        // --- Send CSV Headers ---
        // These headers tell the browser to treat the response as a file download.
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // --- Write CSV Data ---
        // 'php://output' is a special stream that writes directly to the response body.
        $output = fopen('php://output', 'w');

        // Write header row
        fputcsv($output, $headers);

        // Write data rows
        foreach ($results as $row) {
            // fputcsv handles escaping data for CSV format
            fputcsv($output, $row);
        }

        fclose($output);
        exit; // Stop script execution; no HTML should be sent.
    }

    // If we got here, export type was invalid, just fall through to HTML page
}
// --- END CSV EXPORT HANDLER ---


// --- HTML Page Logic ---
// This code runs only if the `export` param was NOT set or was invalid.
try {
    // Report 1: Computers by Status
    // This query is identical to the one in the CSV export block.
    $status_report = $pdo->query('
        SELECT status, COUNT(*) as count 
        FROM computers 
        GROUP BY status
        ORDER BY status
    ')->fetchAll();

    // Report 2: Computers by Category
    // This query is identical to the one in the CSV export block.
    $category_report = $pdo->query('
        SELECT cat.name, COUNT(c.id) as count 
        FROM categories cat
        LEFT JOIN computers c ON cat.id = c.category_id
        GROUP BY cat.name
        ORDER BY cat.name
    ')->fetchAll();

    // Report 3: Computers by Supplier
    // This query is identical to the one in the CSV export block.
    $supplier_report = $pdo->query('
        SELECT s.name, COUNT(c.id) as count 
        FROM suppliers s
        LEFT JOIN computers c ON s.id = c.supplier_id
        GROUP BY s.name
        ORDER BY s.name
    ')->fetchAll();

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error generating reports: ' . $e->getMessage() . '</div>';
    $status_report = $category_report = $supplier_report = []; // Ensure arrays to prevent errors
}
?>

<h1 class="mb-4">Reports</h1>

<div class="row g-4">
    <!-- Report 1: By Status -->
    <div class="col-md-6">
        <div class="card shadow-sm rounded-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Computers by Status</h5>
                <!-- This link triggers the CSV export handler at the top of the file -->
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
                <!-- This link triggers the CSV export handler -->
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
                <!-- This link triggers the CSV export handler -->
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