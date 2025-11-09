<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../main/signin.html");
    exit();
}

require_once('../../config.php');

// Get current month and year
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$student_id = $_SESSION['user_id'];
$reference_code = $_SESSION['reference_code'];

// Fetch student's class information
$class_info = null;
$stmt = $conn->prepare("SELECT * FROM reference_code WHERE referencecode = ?");
if ($stmt) {
    $stmt->bind_param("s", $reference_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $class_info = $result->fetch_assoc();
    $stmt->close();
}

// Fetch attendance summary for current month
$summary = null;
$stmt = $conn->prepare("SELECT * FROM attendance_summary WHERE student_id = ? AND month = ? AND year = ?");
if ($stmt) {
    $stmt->bind_param("iii", $student_id, $current_month, $current_year);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();
}

// Fetch detailed attendance records for current month
$attendance_records = [];
$stmt = $conn->prepare("SELECT * FROM attendance WHERE student_id = ? AND month = ? AND year = ? ORDER BY attendance_date ASC");
if ($stmt) {
    $stmt->bind_param("iii", $student_id, $current_month, $current_year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
    $stmt->close();
}

// Fetch all months with attendance data
$available_months = [];
$stmt = $conn->prepare("SELECT DISTINCT month, year FROM attendance WHERE student_id = ? ORDER BY year DESC, month DESC");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $available_months[] = $row;
    }
    $stmt->close();
}

