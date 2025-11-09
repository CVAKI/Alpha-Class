<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: signin.html");
    exit();
}

require_once('../config.php');

// Ensure student_notifications table exists
$create_table = "CREATE TABLE IF NOT EXISTS student_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    student_id INT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_announcement (announcement_id),
    UNIQUE KEY unique_notification (announcement_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($create_table);

// Fetch student data
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
if ($stmt) {
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    
    if ($user_data) {
        $_SESSION['name'] = $user_data['name'];
        $_SESSION['reference_code'] = $user_data['reference_code'];
    }
    $stmt->close();
}

// Fetch all announcements (class + personal)
$announcements = [];
$student_id = $_SESSION['user_id'];
$reference_code = $_SESSION['reference_code'];

// Query to get both class and personal announcements
$query = "
    SELECT 
        a.*,
        sn.is_read,
        CASE 
            WHEN a.announcement_type = 'class' THEN 'class'
            ELSE 'personal'
        END as display_type
    FROM announcements a
    LEFT JOIN student_notifications sn ON a.id = sn.announcement_id AND sn.student_id = ?
    WHERE a.reference_code = ?
    AND (
        a.announcement_type = 'class'
        OR (a.announcement_type = 'personal' AND sn.student_id IS NOT NULL)
    )
    ORDER BY a.created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("is", $student_id, $reference_code);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
    $stmt->close();
}

// Fetch upcoming events
$events = [];
$stmt = $conn->prepare("SELECT * FROM calendar_events WHERE reference_code = ? AND event_date >= CURDATE() ORDER BY event_date ASC, event_time ASC LIMIT 20");
if ($stmt) {
    $stmt->bind_param("s", $reference_code);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
}

