<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: signin.html");
    exit();
}

require_once('../../config.php');

// Create marks table if it doesn't exist
$createMarksTable = "
CREATE TABLE IF NOT EXISTS marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    reference_code VARCHAR(100) NOT NULL,
    semester INT NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    internal_mark DECIMAL(5,2) DEFAULT 0.00,
    external_mark DECIMAL(5,2) DEFAULT 0.00,
    total_mark DECIMAL(5,2) DEFAULT 0.00,
    grade VARCHAR(10) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_subject (student_id, semester, subject_name),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reference_code (reference_code),
    INDEX idx_semester (semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$conn->query($createMarksTable)) {
    die("Error creating marks table: " . $conn->error);
}

// Create trigger to auto-calculate total marks and grade
$dropTrigger = "DROP TRIGGER IF EXISTS calculate_total_and_grade";
$conn->query($dropTrigger);

$createTrigger = "
CREATE TRIGGER calculate_total_and_grade 
BEFORE INSERT ON marks
FOR EACH ROW
BEGIN
    SET NEW.total_mark = NEW.internal_mark + NEW.external_mark;
    
    IF NEW.total_mark >= 95 THEN
        SET NEW.grade = 'S';
    ELSEIF NEW.total_mark >= 90 THEN
        SET NEW.grade = 'A+';
    ELSEIF NEW.total_mark >= 80 THEN
        SET NEW.grade = 'A';
    ELSEIF NEW.total_mark >= 70 THEN
        SET NEW.grade = 'B+';
    ELSEIF NEW.total_mark >= 60 THEN
        SET NEW.grade = 'B';
    ELSEIF NEW.total_mark >= 50 THEN
        SET NEW.grade = 'C';
    ELSEIF NEW.total_mark >= 40 THEN
        SET NEW.grade = 'D';
    ELSE
        SET NEW.grade = 'F';
    END IF;
END;
";

if (!$conn->query($createTrigger)) {
    die("Error creating trigger: " . $conn->error);
}

// Create update trigger
$dropUpdateTrigger = "DROP TRIGGER IF EXISTS update_total_and_grade";
$conn->query($dropUpdateTrigger);

$createUpdateTrigger = "
CREATE TRIGGER update_total_and_grade 
BEFORE UPDATE ON marks
FOR EACH ROW
BEGIN
    SET NEW.total_mark = NEW.internal_mark + NEW.external_mark;
    
    IF NEW.total_mark >= 95 THEN
        SET NEW.grade = 'S';
    ELSEIF NEW.total_mark >= 90 THEN
        SET NEW.grade = 'A+';
    ELSEIF NEW.total_mark >= 80 THEN
        SET NEW.grade = 'A';
    ELSEIF NEW.total_mark >= 70 THEN
        SET NEW.grade = 'B+';
    ELSEIF NEW.total_mark >= 60 THEN
        SET NEW.grade = 'B';
    ELSEIF NEW.total_mark >= 50 THEN
        SET NEW.grade = 'C';
    ELSEIF NEW.total_mark >= 40 THEN
        SET NEW.grade = 'D';
    ELSE
        SET NEW.grade = 'F';
    END IF;
END;
";

if (!$conn->query($createUpdateTrigger)) {
    die("Error creating update trigger: " . $conn->error);
}

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

// Fetch current semester subjects
$current_subjects = [];
if ($class_info) {
    $stmt = $conn->prepare("SELECT subject_name FROM class_subjects WHERE referencecode = ? AND semester = ? ORDER BY subject_name ASC");
    if ($stmt) {
        $stmt->bind_param("si", $class_info['referencecode'], $class_info['currentSem']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $current_subjects[] = $row['subject_name'];
        }
        $stmt->close();
    }
}

