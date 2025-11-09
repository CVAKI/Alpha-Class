<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once('../../config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $announcement = trim($_POST['announcement'] ?? '');
    $announcement_type = $_POST['announcement_type'] ?? 'class';
    $teacher_id = $_SESSION['user_id'];
    $teacher_name = $_SESSION['name'] ?? 'Teacher';
    $reference_code = $_SESSION['reference_code'] ?? '';
    
    if (empty($announcement)) {
        echo json_encode(['success' => false, 'message' => 'Announcement text is required']);
        exit();
    }
    
    if (empty($reference_code)) {
        echo json_encode(['success' => false, 'message' => 'Reference code not found']);
        exit();
    }
    
    $recipients = null;
    
    // If personal announcement, get selected students
    if ($announcement_type === 'personal') {
        if (!isset($_POST['students']) || empty($_POST['students'])) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one student']);
            exit();
        }
        
        $student_ids = $_POST['students'];
        
        // Get student names
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $stmt = $conn->prepare("SELECT name FROM users WHERE id IN ($placeholders) AND role = 'student'");
        
        $types = str_repeat('i', count($student_ids));
        $stmt->bind_param($types, ...$student_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $student_names = [];
        while ($row = $result->fetch_assoc()) {
            $student_names[] = $row['name'];
        }
        $stmt->close();
        
        $recipients = implode(', ', $student_names);
    }
    
    // Insert announcement
    $stmt = $conn->prepare("INSERT INTO announcements (teacher_id, reference_code, teacher_name, announcement, announcement_type, recipients) VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("isssss", $teacher_id, $reference_code, $teacher_name, $announcement, $announcement_type, $recipients);
        
        if ($stmt->execute()) {
            $announcement_id = $conn->insert_id;
            
            // If personal announcement, create notification records for each student
            if ($announcement_type === 'personal' && isset($_POST['students']) && !empty($_POST['students'])) {
                // First, ensure student_notifications table exists
                $create_table = "CREATE TABLE IF NOT EXISTS student_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    announcement_id INT NOT NULL,
                    student_id INT NOT NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
                    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_notification (announcement_id, student_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $conn->query($create_table);
                
                // Insert notification for each student
                $notification_stmt = $conn->prepare("INSERT INTO student_notifications (announcement_id, student_id) VALUES (?, ?)");
                foreach ($_POST['students'] as $student_id) {
                    $notification_stmt->bind_param("ii", $announcement_id, $student_id);
                    $notification_stmt->execute();
                }
                $notification_stmt->close();
            }
            
            echo json_encode(['success' => true, 'message' => 'Announcement posted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to post announcement: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>