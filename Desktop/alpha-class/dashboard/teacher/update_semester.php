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
    $referenceCode = $_POST['referenceCode'] ?? '';
    $newSemester = $_POST['newSemester'] ?? '';
    
    // Validate inputs
    if (empty($referenceCode) || empty($newSemester)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    // Verify that the reference code belongs to the logged-in teacher
    if ($referenceCode !== $_SESSION['reference_code']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized to modify this class']);
        exit();
    }
    
    // Update the semester in database
    $stmt = $conn->prepare("UPDATE reference_code SET currentSem = ? WHERE referencecode = ?");
    
    if ($stmt) {
        $stmt->bind_param("ss", $newSemester, $referenceCode);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Semester updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No changes made or reference code not found']);
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