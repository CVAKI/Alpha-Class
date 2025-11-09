<?php
// File: /alpha-class/config.php

$host = 'localhost';        // Usually 'localhost' for XAMPP
$user = 'root';             // Default MySQL username in XAMPP
$password = '';             // Default password is blank in XAMPP
$database = 'alpha_class';  // Your database name

// Create connection
$conn = new mysqli($host, $user, $password);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $database";
if (!$conn->query($sql)) {
    die(json_encode(['status' => 'error', 'message' => 'Database creation failed: ' . $conn->error]));
}

// Select the database
$conn->select_db($database);

?>