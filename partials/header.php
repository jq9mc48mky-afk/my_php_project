<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Computer Inventory System</title>
    
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/flatpickr.min.css" rel="stylesheet">
    
    <style nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
        /* --- Sidebar Layout Styles --- */
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #f8f9fa;
        }

        /* Sidebar specific */
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            width: 250px; /* Default Width */
            background-color: #212529;
            transition: width 0.3s ease-in-out; 
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* *** FIX: WRAP MINIMIZED STYLES IN MEDIA QUERY *** */
        /* These rules will ONLY apply on Desktop screens (>= 992px) */
        @media (min-width: 992px) {
            body.sidebar-minimized .sidebar {
                width: 80px;
            }
            body.sidebar-minimized .main-content {
                margin-left: 80px;
                width: calc(100% - 80px);
            }
            body.sidebar-minimized .nav-text, 
            body.sidebar-minimized .sidebar-footer-text,
            body.sidebar-minimized .sidebar-header-text,
            body.sidebar-minimized .nav-header,
            body.sidebar-minimized .sidebar-badge { /* Hide badge when minimized */
                display: none !important;
            }
            body.sidebar-minimized .sidebar-nav .nav-link {
                text-align: center;
                padding: 10px 0;
            }
            body.sidebar-minimized .sidebar-nav .nav-link i {
                margin-right: 0;
                font-size: 1.5rem;
            }
            body.sidebar-minimized .sidebar-header {
                justify-content: center;
                padding: 1rem 0;
            }
            /* Show the centered icon in footer when minimized */
            body.sidebar-minimized .d-minimized-block {
                display: block !important;
            }
        }

        /* Sidebar Overlay */
        #sidebarOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.5); z-index: 1035;
            display: none; backdrop-filter: blur(2px);
        }

        .sidebar-nav .nav-link {
            color: rgba(255, 255, 255, .75);
            padding: 10px 20px;
            font-size: 1rem;
            border-radius: 0.25rem;
            margin: 2px 10px;
            white-space: nowrap;
            transition: all 0.2s ease-in-out; /* Smooth Hover */
        }

        /* Active State & Hover */
        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-nav .nav-link.active {
            border-left: 4px solid #0d6efd; /* Accent Border */
            padding-left: 16px;
        }

        .sidebar-nav .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 1rem 1.25rem;
            color: #fff;
            font-size: 1.25rem;
            font-weight: bold;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .sidebar-badge {
            margin-left: auto;
            font-size: 0.7rem;
        }

        /* Main Content Area */
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            width: calc(100% - 250px);
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
        }

        /* Mobile Responsiveness */
        @media (max-width: 991.98px) {
            .sidebar {
                width: 250px !important; /* Force full width on mobile */
                transform: translateX(-100%);
                padding-top: 60px;
                transition: transform 0.2s ease-in-out;
            }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; width: 100% !important; }
            #sidebarToggle { display: none; }
        }
        
        @media (min-width: 992px) {
            #sidebarOverlay { display: none !important; }
        }

        /* Existing Styles */
        .list-asset-img { width: 60px; height: 60px; object-fit: cover; }
        .form-asset-img { max-height: 250px; }
        #data-table-container { position: relative; min-height: 200px; }
        .loading-overlay {
            position: absolute; inset: 0; background: rgba(255, 255, 255, 0.7);
            display: none; align-items: center; justify-content: center;
            z-index: 10; transition: opacity 0.15s linear;
        }
        .ts-dropdown, .ts-wrapper.single.input-active .ts-dropdown { z-index: 2000 !important; }
    </style>
</head>
<body>

<!-- Top Navbar (Mobile) -->
<nav class="navbar navbar-dark bg-dark sticky-top shadow-sm d-lg-none">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="#">Inventory System</a>
        <a href="index.php?page=profile" class="text-white text-decoration-none">
            <i class="bi bi-person-circle fs-4"></i>
        </a>
    </div>
</nav>

