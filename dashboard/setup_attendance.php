<?php
// =============================================
// File: backend/_create_attendance_tables.php
// Purpose: Create attendance_records & attendance_summary tables
// =============================================

// Include your DB configuration file
include_once("../config.php");

// Create a connection (if config.php doesn't create $conn)
if (!isset($conn)) {
    $conn = new mysqli($servername, $username, $password, $dbname);
}

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL statements (multiple table creation)
$sql = "
-- =============================================
-- Create attendance_records table
-- =============================================
CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    reference_code VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    remarks TEXT NULL,
    marked_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Create attendance_summary table
-- =============================================
CREATE TABLE IF NOT EXISTS attendance_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    reference_code VARCHAR(50) NOT NULL,
    total_days INT DEFAULT 0,
    present_days INT DEFAULT 0,
    absent_days INT DEFAULT 0,
    late_days INT DEFAULT 0,
    excused_days INT DEFAULT 0,
    attendance_percentage DECIMAL(5,2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student (student_id, reference_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Create indexes for performance
-- =============================================
CREATE INDEX idx_attendance_date ON attendance_records(date);
CREATE INDEX idx_attendance_student ON attendance_records(student_id);
CREATE INDEX idx_attendance_refcode ON attendance_records(reference_code);
CREATE INDEX idx_summary_refcode ON attendance_summary(reference_code);
";

// Execute multiple queries
if ($conn->multi_query($sql)) {
    echo "<h3>✅ Attendance tables created successfully!</h3>";
    do {
        $conn->next_result();
    } while ($conn->more_results());
} else {
    echo "<h3>❌ Error creating tables: " . $conn->error . "</h3>";
}

$conn->close();
?>
