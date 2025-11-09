<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once('../../config.php');

// Ensure announcements table exists with updated schema
$createTableQuery = "
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    reference_code VARCHAR(100) NOT NULL,
    teacher_name VARCHAR(255) NOT NULL,
    announcement TEXT NOT NULL,
    announcement_type ENUM('class', 'personal') DEFAULT 'class',
    recipients TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reference_code (reference_code),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$conn->query($createTableQuery)) {
    echo json_encode(['success' => false, 'message' => 'Database setup error: ' . $conn->error]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $announcement = trim($_POST['announcement'] ?? '');
    $announcement_type = $_POST['announcement_type'] ?? 'class';
    $selected_students = $_POST['students'] ?? [];
    
    // Validate input
    if (empty($announcement)) {
        echo json_encode(['success' => false, 'message' => 'Announcement cannot be empty']);
        exit();
    }
    
    // Validate personal announcement has students selected
    if ($announcement_type === 'personal' && empty($selected_students)) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one student for personal announcement']);
        exit();
    }
    
    // Get teacher information
    $teacherId = $_SESSION['user_id'];
    $referenceCode = $_SESSION['reference_code'];
    $teacherName = $_SESSION['name'];
    
    // Get student names for personal announcements
    $recipients = null;
    if ($announcement_type === 'personal' && !empty($selected_students)) {
        $placeholders = str_repeat('?,', count($selected_students) - 1) . '?';
        $stmt = $conn->prepare("SELECT name FROM users WHERE id IN ($placeholders)");
        
        if ($stmt) {
            $types = str_repeat('i', count($selected_students));
            $stmt->bind_param($types, ...$selected_students);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $student_names = [];
            while ($row = $result->fetch_assoc()) {
                $student_names[] = $row['name'];
            }
            $recipients = implode(', ', $student_names);
            $stmt->close();
        }
    }
    
    // Insert announcement into database
    $stmt = $conn->prepare("INSERT INTO announcements (teacher_id, reference_code, teacher_name, announcement, announcement_type, recipients, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    
    if ($stmt) {
        $stmt->bind_param("isssss", $teacherId, $referenceCode, $teacherName, $announcement, $announcement_type, $recipients);
        
        if ($stmt->execute()) {
            $announcement_id = $conn->insert_id;
            
            // If personal announcement, create notification records for selected students
            if ($announcement_type === 'personal' && !empty($selected_students)) {
                $notif_stmt = $conn->prepare("INSERT INTO student_notifications (student_id, announcement_id, is_read) VALUES (?, ?, 0)");
                
                if ($notif_stmt) {
                    foreach ($selected_students as $student_id) {
                        $notif_stmt->bind_param("ii", $student_id, $announcement_id);
                        $notif_stmt->execute();
                    }
                    $notif_stmt->close();
                }
            }
            
            $message = $announcement_type === 'personal' 
                ? 'Personal announcement sent successfully to selected students!' 
                : 'Class announcement posted successfully!';
            
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'announcement_id' => $announcement_id
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