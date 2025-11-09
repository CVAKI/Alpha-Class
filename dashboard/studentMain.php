<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.html");
    exit();
}

// Include database configuration to fetch latest user data
require_once('../config.php');

// Fetch current user data from database
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
if ($stmt) {
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    
    if ($user_data) {
        $_SESSION['name'] = $user_data['name'];
        $_SESSION['phone'] = $user_data['phone'];
        $_SESSION['profile_picture'] = $user_data['profile_picture'];
        $_SESSION['role'] = $user_data['role'];
        $_SESSION['reference_code'] = $user_data['reference_code'];
    }
    $stmt->close();
}

// Fetch student's class information
$class_info = null;
if (isset($_SESSION['reference_code'])) {
    $stmt = $conn->prepare("SELECT * FROM reference_code WHERE referencecode = ?");
    if ($stmt) {
        $stmt->bind_param("s", $_SESSION['reference_code']);
        $stmt->execute();
        $result = $stmt->get_result();
        $class_info = $result->fetch_assoc();
        $stmt->close();
    }
}

// Fetch class teacher's phone number
$teacher_phone = null;
if ($class_info && !empty($class_info['class_teacher'])) {
    $stmt = $conn->prepare("SELECT phone FROM users WHERE name = ? AND role = 'teacher' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $class_info['class_teacher']);
        $stmt->execute();
        $result = $stmt->get_result();
        $teacher_data = $result->fetch_assoc();
        if ($teacher_data) {
            $teacher_phone = $teacher_data['phone'];
        }
        $stmt->close();
    }
}

// Fetch current semester subjects
$current_subjects = [];
if ($class_info) {
    $stmt = $conn->prepare("SELECT subject_name, teaching_teacher FROM class_subjects WHERE referencecode = ? AND semester = ? ORDER BY subject_name ASC");
    if ($stmt) {
        $stmt->bind_param("si", $class_info['referencecode'], $class_info['currentSem']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $current_subjects[] = $row;
        }
        $stmt->close();
    }
}

// Fetch current semester marks for grade overview
$current_semester_marks = [];
$grade_stats = [
    'total_subjects' => 0,
    'average' => 0,
    'highest' => 0,
    'lowest' => 100,
    'passed' => 0,
    'failed' => 0
];

if (isset($_SESSION['user_id']) && $class_info) {
    $current_sem = $class_info['currentSem'];
    $stmt = $conn->prepare("SELECT * FROM marks WHERE student_id = ? AND semester = ? ORDER BY subject_name");
    if ($stmt) {
        $stmt->bind_param("ii", $_SESSION['user_id'], $current_sem);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $total_marks = 0;
        while ($row = $result->fetch_assoc()) {
            $current_semester_marks[] = $row;
            $grade_stats['total_subjects']++;
            $total_marks += $row['total_mark'];
            
            if ($row['total_mark'] > $grade_stats['highest']) {
                $grade_stats['highest'] = $row['total_mark'];
            }
            if ($row['total_mark'] < $grade_stats['lowest']) {
                $grade_stats['lowest'] = $row['total_mark'];
            }
            
            if ($row['total_mark'] >= 40) {
                $grade_stats['passed']++;
            } else {
                $grade_stats['failed']++;
            }
        }
        
        if ($grade_stats['total_subjects'] > 0) {
            $grade_stats['average'] = $total_marks / $grade_stats['total_subjects'];
        }
        
        $stmt->close();
    }
}

// Fetch attendance data
$current_month = date('n');
$current_year = date('Y');
$attendance_data = null;

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM attendance_summary WHERE student_id = ? AND month = ? AND year = ?");
    if ($stmt) {
        $stmt->bind_param("iii", $_SESSION['user_id'], $current_month, $current_year);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance_data = $result->fetch_assoc();
        $stmt->close();
    }
}

