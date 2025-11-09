<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once('../../config.php');

try {
    $teacher_id = $_SESSION['user_id'];
    $reference_code = $_SESSION['reference_code'];
    $subject = trim($_POST['subject']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['dueDate'];
    $total_marks = intval($_POST['totalMarks']);

    // Validate inputs
    if (empty($subject) || empty($title) || empty($due_date) || $total_marks < 1) {
        throw new Exception('All required fields must be filled');
    }

    // Insert assignment
    $stmt = $conn->prepare("INSERT INTO assignments (teacher_id, reference_code, subject_name, title, description, due_date, total_marks) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssi", $teacher_id, $reference_code, $subject, $title, $description, $due_date, $total_marks);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Assignment created successfully']);
    } else {
        throw new Exception('Failed to create assignment');
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>