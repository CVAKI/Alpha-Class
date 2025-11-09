<?php
// Include your database configuration file
include('../config.php');

// Create a connection (if not already created inside config.php)
if (!isset($conn)) {
    $conn = new mysqli($servername, $username, $password, $dbname);
}

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// SQL query for assignments table
$sqlAssignments = "
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    reference_code VARCHAR(50) NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE NOT NULL,
    total_marks INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reference_code (reference_code),
    INDEX idx_due_date (due_date)
);
";

// SQL query for assignment_submissions table
$sqlSubmissions = "
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    marks_obtained INT DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    graded_at TIMESTAMP NULL,
    graded_by INT DEFAULT NULL,
    status ENUM('submitted', 'graded', 'late') DEFAULT 'submitted',
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_submission (assignment_id, student_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status)
);
";

// Execute both queries
if ($conn->query($sqlAssignments) === TRUE) {
    echo "✅ Table 'assignments' created successfully.<br>";
} else {
    echo "❌ Error creating 'assignments': " . $conn->error . "<br>";
}

if ($conn->query($sqlSubmissions) === TRUE) {
    echo "✅ Table 'assignment_submissions' created successfully.<br>";
} else {
    echo "❌ Error creating 'assignment_submissions': " . $conn->error . "<br>";
}

$conn->close();
?>
