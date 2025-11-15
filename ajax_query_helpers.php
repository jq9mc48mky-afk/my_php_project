<?php

/**
 * AJAX Data Query Helpers
 *
 * This file contains the data-fetching and query-building logic.
 * These functions are used by the main pages (for initial page load)
 * and by api.php (for all subsequent AJAX filter/pagination requests).
 * This DRY (Don't Repeat Yourself) approach ensures that the data displayed
 * on load is fetched using the exact same logic as the data fetched via AJAX.
 */

/**
 * Fetches paginated and filtered data for the 'computers' list.
 *
 * This function dynamically constructs a SQL query based on the provided
 * filter parameters. It performs two queries:
 * 1. A COUNT(*) query to get the total number of results for pagination.
 * 2. A SELECT query with LIMIT/OFFSET to get the actual data for the current page.
 *
 * @param PDO $pdo The database connection object.
 * @param array $params An associative array of GET parameters, e.g.:
 * [
 * 'p' => 1,
 * 'search' => 'laptop',
 * 'status_filter' => 'In Stock',
 * 'category_filter' => 3,
 * 'assigned_user_id' => 5
 * ]
 * @param int $results_per_page The number of items to show per page.
 * @return array An associative array containing:
 * [
 * 'results' => (array) The rows of computer data.
 * 'total_pages' => (int) The total number of pages.
 * 'current_page' => (int) The current page number.
 * ]
 */
function fetchComputersData($pdo, $params, $results_per_page = 10)
{
    // --- 1. Pagination Setup ---
    $current_page = $params['p'] ?? 1;
    if ($current_page < 1) {
        $current_page = 1;
    }
    $offset = ($current_page - 1) * $results_per_page;

    // --- 2. Filter Setup ---
    $search_term = $params['search'] ?? '';
    $status_filter = $params['status_filter'] ?? '';
    $category_filter = $params['category_filter'] ?? '';

    // Base query selects all necessary columns and joins tables
    $sql_results_base = '
        SELECT 
            c.*, 
            cat.name as category_name, 
            s.name as supplier_name, 
            u.username as assigned_to_username,
            u.full_name as assigned_to_full_name
        FROM computers c
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN suppliers s ON c.supplier_id = s.id
        LEFT JOIN users u ON c.assigned_to_user_id = u.id
    ';
    // Base query for *counting* total results (much faster than COUNT(results))
    $sql_count_base = 'SELECT COUNT(*) FROM computers c';

    // --- 3. Dynamic WHERE Clause Builder ---
    $where_clauses = []; // Stores SQL snippets (e.g., "c.status = ?")
    $query_params = [];  // Stores the values for the prepared statement

    if (!empty($search_term)) {
        $where_clauses[] = '(c.asset_tag LIKE ? OR c.model LIKE ? OR c.serial_number LIKE ?)';
        $like_term = "%{$search_term}%";
        // Add the param 3 times, once for each field
        $query_params[] = $like_term;
        $query_params[] = $like_term;
        $query_params[] = $like_term;
    }
    if (!empty($status_filter)) {
        $where_clauses[] = 'c.status = ?';
        $query_params[] = $status_filter;
    }
    if (!empty($category_filter)) {
        $where_clauses[] = 'c.category_id = ?';
        $query_params[] = $category_filter;
    }
    if (!empty($params['assigned_user_id'])) {
        $where_clauses[] = 'c.assigned_to_user_id = ?';
        $query_params[] = $params['assigned_user_id'];
    }

    $sql_where = '';
    if (!empty($where_clauses)) {
        // Join all 'WHERE' snippets with 'AND'
        $sql_where = ' WHERE ' . implode(' AND ', $where_clauses);
    }

    // --- 4. Execute COUNT Query (for pagination) ---
    $count_stmt = $pdo->prepare($sql_count_base . $sql_where);
    $count_stmt->execute($query_params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);

    // --- 5. Execute SELECT Query (for data) ---
    $sql_results = $sql_results_base . $sql_where . ' ORDER BY c.asset_tag LIMIT ? OFFSET ?';
    $results_stmt = $pdo->prepare($sql_results);

    // Bind all the filter parameters first
    $param_index = 1;
    foreach ($query_params as $param) {
        $results_stmt->bindValue($param_index++, $param);
    }
    // Bind the final LIMIT and OFFSET parameters
    $results_stmt->bindValue($param_index++, $results_per_page, PDO::PARAM_INT);
    $results_stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);

    $results_stmt->execute();
    $results = $results_stmt->fetchAll();

    // --- 6. Return Data ---
    return [
        'results' => $results,
        'total_pages' => (int)$total_pages,
        'current_page' => (int)$current_page
    ];
}

