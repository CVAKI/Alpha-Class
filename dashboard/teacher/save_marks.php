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
    $studentId = $_POST['student_id'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $internalMark = $_POST['internal_mark'] ?? 0;
    $externalMark = $_POST['external_mark'] ?? 0;
    $studentName = $_POST['student_name'] ?? '';
    
    // Validate inputs
    if (empty($studentId) || empty($subject)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    // Validate mark ranges (Internal: 0-20, External: 0-80)
    if ($internalMark < 0 || $internalMark > 20) {
        echo json_encode(['success' => false, 'message' => 'Internal mark must be between 0 and 20']);
        exit();
    }
    
    if ($externalMark < 0 || $externalMark > 80) {
        echo json_encode(['success' => false, 'message' => 'External mark must be between 0 and 80']);
        exit();
    }
    
    // Verify student belongs to teacher's class
    $stmt = $conn->prepare("SELECT reference_code FROM users WHERE id = ? AND role = 'student'");
    if ($stmt) {
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit();
        }
        
        if ($student['reference_code'] !== $_SESSION['reference_code']) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized to modify this student\'s marks']);
            exit();
        }
    }
    
    // Get current semester
    $stmt = $conn->prepare("SELECT currentSem FROM reference_code WHERE referencecode = ?");
    if ($stmt) {
        $stmt->bind_param("s", $_SESSION['reference_code']);
        $stmt->execute();
        $result = $stmt->get_result();
        $class_info = $result->fetch_assoc();
        $currentSem = $class_info['currentSem'];
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to get semester information']);
        exit();
    }
    
    // Insert or update marks (trigger will calculate total and grade)
    $stmt = $conn->prepare("
        INSERT INTO marks (student_id, student_name, reference_code, semester, subject_name, internal_mark, external_mark) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            internal_mark = VALUES(internal_mark),
            external_mark = VALUES(external_mark),
            student_name = VALUES(student_name)
    ");
    
    if ($stmt) {
        $stmt->bind_param("issisdd", 
            $studentId, 
            $studentName, 
            $_SESSION['reference_code'], 
            $currentSem, 
            $subject, 
            $internalMark, 
            $externalMark
        );
        
        if ($stmt->execute()) {
            // Fetch the updated record to return grade and total
            $fetchStmt = $conn->prepare("SELECT total_mark, grade FROM marks WHERE student_id = ? AND semester = ? AND subject_name = ?");
            $fetchStmt->bind_param("iis", $studentId, $currentSem, $subject);
            $fetchStmt->execute();
            $result = $fetchStmt->get_result();
            $markData = $result->fetch_assoc();
            $fetchStmt->close();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Marks saved successfully',
                'total_mark' => $markData['total_mark'],
                'grade' => $markData['grade']
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