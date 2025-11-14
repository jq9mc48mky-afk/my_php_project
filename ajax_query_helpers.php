<?php
// This file contains the data-fetching and query-building logic.
// It will be shared by the main pages (for initial load) and the new api.php.

/**
 * Fetches the data for the 'computers' list.
 *
 * @param PDO $pdo
 * @param array $params (search, status_filter, category_filter, p)
 * @param int $results_per_page
 * @return array ['results', 'total_pages']
 */
function fetchComputersData($pdo, $params, $results_per_page = 10) {
    $current_page = $params['p'] ?? 1;
    if ($current_page < 1) { $current_page = 1; }
    $offset = ($current_page - 1) * $results_per_page;

    $search_term = $params['search'] ?? '';
    $status_filter = $params['status_filter'] ?? '';
    $category_filter = $params['category_filter'] ?? '';

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
    $sql_count_base = 'SELECT COUNT(*) FROM computers c';
    
    $where_clauses = [];
    $query_params = [];

    if (!empty($search_term)) {
        $where_clauses[] = '(c.asset_tag LIKE ? OR c.model LIKE ? OR c.serial_number LIKE ?)';
        $like_term = "%{$search_term}%";
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
        $sql_where = ' WHERE ' . implode(' AND ', $where_clauses);
    }

    $count_stmt = $pdo->prepare($sql_count_base . $sql_where);
    $count_stmt->execute($query_params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);

    $sql_results = $sql_results_base . $sql_where . ' ORDER BY c.asset_tag LIMIT ? OFFSET ?';
    $results_stmt = $pdo->prepare($sql_results);
    
    $param_index = 1;
    foreach ($query_params as $param) {
        $results_stmt->bindValue($param_index++, $param);
    }
    $results_stmt->bindValue($param_index++, $results_per_page, PDO::PARAM_INT);
    $results_stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
    
    $results_stmt->execute();
    $results = $results_stmt->fetchAll();

    return [
        'results' => $results, 
        'total_pages' => (int)$total_pages,
        'current_page' => (int)$current_page
    ];
}

/**
 * Fetches the data for the 'categories' list.
 *
 * @param PDO $pdo
 * @param array $params (search, p)
 * @param int $results_per_page
 * @return array ['results', 'total_pages']
 */
function fetchCategoriesData($pdo, $params, $results_per_page = 10) {
    $current_page = $params['p'] ?? 1;
    if ($current_page < 1) { $current_page = 1; }
    $offset = ($current_page - 1) * $results_per_page;

    $search_term = $params['search'] ?? '';
    $query_params = [];

    $sql_results_base = 'SELECT * FROM categories';
    $sql_count_base = 'SELECT COUNT(*) FROM categories';
    $sql_where = '';

    if (!empty($search_term)) {
        $sql_where = ' WHERE name LIKE ? OR description LIKE ?';
        $like_term = "%{$search_term}%";
        $query_params = [$like_term, $like_term];
    }

    $count_stmt = $pdo->prepare($sql_count_base . $sql_where);
    $count_stmt->execute($query_params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);

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
    
    return [
        'results' => $results, 
        'total_pages' => (int)$total_pages,
        'current_page' => (int)$current_page
    ];
}

/**
 * Fetches the data for the 'suppliers' list.
 *
 * @param PDO $pdo
 * @param array $params (search, p)
 * @param int $results_per_page
 * @return array ['results', 'total_pages']
 */
function fetchSuppliersData($pdo, $params, $results_per_page = 10) {
    $current_page = $params['p'] ?? 1;
    if ($current_page < 1) { $current_page = 1; }
    $offset = ($current_page - 1) * $results_per_page;

    $search_term = $params['search'] ?? '';
    $query_params = [];

    $sql_results_base = 'SELECT * FROM suppliers';
    $sql_count_base = 'SELECT COUNT(*) FROM suppliers';
    $sql_where = '';

    if (!empty($search_term)) {
        $sql_where = ' WHERE name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?';
        $like_term = "%{$search_term}%";
        $query_params = [$like_term, $like_term, $like_term, $like_term];
    }

    $count_stmt = $pdo->prepare($sql_count_base . $sql_where);
    $count_stmt->execute($query_params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);

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
    
    return [
        'results' => $results, 
        'total_pages' => (int)$total_pages,
        'current_page' => (int)$current_page
    ];
}

?>