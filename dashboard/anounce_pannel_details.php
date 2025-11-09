<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: signin.html");
    exit();
}

require_once('../config.php');

// Function to check if table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Function to create announcements table
function createAnnouncementsTable($conn) {
    $sql = "
    CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    reference_code VARCHAR(100) NOT NULL,
    teacher_name VARCHAR(255) NOT NULL,
    announcement TEXT NOT NULL,
    announcement_type ENUM('class', 'personal') DEFAULT 'class',
    recipients TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reference_code (reference_code),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
    
    return $conn->query($sql);
}

// Function to create calendar_events table
function createCalendarEventsTable($conn) {
    $sql = "
    CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    reference_code VARCHAR(100) NOT NULL,
    teacher_name VARCHAR(255) NOT NULL,
    event_title VARCHAR(255) NOT NULL,
    event_description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    event_type VARCHAR(50) NOT NULL,
    reminder_days INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reference_code (reference_code),
    INDEX idx_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
    
    return $conn->query($sql);
}

// Check and create tables if they don't exist
if (!tableExists($conn, 'announcements')) {
    if (createAnnouncementsTable($conn)) {
        error_log("Announcements table created successfully");
    } else {
        error_log("Error creating announcements table: " . $conn->error);
    }
}

if (!tableExists($conn, 'calendar_events')) {
    if (createCalendarEventsTable($conn)) {
        error_log("Calendar events table created successfully");
    } else {
        error_log("Error creating calendar_events table: " . $conn->error);
    }
}

// Fetch teacher data
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

