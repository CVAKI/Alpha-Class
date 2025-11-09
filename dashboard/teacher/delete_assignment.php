<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once('../../config.php');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $assignment_id = intval($data['assignment_id']);

    // Verify this assignment belongs to the teacher
    $stmt = $conn->prepare("SELECT id FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Assignment not found or unauthorized');
    }
    $stmt->close();

    // Get all submission files to delete
    $stmt = $conn->prepare("SELECT file_path FROM assignment_submissions WHERE assignment_id = ?");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (file_exists($row['file_path'])) {
            unlink($row['file_path']);
        }
    }
    $stmt->close();

    // Delete assignment (submissions will be deleted by CASCADE)
    $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
    $stmt->bind_param("i", $assignment_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to delete assignment');
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>