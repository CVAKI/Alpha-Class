<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../main/signin.html");
    exit();
}

require_once('../../config.php');

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch student details
$student = null;
$stmt = $conn->prepare("SELECT id, name, email, profile_picture FROM users WHERE id = ? AND reference_code = ? AND role = 'student'");
if ($stmt) {
    $stmt->bind_param("is", $student_id, $_SESSION['reference_code']);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
}

if (!$student) {
    die("Student not found or unauthorized");
}

// Fetch attendance records for the month
$attendance_records = [];
$stmt = $conn->prepare("SELECT * FROM attendance WHERE student_id = ? AND month = ? AND year = ? ORDER BY attendance_date ASC");
if ($stmt) {
    $stmt->bind_param("iii", $student_id, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
    $stmt->close();
}

// Fetch summary
$summary = null;
$stmt = $conn->prepare("SELECT * FROM attendance_summary WHERE student_id = ? AND month = ? AND year = ?");
if ($stmt) {
    $stmt->bind_param("iii", $student_id, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();
}

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
    <title>Student Attendance Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .student-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .student-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
        }

        .student-info h1 {
            color: #2d3748;
            font-size: 2em;
            margin-bottom: 5px;
        }

        .student-info p {
            color: #718096;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 0.9em;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-card .value {
            font-size: 2.5em;
            font-weight: 700;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: #2d3748;
            margin-bottom: 20px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 20px;
        }

        .calendar-header {
            text-align: center;
            font-weight: 600;
            color: #667eea;
            padding: 10px;
        }

        .calendar-day {
            aspect-ratio: 1;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .calendar-day:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .calendar-day.present {
            background: #c6f6d5;
            border-color: #48bb78;
            color: #22543d;
        }

        .calendar-day.absent {
            background: #fed7d7;
            border-color: #f56565;
            color: #c53030;
        }

        .calendar-day.empty {
            background: #f7fafc;
            border-color: transparent;
            cursor: default;
        }

        .calendar-day .day-number {
            font-size: 1.2em;
            font-weight: 700;
        }

        .calendar-day .day-status {
            font-size: 0.7em;
            margin-top: 5px;
        }

        .records-list {
            margin-top: 20px;
        }

        .record-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .record-item.present {
            border-color: #48bb78;
            background: #f0fff4;
        }

        .record-item.absent {
            border-color: #f56565;
            background: #fff5f5;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
        }

        .status-present {
            background: #48bb78;
            color: white;
        }

        .status-absent {
            background: #f56565;
            color: white;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background: #4a5568;
            color: white;
        }

        .btn-secondary:hover {
            background: #2d3748;
            transform: translateY(-2px);
        }

        .back-button {
            text-align: center;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 5px;
            }

            .calendar-day {
                font-size: 0.8em;
            }

            .student-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="student-header">
                <img src="<?php 
                    $pic = "../../asset/img/dashboard/default-user.png";
                    if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])) {
                        $pic = $student['profile_picture'];
                    }
                    echo htmlspecialchars($pic); 
                ?>" alt="Student" class="student-avatar">
                <div class="student-info">
                    <h1><?php echo htmlspecialchars($student['name']); ?></h1>
                    <p><?php echo htmlspecialchars($student['email']); ?></p>
                    <p><strong>Month:</strong> <?php echo $months[$month] . ' ' . $year; ?></p>
                </div>
            </div>

            <?php if ($summary): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Days</h3>
                    <div class="value"><?php echo $summary['total_days']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Present</h3>
                    <div class="value"><?php echo $summary['present_days']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Absent</h3>
                    <div class="value"><?php echo $summary['absent_days']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Percentage</h3>
                    <div class="value"><?php echo number_format($summary['attendance_percentage'], 1); ?>%</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>📅 Monthly Calendar View</h2>
            <div class="calendar-grid">
                <div class="calendar-header">Sun</div>
                <div class="calendar-header">Mon</div>
                <div class="calendar-header">Tue</div>
                <div class="calendar-header">Wed</div>
                <div class="calendar-header">Thu</div>
                <div class="calendar-header">Fri</div>
                <div class="calendar-header">Sat</div>

                <?php
                // Create attendance lookup
                $attendance_lookup = [];
                foreach ($attendance_records as $record) {
                    $day = date('j', strtotime($record['attendance_date']));
                    $attendance_lookup[$day] = $record['status'];
                }

                // Get first day and total days in month
                $first_day = date('w', strtotime("$year-$month-01"));
                $days_in_month = date('t', strtotime("$year-$month-01"));

                // Empty cells before first day
                for ($i = 0; $i < $first_day; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }

                // Days of the month
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $status = $attendance_lookup[$day] ?? '';
                    $class = $status ? "calendar-day $status" : 'calendar-day';
                    $status_text = $status === 'present' ? 'P' : ($status === 'absent' ? 'A' : '');
                    
                    echo "<div class='$class'>";
                    echo "<div class='day-number'>$day</div>";
                    if ($status_text) {
                        echo "<div class='day-status'>$status_text</div>";
                    }
                    echo "</div>";
                }
                ?>
            </div>
        </div>

        <div class="card">
            <h2>📋 Detailed Records</h2>
            <?php if (count($attendance_records) > 0): ?>
            <div class="records-list">
                <?php foreach ($attendance_records as $record): ?>
                <div class="record-item <?php echo $record['status']; ?>">
                    <div>
                        <strong><?php echo date('l, F j, Y', strtotime($record['attendance_date'])); ?></strong>
                    </div>
                    <span class="status-badge status-<?php echo $record['status']; ?>">
                        <?php echo $record['status'] === 'present' ? '✅ Present' : '❌ Absent'; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="text-align: center; color: #718096; padding: 40px;">No attendance records for this month.</p>
            <?php endif; ?>
        </div>

        <div class="back-button">
            <button class="btn btn-secondary" onclick="window.location.href='attendance.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>'">
                ← Back to Attendance
            </button>
        </div>
    </div>
</body>
</html>