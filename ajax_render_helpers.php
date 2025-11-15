<?php
/**
 * AJAX HTML Render Helpers
 *
 * This file contains functions that generate the HTML for table bodies
 * and pagination controls. They are used by both the main page files
 * (for the initial page load) and by api.php (to generate HTML for
 * AJAX responses).
 *
 * Using output buffering (ob_start/ob_get_clean) allows us to write
 * complex HTML with embedded PHP logic easily.
 */

/**
 * Renders the HTML for the computers table body (all the <tr>...</tr> rows).
 *
 * @param array $computers The array of computer data from fetchComputersData().
 * @param string $role The role of the current user (to show/hide admin buttons).
 * @param string $csrf_token_html The HTML string from csrf_input() to be
 * embedded in all mini-forms.
 * @return string The generated HTML for the <tbody>.
 */
function renderComputersTableBody($computers, $role, $csrf_token_html)
{
    // Handle the "No results" case
    if (empty($computers)) {
        $colspan = ($role != 'User') ? '8' : '6'; // Adjust colspan based on role
        return "<tr><td colspan=\"$colspan\" class=\"text-center\">No computers found.</td></tr>";
    }

    // Start output buffering
    ob_start();
    foreach ($computers as $computer):
        // --- Thumbnail Logic ---
        $thumb_path = 'uploads/placeholder.png'; // Default
        if (!empty($computer['image_filename'])) {
            // e.g., 'image.jpg' -> 'image_thumb.jpg'
            $thumb_filename = preg_replace('/(\.[^.]+)$/', '_thumb$1', $computer['image_filename']);
            $potential_path = UPLOAD_DIR . $thumb_filename;
            if (file_exists($potential_path)) {
                $thumb_path = $potential_path;
            }
        }
        ?>
        <tr>
            <!-- Admin-only column: Checkbox for bulk actions -->
            <?php if ($role != 'User'): ?>
                <td>
                    <input class="form-check-input item-checkbox" type="checkbox" 
                           value="<?php echo $computer['id']; ?>" 
                           aria-label="Select item <?php echo htmlspecialchars($computer['asset_tag']); ?>">
                </td>
            <?php endif; ?>
            <td>
                <img src="<?php echo htmlspecialchars($thumb_path); ?>" alt="Asset" 
                     class="img-thumbnail list-asset-img">
            </td>
            <td><strong><?php echo htmlspecialchars($computer['asset_tag']); ?></strong></td>
            <td><?php echo htmlspecialchars($computer['category_name'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($computer['model']); ?></td>
            <td>
                <!-- Dynamic status badge color -->
                <span class="badge 
                    <?php if ($computer['status'] == 'Assigned') {
                        echo 'bg-success';
                    } elseif ($computer['status'] == 'In Stock') {
                        echo 'bg-secondary';
                    } elseif ($computer['status'] == 'In Repair') {
                        echo 'bg-warning text-dark';
                    } elseif ($computer['status'] == 'Retired') {
                        echo 'bg-danger';
                    } else {
                        echo 'bg-light text-dark';
                    } ?>
                ">
                    <?php echo htmlspecialchars($computer['status']); ?>
                </span>
            </td>
            <td>
                <?php if ($computer['assigned_to_full_name']): ?>
                    <?php echo htmlspecialchars($computer['assigned_to_full_name']); ?>
                    <br>
                    <small class="text-muted">(User #<?php echo htmlspecialchars($computer['assigned_to_username']); ?>)</small>
                <?php else: ?>
                    Unassigned
                <?php endif; ?>
            </td>
            <!-- Admin-only column: Action buttons -->
            <?php if ($role != 'User'): ?>
            <td>
                <!-- Conditional Check-in/Check-out buttons -->
                <?php if ($computer['status'] == 'In Stock'): ?>
                    <a href="index.php?page=computers&action=checkout&id=<?php echo $computer['id']; ?>" class="btn btn-sm btn-success" title="Check Out">
                        <i class="bi bi-box-arrow-up-right"></i> Check Out
                    </a>
                <?php elseif ($computer['status'] == 'Assigned'): ?>
                    <!-- This "Check In" form is intercepted by global JS (footer.php) -->
                    <form method="POST" action="index.php?page=computers" style="display:inline-block;" class="form-confirm-action"
                        data-confirm-title="Confirm Check-In"
                        data-confirm-message="Are you sure you want to check this asset back in? This will set its status to 'In Stock' and unassign it."
                        data-confirm-button-text="Check In"
                        data-confirm-button-class="btn-success">
                        <?php echo $csrf_token_html; // Inject CSRF token?>
                        <input type="hidden" name="computer_id" value="<?php echo $computer['id']; ?>">
                        <input type="hidden" name="check_in" value="true">
                        <button type="submit" class="btn btn-sm btn-outline-success" title="Check In">
                            <i class="bi bi-box-arrow-in-down"></i> Check In
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Standard action buttons -->
                <a href="index.php?page=computer_history&id=<?php echo $computer['id']; ?>" class="btn btn-sm btn-outline-info" title="History">
                    <i class="bi bi-clock-history"></i>
                </a>
                <a href="index.php?page=computers&action=edit&id=<?php echo $computer['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                    <i class="bi bi-pencil-fill"></i>
                </a>
                
                <!-- This "Delete" form is intercepted by global JS (footer.php) -->
                <form method="POST" action="index.php?page=computers&action=delete" style="display:inline-block;" class="form-confirm-delete" 
                    data-confirm-message="Are you sure you want to <strong>permanently delete</strong> this computer? All its associated history will be lost. <strong>This action cannot be undone.</strong>">
                    <?php echo $csrf_token_html; // Inject CSRF token?>
                    <input type="hidden" name="id" value="<?php echo $computer['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
    <?php
    endforeach;
    // Get the buffered content and stop buffering
    return ob_get_clean();
}

/**
 * Renders the HTML for the categories table body (all the <tr>...</tr> rows).
 *
 * @param array $categories The array of category data.
 * @param string $csrf_token_html The HTML string from csrf_input().
 * @return string The generated HTML for the <tbody>.
 */
function renderCategoriesTableBody($categories, $csrf_token_html)
{
    if (empty($categories)) {
        return '<tr><td colspan="3" class="text-center">No categories found.</td></tr>';
    }

    ob_start();
    foreach ($categories as $category):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($category['name']); ?></td>
            <td><?php echo htmlspecialchars($category['description'] ?? 'N/A'); ?></td>
            <td>
                <!-- This "Edit" button triggers the modal JS in categories.php -->
                <button type="button" class="btn btn-sm btn-outline-primary edit-btn" 
                        data-bs-toggle="modal" data-bs-target="#categoryModal"
                        data-id="<?php echo $category['id']; ?>"
                        data-name="<?php echo htmlspecialchars($category['name']); ?>"
                        data-description="<?php echo htmlspecialchars($category['description'] ?? ''); ?>"
                        title="Edit">
                    <i class="bi bi-pencil-fill"></i>
                </button>
                <!-- This "Delete" form is intercepted by global JS (footer.php) -->
                <form method="POST" action="index.php?page=categories" style="display:inline-block;" class="form-confirm-delete" 
                    data-confirm-message="Are you sure you want to <strong>permanently delete</strong> this category? <p><strong>This action cannot be undone.</strong></p> (Note: The system will prevent deletion if this category is linked to any computers.)">
                    <?php echo $csrf_token_html; // Inject CSRF token?>
                    <input type="hidden" name="delete_id" value="<?php echo $category['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </form>
            </td>
        </tr>
    <?php
    endforeach;
    return ob_get_clean();
}

/**
 * Renders the HTML for the suppliers table body (all the <tr>...</tr> rows).
 *
 * @param array $suppliers The array of supplier data.
 * @param string $csrf_token_html The HTML string from csrf_input().
 * @return string The generated HTML for the <tbody>.
 */
function renderSuppliersTableBody($suppliers, $csrf_token_html)
{
    if (empty($suppliers)) {
        return '<tr><td colspan="5" class="text-center">No suppliers found.</td></tr>';
    }

    ob_start();
    foreach ($suppliers as $supplier):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($supplier['name']); ?></td>
            <td><?php echo htmlspecialchars($supplier['contact_person'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($supplier['phone'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($supplier['email'] ?? 'N/A'); ?></td>
            <td>
                <!-- This "Edit" button triggers the modal JS in suppliers.php -->
                <button type="button" class="btn btn-sm btn-outline-primary edit-btn"
                        data-bs-toggle="modal" data-bs-target="#supplierModal"
                        data-id="<?php echo $supplier['id']; ?>"
                        data-name="<?php echo htmlspecialchars($supplier['name']); ?>"
                        data-contact="<?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?>"
                        data-phone="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>"
                        data-email="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>"
                        title="Edit">
                    <i class="bi bi-pencil-fill"></i>
                </button>
                <!-- This "Delete" form is intercepted by global JS (footer.php) -->
                <form method="POST" action="index.php?page=suppliers" style="display:inline-block;" class="form-confirm-delete" 
                    data-confirm-message="Are you sure you want to <strong>permanently delete</strong> this supplier? <p><strong>This action cannot be undone.</strong></p> (Note: The system will prevent deletion if this supplier is linked to any computers.)">
                    <?php echo $csrf_token_html; // Inject CSRF token?>
                    <input type="hidden" name="delete_id" value="<?php echo $supplier['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </form>
            </td>
        </tr>
    <?php
    endforeach;
    return ob_get_clean();
}

/**
 * Renders the HTML for the pagination controls.
 *
 * @param int $current_page The active page number.
 * @param int $total_pages The total number of pages.
 * @param array $query_params All current filter parameters (from $_GET).
 * @return string The generated HTML for the <nav> element.
 */
function renderPagination($current_page, $total_pages, $query_params)
{
    // Don't show pagination if there's only one page
    if ($total_pages <= 1) {
        return '';
    }

    ob_start();
    ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            
            <!-- Previous Button -->
            <!-- Disable 'Previous' button if on page 1 -->
            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                <?php $query_params['p'] = $current_page - 1; ?>
                <!-- http_build_query preserves all existing filters (search, status, etc.) -->
                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">Previous</a>
            </li>

            <!-- Page Number Buttons -->
            <?php for ($i = 1; $i <= $total_pages; $i++):
                $query_params['p'] = $i;
                ?>
                <!-- Add 'active' class to the current page number -->
                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <!-- Next Button -->
            <!-- Disable 'Next' button if on the last page -->
            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                <?php $query_params['p'] = $current_page + 1; ?>
                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php
    return ob_get_clean();
}
?>