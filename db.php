<?php

/**
 * Database Connection Setup
 *
 * This file handles the critical setup for the database connection.
 * 1. Loads the Composer autoloader (for the dotenv library).
 * 2. Loads environment variables from the .env file (for security).
 * 3. Establishes a PDO (PHP Data Objects) connection to the MySQL database.
 * 4. Configures PDO attributes for error handling and fetch mode.
 *
 * @global PDO $pdo This file creates the global $pdo object.
 */

// 1. Load Composer autoloader
require __DIR__ . '/vendor/autoload.php';

// 2. Load .env variables
// `createImmutable` ensures variables are loaded safely and won't be overwritten.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- 3. Database Configuration ---
// Read from .env, providing sensible defaults if keys are missing.
$dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'inventory_db');
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

// --- 4. Create PDO Connection ---
try {
    // The $pdo object is our gateway to the database.
    $pdo = new PDO($dsn, $username, $password);

    // --- 5. Configure PDO Attributes ---

    // Set PDO to throw exceptions on error (e.g., bad SQL).
    // This allows us to use try...catch blocks for error handling.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set the default fetch mode to associative array (e.g., $row['column_name']).
    // This is more convenient than the default (which returns both numeric and assoc. keys).
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // If the database connection fails entirely, stop the application.
    // This is one of the few places we 'die' because the app cannot run without a DB.
    die("Database connection failed: " . $e->getMessage());
}