// Fetch students with matching reference code
$students = [];
if (isset($_SESSION['reference_code'])) {
    $stmt = $conn->prepare("SELECT id, name, email, profile_picture FROM users WHERE reference_code = ? AND role = 'student' ORDER BY name ASC");
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

// Fetch marks for all students in current semester
$student_marks = [];
if ($class_info && count($students) > 0) {
    $student_ids = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    
    $stmt = $conn->prepare("SELECT * FROM marks WHERE student_id IN ($placeholders) AND semester = ? ORDER BY student_name, subject_name");
    if ($stmt) {
        $types = str_repeat('i', count($student_ids)) . 'i';
        $params = array_merge($student_ids, [$class_info['currentSem']]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $student_marks[$row['student_id']][$row['subject_name']] = $row;
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
    <title>Student Details & Marks - Alpha-Class</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="studentdetails.css">
   
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Student Details & Marks Management</h1>
            <a href="../teacherMain.php" class="back-btn">Back to Dashboard</a>
        </div>

        <?php if ($class_info): ?>
        <div class="semester-info">
            <h2>Current Semester: <?php echo htmlspecialchars($class_info['currentSem']); ?></h2>
            <p><strong>Class:</strong> <?php echo htmlspecialchars($class_info['class']); ?> | 
               <strong>Department:</strong> <?php echo htmlspecialchars($class_info['department']); ?></p>
            
            <?php if (count($current_subjects) > 0): ?>
            <div class="subjects-list">
                <?php foreach ($current_subjects as $subject): ?>
                <span class="subject-badge"><?php echo htmlspecialchars($subject); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (count($students) > 0 && count($current_subjects) > 0): ?>
            <?php foreach ($students as $student): ?>
            <div class="student-section">
                <div class="student-header">
                    <?php 
                    $student_pic = "../../asset/img/dashboard/default-user.png";
                    if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])) {
                        $student_pic = $student['profile_picture'];
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($student_pic); ?>" alt="Student" class="student-pic">
                    <div class="student-info">
                        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                        <p><?php echo htmlspecialchars($student['email']); ?></p>
                    </div>
                </div>

                <table class="marks-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Internal Mark (20)</th>
                            <th>External Mark (80)</th>
                            <th>Total Mark (100)</th>
                            <th>Grade</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($current_subjects as $subject): ?>
                        <?php 
                        $mark_data = $student_marks[$student['id']][$subject] ?? null;
                        $internal = $mark_data['internal_mark'] ?? 0;
                        $external = $mark_data['external_mark'] ?? 0;
                        $total = $mark_data['total_mark'] ?? 0;
                        $grade = $mark_data['grade'] ?? '';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($subject); ?></strong></td>
                            <td>
                                <input type="number" 
                                       class="mark-input internal-mark" 
                                       min="0" 
                                       max="20" 
                                       step="0.01"
                                       value="<?php echo $internal; ?>"
                                       data-student-id="<?php echo $student['id']; ?>"
                                       data-subject="<?php echo htmlspecialchars($subject); ?>">
                            </td>
                            <td>
                                <input type="number" 
                                       class="mark-input external-mark" 
                                       min="0" 
                                       max="80" 
                                       step="0.01"
                                       value="<?php echo $external; ?>"
                                       data-student-id="<?php echo $student['id']; ?>"
                                       data-subject="<?php echo htmlspecialchars($subject); ?>">
                            </td>
                            <td><strong><?php echo number_format($total, 2); ?></strong></td>
                            <td>
                                <?php if ($grade): ?>
                                <span class="grade-badge grade-<?php echo str_replace('+', '-plus', $grade); ?>">
                                    <?php echo htmlspecialchars($grade); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="save-btn" onclick="saveMarks(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($subject); ?>', '<?php echo htmlspecialchars($student['name']); ?>')">
                                    Save
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">
                <?php if (count($students) == 0): ?>
                    No students found in your class.
                <?php elseif (count($current_subjects) == 0): ?>
                    No subjects found for the current semester.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="no-data">Class information not found.</div>
        <?php endif; ?>
    </div>

    <script>
        function saveMarks(studentId, subject, studentName) {
            const row = document.querySelector(`input[data-student-id="${studentId}"][data-subject="${subject}"]`).closest('tr');
            const internalInput = row.querySelector('.internal-mark');
            const externalInput = row.querySelector('.external-mark');
            
            const internalMark = parseFloat(internalInput.value) || 0;
            const externalMark = parseFloat(externalInput.value) || 0;
            
            if (internalMark < 0 || internalMark > 20) {
                alert('Internal mark must be between 0 and 20');
                return;
            }
            
            if (externalMark < 0 || externalMark > 80) {
                alert('External mark must be between 0 and 80');
                return;
            }
            
            const formData = new FormData();
            formData.append('student_id', studentId);
            formData.append('subject', subject);
            formData.append('internal_mark', internalMark);
            formData.append('external_mark', externalMark);
            formData.append('student_name', studentName);
            
            fetch('save_marks.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Marks saved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving marks.');
            });
        }
    </script>
</body>
</html>