// Mark personal announcements as read when viewed
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $announcement_id = intval($_GET['mark_read']);
    $update_stmt = $conn->prepare("UPDATE student_notifications SET is_read = 1 WHERE announcement_id = ? AND student_id = ?");
    if ($update_stmt) {
        $update_stmt->bind_param("ii", $announcement_id, $student_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements & Events - Alpha-Class</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .back-btn {
            position: absolute;
            top: 30px;
            left: 30px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateX(-5px);
        }

        .main-content {
            padding: 30px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab {
            padding: 15px 30px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab:hover {
            color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .announcements-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .announcement-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 5px solid #667eea;
            transition: all 0.3s;
            position: relative;
        }

        .announcement-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.12);
        }

        .announcement-card.personal {
            border-left-color: #28a745;
            background: #f0fff4;
        }

        .announcement-card.unread {
            background: #fff5f5;
            border-left-color: #fc8181;
        }

        .announcement-card.unread::before {
            content: '●';
            position: absolute;
            top: 20px;
            right: 20px;
            color: #fc8181;
            font-size: 1.5em;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .announcement-badge {
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .announcement-badge.personal {
            background: #28a745;
        }

        .announcement-badge.unread {
            background: #fc8181;
        }

        .announcement-time {
            color: #999;
            font-size: 0.9em;
        }

        .announcement-teacher {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .announcement-text {
            color: #333;
            line-height: 1.8;
            font-size: 1.05em;
        }

        .events-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .event-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-top: 4px solid #667eea;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .event-type-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .event-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .event-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .event-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .event-detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
            font-size: 0.95em;
        }

        .event-icon {
            font-size: 1.2em;
        }

        .event-date-highlight {
            background: #667eea;
            color: white;
            padding: 10px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 10px;
        }

        .event-day {
            font-size: 2em;
            font-weight: 700;
        }

        .event-month {
            font-size: 0.9em;
            text-transform: uppercase;
            opacity: 0.9;
        }

        .no-content {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .no-content-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-card.personal {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .stat-card.unread {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .events-container {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
            }

            .tab {
                text-align: left;
            }

            .stats-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <button class="back-btn" onclick="window.location.href='studentmain.php'">← Back to Dashboard</button>
            <h1>📢 Announcements & Events</h1>
            <p>Stay updated with class announcements and upcoming events</p>
        </div>

        <div class="main-content">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('announcements')">📣 Announcements 
                    <?php 
                    $unread_count = 0;
                    foreach ($announcements as $ann) {
                        if (isset($ann['is_read']) && $ann['is_read'] == 0 && $ann['announcement_type'] == 'personal') {
                            $unread_count++;
                        }
                    }
                    if ($unread_count > 0) echo "($unread_count)";
                    ?>
                </button>
                <button class="tab" onclick="switchTab('events')">📅 Upcoming Events (<?php echo count($events); ?>)</button>
            </div>

            <!-- Announcements Tab -->
            <div id="announcements-tab" class="tab-content active">
                <div class="stats-summary">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($announcements); ?></div>
                        <div class="stat-label">Total Announcements</div>
                    </div>
                    <div class="stat-card personal">
                        <div class="stat-number">
                            <?php 
                            $personal_count = 0;
                            foreach ($announcements as $ann) {
                                if ($ann['announcement_type'] == 'personal') $personal_count++;
                            }
                            echo $personal_count;
                            ?>
                        </div>
                        <div class="stat-label">Personal Messages</div>
                    </div>
                    <?php if ($unread_count > 0): ?>
                    <div class="stat-card unread">
                        <div class="stat-number"><?php echo $unread_count; ?></div>
                        <div class="stat-label">Unread</div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="announcements-container">
                    <?php if (count($announcements) > 0): ?>
                        <?php foreach ($announcements as $announcement): 
                            $isUnread = isset($announcement['is_read']) && $announcement['is_read'] == 0 && $announcement['announcement_type'] == 'personal';
                            $cardClass = 'announcement-card';
                            if ($announcement['announcement_type'] == 'personal') $cardClass .= ' personal';
                            if ($isUnread) $cardClass .= ' unread';
                        ?>
                        <div class="<?php echo $cardClass; ?>">
                            <div class="announcement-header">
                                <div>
                                    <span class="announcement-badge <?php echo $announcement['announcement_type'] == 'personal' ? 'personal' : ''; ?> <?php echo $isUnread ? 'unread' : ''; ?>">
                                        <?php 
                                        if ($announcement['announcement_type'] == 'personal') {
                                            echo $isUnread ? '🔴 New Personal Message' : '✉️ Personal Message';
                                        } else {
                                            echo '📢 Class Announcement';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <span class="announcement-time">
                                    <?php echo date('d M Y, h:i A', strtotime($announcement['created_at'])); ?>
                                </span>
                            </div>
                            <div class="announcement-teacher">
                                <span>👨‍🏫</span>
                                <span><?php echo htmlspecialchars($announcement['teacher_name']); ?></span>
                            </div>
                            <div class="announcement-text">
                                <?php echo nl2br(htmlspecialchars($announcement['announcement'])); ?>
                            </div>
                            <?php if ($announcement['announcement_type'] == 'personal' && !empty($announcement['recipients'])): ?>
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 0.85em; color: #666;">
                                <strong>Recipients:</strong> <?php echo htmlspecialchars($announcement['recipients']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-content">
                            <div class="no-content-icon">📭</div>
                            <h3>No Announcements Yet</h3>
                            <p>You'll see class announcements and personal messages here</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Events Tab -->
            <div id="events-tab" class="tab-content">
                <div class="events-container">
                    <?php if (count($events) > 0): ?>
                        <?php foreach ($events as $event): ?>
                        <div class="event-card">
                            <div class="event-date-highlight">
                                <div class="event-day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                                <div class="event-month"><?php echo date('M Y', strtotime($event['event_date'])); ?></div>
                            </div>
                            <span class="event-type-badge"><?php echo htmlspecialchars($event['event_type']); ?></span>
                            <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                            <?php if ($event['event_description']): ?>
                            <div class="event-description"><?php echo nl2br(htmlspecialchars($event['event_description'])); ?></div>
                            <?php endif; ?>
                            <div class="event-details">
                                <div class="event-detail-item">
                                    <span class="event-icon">📅</span>
                                    <span><?php echo date('l, F j, Y', strtotime($event['event_date'])); ?></span>
                                </div>
                                <?php if ($event['event_time']): ?>
                                <div class="event-detail-item">
                                    <span class="event-icon">⏰</span>
                                    <span><?php echo date('h:i A', strtotime($event['event_time'])); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="event-detail-item">
                                    <span class="event-icon">👨‍🏫</span>
                                    <span><?php echo htmlspecialchars($event['teacher_name']); ?></span>
                                </div>
                                <?php if ($event['reminder_days']): ?>
                                <div class="event-detail-item">
                                    <span class="event-icon">🔔</span>
                                    <span>Reminder: <?php echo $event['reminder_days']; ?> day(s) before</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-content">
                            <div class="no-content-icon">📅</div>
                            <h3>No Upcoming Events</h3>
                            <p>Check back later for scheduled events and reminders</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>