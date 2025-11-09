<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once('../../config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? date('Y-m-d');
    $students = $_POST['students'] ?? [];
    $student_names = $_POST['student_names'] ?? [];
    $attendance = $_POST['attendance'] ?? [];
    $reference_code = $_SESSION['reference_code'];
    $marked_by = $_SESSION['user_id'];
    
    // Extract month and year from date
    $date_parts = explode('-', $date);
    $year = intval($date_parts[0]);
    $month = intval($date_parts[1]);
    
    if (empty($students)) {
        echo json_encode(['success' => false, 'message' => 'No students to mark attendance']);
        exit();
    }
    
    $conn->begin_transaction();
    
    try {
        // Prepare statement for inserting/updating attendance
        $stmt = $conn->prepare("
            INSERT INTO attendance (student_id, student_name, reference_code, attendance_date, status, month, year, marked_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                marked_by = VALUES(marked_by)
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare attendance statement: ' . $conn->error);
        }
        
        // Insert/Update attendance for each student
        foreach ($students as $index => $student_id) {
            $student_name = $student_names[$index];
            $status = $attendance[$student_id] ?? '';
            
            if (empty($status)) {
                throw new Exception('Attendance status missing for student: ' . $student_name);
            }
            
            $stmt->bind_param("issssiii", 
                $student_id, 
                $student_name, 
                $reference_code, 
                $date, 
                $status, 
                $month, 
                $year, 
                $marked_by
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to save attendance for ' . $student_name);
            }
        }
        
        $stmt->close();
        
        // Update attendance summary for each student
        $summary_stmt = $conn->prepare("
            INSERT INTO attendance_summary (student_id, student_name, reference_code, month, year, total_days, present_days, absent_days, attendance_percentage)
            SELECT 
                student_id,
                student_name,
                reference_code,
                month,
                year,
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_percentage
            FROM attendance
            WHERE student_id = ? AND month = ? AND year = ? AND reference_code = ?
            GROUP BY student_id, student_name, reference_code, month, year
            ON DUPLICATE KEY UPDATE
                total_days = VALUES(total_days),
                present_days = VALUES(present_days),
                absent_days = VALUES(absent_days),
                attendance_percentage = VALUES(attendance_percentage),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        if (!$summary_stmt) {
            throw new Exception('Failed to prepare summary statement: ' . $conn->error);
        }
        
        foreach ($students as $student_id) {
            $summary_stmt->bind_param("iiis", $student_id, $month, $year, $reference_code);
            
            if (!$summary_stmt->execute()) {
                throw new Exception('Failed to update summary for student ID: ' . $student_id);
            }
        }
        
        $summary_stmt->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Attendance saved successfully',
            'date' => $date,
            'students_count' => count($students)
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>