// Calculate overall attendance (all time)
$overall_stats = null;
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
    FROM attendance 
    WHERE student_id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $overall_stats = $result->fetch_assoc();
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
    <title>My Attendance - Alpha-Class</title>
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
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .header h1 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 2.5em;
        }

        .header p {
            color: #718096;
            font-size: 1.1em;
        }

        .student-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            padding: 15px;
            background: linear-gradient(135deg, #f0f4ff 0%, #e6f0ff 100%);
            border-radius: 15px;
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
        }

        .student-details h3 {
            color: #2d3748;
            font-size: 1.2em;
        }

        .student-details p {
            color: #667eea;
            font-size: 0.9em;
        }

        /* Month Selector */
        .month-selector {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .month-selector label {
            font-weight: 600;
            color: #2d3748;
            font-size: 1.1em;
        }

        .month-selector select {
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .month-selector select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card:hover {
            transform: translateY(-10px);
        }

        .stat-card.overall {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-card.overall::before {
            background: rgba(255, 255, 255, 0.3);
        }

        .stat-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }

        .stat-label {
            color: #718096;
            font-size: 0.95em;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card.overall .stat-label {
            color: rgba(255, 255, 255, 0.9);
        }

        .stat-value {
            font-size: 3em;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .stat-card.overall .stat-value {
            color: white;
        }

        .stat-subtitle {
            font-size: 0.85em;
            color: #a0aec0;
        }

        .stat-card.overall .stat-subtitle {
            color: rgba(255, 255, 255, 0.8);
        }

        /* Warning Banner */
        .warning-banner {
            background: linear-gradient(135deg, #fed7d7 0%, #fc8181 100%);
            border-left: 5px solid #c53030;
            border-radius: 15px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(197, 48, 48, 0.2);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .warning-banner .icon {
            font-size: 2.5em;
        }

        .warning-banner h3 {
            color: #742a2a;
            margin-bottom: 5px;
        }

        .warning-banner p {
            color: #742a2a;
            font-weight: 500;
        }

        .success-banner {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            border-left: 5px solid #38a169;
            border-radius: 15px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(56, 161, 105, 0.2);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .success-banner .icon {
            font-size: 2.5em;
        }

        .success-banner h3 {
            color: #22543d;
            margin-bottom: 5px;
        }

        .success-banner p {
            color: #22543d;
            font-weight: 500;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: #2d3748;
            margin-bottom: 25px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Calendar Grid */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
            margin-top: 20px;
        }

        .calendar-header {
            text-align: center;
            font-weight: 700;
            color: #667eea;
            padding: 15px 5px;
            font-size: 0.9em;
            text-transform: uppercase;
        }

        .calendar-day {
            aspect-ratio: 1;
            border: 3px solid #e2e8f0;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .calendar-day:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            z-index: 10;
        }

        .calendar-day.present {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            border-color: #48bb78;
            color: #22543d;
        }

        .calendar-day.absent {
            background: linear-gradient(135deg, #fed7d7 0%, #fc8181 100%);
            border-color: #f56565;
            color: #c53030;
        }

        .calendar-day.empty {
            background: #f7fafc;
            border-color: transparent;
            cursor: default;
        }

        .calendar-day.empty:hover {
            transform: none;
            box-shadow: none;
        }

        .calendar-day .day-number {
            font-size: 1.4em;
            font-weight: 700;
        }

        .calendar-day .day-status {
            font-size: 0.75em;
            margin-top: 5px;
            font-weight: 600;
        }

        /* Progress Bar */
        .progress-container {
            margin: 30px 0;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2d3748;
        }

        .progress-bar {
            width: 100%;
            height: 40px;
            background: #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .progress-fill {
            height: 100%;
            transition: width 1s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1em;
            position: relative;
        }

        .progress-fill.good {
            background: linear-gradient(90deg, #48bb78 0%, #38a169 100%);
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, #ed8936 0%, #dd6b20 100%);
        }

        .progress-fill.danger {
            background: linear-gradient(90deg, #f56565 0%, #c53030 100%);
        }

        /* Button */
        .btn {
            padding: 15px 35px;
            border: none;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1em;
            display: inline-block;
            text-decoration: none;
        }

        .btn-secondary {
            background: #4a5568;
            color: white;
        }

        .btn-secondary:hover {
            background: #2d3748;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(45, 55, 72, 0.3);
        }

        .back-button {
            text-align: center;
            margin-top: 30px;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
            font-size: 1.2em;
        }

        .no-data-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .calendar-grid {
                gap: 5px;
            }

            .calendar-day {
                font-size: 0.8em;
            }

            .calendar-header {
                font-size: 0.7em;
                padding: 10px 2px;
            }

            .student-info {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 My Attendance</h1>
            <p>Track your attendance and stay on top of your academic requirements</p>
            
            <div class="student-info">
                <img src="<?php 
                    $pic = "../../asset/img/dashboard/default-user.png";
                    if (!empty($_SESSION['profile_picture']) && file_exists($_SESSION['profile_picture'])) {
                        $pic = $_SESSION['profile_picture'];
                    }
                    echo htmlspecialchars($pic); 
                ?>" alt="Student" class="student-avatar">
                <div class="student-details">
                    <h3><?php echo htmlspecialchars($_SESSION['name']); ?></h3>
                    <p><?php echo $class_info ? htmlspecialchars($class_info['class']) . ' - ' . htmlspecialchars($class_info['department']) : 'No class info'; ?></p>
                </div>
            </div>
        </div>

        <!-- Month Selector -->
        <div class="month-selector">
            <label for="month">📅 Select Month:</label>
            <form method="GET" style="display: flex; gap: 15px; align-items: center;">
                <select name="month" id="month" onchange="this.form.submit()">
                    <?php foreach ($months as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo ($num == $current_month) ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="year" id="year" onchange="this.form.submit()">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($y == $current_year) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>

        <!-- Warning/Success Banner -->
        <?php if ($summary && $summary['attendance_percentage'] < 72): ?>
        <div class="warning-banner">
            <div class="icon">⚠️</div>
            <div>
                <h3>Attendance Warning!</h3>
                <p>Your attendance is below 72%. You need to improve to avoid condonation. Current: <?php echo number_format($summary['attendance_percentage'], 1); ?>%</p>
            </div>
        </div>
        <?php elseif ($summary && $summary['attendance_percentage'] >= 72): ?>
        <div class="success-banner">
            <div class="icon">✅</div>
            <div>
                <h3>Great Job!</h3>
                <p>Your attendance is excellent! Keep it up. Current: <?php echo number_format($summary['attendance_percentage'], 1); ?>%</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <?php if ($overall_stats && $overall_stats['total_days'] > 0): ?>
            <div class="stat-card overall">
                <div class="stat-icon">🎯</div>
                <div class="stat-label">Overall Attendance</div>
                <div class="stat-value"><?php echo number_format($overall_stats['percentage'], 1); ?>%</div>
                <div class="stat-subtitle">All Time</div>
            </div>
            <?php endif; ?>

            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-label">Total Days</div>
                <div class="stat-value"><?php echo $summary ? $summary['total_days'] : 0; ?></div>
                <div class="stat-subtitle"><?php echo $months[$current_month]; ?> <?php echo $current_year; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-label">Present Days</div>
                <div class="stat-value"><?php echo $summary ? $summary['present_days'] : 0; ?></div>
                <div class="stat-subtitle"><?php echo $months[$current_month]; ?> <?php echo $current_year; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-label">Absent Days</div>
                <div class="stat-value"><?php echo $summary ? $summary['absent_days'] : 0; ?></div>
                <div class="stat-subtitle"><?php echo $months[$current_month]; ?> <?php echo $current_year; ?></div>
            </div>
        </div>

        <!-- Progress Bar -->
        <?php if ($summary): ?>
        <div class="card">
            <h2>📈 Monthly Progress</h2>
            <div class="progress-container">
                <div class="progress-label">
                    <span>Attendance Percentage</span>
                    <span><?php echo number_format($summary['attendance_percentage'], 1); ?>%</span>
                </div>
                <div class="progress-bar">
                    <?php 
                    $percentage = $summary['attendance_percentage'];
                    $class = $percentage >= 72 ? 'good' : ($percentage >= 50 ? 'warning' : 'danger');
                    ?>
                    <div class="progress-fill <?php echo $class; ?>" style="width: <?php echo min($percentage, 100); ?>%">
                        <?php echo number_format($percentage, 1); ?>%
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Calendar View -->
        <div class="card">
            <h2>📅 Calendar View - <?php echo $months[$current_month] . ' ' . $current_year; ?></h2>
            
            <?php if (count($attendance_records) > 0): ?>
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
                $first_day = date('w', strtotime("$current_year-$current_month-01"));
                $days_in_month = date('t', strtotime("$current_year-$current_month-01"));

                // Empty cells before first day
                for ($i = 0; $i < $first_day; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }

                // Days of the month
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $status = $attendance_lookup[$day] ?? '';
                    $class = $status ? "calendar-day $status" : 'calendar-day';
                    $status_text = $status === 'present' ? 'P' : ($status === 'absent' ? 'A' : '');
                    $status_label = $status === 'present' ? 'Present' : ($status === 'absent' ? 'Absent' : '');
                    
                    echo "<div class='$class' title='$status_label'>";
                    echo "<div class='day-number'>$day</div>";
                    if ($status_text) {
                        echo "<div class='day-status'>$status_text</div>";
                    }
                    echo "</div>";
                }
                ?>
            </div>
            <?php else: ?>
            <div class="no-data">
                <div class="no-data-icon">📭</div>
                <p>No attendance records found for this month.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="back-button">
            <a href="../studentMain.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>