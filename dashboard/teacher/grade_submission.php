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
    $submission_id = intval($data['submission_id']);
    $marks = intval($data['marks']);
    $feedback = trim($data['feedback']);
    $graded_by = $_SESSION['user_id'];

    // Update submission with grade
    $stmt = $conn->prepare("UPDATE assignment_submissions SET marks_obtained = ?, feedback = ?, graded_at = NOW(), graded_by = ?, status = 'graded' WHERE id = ?");
    $stmt->bind_param("isii", $marks, $feedback, $graded_by, $submission_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Grade submitted successfully']);
    } else {
        throw new Exception('Failed to submit grade');
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>