<?php
/**
 * Database Connection Setup
 *
 * This file loads Composer's autoloader, reads environment variables
 * from the .env file, and establishes a PDO database connection.
 * It also configures PDO attributes for error handling and fetch mode.
 */

// Load Composer autoloader
require __DIR__ . '/vendor/autoload.php';

// Load .env variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- Database Configuration ---
$dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'inventory_db');
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

// --- Create PDO Connection ---
// Allow exceptions to be thrown by the PDO object
$pdo = new PDO($dsn, $username, $password);

// Set PDO to throw exceptions on error
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Set the default fetch mode to associative array
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);