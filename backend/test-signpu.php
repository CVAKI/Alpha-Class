<?php
// Prevent any output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser
ini_set('log_errors', 1); // Log errors instead

// Set JSON header first
header('Content-Type: application/json');

// Start output buffering to catch any accidental output
ob_start();

try {
    require_once "../config.php"; 

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $reference = trim($_POST['reference'] ?? '');

        // Validate all fields are filled
        if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password) || empty($reference)) {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
            exit;
        }

        // Check if passwords match
        if ($password !== $confirm_password) {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
            exit;
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Create users table if not exists
        $createTableSQL = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100),
            email VARCHAR(100) UNIQUE,
            phone VARCHAR(20),
            password VARCHAR(255),
            reference_code VARCHAR(100),
            profile_picture VARCHAR(255),
            role VARCHAR(20) DEFAULT 'student',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($createTableSQL)) {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Table creation failed: ' . $conn->error]);
            exit;
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        
        if (!$stmt) {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, reference_code) VALUES (?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("sssss", $name, $email, $phone, $hashedPassword, $reference);

        if ($stmt->execute()) {
            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Account created successfully']);
        } else {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Signup failed: ' . $stmt->error]);
        }
        $stmt->close();
        $conn->close();
    } else {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
?>