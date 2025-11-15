<?php

/**
 * Logs an action related to a computer asset.
 *
 * @param PDO $pdo The PDO database connection.
 * @param int $computer_id The ID of the computer.
 * @param int $admin_user_id The ID of the user performing the action.
 * @param string $action The type of action (e.g., 'Created', 'Updated', 'Deleted').
 * @param string $details A description of what changed.
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
        // Log error to server log
        error_log('Failed to write to asset_log: ' . $e->getMessage());
        // We don't stop the user's action if logging fails
    }
}

/**
 * --- NEW: Global System Audit Log ---
 * Logs a general administrative action.
 *
 * @param PDO $pdo The PDO database connection.
 * @param int $admin_user_id The ID of the user performing the action.
 * @param string $action_type The general category of action (e.g., 'User Management', 'Category').
 * @param string $details A specific description of the action.
 */
function log_system_change($pdo, $admin_user_id, $action_type, $details)
{
    try {
        // Get user's IP address
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

        $stmt = $pdo->prepare('
            INSERT INTO system_log (admin_user_id, action_type, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$admin_user_id, $action_type, $details, $ip_address]);
    } catch (PDOException $e) {
        // Log error to server log
        error_log('Failed to write to system_log: ' . $e->getMessage());
        // We don't stop the user's action if logging fails
    }
}
