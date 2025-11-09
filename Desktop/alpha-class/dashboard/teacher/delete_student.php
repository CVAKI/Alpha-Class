<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}
require_once(__DIR__ . '/../../config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'] ?? '';
    
    // Validate input
    if (empty($userId)) {
        echo json_encode(['success' => false, 'message' => 'Missing user ID']);
        exit();
    }
    
    // First, verify that the student belongs to the teacher's class
    $stmt = $conn->prepare("SELECT reference_code FROM users WHERE id = ? AND role = 'student'");
    
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit();
        }
        
        // Check if student belongs to teacher's class
        if ($student['reference_code'] !== $_SESSION['reference_code']) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized to delete this student']);
            exit();
        }
        
        // Delete the student
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Student not found or already deleted']);
            }
            
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to prepare delete statement']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare verification statement']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>