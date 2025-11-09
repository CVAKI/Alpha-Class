<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once('../../config.php');

try {
    $assignment_id = intval($_POST['assignment_id']);
    $student_id = $_SESSION['user_id'];

    // Check if assignment exists and belongs to student's class
    $stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ? AND reference_code = ?");
    $stmt->bind_param("is", $assignment_id, $_SESSION['reference_code']);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();

    if (!$assignment) {
        throw new Exception('Assignment not found');
    }

    // Check if already submitted
    $stmt = $conn->prepare("SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $assignment_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('You have already submitted this assignment');
    }
    $stmt->close();

    // Validate file upload
    if (!isset($_FILES['assignment_file']) || $_FILES['assignment_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }

    $file = $_FILES['assignment_file'];
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if ($mimeType !== 'application/pdf') {
        throw new Exception('Only PDF files are allowed');
    }

    // Validate file size (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File size must be less than 10MB');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = '../../uploads/assignments/' . $assignment_id . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'student_' . $student_id . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save file');
    }

    // Check if submission is late
    $dueDate = new DateTime($assignment['due_date']);
    $today = new DateTime();
    $status = $dueDate < $today ? 'late' : 'submitted';

    // Save submission to database
    $relativeFilePath = 'uploads/assignments/' . $assignment_id . '/' . $filename;
    $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_path, original_filename, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $assignment_id, $student_id, $relativeFilePath, $file['name'], $status);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Assignment submitted successfully']);
    } else {
        // Delete uploaded file if database insert fails
        unlink($filepath);
        throw new Exception('Failed to save submission');
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>