/**
 * Fetches paginated and filtered data for the 'categories' list.
 *
 * @param PDO $pdo The database connection object.
 * @param array $params An associative array of GET parameters (e.g., 'search', 'p').
 * @param int $results_per_page The number of items to show per page.
 * @return array An associative array containing 'results', 'total_pages', 'current_page'.
 */
function fetchCategoriesData($pdo, $params, $results_per_page = 10)
{
    // 1. Pagination
    $current_page = $params['p'] ?? 1;
    if ($current_page < 1) {
        $current_page = 1;
    }
    $offset = ($current_page - 1) * $results_per_page;

    // 2. Filters
    $search_term = $params['search'] ?? '';
    $query_params = [];

    $sql_results_base = 'SELECT * FROM categories';
    $sql_count_base = 'SELECT COUNT(*) FROM categories';
    $sql_where = '';

    // 3. Dynamic WHERE
    if (!empty($search_term)) {
        $sql_where = ' WHERE name LIKE ? OR description LIKE ?';
        $like_term = "%{$search_term}%";
        $query_params = [$like_term, $like_term];
    }

    // 4. Execute COUNT
    $count_stmt = $pdo->prepare($sql_count_base . $sql_where);
    $count_stmt->execute($query_params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);

    // 5. Execute SELECT
    $sql_results = $sql_results_base . $sql_where . ' ORDER BY name LIMIT ? OFFSET ?';
    $results_stmt = $pdo->prepare($sql_results);

    $param_index = 1;
    foreach ($query_params as $param) {
        $results_stmt->bindValue($param_index++, $param);
    }
    $results_stmt->bindValue($param_index++, $results_per_page, PDO::PARAM_INT);
    $results_stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);

    $results_stmt->execute();
    $results = $results_stmt->fetchAll();

    // 6. Return
    return [
        'results' => $results,
        'total_pages' => (int)$total_pages,
        'current_page' => (int)$current_page
    ];
}

/**
 * Fetches paginated and filtered data for the 'suppliers' list.
 *
 * @param PDO $pdo The database connection object.
 * @param array $params An associative array of GET parameters (e.g., 'search', 'p').
 * @param int $results_per_page The number of items to show per page.
 * @return array An associative array containing 'results', 'total_pages', 'current_page'.
 */
function fetchSuppliersData($pdo, $params, $results_per_page = 10)
{
    // 1. Pagination
    $current_page = $params['p'] ?? 1;
    if ($current_page < 1) {
        $current_page = 1;
    }
    $offset = ($current_page - 1) * $results_per_page;

    // 2. Filters
    $search_term = $params['search'] ?? '';
    $query_params = [];

    $sql_results_base = 'SELECT * FROM suppliers';
    $sql_count_base = 'SELECT COUNT(*) FROM suppliers';
    $sql_where = '';

    // 3. Dynamic WHERE (searches 4 columns)
    if (!empty($search_term)) {
        $sql_where = ' WHERE name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?';
        $like_term = "%{$search_term}%";
        $query_params = [$like_term, $like_term, $like_term, $like_term];
    }

    // 4. Execute COUNT
    $count_stmt = $pdo->prepare($sql_count_base . $sql_where);
    $count_stmt->execute($query_params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);

    // 5. Execute SELECT
    $sql_results = $sql_results_base . $sql_where . ' ORDER BY name LIMIT ? OFFSET ?';
    $results_stmt = $pdo->prepare($sql_results);

    $param_index = 1;
    foreach ($query_params as $param) {
        $results_stmt->bindValue($param_index++, $param);
    }
    $results_stmt->bindValue($param_index++, $results_per_page, PDO::PARAM_INT);
    $results_stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);

    $results_stmt->execute();
    $results = $results_stmt->fetchAll();

    // 6. Return
    return [
        'results' => $results,
        'total_pages' => (int)$total_pages,
        'current_page' => (int)$current_page
    ];
}
