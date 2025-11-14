</div> <!-- End .main-content -->
</div> <!-- End .d-flex -->

<!-- Modals (Delete, Confirm Action, Validation) -->
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

<!-- Scripts -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/tom-select.complete.min.js"></script>
<script src="assets/js/flatpickr.min.js"></script>

<script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
    // --- STEP 1: DEFINE ALL HELPERS GLOBALLY ---
    // We define these functions first, outside any listeners,
    // so they are available immediately.

    window.initTomSelect = (selector, userOptions = {}) => {
        const el = document.querySelector(selector);
        if (el) {
            const defaultOptions = {
                create: false,
                sortField: { field: "text", direction: "asc" }
            };
            return new TomSelect(el, { ...defaultOptions, ...userOptions });
        }
        return null;
    };

    window.initFlatpickr = (selector, userOptions = {}, cspNonce) => {
        const el = document.querySelector(selector);
        if (el) {
            const defaultOptions = {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "F j, Y",
                allowInput: true,
                cspNonce: cspNonce
            };
            return flatpickr(el, { ...defaultOptions, ...userOptions });
        }
        return null;
    };

    window.showValidationModal = (message) => {
        const body = document.getElementById('validationErrorModalBody');
        if (body) body.innerHTML = message;
        const el = document.getElementById('validationErrorModal');
        if (el) new bootstrap.Modal(el).show();
    };

    window.showToast = (message, type = 'success') => {
        const container = document.querySelector('.toast-container');
        if (!container) return;
        const id = 'toast-' + Math.random().toString(36).substring(2, 9);
        const bg = (type === 'success') ? 'bg-success' : 'bg-danger';
        const icon = (type === 'success') ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
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
        el.addEventListener('hidden.bs.toast', () => el.remove());
        toast.show();
    };

    // --- STEP 2: RUN ALL LOGIC AFTER DOM IS READY ---
    // Now we have ONE single listener for all page logic.
    
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- NEW: Mini Sidebar Toggle Logic ---
        const sidebarToggle = document.getElementById('sidebarToggle');
        
        // Check LocalStorage on load
        if (localStorage.getItem('sidebar-minimized') === 'true') {
            document.body.classList.add('sidebar-minimized');
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                document.body.classList.toggle('sidebar-minimized');
                // Save state
                const isMinimized = document.body.classList.contains('sidebar-minimized');
                localStorage.setItem('sidebar-minimized', isMinimized);
            });
        }
        // --- End Mini Sidebar Logic ---

        // --- Sidebar Overlay Logic (Mobile) ---
        const sidebar = document.getElementById('sidebarMenu');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar && overlay) {
            sidebar.addEventListener('show.bs.collapse', () => overlay.style.display = 'block');
            sidebar.addEventListener('hide.bs.collapse', () => overlay.style.display = 'none');
            overlay.addEventListener('click', () => {
                const bsCollapse = bootstrap.Collapse.getInstance(sidebar);
                if (bsCollapse) bsCollapse.hide();
            });
        }

        // --- Toast Notification Logic (Now uses the helper) ---
        <?php if (isset($_SESSION['success'])): ?>
            window.showToast(<?php echo json_encode($_SESSION['success']); ?>, 'success');
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            window.showToast(<?php echo json_encode($_SESSION['error']); ?>, 'error');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        // --- Modal Delegation Logic (Delete/Confirm) ---
        const deleteModalEl = document.getElementById('confirmDeleteModal');
        let deleteForm = null;
        if (deleteModalEl) {
            const modal = new bootstrap.Modal(deleteModalEl);
            const btn = document.getElementById('confirmDeleteButton');
            const body = document.getElementById('confirmDeleteModalBody');
            document.body.addEventListener('submit', (e) => {
                if (e.target.matches('.form-confirm-delete')) {
                    e.preventDefault();
                    deleteForm = e.target;
                    body.innerHTML = deleteForm.getAttribute('data-confirm-message') || 'Are you sure?';
                    modal.show();
                }
            });
            btn.addEventListener('click', () => { if(deleteForm) deleteForm.submit(); modal.hide(); });
        }

        const actionModalEl = document.getElementById('confirmActionModal');
        let actionForm = null;
        if (actionModalEl) {
            const modal = new bootstrap.Modal(actionModalEl);
            const btn = document.getElementById('confirmActionButton');
            const title = document.getElementById('confirmActionModalLabel');
            const body = document.getElementById('confirmActionModalBody');
            document.body.addEventListener('submit', (e) => {
                if (e.target.matches('.form-confirm-action')) {
                    e.preventDefault();
                    actionForm = e.target;
                    title.textContent = actionForm.getAttribute('data-confirm-title') || 'Confirm';
                    body.innerHTML = actionForm.getAttribute('data-confirm-message') || 'Proceed?';
                    btn.textContent = actionForm.getAttribute('data-confirm-button-text') || 'Confirm';
                    btn.className = `btn ${actionForm.getAttribute('data-confirm-button-class') || 'btn-primary'}`;
                    modal.show();
                }
            });
            btn.addEventListener('click', () => { if(actionForm) actionForm.submit(); modal.hide(); });
        }

        // --- AJAX Table Logic (Filter Form) ---
        const filterForm = document.getElementById('ajax-filter-form');
        if (filterForm) {
            const tableBody = document.getElementById('data-table-body');
            const pagination = document.getElementById('pagination-controls');
            const loading = document.getElementById('loading-overlay');
            const clearBtn = document.getElementById('clear-filters-btn');
            const pageType = filterForm.dataset.type;
            let timer;

            const fetchData = async (url) => {
                if (loading) loading.style.display = 'flex';
                try {
                    const res = await fetch(url);
                    if (!res.ok) throw new Error((await res.json()).error || res.status);
                    const data = await res.json();
                    if (tableBody) tableBody.innerHTML = data.tableBody;
                    if (pagination) pagination.innerHTML = data.pagination;
                    const bulkFooter = document.getElementById('bulk-action-footer');
                    if (bulkFooter) {
                        const noResults = tableBody.querySelector('td[colspan]');
                        bulkFooter.style.display = noResults ? 'none' : 'block';
                    }
                } catch (err) {
                    window.showToast(`Error: ${err.message}`, 'error');
                } finally {
                    if (loading) loading.style.display = 'none';
                }
            };

            const getUrl = (p = 1) => {
                const params = new URLSearchParams(new FormData(filterForm));
                params.set('type', pageType);
                params.set('p', p);
                params.delete('page');
                return `api.php?${params.toString()}`;
            };

            const update = () => {
                const url = getUrl(1);
                window.history.pushState({}, '', url.replace('api.php', 'index.php').replace('type=', 'page='));
                fetchData(url);
            };

            filterForm.addEventListener('submit', (e) => { e.preventDefault(); update(); });
            
            filterForm.querySelectorAll('input, select').forEach(input => {
                if (input.name === 'page') return;
                input.addEventListener(input.tagName === 'SELECT' ? 'change' : 'input', () => {
                    clearTimeout(timer);
                    timer = setTimeout(update, 300);
                });
            });

            document.body.addEventListener('click', (e) => {
                if (e.target.matches('#pagination-controls .page-link')) {
                    e.preventDefault();
                    const href = e.target.getAttribute('href');
                    if (href) {
                        const p = new URL(href, window.location.origin).searchParams.get('p') || 1;
                        const url = getUrl(p);
                        window.history.pushState({}, '', url.replace('api.php', 'index.php').replace('type=', 'page='));
                        fetchData(url);
                    }
                }
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', (e) => {
                    e.preventDefault();

                    // Manually clear any Tom Select instances before resetting the form
                    filterForm.querySelectorAll('select').forEach(select => {
                        if (select.tomselect) {
                            select.tomselect.clear();
                        }
                    });

                    filterForm.reset();
                    window.history.pushState({}, '', window.location.pathname + '?page=' + pageType);
                    fetchData(getUrl(1));
                });
            }
        }

        // --- STEP 3: FIRE THE "APP LOADED" EVENT ---
        // This *must* be at the end of this listener, after
        // all other logic is set up.
        document.dispatchEvent(new Event('app:loaded'));

    });
</script>
</body>
</html>