// Fetch assignment statistics
$assignment_stats = [
    'total' => 0,
    'pending' => 0,
    'submitted' => 0,
    'graded' => 0,
    'overdue' => 0
];

if (isset($_SESSION['user_id']) && isset($_SESSION['reference_code'])) {
    $stmt = $conn->prepare("SELECT a.*, 
                            s.id as submission_id, s.status, s.marks_obtained
                            FROM assignments a
                            LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
                            WHERE a.reference_code = ?");
    if ($stmt) {
        $stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['reference_code']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $assignment_stats['total']++;
            
            if ($row['submission_id']) {
                if ($row['status'] === 'graded') {
                    $assignment_stats['graded']++;
                } else {
                    $assignment_stats['submitted']++;
                }
            } else {
                $dueDate = new DateTime($row['due_date']);
                $today = new DateTime();
                if ($dueDate < $today) {
                    $assignment_stats['overdue']++;
                } else {
                    $assignment_stats['pending']++;
                }
            }
        }
        $stmt->close();
    }
}

// Fetch announcements
$announcement_stats = [
    'total' => 0,
    'class' => 0,
    'personal' => 0,
    'unread' => 0,
    'recent' => []
];

if (isset($_SESSION['user_id']) && isset($_SESSION['reference_code'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM announcements WHERE reference_code = ? AND announcement_type = 'class'");
    if ($stmt) {
        $stmt->bind_param("s", $_SESSION['reference_code']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $announcement_stats['class'] = $row['count'];
        $stmt->close();
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM announcements a 
                           INNER JOIN student_notifications sn ON a.id = sn.announcement_id 
                           WHERE sn.student_id = ? AND a.announcement_type = 'personal'");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $announcement_stats['personal'] = $row['count'];
        $stmt->close();
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM student_notifications WHERE student_id = ? AND is_read = 0");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $announcement_stats['unread'] = $row['count'];
        $stmt->close();
    }
    
    $announcement_stats['total'] = $announcement_stats['class'] + $announcement_stats['personal'];
    
    $stmt = $conn->prepare("
        (SELECT a.*, 'class' as type, NULL as is_read 
         FROM announcements a 
         WHERE a.reference_code = ? AND a.announcement_type = 'class')
        UNION
        (SELECT a.*, 'personal' as type, sn.is_read 
         FROM announcements a 
         INNER JOIN student_notifications sn ON a.id = sn.announcement_id 
         WHERE sn.student_id = ? AND a.announcement_type = 'personal')
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    if ($stmt) {
        $stmt->bind_param("si", $_SESSION['reference_code'], $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $announcement_stats['recent'][] = $row;
        }
        $stmt->close();
    }
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Alpha-Class Dashboard</title>
    <link rel="stylesheet" href="../asset/style_sheet/dashboard.css" />
    <script src="../asset/script/dashboard.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Previous styles remain the same */
        .class-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .info-card h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .semester-container {
            margin-bottom: 30px;
        }
        
        .semester-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .semester-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 0 0 10px 10px;
            overflow: hidden;
        }
        
        .semester-table thead {
            background-color: #f8f9fa;
        }
        
        .semester-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        .semester-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            color: #555;
        }
        
        .semester-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .semester-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .teacher-name {
            color: #667eea;
            font-weight: 500;
        }
        
        .subject-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9em;
        }

        .attendance-overview, .assignment-overview, .announcements-overview {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .attendance-stats, .assignment-stats, .announcements-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .attendance-stat-card, .assignment-stat-card, .announcement-stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .attendance-stat-card:hover, .assignment-stat-card:hover, .announcement-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .attendance-stat-card.present {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .attendance-stat-card.absent {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }

        .attendance-stat-card.good {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .attendance-stat-card.warning {
            background: linear-gradient(135deg, #f09819 0%, #ff512f 100%);
        }

        .assignment-stat-card.pending {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .assignment-stat-card.submitted {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .assignment-stat-card.graded {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .assignment-stat-card.overdue {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .announcement-stat-card.class {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .announcement-stat-card.personal {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .announcement-stat-card.unread {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .stat-icon {
            font-size: 2.5em;
        }

        .stat-details {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .stat-value {
            font-size: 1.8em;
            font-weight: 700;
        }

        .attendance-progress {
            margin-top: 25px;
        }

        .progress-bar {
            width: 100%;
            height: 40px;
            background: #e9ecef;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            transition: width 0.5s ease;
        }

        .progress-fill.good {
            background: linear-gradient(90deg, #11998e 0%, #38ef7d 100%);
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, #f09819 0%, #ff512f 100%);
        }

        .attendance-status {
            margin-top: 15px;
            font-size: 1.1em;
            font-weight: 500;
            text-align: center;
        }

        .btn {
            margin-top: 20px;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .recent-announcements {
            margin-top: 20px;
        }

        .announcement-item {
            background: #f7fafc;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 12px;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .announcement-item:hover {
            background: #edf2f7;
            transform: translateX(5px);
        }

        .announcement-item.personal {
            border-left-color: #4facfe;
        }

        .announcement-item.unread {
            background: #fff5f5;
            border-left-color: #fc8181;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .announcement-meta {
            display: flex;
            gap: 10px;
            font-size: 0.85em;
            color: #718096;
        }

        .announcement-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
        }

        .badge-class {
            background: #fef5e7;
            color: #d68910;
        }

        .badge-personal {
            background: #ebf8ff;
            color: #2c5282;
        }

        .badge-unread {
            background: #fff5f5;
            color: #c53030;
        }

        .announcement-text {
            color: #2d3748;
            line-height: 1.6;
        }

        /* Grade Overview Styles */
        .grade-overview {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .grade-quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .grade-stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .grade-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .grade-stat-card.average {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .grade-stat-card.highest {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .grade-stat-card.lowest {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .grade-stat-card.failed {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .grade-stat-card h4 {
            font-size: 0.85em;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .grade-stat-card .value {
            font-size: 2em;
            font-weight: 700;
        }

        .grade-chart-container {
            margin: 25px 0;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }

        .grade-chart-container h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .grade-chart-wrapper {
            position: relative;
            height: 350px;
        }

        .no-grades-message {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-grades-message h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.5em;
        }

        @media (max-width: 768px) {
            .grade-quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .grade-chart-wrapper {
                height: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <h2>Alpha-Class</h2>
            <ul>
                <li><a href="#class-info">Class Info</a></li>
                <li><a href="#subjects">Subjects</a></li>
                <li><a href="#assignments">Assignments</a></li>
                <li><a href="#attendance">Attendance</a></li>
                <li><a href="#grades">Grades</a></li>
                <li><a href="student_announcements.php">Announcements</a></li>
                <li><a href="../main/chat.html">Chat</a></li>
                <li><a href="../index.html">Logout</a></li>
            </ul>
        </aside>

        <main class="content">
            <section id="welcome" class="profile-section">
                <h1 class="whiteclr">Welcome Back, Student!</h1>
                <div class="profile-info">
                    <section id="profile" class="profile-second-layer">
                        <div class="profile-card">
                            <div class="profile-pic">
                                <?php 
                                $profile_pic_src = "../asset/img/dashboard/default-user.png";
                                if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
                                    if (file_exists($_SESSION['profile_picture'])) {
                                        $profile_pic_src = $_SESSION['profile_picture'];
                                    }
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($profile_pic_src); ?>" alt="Profile Picture" />
                            </div>
                            <div class="profile-name">
                                <h2 class="highlight"><strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong></h2>
                                <div class="profile-buttons">
                                    <button class="profile-btn edit-btn" onclick="editProfile()">
                                        <svg class="btn-icon" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                                        </svg>
                                        Edit
                                    </button>
                                    <button class="profile-btn chatbot-btn" onclick="openChatbot()">
                                        <svg class="btn-icon" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"></path>
                                        </svg>
                                        Chatbot
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                    <div class="email-role">
                        <p><strong>Email :</strong> <?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        <p><strong>Role  :</strong> <?php echo htmlspecialchars($_SESSION['role']); ?></p>
                        <?php if (isset($_SESSION['phone']) && !empty($_SESSION['phone'])): ?>
                        <p><strong>Phone :</strong> <?php echo htmlspecialchars($_SESSION['phone']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Class Info Section -->
            <section id="class-info" class="section">
                <h2>🎓 Class Information</h2>
                <?php if ($class_info): ?>
                <div class="class-info-grid">
                    <div class="info-card">
                        <h3>Class</h3>
                        <p class="info-value"><?php echo htmlspecialchars($class_info['class']); ?></p>
                    </div>
                    <div class="info-card">
                        <h3>Department</h3>
                        <p class="info-value"><?php echo htmlspecialchars($class_info['department']); ?></p>
                    </div>
                    <div class="info-card">
                        <h3>Current Semester</h3>
                        <p class="info-value"><?php echo htmlspecialchars($class_info['currentSem']); ?></p>
                    </div>
                    <div class="info-card">
                        <h3>Class Teacher</h3>
                        <p class="info-value"><?php echo htmlspecialchars($class_info['class_teacher']); ?></p>
                        <?php if ($teacher_phone): ?>
                        <p style="font-size: 0.9em; color: #667eea; margin-top: 5px;">
                            📞 <?php echo htmlspecialchars($teacher_phone); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="info-card">
                        <h3>Total Students</h3>
                        <p class="info-value"><?php echo htmlspecialchars($class_info['total_strength']); ?></p>
                    </div>
                    <div class="info-card">
                        <h3>Reference Code</h3>
                        <p class="info-value"><?php echo htmlspecialchars($class_info['referencecode']); ?></p>
                    </div>
                </div>
                <?php else: ?>
                <p>No class information available. Please contact your administrator.</p>
                <?php endif; ?>
            </section>

            <!-- Subjects Section -->
            <section id="subjects" class="section">
                <h2>📚 Current Semester Subjects</h2>
                <?php if ($class_info && count($current_subjects) > 0): ?>
                    <div class="semester-container">
                        <div class="semester-header">
                            📖 Semester <?php echo htmlspecialchars($class_info['currentSem']); ?>
                        </div>
                        <table class="semester-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Subject Name</th>
                                    <th>Teaching Teacher</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $subjectCount = 1;
                                foreach ($current_subjects as $subject): 
                                ?>
                                <tr>
                                    <td>
                                        <span class="subject-number"><?php echo $subjectCount++; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($subject['subject_name'] ?? 'N/A'); ?></td>
                                    <td class="teacher-name"><?php echo htmlspecialchars($subject['teaching_teacher'] ?? 'Not Assigned'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                <p>No subjects found for the current semester.</p>
                <?php endif; ?>
            </section>

            <!-- Assignments Section -->
            <section id="assignments" class="section">
                <h2>📄 Assignments Overview</h2>
                <div class="assignment-overview">
                    <div class="assignment-stats">
                        <div class="assignment-stat-card">
                            <div class="stat-icon">📚</div>
                            <div class="stat-details">
                                <span class="stat-label">Total</span>
                                <span class="stat-value"><?php echo $assignment_stats['total']; ?></span>
                            </div>
                        </div>
                        
                        <div class="assignment-stat-card pending">
                            <div class="stat-icon">⏳</div>
                            <div class="stat-details">
                                <span class="stat-label">Pending</span>
                                <span class="stat-value"><?php echo $assignment_stats['pending']; ?></span>
                            </div>
                        </div>
                        
                        <div class="assignment-stat-card submitted">
                            <div class="stat-icon">📤</div>
                            <div class="stat-details">
                                <span class="stat-label">Submitted</span>
                                <span class="stat-value"><?php echo $assignment_stats['submitted']; ?></span>
                            </div>
                        </div>
                        
                        <div class="assignment-stat-card graded">
                            <div class="stat-icon">✅</div>
                            <div class="stat-details">
                                <span class="stat-label">Graded</span>
                                <span class="stat-value"><?php echo $assignment_stats['graded']; ?></span>
                            </div>
                        </div>
                        
                        <?php if ($assignment_stats['overdue'] > 0): ?>
                        <div class="assignment-stat-card overdue">
                            <div class="stat-icon">⚠️</div>
                            <div class="stat-details">
                                <span class="stat-label">Overdue</span>
                                <span class="stat-value"><?php echo $assignment_stats['overdue']; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($assignment_stats['overdue'] > 0): ?>
                    <div style="background: #fff5f5; border-left: 4px solid #fc8181; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <strong>⚠️ Warning:</strong> You have <?php echo $assignment_stats['overdue']; ?> overdue assignment(s). Please submit them as soon as possible!
                    </div>
                    <?php endif; ?>
                    
                    <button class="btn btn-primary" onclick="window.location.href='student/assignment.php'">
                        View All Assignments
                    </button>
                </div>
            </section>

            <!-- Attendance Section -->
            <section id="attendance" class="section">
                <h2>📊 Attendance - <?php echo $months[$current_month] . ' ' . $current_year; ?></h2>
                
                <?php if ($attendance_data && $attendance_data['total_days'] > 0): ?>
                    <div class="attendance-overview">
                        <div class="attendance-stats">
                            <div class="attendance-stat-card">
                                <div class="stat-icon">📅</div>
                                <div class="stat-details">
                                    <span class="stat-label">Total Days</span>
                                    <span class="stat-value"><?php echo $attendance_data['total_days']; ?></span>
                                </div>
                            </div>
                            
                            <div class="attendance-stat-card present">
                                <div class="stat-icon">✅</div>
                                <div class="stat-details">
                                    <span class="stat-label">Present</span>
                                    <span class="stat-value"><?php echo $attendance_data['present_days']; ?></span>
                                </div>
                            </div>
                            
                            <div class="attendance-stat-card absent">
                                <div class="stat-icon">❌</div>
                                <div class="stat-details">
                                    <span class="stat-label">Absent</span>
                                    <span class="stat-value"><?php echo $attendance_data['absent_days']; ?></span>
                                </div>
                            </div>
                            
                            <div class="attendance-stat-card <?php echo $attendance_data['attendance_percentage'] >= 72 ? 'good' : 'warning'; ?>">
                                <div class="stat-icon">📊</div>
                                <div class="stat-details">
                                    <span class="stat-label">Attendance %</span>
                                    <span class="stat-value"><?php echo number_format($attendance_data['attendance_percentage'], 1); ?>%</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="attendance-progress">
                            <div class="progress-bar">
                                <div class="progress-fill <?php echo $attendance_data['attendance_percentage'] >= 72 ? 'good' : 'warning'; ?>" 
                                     style="width: <?php echo min($attendance_data['attendance_percentage'], 100); ?>%">
                                    <?php echo number_format($attendance_data['attendance_percentage'], 1); ?>%
                                </div>
                            </div>
                            <p class="attendance-status">
                                <?php if ($attendance_data['attendance_percentage'] >= 72): ?>
                                    ✅ Your attendance is good! Keep it up!
                                <?php else: ?>
                                    ⚠️ Warning: Your attendance is below 72%. Please improve.
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <button class="btn btn-primary" onclick="window.location.href='student/attendance_details.php'">
                            View Detailed Attendance
                        </button>
                    </div>
                <?php else: ?>
                    <div class="attendance-overview">
                        <p style="text-align: center; padding: 40px; color: #666;">
                            📅 No attendance records found for <?php echo $months[$current_month]; ?> <?php echo $current_year; ?>.
                        </p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Grades Section with Graph -->
            <section id="grades" class="section">
                <h2>🏅 Grades - Current Semester Performance</h2>
                
                <?php if (!empty($current_semester_marks)): ?>
                    <div class="grade-overview">
                        <!-- Quick Stats -->
                        <div class="grade-quick-stats">
                            <div class="grade-stat-card average">
                                <h4>Average</h4>
                                <div class="value"><?php echo number_format($grade_stats['average'], 1); ?>%</div>
                            </div>
                            
                            <div class="grade-stat-card">
                                <h4>Total Subjects</h4>
                                <div class="value"><?php echo $grade_stats['total_subjects']; ?></div>
                            </div>
                            
                            <div class="grade-stat-card highest">
                                <h4>Highest Mark</h4>
                                <div class="value"><?php echo number_format($grade_stats['highest'], 1); ?></div>
                            </div>
                            
                            <div class="grade-stat-card lowest">
                                <h4>Lowest Mark</h4>
                                <div class="value"><?php echo number_format($grade_stats['lowest'], 1); ?></div>
                            </div>
                            
                            <div class="grade-stat-card">
                                <h4>Passed</h4>
                                <div class="value" style="color: #fff;"><?php echo $grade_stats['passed']; ?></div>
                            </div>
                            
                            <?php if ($grade_stats['failed'] > 0): ?>
                            <div class="grade-stat-card failed">
                                <h4>Failed</h4>
                                <div class="value"><?php echo $grade_stats['failed']; ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($grade_stats['failed'] > 0): ?>
                        <div style="background: #fff5f5; border-left: 4px solid #fc8181; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                            <strong>⚠️ Alert:</strong> You have <?php echo $grade_stats['failed']; ?> failed subject(s). Please focus on improvement!
                        </div>
                        <?php endif; ?>

                        <!-- Performance Chart -->
                        <div class="grade-chart-container">
                            <h3>📊 Subject-wise Performance (Semester <?php echo $class_info['currentSem']; ?>)</h3>
                            <div class="grade-chart-wrapper">
                                <canvas id="gradeChart"></canvas>
                            </div>
                        </div>

                        <button class="btn btn-primary" onclick="window.location.href='student/grade.php'">
                            View Detailed Analysis & All Semesters
                        </button>
                    </div>
                <?php else: ?>
                    <div class="grade-overview">
                        <div class="no-grades-message">
                            <h3>📊 No Grades Available Yet</h3>
                            <p>Your marks for Semester <?php echo $class_info['currentSem'] ?? ''; ?> will appear here once your teachers enter them.</p>
                            <p style="margin-top: 15px; color: #999;">Check back later or contact your class teacher for updates.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Announcements Section -->
            <section id="announcements" class="section">
                <h2>📢 Announcements</h2>
                <div class="announcements-overview">
                    <div class="announcements-stats">
                        <div class="announcement-stat-card">
                            <div class="stat-icon">📢</div>
                            <div class="stat-details">
                                <span class="stat-label">Total</span>
                                <span class="stat-value"><?php echo $announcement_stats['total']; ?></span>
                            </div>
                        </div>
                        
                        <div class="announcement-stat-card class">
                            <div class="stat-icon">👥</div>
                            <div class="stat-details">
                                <span class="stat-label">Class</span>
                                <span class="stat-value"><?php echo $announcement_stats['class']; ?></span>
                            </div>
                        </div>
                        
                        <div class="announcement-stat-card personal">
                            <div class="stat-icon">👤</div>
                            <div class="stat-details">
                                <span class="stat-label">Personal</span>
                                <span class="stat-value"><?php echo $announcement_stats['personal']; ?></span>
                            </div>
                        </div>
                        
                        <?php if ($announcement_stats['unread'] > 0): ?>
                        <div class="announcement-stat-card unread">
                            <div class="stat-icon">🔔</div>
                            <div class="stat-details">
                                <span class="stat-label">Unread</span>
                                <span class="stat-value"><?php echo $announcement_stats['unread']; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($announcement_stats['unread'] > 0): ?>
                    <div style="background: #fff5f5; border-left: 4px solid #fc8181; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <strong>🔔 New:</strong> You have <?php echo $announcement_stats['unread']; ?> unread announcement(s). Check them now!
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($announcement_stats['recent'])): ?>
                    <div class="recent-announcements">
                        <h3 style="margin-bottom: 15px; color: #2d3748;">Recent Announcements</h3>
                        <?php foreach ($announcement_stats['recent'] as $announcement): 
                            $isUnread = isset($announcement['is_read']) && $announcement['is_read'] == 0;
                            $itemClass = 'announcement-item';
                            if ($announcement['type'] === 'personal') {
                                $itemClass .= ' personal';
                            }
                            if ($isUnread) {
                                $itemClass .= ' unread';
                            }
                        ?>
                        <div class="<?php echo $itemClass; ?>">
                            <div class="announcement-header">
                                <div class="announcement-meta">
                                    <span>👨‍🏫 <?php echo htmlspecialchars($announcement['teacher_name']); ?></span>
                                    <span>📅 <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></span>
                                    <?php if ($announcement['type'] === 'class'): ?>
                                        <span class="announcement-badge badge-class">Class</span>
                                    <?php else: ?>
                                        <span class="announcement-badge badge-personal">Personal</span>
                                    <?php endif; ?>
                                    <?php if ($isUnread): ?>
                                        <span class="announcement-badge badge-unread">New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="announcement-text">
                                <?php echo htmlspecialchars(substr($announcement['announcement'], 0, 150)); ?>
                                <?php if (strlen($announcement['announcement']) > 150) echo '...'; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <button class="btn btn-primary" onclick="window.location.href='student_announcements.php'">
                        View All Announcements
                    </button>
                </div>
            </section>
            
        </main>
    </div>

    <script>
        function editProfile() {
            window.location.href = 'edit_profile_student.php';
        }

        function openChatbot() {
            window.location.href = '../main/chatbot.html';
        }

        // Initialize Grade Chart
        <?php if (!empty($current_semester_marks)): ?>
        window.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('gradeChart');
            if (ctx) {
                const subjectNames = <?php echo json_encode(array_column($current_semester_marks, 'subject_name')); ?>;
                const totalMarks = <?php echo json_encode(array_column($current_semester_marks, 'total_mark')); ?>;
                const internalMarks = <?php echo json_encode(array_column($current_semester_marks, 'internal_mark')); ?>;
                const externalMarks = <?php echo json_encode(array_column($current_semester_marks, 'external_mark')); ?>;

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: subjectNames,
                        datasets: [
                            {
                                label: 'Internal Marks (20)',
                                data: internalMarks,
                                backgroundColor: 'rgba(102, 126, 234, 0.7)',
                                borderColor: 'rgba(102, 126, 234, 1)',
                                borderWidth: 2
                            },
                            {
                                label: 'External Marks (80)',
                                data: externalMarks,
                                backgroundColor: 'rgba(118, 75, 162, 0.7)',
                                borderColor: 'rgba(118, 75, 162, 1)',
                                borderWidth: 2
                            },
                            {
                                label: 'Total Marks (100)',
                                data: totalMarks,
                                backgroundColor: 'rgba(17, 153, 142, 0.7)',
                                borderColor: 'rgba(17, 153, 142, 1)',
                                borderWidth: 2,
                                type: 'line',
                                fill: false,
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    font: {
                                        size: 12,
                                        family: 'Poppins'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: {
                                    size: 14,
                                    family: 'Poppins'
                                },
                                bodyFont: {
                                    size: 13,
                                    family: 'Poppins'
                                },
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y.toFixed(1);
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>