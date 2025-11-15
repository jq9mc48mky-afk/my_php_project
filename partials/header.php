<?php
/**
 * Global page header and navigation.
 *
 * This file is included at the top of index.php. It contains:
 * - The HTML <head> section with all CSS includes.
 * - The responsive sidebar navigation menu.
 * - Conditional logic to show/hide navigation links based on user role.
 * - The mobile-specific top navigation bar.
 * - The start of the main page content wrapper.
 *
 * @global string $page The name of the current page (e.g., 'dashboard', 'computers').
 * @global string $role The role of the currently logged-in user (e.g., 'User', 'Admin').
 * @global string $csp_nonce The Content Security Policy nonce for inline styles/scripts.
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Computer Inventory System</title>
    
    <!-- CSS Assets -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/flatpickr.min.css" rel="stylesheet">
    
    <!-- Main application styles -->
    <style nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
        /* --- Sidebar Layout Styles --- */
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #f8f9fa; /* Light grey background */
        }

        /* Sidebar (Desktop) */
        .sidebar {
            height: 100vh; /* Full height */
            position: fixed; /* Sticky on the left */
            top: 0;
            left: 0;
            z-index: 1040; /* Above most content, below modals */
            width: 250px; /* Default Width */
            background-color: #212529; /* Dark background */
            transition: width 0.3s ease-in-out; 
            display: flex;
            flex-direction: column; /* Allows footer to stick to bottom */
            overflow-y: auto; /* Scrollable if content overflows */
            overflow-x: hidden; /* Prevents horizontal scroll */
        }

        /* --- Minimized Sidebar Styles (Desktop) --- */
        /* These rules ONLY apply on screens >= 992px wide */
        @media (min-width: 992px) {
            /* When body has 'sidebar-minimized' class, shrink sidebar */
            body.sidebar-minimized .sidebar {
                width: 80px;
            }
            /* Push main content to the left by the new sidebar width */
            body.sidebar-minimized .main-content {
                margin-left: 80px;
                width: calc(100% - 80px);
            }
            /* Hide all text elements in the minimized sidebar */
            body.sidebar-minimized .nav-text, 
            body.sidebar-minimized .sidebar-footer-text,
            body.sidebar-minimized .sidebar-header-text,
            body.sidebar-minimized .nav-header,
            body.sidebar-minimized .sidebar-badge {
                display: none !important;
            }
            /* Center the icons in the minimized state */
            body.sidebar-minimized .sidebar-nav .nav-link {
                text-align: center;
                padding: 10px 0;
            }
            body.sidebar-minimized .sidebar-nav .nav-link i {
                margin-right: 0;
                font-size: 1.5rem; /* Make icons slightly larger */
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

        /* Sidebar Overlay (Mobile) */
        /* This appears when the mobile menu is open, to darken the content */
        #sidebarOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1035; /* Just below the sidebar */
            display: none;
            backdrop-filter: blur(2px);
        }

        /* Sidebar Navigation Links */
        .sidebar-nav .nav-link {
            color: rgba(255, 255, 255, .75); /* Light text */
            padding: 10px 20px;
            font-size: 1rem;
            border-radius: 0.25rem;
            margin: 2px 10px;
            white-space: nowrap; /* Prevents text wrap */
            transition: all 0.2s ease-in-out;
        }

        /* Active State & Hover for nav links */
        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        /* Active link gets a blue left border */
        .sidebar-nav .nav-link.active {
            border-left: 4px solid #0d6efd;
            padding-left: 16px; /* Adjust padding to account for border */
        }

        .sidebar-nav .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        /* Sidebar Header (Logo/Title) */
        .sidebar-header {
            padding: 1rem 1.25rem;
            color: #fff;
            font-size: 1.25rem;
            font-weight: bold;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
            flex-shrink: 0; /* Prevents header from shrinking */
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
            margin-left: 250px; /* Default margin to match sidebar width */
            padding: 2rem;
            width: calc(100% - 250px);
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
        }

        /* --- Mobile Responsiveness (< 992px) --- */
        @media (max-width: 991.98px) {
            .sidebar {
                width: 250px !important; /* Force full width on mobile */
                transform: translateX(-100%); /* Hide off-screen */
                padding-top: 60px; /* Space for the top navbar */
                transition: transform 0.2s ease-in-out;
            }
            .sidebar.show { transform: translateX(0); } /* Slide in */
            .main-content { margin-left: 0 !important; width: 100% !important; }
            #sidebarToggle { display: none; } /* Hide desktop toggle */
        }
        
        /* On desktop, the overlay is never needed */
        @media (min-width: 992px) {
            #sidebarOverlay { display: none !important; }
        }

        /* --- Global Utility Styles --- */
        .list-asset-img { width: 60px; height: 60px; object-fit: cover; }
        .form-asset-img { max-height: 250px; }
        /* Container for AJAX tables, allows loading overlay */
        #data-table-container { position: relative; min-height: 200px; }
        /* Loading overlay for AJAX tables */
        .loading-overlay {
            position: absolute; inset: 0; background: rgba(255, 255, 255, 0.7);
            display: none; /* Hidden by default */
            align-items: center; justify-content: center;
            z-index: 10;
            transition: opacity 0.15s linear;
        }
        /* Fix for TomSelect dropdowns appearing behind modals */
        .ts-dropdown, .ts-wrapper.single.input-active .ts-dropdown { z-index: 2000 !important; }
    </style>
</head>
<body class="<?php echo $_COOKIE['sidebar-minimized'] ?? ''; ?>">

<!-- Top Navbar (Mobile-only: d-lg-none) -->
<nav class="navbar navbar-dark bg-dark sticky-top shadow-sm d-lg-none">
    <div class="container-fluid">
        <!-- Mobile menu toggle button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="#">Inventory System</a>
        <!-- Mobile profile link -->
        <a href="index.php?page=profile" class="text-white text-decoration-none">
            <i class="bi bi-person-circle fs-4"></i>
        </a>
    </div>
</nav>

<div class="d-flex">
    <!-- Overlay for mobile menu -->
    <div id="sidebarOverlay"></div>

    <!-- Main Sidebar -->
    <!-- 'collapse' for mobile, 'd-lg-block' for desktop -->
    <div class="collapse d-lg-block sidebar bg-dark" id="sidebarMenu">
        
        <!-- Sidebar Header (Desktop-only: d-none d-lg-flex) -->
        <div class="sidebar-header d-none d-lg-flex">
            <div>
                <i class="bi bi-pc-display me-2"></i>
                <span class="sidebar-header-text">Inventory</span>
            </div>
            <!-- Desktop sidebar minimize toggle button -->
            <i class="bi bi-list fs-4" id="sidebarToggle" style="cursor: pointer;" title="Toggle Sidebar"></i>
        </div>
        
        <!-- Navigation Links -->
        <ul class="nav flex-column sidebar-nav flex-grow-1">
            <!-- Dashboard (All users) -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($page == 'dashboard') ? 'active' : ''; ?>" 
                   href="index.php?page=dashboard" 
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <i class="bi bi-speedometer2"></i> <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <!-- Computers (All users) -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($page == 'computers') ? 'active' : ''; ?>" 
                   href="index.php?page=computers"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Computers">
                    <i class="bi bi-laptop"></i> <span class="nav-text">Computers</span>
                </a>
            </li>
            
            <!-- Admin-Only Links -->
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
            
            <!-- Super Admin-Only Links -->
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

            <!-- Account Links (All users) -->
            <li class="nav-header text-uppercase text-muted fs-7 fw-bold mt-3 ms-3 mb-1" style="font-size: 0.75rem;">Account</li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($page == 'profile') ? 'active' : ''; ?>" 
                   href="index.php?page=profile"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Profile">
                    <i class="bi bi-person-circle"></i> <span class="nav-text">My Profile</span>
                </a>
            </li>
            <!-- Logout Button -->
            <li class="nav-item mt-2 mb-3">
                <form action="logout.php" method="POST" class="px-3">
                    <?php echo csrf_input(); // CSRF token for logout?>
                    <button type="submit" class="btn btn-outline-light w-100 btn-sm" title="Logout">
                        <i class="bi bi-box-arrow-right"></i> <span class="nav-text">Logout</span>
                    </button>
                </form>
            </li>
        </ul>
        
        <!-- Sidebar Footer (Logged in as...) -->
        <!-- 'flex-shrink: 0' ensures it doesn't shrink -->
        <!-- 'mt-auto' pushes it to the bottom of the flex column -->
        <div class="mt-auto p-3 text-white-50 small d-none d-lg-block border-top border-secondary flex-shrink-0">
            <span class="sidebar-footer-text">Logged in as: <br></span>
            <strong class="text-white sidebar-footer-text"><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
            
            <!-- Centered Icon for Minimized State (hidden by default) -->
            <div class="text-center d-none d-minimized-block">
                 <i class="bi bi-person-circle fs-5" title="<?php echo htmlspecialchars($_SESSION['full_name']); ?>"></i>
            </div>
        </div>
    </div>

    <!-- Main Content Wrapper (closed in footer.php) -->
    <div class="main-content">
    
    <!-- Toast container (for session messages) -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100"></div>