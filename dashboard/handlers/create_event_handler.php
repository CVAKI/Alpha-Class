<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once('../../config.php');

// Ensure calendar_events table exists
$createTableQuery = "
CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    reference_code VARCHAR(100) NOT NULL,
    teacher_name VARCHAR(255) NOT NULL,
    event_title VARCHAR(255) NOT NULL,
    event_description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    event_type VARCHAR(50) NOT NULL,
    reminder_days INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reference_code (reference_code),
    INDEX idx_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$conn->query($createTableQuery)) {
    echo json_encode(['success' => false, 'message' => 'Database setup error: ' . $conn->error]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_title = trim($_POST['event_title'] ?? '');
    $event_description = trim($_POST['event_description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? null;
    $event_type = $_POST['event_type'] ?? '';
    $reminder = $_POST['reminder'] ?? null;
    
    // Validate input
    if (empty($event_title)) {
        echo json_encode(['success' => false, 'message' => 'Event title is required']);
        exit();
    }
    
    if (empty($event_date)) {
        echo json_encode(['success' => false, 'message' => 'Event date is required']);
        exit();
    }
    
    if (empty($event_type)) {
        echo json_encode(['success' => false, 'message' => 'Event type is required']);
        exit();
    }
    
    // Validate date is not in the past
    if (strtotime($event_date) < strtotime(date('Y-m-d'))) {
        echo json_encode(['success' => false, 'message' => 'Event date cannot be in the past']);
        exit();
    }
    
    // Get teacher information
    $teacherId = $_SESSION['user_id'];
    $referenceCode = $_SESSION['reference_code'];
    $teacherName = $_SESSION['name'];
    
    // Convert empty time to NULL
    if (empty($event_time)) {
        $event_time = null;
    }
    
    // Convert empty reminder to NULL
    if (empty($reminder)) {
        $reminder = null;
    }
    
    // Insert event into database
    $stmt = $conn->prepare("INSERT INTO calendar_events (teacher_id, reference_code, teacher_name, event_title, event_description, event_date, event_time, event_type, reminder_days, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    if ($stmt) {
        $stmt->bind_param("isssssssi", $teacherId, $referenceCode, $teacherName, $event_title, $event_description, $event_date, $event_time, $event_type, $reminder);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Event scheduled successfully!',
                'event_id' => $conn->insert_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>