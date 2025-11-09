<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: signin.html");
    exit();
}

require_once('../config.php');   

// Fetch current teacher data from database
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

// Fetch teacher's class information from reference_code table
$class_info = null;
$stmt = $conn->prepare("SELECT * FROM reference_code WHERE referencecode = ?");
if ($stmt) {
    $stmt->bind_param("s", $_SESSION['reference_code']);
    $stmt->execute();
    $result = $stmt->get_result();
    $class_info = $result->fetch_assoc();
    $stmt->close();
}

// Fetch current semester subjects only
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

// Fetch students with matching reference code
$students = [];
if (isset($_SESSION['reference_code'])) {
    $stmt = $conn->prepare("SELECT id, name, email, phone, profile_picture FROM users WHERE reference_code = ? AND role = 'student'");
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Teacher Dashboard - Alpha-Class</title>
    <link rel="stylesheet" href="teacher/teachermain.css" />
    <script src="teacher/teachermain.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <style>
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

        /* Button Styles */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 1em;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <h2>Alpha-Class</h2>
            <ul>
                <li><a href="#class-info">Class Information</a></li>
                <li><a href="#subjects">Current Semester</a></li>
                <li><a href="teacher/assignments.php">Assignments</a></li>
                <li><a href="teacher/student_details.php">Students Marks</a></li>
                <li><a href="anounce_pannel_details.php">Announcements</a></li>
                <li><a href="teacher/attendance.php">Attendance</a></li>
                <li><a href="../main/chat.html">Chat</a></li>
                <li><a href="../index.html">Logout</a></li>
            </ul>
        </aside>

        <main class="content">
            <section id="welcome" class="profile-section">
                <h1 class="whiteclr">Welcome Back, Teacher!</h1>
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
                        <h3>Class Teacher</h3>
                        <p class="info-value"><?php echo htmlspecialchars($class_info['class_teacher']); ?></p>
                    </div>
                    <div class="info-card">
                        <h3>Total Students</h3>
                        <p class="info-value"><?php echo htmlspecialchars($class_info['total_strength']); ?></p>
                    </div>
                    <div class="info-card">
                        <h3>Current Semester</h3>
                        <p class="info-value"><?php echo htmlspecialchars($class_info['currentSem']); ?></p>
                        <button class="edit-sem-btn" onclick="editSemester('<?php echo htmlspecialchars($class_info['referencecode']); ?>', '<?php echo htmlspecialchars($class_info['currentSem']); ?>')">
                            Edit Semester
                        </button>
                    </div>
                    <div class="info-card">
                        <h3>Reference Code</h3>
                        <p class="info-value"><?php echo htmlspecialchars($class_info['referencecode']); ?></p>
                    </div>
                </div>
                <?php else: ?>
                <p>No class information found for your reference code.</p>
                <?php endif; ?>
            </section>

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

            <section id="assignments-section" class="section">
                <h2>📝 Assignments</h2>
                <p>Manage and grade student assignments.</p>
                <button class="btn btn-primary" onclick="window.location.href='teacher/assignments.php'" style="margin-top: 15px;">
                    Manage Assignments
                </button>
            </section>

            <section id="students" class="section">
                <h2>👨‍🎓 Students in Your Class</h2>
                <?php if (count($students) > 0): ?>
                <div class="students-container">
                    <div class="students-grid">
                        <?php foreach ($students as $student): ?>
                        <div class="student-card" data-student-id="<?php echo htmlspecialchars($student['id']); ?>">
                            <div class="student-pic">
                                <?php 
                                $student_pic = "../asset/img/dashboard/default-user.png";
                                if (isset($student['profile_picture']) && !empty($student['profile_picture'])) {
                                    if (file_exists($student['profile_picture'])) {
                                        $student_pic = $student['profile_picture'];
                                    }
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($student_pic); ?>" alt="Student Picture" />
                            </div>
                            <div class="student-info">
                                <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                                <?php if (!empty($student['phone'])): ?>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['phone']); ?></p>
                                <?php endif; ?>
                            </div>
                            <button class="delete-student-btn" onclick="deleteStudent(<?php echo htmlspecialchars($student['id']); ?>, '<?php echo htmlspecialchars($student['name']); ?>')">
                                Delete Student
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <p>No students found with your reference code.</p>
                <?php endif; ?>
            </section>

            <section id="announcements" class="section">
                <h2>📢 Announcements</h2>
                <p>Create and manage announcements for your class.</p>
                <button class="create-announcement-btn" onclick="createAnnouncement()">Create Announcement</button>
            </section>
        </main>
    </div>

    <!-- Edit Semester Modal -->
    <div id="editSemesterModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Edit Current Semester</h2>
            <form id="editSemesterForm">
                <input type="hidden" id="referenceCode" name="referenceCode">
                <label for="newSemester">New Semester:</label>
                <input type="text" id="newSemester" name="newSemester" required>
                <button type="submit">Update Semester</button>
            </form>
        </div>
    </div>

    <script>
        function editProfile() {
            window.location.href = 'edit_profile_teacher.php';
        }

        function openChatbot() {
            window.location.href = '../main/chatbot.html';
        }
    </script>
</body>
</html>