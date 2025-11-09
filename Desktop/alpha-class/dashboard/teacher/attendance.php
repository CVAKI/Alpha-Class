<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../main/signin.html");
    exit();
}

require_once('../../config.php');

// Create attendance table if it doesn't exist
$createAttendanceTable = "
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    reference_code VARCHAR(100) NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent') NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    marked_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (student_id, attendance_date),
    INDEX idx_reference_code (reference_code),
    INDEX idx_date (attendance_date),
    INDEX idx_month_year (month, year),
    INDEX idx_student (student_id),
    INDEX idx_marked_by (marked_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$conn->query($createAttendanceTable);

// Create attendance_summary table for quick stats
$createSummaryTable = "
CREATE TABLE IF NOT EXISTS attendance_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    reference_code VARCHAR(100) NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    total_days INT DEFAULT 0,
    present_days INT DEFAULT 0,
    absent_days INT DEFAULT 0,
    attendance_percentage DECIMAL(5,2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_summary (student_id, month, year),
    INDEX idx_reference_code (reference_code),
    INDEX idx_percentage (attendance_percentage),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$conn->query($createSummaryTable);

// Get current month and year
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch teacher's class information
$class_info = null;
$stmt = $conn->prepare("SELECT * FROM reference_code WHERE referencecode = ?");
if ($stmt) {
    $stmt->bind_param("s", $_SESSION['reference_code']);
    $stmt->execute();
    $result = $stmt->get_result();
    $class_info = $result->fetch_assoc();
    $stmt->close();
}

// Fetch students with their attendance summary
$students = [];
if (isset($_SESSION['reference_code'])) {
    // First, check if attendance_summary table has the required columns
    $checkColumns = $conn->query("SHOW COLUMNS FROM attendance_summary LIKE 'month'");
    $hasColumns = ($checkColumns && $checkColumns->num_rows > 0);
    
    if ($hasColumns) {
        // Query with attendance_summary join
        $query = "
            SELECT u.id, u.name, u.email, u.profile_picture,
                   COALESCE(ats.total_days, 0) as total_days,
                   COALESCE(ats.present_days, 0) as present_days,
                   COALESCE(ats.absent_days, 0) as absent_days,
                   COALESCE(ats.attendance_percentage, 0) as attendance_percentage
            FROM users u
            LEFT JOIN attendance_summary ats ON u.id = ats.student_id 
                AND ats.month = ? AND ats.year = ? AND ats.reference_code = ?
            WHERE u.reference_code = ? AND u.role = 'student'
            ORDER BY u.name ASC
        ";
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("iiss", $current_month, $current_year, $_SESSION['reference_code'], $_SESSION['reference_code']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            $stmt->close();
        }
    } else {
        // Simple query without summary (for first run)
        $query = "
            SELECT id, name, email, profile_picture,
                   0 as total_days,
                   0 as present_days,
                   0 as absent_days,
                   0 as attendance_percentage
            FROM users 
            WHERE reference_code = ? AND role = 'student'
            ORDER BY name ASC
        ";
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $_SESSION['reference_code']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            $stmt->close();
        }
    }
}

// Calculate condonation list (students below 72%)
$condonation_list = array_filter($students, function($student) {
    return $student['total_days'] > 0 && $student['attendance_percentage'] < 72;
});

// Month names
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - Teacher</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="attendance.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Attendance Management</h1>
            <p>Track and manage student attendance</p>
            <?php if ($class_info): ?>
                <div class="class-badge">
                    🎓 <?php echo htmlspecialchars($class_info['class']); ?> - 
                    <?php echo htmlspecialchars($class_info['department']); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Month Selector -->
        <div class="month-selector">
            <form method="GET" class="month-form">
                <label for="month">Select Month:</label>
                <select name="month" id="month" onchange="this.form.submit()">
                    <?php foreach ($months as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo ($num == $current_month) ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="year">Year:</label>
                <select name="year" id="year" onchange="this.form.submit()">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($y == $current_year) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </form>

            <button class="btn btn-primary" onclick="markTodayAttendance()">
                📝 Mark Today's Attendance
            </button>
        </div>

        <!-- Condonation Warning -->
        <?php if (count($condonation_list) > 0): ?>
        <div class="condonation-warning">
            <h3>⚠️ Condonation List (Below 72%)</h3>
            <p><?php echo count($condonation_list); ?> student(s) require condonation</p>
            <div class="condonation-students">
                <?php foreach ($condonation_list as $student): ?>
                    <div class="condonation-badge">
                        <?php echo htmlspecialchars($student['name']); ?>
                        <span class="percentage-badge danger">
                            <?php echo number_format($student['attendance_percentage'], 1); ?>%
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <div class="stat-label">Total Students</div>
                    <div class="stat-value"><?php echo count($students); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <div class="stat-label">Above 72%</div>
                    <div class="stat-value">
                        <?php echo count($students) - count($condonation_list); ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-icon">⚠️</div>
                <div class="stat-info">
                    <div class="stat-label">Below 72%</div>
                    <div class="stat-value"><?php echo count($condonation_list); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <div class="stat-label">Current Month</div>
                    <div class="stat-value"><?php echo $months[$current_month]; ?></div>
                </div>
            </div>
        </div>

        <!-- Students Attendance Table -->
        <div class="card">
            <h2>Student Attendance Overview - <?php echo $months[$current_month] . ' ' . $current_year; ?></h2>
            
            <?php if (count($students) > 0): ?>
            <div class="table-responsive">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Total Days</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Percentage</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 1;
                        foreach ($students as $student): 
                            $percentage = $student['attendance_percentage'];
                            $status_class = $percentage >= 72 ? 'good' : 'warning';
                            $status_text = $percentage >= 72 ? 'Good' : 'Need Attention';
                        ?>
                        <tr>
                            <td><?php echo $count++; ?></td>
                            <td>
                                <div class="student-cell">
                                    <img src="<?php 
                                        $pic = "../../asset/img/dashboard/default-user.png";
                                        if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])) {
                                            $pic = $student['profile_picture'];
                                        }
                                        echo htmlspecialchars($pic); 
                                    ?>" alt="Student" class="student-avatar">
                                    <div>
                                        <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                        <div class="student-email"><?php echo htmlspecialchars($student['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><strong><?php echo $student['total_days']; ?></strong></td>
                            <td><span class="badge badge-success"><?php echo $student['present_days']; ?></span></td>
                            <td><span class="badge badge-danger"><?php echo $student['absent_days']; ?></span></td>
                            <td>
                                <div class="percentage-bar">
                                    <div class="percentage-fill <?php echo $status_class; ?>" 
                                         style="width: <?php echo min($percentage, 100); ?>%"></div>
                                    <span class="percentage-text"><?php echo number_format($percentage, 1); ?>%</span>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-small btn-primary" 
                                        onclick="viewDetails(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['name']); ?>')">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="no-data">No students found in your class.</p>
            <?php endif; ?>
        </div>

        <div class="back-button">
            <button class="btn btn-secondary" onclick="window.location.href='../teacherMain.php'">
                ← Back to Dashboard
            </button>
        </div>
    </div>

    <script>
        function markTodayAttendance() {
            window.location.href = 'mark_attendance.php?month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>';
        }

        function viewDetails(studentId, studentName) {
            window.location.href = 'attendance_details.php?student_id=' + studentId + 
                                  '&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>';
        }
    </script>
</body>
</html>