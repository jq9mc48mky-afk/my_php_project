<?php
/**
 * Global page footer.
 *
 * This file is included at the bottom of index.php. It contains:
 * - Closing HTML tags for the main content and layout.
 * - HTML definitions for all global modals (Delete Confirmation,
 * Action Confirmation, Validation Error).
 * - All global JavaScript includes (Bootstrap, TomSelect, Flatpickr).
 * - The main global JavaScript block for the application, which handles:
 * - Global helper functions (TomSelect, Flatpickr, Modals).
 * - Sidebar toggle logic (desktop minimize, mobile overlay).
 * - Toast notification logic (showing messages from PHP sessions).
 * - Global modal event delegation (for delete/action confirmations).
 * - Global AJAX logic for all filterable tables.
 *
 * @global string $csp_nonce The Content Security Policy nonce for inline scripts.
 */
?>
</div> <!-- End .main-content (from header.php) -->
</div> <!-- End .d-flex (from header.php) -->

<!-- === GLOBAL MODALS === -->

<!-- Delete Confirmation Modal -->
<!-- This modal is triggered by any form with class .form-confirm-delete -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmDeleteModalBody">Are you sure? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteButton">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Generic Action Confirmation Modal -->
<!-- This modal is triggered by any form with class .form-confirm-action -->
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmActionModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmActionModalBody">Proceed?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmActionButton">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Validation Error Modal -->
<!-- This modal is triggered manually by JS (e.g., in computers.php) -->
<div class="modal fade" id="validationErrorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Validation Error</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="validationErrorModalBody">Error details.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- === GLOBAL SCRIPTS === -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/tom-select.complete.min.js"></script>
<script src="assets/js/flatpickr.min.js"></script>