// Fetch students in the class
$students = [];
if (isset($_SESSION['reference_code'])) {
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE reference_code = ? AND role = 'student' ORDER BY name ASC");
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

// Fetch upcoming events
$events = [];
if (tableExists($conn, 'calendar_events') && isset($_SESSION['reference_code'])) {
    $stmt = $conn->prepare("SELECT * FROM calendar_events WHERE reference_code = ? AND event_date >= CURDATE() ORDER BY event_date ASC, event_time ASC LIMIT 10");
    if ($stmt) {
        $stmt->bind_param("s", $_SESSION['reference_code']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        $stmt->close();
    }
}

// Fetch recent announcements
$recent_announcements = [];
if (tableExists($conn, 'announcements') && isset($_SESSION['reference_code'])) {
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE reference_code = ? ORDER BY created_at DESC LIMIT 10");
    if ($stmt) {
        $stmt->bind_param("s", $_SESSION['reference_code']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recent_announcements[] = $row;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement Panel - Alpha-Class</title>
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
            max-width: 1400px;
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
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
        }

        .section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .section-title {
            font-size: 1.5em;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .icon {
            font-size: 1.3em;
        }

        /* Live Announcement Section */
        .announcement-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: #555;
            font-size: 0.95em;
        }

        .form-group textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 1em;
            resize: vertical;
            min-height: 120px;
            transition: all 0.3s;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .announcement-type {
            display: flex;
            gap: 15px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .student-select-container {
            display: none;
        }

        .student-select-container.active {
            display: block;
        }

        .student-checkboxes {
            max-height: 200px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            background: white;
        }

        .student-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            cursor: pointer;
            border-radius: 5px;
            transition: all 0.2s;
        }

        .student-checkbox:hover {
            background: #f0f0f0;
        }

        .student-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .select-all-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }

        /* Calendar Event Section */
        .calendar-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .calendar-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 1em;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        /* Upcoming Events */
        .events-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
        }

        .event-card {
            background: white;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .event-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .event-title {
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
            margin-bottom: 5px;
        }

        .event-details {
            color: #666;
            font-size: 0.9em;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .event-date {
            color: #667eea;
            font-weight: 500;
        }

        .event-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        /* Recent Announcements */
        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-height: 500px;
            overflow-y: auto;
        }

        .announcement-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .announcement-type-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .announcement-type-badge.personal {
            background: #28a745;
        }

        .announcement-time {
            color: #999;
            font-size: 0.85em;
        }

        .announcement-text {
            color: #555;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .announcement-recipients {
            font-size: 0.85em;
            color: #667eea;
            font-style: italic;
        }

        .success-message {
            background: #28a745;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: none;
        }

        .error-message {
            background: #dc3545;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: none;
        }

        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <button class="back-btn" onclick="window.location.href='teachermain.php'">← Back to Dashboard</button>
            <h1>📢 Announcement & Event Panel</h1>
            <p>Manage announcements, events, and reminders for your students</p>
        </div>

        <div class="main-content">
            <!-- Live Announcement Section -->
            <div class="section">
                <h2 class="section-title"><span class="icon">📣</span> Create Live Announcement</h2>
                <div id="announcementSuccess" class="success-message"></div>
                <div id="announcementError" class="error-message"></div>
                
                <form class="announcement-form" id="announcementForm">
                    <div class="form-group">
                        <label>Announcement Type</label>
                        <div class="announcement-type">
                            <label class="radio-option">
                                <input type="radio" name="announcement_type" value="class" checked onchange="toggleStudentSelect()">
                                <span>Entire Class</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="announcement_type" value="personal" onchange="toggleStudentSelect()">
                                <span>Personal (Select Students)</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group student-select-container" id="studentSelectContainer">
                        <label>Select Students</label>
                        <button type="button" class="select-all-btn" onclick="toggleSelectAll()">Select All</button>
                        <div class="student-checkboxes" id="studentCheckboxes">
                            <?php foreach ($students as $student): ?>
                            <label class="student-checkbox">
                                <input type="checkbox" name="students[]" value="<?php echo $student['id']; ?>">
                                <span><?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)</span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Announcement Message *</label>
                        <textarea name="announcement" placeholder="Enter your announcement here..." required></textarea>
                    </div>

                    <button type="submit" class="btn-primary">📤 Post Announcement</button>
                </form>
            </div>

            <!-- Calendar Event Section -->
            <div class="section">
                <h2 class="section-title"><span class="icon">📅</span> Schedule Event/Reminder</h2>
                <div id="eventSuccess" class="success-message"></div>
                <div id="eventError" class="error-message"></div>
                
                <form class="calendar-form" id="calendarForm">
                    <div class="form-group">
                        <label>Event Title *</label>
                        <input type="text" name="event_title" placeholder="e.g., Final Exam, Assignment Deadline" required>
                    </div>

                    <div class="form-group">
                        <label>Event Description</label>
                        <textarea name="event_description" placeholder="Additional details about the event..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Event Date *</label>
                            <input type="date" name="event_date" required>
                        </div>
                        <div class="form-group">
                            <label>Event Time</label>
                            <input type="time" name="event_time">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Event Type *</label>
                        <select name="event_type" required>
                            <option value="">Select Type</option>
                            <option value="exam">Exam</option>
                            <option value="assignment">Assignment</option>
                            <option value="project">Project Deadline</option>
                            <option value="test">Test</option>
                            <option value="event">General Event</option>
                            <option value="holiday">Holiday</option>
                            <option value="meeting">Meeting</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Reminder</label>
                        <select name="reminder">
                            <option value="">No Reminder</option>
                            <option value="1">1 Day Before</option>
                            <option value="3">3 Days Before</option>
                            <option value="7">1 Week Before</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-primary">📌 Schedule Event</button>
                </form>
            </div>

            <!-- Upcoming Events Section -->
            <div class="section">
                <h2 class="section-title"><span class="icon">⏰</span> Upcoming Events</h2>
                <div class="events-list" id="eventsList">
                    <?php if (count($events) > 0): ?>
                        <?php foreach ($events as $event): ?>
                        <div class="event-card" id="event-<?php echo $event['id']; ?>">
                            <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                            <div class="event-details">
                                <span class="event-date">📅 <?php echo date('d M Y', strtotime($event['event_date'])); ?> <?php echo $event['event_time'] ? '⏰ ' . date('h:i A', strtotime($event['event_time'])) : ''; ?></span>
                                <span>🏷️ <?php echo ucfirst($event['event_type']); ?></span>
                                <?php if ($event['event_description']): ?>
                                <span><?php echo htmlspecialchars($event['event_description']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="event-actions">
                                <button class="btn-delete" onclick="deleteEvent(<?php echo $event['id']; ?>)">Delete</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #999; text-align: center;">No upcoming events scheduled</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Announcements Section -->
            <div class="section">
                <h2 class="section-title"><span class="icon">📋</span> Recent Announcements</h2>
                <div class="announcements-list" id="announcementsList">
                    <?php if (count($recent_announcements) > 0): ?>
                        <?php foreach ($recent_announcements as $announcement): ?>
                        <div class="announcement-card">
                            <div class="announcement-header">
                                <span class="announcement-type-badge <?php echo $announcement['announcement_type'] == 'personal' ? 'personal' : ''; ?>">
                                    <?php echo $announcement['announcement_type'] == 'personal' ? 'Personal' : 'Class'; ?>
                                </span>
                                <span class="announcement-time"><?php echo date('d M Y, h:i A', strtotime($announcement['created_at'])); ?></span>
                            </div>
                            <div class="announcement-text"><?php echo htmlspecialchars($announcement['announcement']); ?></div>
                            <?php if ($announcement['announcement_type'] == 'personal' && $announcement['recipients']): ?>
                            <div class="announcement-recipients">
                                Sent to: <?php echo htmlspecialchars($announcement['recipients']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #999; text-align: center;">No announcements yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleStudentSelect() {
            const personalRadio = document.querySelector('input[name="announcement_type"][value="personal"]');
            const studentContainer = document.getElementById('studentSelectContainer');
            
            if (personalRadio.checked) {
                studentContainer.classList.add('active');
            } else {
                studentContainer.classList.remove('active');
            }
        }

        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('#studentCheckboxes input[type="checkbox"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => cb.checked = !allChecked);
        }

        // Handle announcement form submission
        document.getElementById('announcementForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const successMsg = document.getElementById('announcementSuccess');
            const errorMsg = document.getElementById('announcementError');
            
            try {
                const response = await fetch('handlers/create_announcement_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    successMsg.textContent = data.message;
                    successMsg.style.display = 'block';
                    errorMsg.style.display = 'none';
                    this.reset();
                    toggleStudentSelect();
                    
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    errorMsg.textContent = data.message;
                    errorMsg.style.display = 'block';
                    successMsg.style.display = 'none';
                }
            } catch (error) {
                errorMsg.textContent = 'An error occurred. Please try again.';
                errorMsg.style.display = 'block';
                successMsg.style.display = 'none';
            }
        });

        // Handle calendar event form submission
        document.getElementById('calendarForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const successMsg = document.getElementById('eventSuccess');
            const errorMsg = document.getElementById('eventError');
            
            try {
                const response = await fetch('handlers/create_event_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    successMsg.textContent = data.message;
                    successMsg.style.display = 'block';
                    errorMsg.style.display = 'none';
                    this.reset();
                    
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    errorMsg.textContent = data.message;
                    errorMsg.style.display = 'block';
                    successMsg.style.display = 'none';
                }
            } catch (error) {
                errorMsg.textContent = 'An error occurred. Please try again.';
                errorMsg.style.display = 'block';
                successMsg.style.display = 'none';
            }
        });

        async function deleteEvent(eventId) {
            if (!confirm('Are you sure you want to delete this event?')) {
                return;
            }
            
            try {
                const response = await fetch('handlers/delete_event_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ event_id: eventId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('event-' + eventId).remove();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            }
        }
    </script>
</body>
</html>