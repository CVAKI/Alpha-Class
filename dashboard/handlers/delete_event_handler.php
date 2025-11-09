<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once('../../config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $event_id = $input['event_id'] ?? 0;
    
    // Validate input
    if (empty($event_id)) {
        echo json_encode(['success' => false, 'message' => 'Event ID is required']);
        exit();
    }
    
    // Get teacher information
    $teacherId = $_SESSION['user_id'];
    $referenceCode = $_SESSION['reference_code'];
    
    // Delete event (only if it belongs to the teacher's class)
    $stmt = $conn->prepare("DELETE FROM calendar_events WHERE id = ? AND reference_code = ?");
    
    if ($stmt) {
        $stmt->bind_param("is", $event_id, $referenceCode);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Event deleted successfully!'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Event not found or unauthorized']);
            }
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