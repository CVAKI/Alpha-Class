<?php
/**
 * Create Student Notifications Table
 * Run this file once to create the student_notifications table
 * Access: http://yoursite.com/create_notification_table.php
 */

require_once('config.php');

// HTML Header
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Create Notification Table - Alpha-Class</title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 700px;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 2em;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid #28a745;
        }
        .success h2 {
            margin-bottom: 10px;
            font-size: 1.3em;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid #dc3545;
        }
        .error h2 {
            margin-bottom: 10px;
            font-size: 1.3em;
        }
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .info-box h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .info-box ul {
            list-style: none;
            padding-left: 0;
        }
        .info-box li {
            padding: 8px 0;
            color: #555;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-box li:last-child {
            border-bottom: none;
        }
        .info-box li strong {
            color: #667eea;
            display: inline-block;
            min-width: 150px;
        }
        code {
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #c7254e;
        }
        .btn-back {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        .icon {
            font-size: 3em;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>";

// Create student_notifications table
$sql = "CREATE TABLE IF NOT EXISTS student_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    announcement_id INT NOT NULL,
    is_read TINYINT(1) DEFAULT 0 COMMENT '0=unread, 1=read',
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    
    INDEX idx_student_id (student_id),
    INDEX idx_announcement_id (announcement_id),
    INDEX idx_is_read (is_read),
    UNIQUE KEY unique_student_announcement (student_id, announcement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks which students received personal announcements'";

if ($conn->query($sql) === TRUE) {
    echo "<div class='icon'>✅</div>";
    echo "<h1>Success!</h1>";
    echo "<p class='subtitle'>Student Notifications Table Created</p>";
    
    echo "<div class='success'>";
    echo "<h2>✓ Table created successfully!</h2>";
    echo "<p>The <code>student_notifications</code> table has been created in your database.</p>";
    echo "</div>";
    
    echo "<div class='info-box'>";
    echo "<h3>📋 Table Structure:</h3>";
    echo "<ul>";
    echo "<li><strong>id:</strong> Primary key (Auto increment)</li>";
    echo "<li><strong>student_id:</strong> References user ID</li>";
    echo "<li><strong>announcement_id:</strong> References announcement ID</li>";
    echo "<li><strong>is_read:</strong> 0 = Unread, 1 = Read</li>";
    echo "<li><strong>read_at:</strong> Timestamp when read</li>";
    echo "<li><strong>created_at:</strong> Timestamp when created</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='info-box'>";
    echo "<h3>🎯 What This Table Does:</h3>";
    echo "<ul>";
    echo "<li>✓ Tracks personal announcements sent to specific students</li>";
    echo "<li>✓ Prevents duplicate notifications (unique constraint)</li>";
    echo "<li>✓ Allows marking announcements as read/unread</li>";
    echo "<li>✓ Auto-deletes when student or announcement is deleted</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='info-box'>";
    echo "<h3>⚠️ Important Notes:</h3>";
    echo "<ul>";
    echo "<li>This table requires <code>users</code> table to exist</li>";
    echo "<li>This table requires <code>announcements</code> table to exist</li>";
    echo "<li>Foreign keys ensure data integrity</li>";
    echo "<li>You can now safely delete this file</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<a href='teacher/announce_panel_detail.php' class='btn-back'>Go to Announcement Panel →</a>";
    
} else {
    echo "<div class='icon'>❌</div>";
    echo "<h1>Error!</h1>";
    echo "<p class='subtitle'>Failed to Create Table</p>";
    
    echo "<div class='error'>";
    echo "<h2>✗ Error creating table</h2>";
    echo "<p><strong>Error:</strong> " . $conn->error . "</p>";
    echo "</div>";
    
    echo "<div class='info-box'>";
    echo "<h3>🔧 Possible Solutions:</h3>";
    echo "<ul>";
    echo "<li>Make sure the <code>users</code> table exists</li>";
    echo "<li>Make sure the <code>announcements</code> table exists</li>";
    echo "<li>Check database connection in config.php</li>";
    echo "<li>Verify database user has CREATE TABLE permissions</li>";
    echo "<li>Check if the table already exists</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<a href='javascript:location.reload()' class='btn-back'>Try Again</a>";
}

// Check if table exists and show info
$result = $conn->query("SHOW TABLES LIKE 'student_notifications'");
if ($result && $result->num_rows > 0) {
    echo "<div class='info-box'>";
    echo "<h3>📊 Table Status:</h3>";
    echo "<p style='color: #28a745; font-weight: bold;'>✓ Table 'student_notifications' exists in database</p>";
    
    // Count records
    $count_result = $conn->query("SELECT COUNT(*) as count FROM student_notifications");
    if ($count_result) {
        $count_row = $count_result->fetch_assoc();
        echo "<p style='color: #555; margin-top: 10px;'>Current records: <strong>" . $count_row['count'] . "</strong></p>";
    }
    echo "</div>";
}

echo "    </div>
</body>
</html>";

$conn->close();
?>