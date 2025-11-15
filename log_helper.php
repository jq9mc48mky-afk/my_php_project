<?php

/**
 * Logging Helper Functions
 *
 * This file provides standardized functions for writing to the two
 * different audit logs:
 * 1. `asset_log`: Records all actions related to a *specific* computer asset.
 * 2. `system_log`: Records all *global* administrative actions
 * (e.g., user management, category/supplier changes, security events).
 */

/**
 * Logs an action related to a specific computer asset.
 *
 * Writes a new entry to the `asset_log` table.
 *
 * @param PDO $pdo The PDO database connection.
 * @param int $computer_id The ID of the computer asset being modified.
 * @param int $admin_user_id The ID of the admin user performing the action.
 * @param string $action The type of action (e.g., 'Created', 'Updated', 'Deleted', 'Checked In').
 * @param string $details A text description of what changed (e.g., "Status changed from 'In Stock' to 'Assigned'").
 */
function log_asset_change($pdo, $computer_id, $admin_user_id, $action, $details = '')
{
    try {
        $stmt = $pdo->prepare('
            INSERT INTO asset_log (computer_id, admin_user_id, action, details) 
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$computer_id, $admin_user_id, $action, $details]);
    } catch (PDOException $e) {
        // If logging fails, we log it to the server's error log
        // but we *do not* stop the user's original action (e.g., saving a computer).
        // Logging is important, but not at the cost of application usability.
        error_log('Failed to write to asset_log: ' . $e->getMessage());
    }
}

/**
 * Logs a general administrative or security action to the global system log.
 *
 * Writes a new entry to the `system_log` table.
 *
 * @param PDO $pdo The PDO database connection.
 * @param int $admin_user_id The ID of the user performing the action.
 * @param string $action_type The general category of action (e.g., 'User Management', 'Category', 'Security').
 * @param string $details A specific description of the action (e.g., "User created (ID: 5, Name: new_admin)").
 */
function log_system_change($pdo, $admin_user_id, $action_type, $details)
{
    try {
        // Get the user's IP address for security auditing
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

        $stmt = $pdo->prepare('
            INSERT INTO system_log (admin_user_id, action_type, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$admin_user_id, $action_type, $details, $ip_address]);
    } catch (PDOException $e) {
        // Same as above: log the failure but don't interrupt the user.
        error_log('Failed to write to system_log: ' . $e->getMessage());
    }
}