<!-- Main Application JavaScript -->
<script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
    
    // --- Global Helper Functions ---

    /**
     * Initializes a TomSelect instance on a given selector with default options.
     * @param {string} selector - The CSS selector for the <select> element.
     * @param {object} userOptions - Custom TomSelect options to merge with defaults.
     * @returns {TomSelect|null} The TomSelect instance or null if element not found.
     */
    window.initTomSelect = (selector, userOptions = {}) => {
        const el = document.querySelector(selector);
        if (el) {
            const defaultOptions = {
                create: false,
                sortField: { field: "text", direction: "asc" },
                allowEmptyOption: false,
                dropdownParent: 'body' // Fix for dropdowns being clipped inside modals
            };
            return new TomSelect(el, { ...defaultOptions, ...userOptions });
        }
        return null;
    };

    /**
     * Initializes a Flatpickr date picker with default options.
     * @param {string} selector - The CSS selector for the <input> element.
     * @param {object} userOptions - Custom Flatpickr options.
     * @param {string} cspNonce - The CSP nonce.
     * @returns {flatpickr|null} The Flatpickr instance or null.
     */
    window.initFlatpickr = (selector, userOptions = {}, cspNonce) => {
        const el = document.querySelector(selector);
        if (el) {
            const defaultOptions = {
                dateFormat: "Y-m-d", // Store as Y-m-d
                altInput: true,      // Show a human-friendly date
                altFormat: "F j, Y", // e.g., "June 10, 2025"
                allowInput: true,    // Allow typing dates
                cspNonce: cspNonce   // Pass nonce for security
            };
            return flatpickr(el, { ...defaultOptions, ...userOptions });
        }
        return null;
    };

    /**
     * Displays the global validation error modal with a custom message.
     * @param {string} message - The HTML message to display in the modal body.
     */
    window.showValidationModal = (message) => {
        const body = document.getElementById('validationErrorModalBody');
        if (body) body.innerHTML = message;
        const el = document.getElementById('validationErrorModal');
        if (el) new bootstrap.Modal(el).show();
    };

    /**
     * Main script execution block. Runs after the DOM is fully loaded.
     */
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- Scroll to top on every full page load ---
        window.scrollTo(0, 0);
        
        // --- Sidebar Toggle Logic (Desktop) ---
        const sidebarToggle = document.getElementById('sidebarToggle');
        
        // Check LocalStorage on page load to restore sidebar state
        if (localStorage.getItem('sidebar-minimized') === 'true') {
            document.body.classList.add('sidebar-minimized');
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                document.body.classList.toggle('sidebar-minimized');
                // Save the new state to localStorage for persistence
                const isMinimized = document.body.classList.contains('sidebar-minimized');
                localStorage.setItem('sidebar-minimized', isMinimized);
            });
        }
        // --- End Sidebar Toggle Logic ---

        // --- Sidebar Overlay Logic (Mobile) ---
        const sidebar = document.getElementById('sidebarMenu');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar && overlay) {
            // Show overlay when Bootstrap's collapse 'show' event fires
            sidebar.addEventListener('show.bs.collapse', () => overlay.style.display = 'block');
            // Hide overlay when 'hide' event fires
            sidebar.addEventListener('hide.bs.collapse', () => overlay.style.display = 'none');
            // Also hide the menu if the user clicks the overlay
            overlay.addEventListener('click', () => {
                const bsCollapse = bootstrap.Collapse.getInstance(sidebar);
                if (bsCollapse) bsCollapse.hide();
            });
        }

        // --- Toast Notification Logic ---
        
        /**
         * Globally accessible function to show a toast notification.
         * @param {string} message - The text message to display.
         * @param {string} type - 'success' or 'error' (controls color/icon).
         */
        window.showToast = (message, type = 'success') => {
            const container = document.querySelector('.toast-container');
            if (!container) return;
            const id = 'toast-' + Math.random().toString(36).substring(2, 9);
            const bg = (type === 'success') ? 'bg-success' : 'bg-danger';
            const icon = (type === 'success') ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
            
            // Create the toast HTML
            const html = `
                <div class="toast align-items-center text-white ${bg} border-0" role="alert" aria-live="assertive" aria-atomic="true" id="${id}">
                    <div class="d-flex">
                        <div class="toast-body"><i class="bi ${icon} me-2"></i> ${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>`;
            
            container.insertAdjacentHTML('beforeend', html);
            const el = document.getElementById(id);
            const toast = new bootstrap.Toast(el, { delay: 5000 });
            // Remove the toast from the DOM after it's hidden
            el.addEventListener('hidden.bs.toast', () => el.remove());
            toast.show();
        };
        
        // --- PHP Session-to-Toast Bridge ---
        // This PHP block checks for session messages and uses the JS
        // 'showToast' function to display them, then unsets them.
        <?php if (isset($_SESSION['success'])): ?>
            window.showToast(<?php echo json_encode($_SESSION['success']); ?>, 'success');
            <?php unset($_SESSION['success']); // Clear the message?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            window.showToast(<?php echo json_encode($_SESSION['error']); ?>, 'error');
            <?php unset($_SESSION['error']); // Clear the message?>
        <?php endif; ?>

        // --- Modal Event Delegation ---
        // This is a global system that intercepts form submissions to show
        // confirmation modals, rather than attaching listeners to each form.
        
        // 1. Delete Modal Delegation
        const deleteModalEl = document.getElementById('confirmDeleteModal');
        let deleteForm = null; // Stores the form that triggered the modal
        if (deleteModalEl) {
            const modal = new bootstrap.Modal(deleteModalEl);
            const btn = document.getElementById('confirmDeleteButton');
            const body = document.getElementById('confirmDeleteModalBody');
            
            // Listen on the *entire body* for submit events
            document.body.addEventListener('submit', (e) => {
                // If the submitted form has the class .form-confirm-delete
                if (e.target.matches('.form-confirm-delete')) {
                    e.preventDefault(); // Stop the form from submitting
                    deleteForm = e.target; // Store this form
                    // Set modal text from the form's data attribute
                    body.innerHTML = deleteForm.getAttribute('data-confirm-message') || 'Are you sure?';
                    modal.show(); // Show the modal
                }
            });
            // When the red "Delete" button is clicked, submit the stored form
            btn.addEventListener('click', () => { if(deleteForm) deleteForm.submit(); modal.hide(); });
        }

        // 2. Generic Action Modal Delegation (for non-delete actions)
        const actionModalEl = document.getElementById('confirmActionModal');
        let actionForm = null; // Stores the form that triggered the modal
        if (actionModalEl) {
            const modal = new bootstrap.Modal(actionModalEl);
            const btn = document.getElementById('confirmActionButton');
            const title = document.getElementById('confirmActionModalLabel');
            const body = document.getElementById('confirmActionModalBody');
            
            // Listen on the *entire body* for submit events
            document.body.addEventListener('submit', (e) => {
                // If the submitted form has the class .form-confirm-action
                if (e.target.matches('.form-confirm-action')) {
                    e.preventDefault(); // Stop submission
                    actionForm = e.target; // Store this form
                    // Customize the modal using data attributes from the form
                    title.textContent = actionForm.getAttribute('data-confirm-title') || 'Confirm';
                    body.innerHTML = actionForm.getAttribute('data-confirm-message') || 'Proceed?';
                    btn.textContent = actionForm.getAttribute('data-confirm-button-text') || 'Confirm';
                    // e.g., 'btn-success' for "Mark Complete"
                    btn.className = `btn ${actionForm.getAttribute('data-confirm-button-class') || 'btn-primary'}`;
                    modal.show();
                }
            });
            // When the "Confirm" button is clicked, submit the stored form
            btn.addEventListener('click', () => { if(actionForm) actionForm.submit(); modal.hide(); });
        }

        // --- AJAX Table Logic (for all filterable lists) ---
        const filterForm = document.getElementById('ajax-filter-form');
        if (filterForm) {
            // Get all the dynamic elements
            const tableBody = document.getElementById('data-table-body');
            const pagination = document.getElementById('pagination-controls');
            const loading = document.getElementById('loading-overlay');
            const clearBtn = document.getElementById('clear-filters-btn');
            // Get the 'data-type' (e.g., "computers", "categories")
            const pageType = filterForm.dataset.type;
            let timer; // For debouncing search input

            /**
             * Fetches data from api.php and updates the table and pagination.
             * @param {string} url - The API URL to fetch.
             */
            const fetchData = async (url) => {
                if (loading) loading.style.display = 'flex'; // Show spinner
                try {
                    const res = await fetch(url);
                    if (!res.ok) throw new Error((await res.json()).error || res.status);
                    const data = await res.json();
                    
                    // Inject the new HTML from the API response
                    if (tableBody) tableBody.innerHTML = data.tableBody;
                    if (pagination) pagination.innerHTML = data.pagination;

                    // --- Scroll to top after AJAX load ---
                    window.scrollTo(0, 0);
                    
                    // Hide bulk action footer if there are no results
                    const bulkFooter = document.getElementById('bulk-action-footer');
                    if (bulkFooter) {
                        const noResults = tableBody.querySelector('td[colspan]');
                        bulkFooter.style.display = noResults ? 'none' : 'block';
                    }
                } catch (err) {
                    window.showToast(`Error: ${err.message}`, 'error');
                } finally {
                    if (loading) loading.style.display = 'none'; // Hide spinner
                }
            };

            /**
             * Builds the API URL from the filter form.
             * @param {number} p - The page number to request.
             * @returns {string} The full URL for the API request.
             */
            const getUrl = (p = 1) => {
                const params = new URLSearchParams(new FormData(filterForm));
                params.set('type', pageType);
                params.set('p', p);
                params.delete('page'); // Remove the 'page' param (e.g., 'computers')
                return `api.php?${params.toString()}`;
            };

            /**
             * Main function to trigger an update.
             * Resets to page 1 and updates browser history.
             */
            const update = () => {
                const url = getUrl(1); // Always go to page 1 on filter change
                // Update the browser's URL bar without reloading the page
                window.history.pushState({}, '', url.replace('api.php', 'index.php').replace('type=', 'page='));
                fetchData(url);
            };

            // --- Attach Event Listeners ---

            // Handle form submission (e.g., pressing Enter)
            filterForm.addEventListener('submit', (e) => { e.preventDefault(); update(); });
            
            // Handle live filtering on inputs (debounced)
            filterForm.querySelectorAll('input, select').forEach(input => {
                if (input.name === 'page') return; // Skip the hidden 'page' input
                
                const eventType = input.tagName === 'SELECT' ? 'change' : 'input';
                
                input.addEventListener(eventType, () => {
                    clearTimeout(timer); // Reset the timer
                    // Wait 300ms after user stops typing to send request
                    timer = setTimeout(update, 300);
                });
            });

            // Handle pagination clicks (uses event delegation)
            document.body.addEventListener('click', (e) => {
                // If the click was on a page link inside the pagination controls
                if (e.target.matches('#pagination-controls .page-link')) {
                    e.preventDefault();
                    const href = e.target.getAttribute('href');
                    if (href) {
                        // Get the 'p' (page) number from the link's href
                        const p = new URL(href, window.location.origin).searchParams.get('p') || 1;
                        const url = getUrl(p); // Get the API URL for that page
                        // Update browser history
                        window.history.pushState({}, '', url.replace('api.php', 'index.php').replace('type=', 'page='));
                        fetchData(url); // Fetch the new page
                    }
                }
            });

            // Handle "Clear" button
            if (clearBtn) {
                clearBtn.addEventListener('click', (e) => {
                    e.preventDefault();

                    // --- CRITICAL FIX ---
                    // We must manually clear any TomSelect instances,
                    // as form.reset() doesn't work on them.
                    filterForm.querySelectorAll('select').forEach(select => {
                        if (select.tomselect) {
                            select.tomselect.clear();
                        }
                    });
                    // --- END FIX ---

                    filterForm.reset(); // Reset all form fields
                    // Reset browser URL
                    window.history.pushState({}, '', window.location.pathname + '?page=' + pageType);
                    fetchData(getUrl(1)); // Fetch page 1 with no filters
                });
            }
        }
    });
</script>
</body>
</html>