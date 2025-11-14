<?php
// Database configuration
$dsn = 'mysql:host=localhost;dbname=inventory_db';
$username = 'root'; // Your MySQL username
$password = ''; // Your MySQL password

// Allow exceptions to be thrown by the PDO object
$pdo = new PDO($dsn, $username, $password);

// Set PDO to throw exceptions on error
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Set the default fetch mode to associative array
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
?>