<div class="d-flex">
    <div id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="collapse d-lg-block sidebar bg-dark" id="sidebarMenu">
        <div class="sidebar-header d-none d-lg-flex">
            <div>
                <i class="bi bi-pc-display me-2"></i>
                <span class="sidebar-header-text">Inventory</span>
            </div>
            <!-- Toggle Button -->
            <i class="bi bi-list fs-4 cursor-pointer" id="sidebarToggle" style="cursor: pointer;" title="Toggle Sidebar"></i>
        </div>
        
        <ul class="nav flex-column sidebar-nav flex-grow-1">
            <li class="nav-item">
                <a class="nav-link <?php echo ($page == 'dashboard') ? 'active' : ''; ?>" 
                   href="index.php?page=dashboard" 
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <i class="bi bi-speedometer2"></i> <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($page == 'computers') ? 'active' : ''; ?>" 
                   href="index.php?page=computers"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Computers">
                    <i class="bi bi-laptop"></i> <span class="nav-text">Computers</span>
                </a>
            </li>
            
            <?php if ($role == 'Admin' || $role == 'Super Admin'): ?>
                <li class="nav-header text-uppercase text-muted fs-7 fw-bold mt-3 ms-3 mb-1" style="font-size: 0.75rem;">Management</li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'suppliers') ? 'active' : ''; ?>" 
                       href="index.php?page=suppliers"
                       data-bs-toggle="tooltip" data-bs-placement="right" title="Suppliers">
                        <i class="bi bi-truck"></i> <span class="nav-text">Suppliers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'categories') ? 'active' : ''; ?>" 
                       href="index.php?page=categories"
                       data-bs-toggle="tooltip" data-bs-placement="right" title="Categories">
                        <i class="bi bi-tags"></i> <span class="nav-text">Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'maintenance') ? 'active' : ''; ?>" 
                       href="index.php?page=maintenance"
                       data-bs-toggle="tooltip" data-bs-placement="right" title="Maintenance">
                        <i class="bi bi-tools"></i> 
                        <span class="nav-text">Maintenance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'reports') ? 'active' : ''; ?>" 
                       href="index.php?page=reports"
                       data-bs-toggle="tooltip" data-bs-placement="right" title="Reports">
                        <i class="bi bi-file-earmark-bar-graph"></i> <span class="nav-text">Reports</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if ($role == 'Super Admin'): ?>
                <li class="nav-header text-uppercase text-muted fs-7 fw-bold mt-3 ms-3 mb-1" style="font-size: 0.75rem;">System</li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'users') ? 'active' : ''; ?>" 
                       href="index.php?page=users"
                       data-bs-toggle="tooltip" data-bs-placement="right" title="Users">
                        <i class="bi bi-people"></i> <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'system_log') ? 'active' : ''; ?>" 
                       href="index.php?page=system_log"
                       data-bs-toggle="tooltip" data-bs-placement="right" title="System Log">
                        <i class="bi bi-journal-text"></i> <span class="nav-text">System Log</span>
                    </a>
                </li>
            <?php endif; ?>

            <li class="nav-header text-uppercase text-muted fs-7 fw-bold mt-3 ms-3 mb-1" style="font-size: 0.75rem;">Account</li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($page == 'profile') ? 'active' : ''; ?>" 
                   href="index.php?page=profile"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Profile">
                    <i class="bi bi-person-circle"></i> <span class="nav-text">My Profile</span>
                </a>
            </li>
            <li class="nav-item mt-2 mb-3">
                <form action="logout.php" method="POST" class="px-3">
                    <?php echo csrf_input(); ?>
                    <button type="submit" class="btn btn-outline-light w-100 btn-sm" title="Logout">
                        <i class="bi bi-box-arrow-right"></i> <span class="nav-text">Logout</span>
                    </button>
                </form>
            </li>
        </ul>
        
        <div class="mt-auto p-3 text-white-50 small d-none d-lg-block border-top border-secondary flex-shrink-0">
            <span class="sidebar-footer-text">Logged in as: <br></span>
            <strong class="text-white sidebar-footer-text"><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
            
            <!-- Centered Icon for Minimized State -->
            <div class="text-center d-none d-minimized-block">
                 <i class="bi bi-person-circle fs-5" title="<?php echo htmlspecialchars($_SESSION['full_name']); ?>"></i>
            </div>
        </div>
    </div>

    <!-- Main Content Wrapper -->
    <div class="main-content">
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100"></div>