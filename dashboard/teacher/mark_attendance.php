<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../main/signin.html");
    exit();
}

require_once('../../config.php');

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch students
$students = [];
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

// Check if attendance already marked for today
$already_marked = [];
$stmt = $conn->prepare("SELECT student_id, status FROM attendance WHERE attendance_date = ? AND reference_code = ?");
if ($stmt) {
    $stmt->bind_param("ss", $selected_date, $_SESSION['reference_code']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $already_marked[$row['student_id']] = $row['status'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance</title>
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
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #2d3748;
            margin-bottom: 10px;
        }

        .date-selector {
            text-align: center;
            margin-bottom: 30px;
        }

        .date-selector input {
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            cursor: pointer;
        }

        .date-selector input:focus {
            outline: none;
            border-color: #667eea;
        }

        .attendance-form {
            margin-top: 30px;
        }

        .student-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .student-row:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
        }

        .student-details h3 {
            color: #2d3748;
            font-size: 1.1em;
            margin-bottom: 5px;
        }

        .student-details p {
            color: #718096;
            font-size: 0.9em;
        }

        .attendance-buttons {
            display: flex;
            gap: 10px;
        }

        .attendance-btn {
            padding: 12px 24px;
            border: 2px solid;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-present {
            background: white;
            border-color: #48bb78;
            color: #48bb78;
        }

        .btn-present:hover,
        .btn-present.active {
            background: #48bb78;
            color: white;
        }

        .btn-absent {
            background: white;
            border-color: #f56565;
            color: #f56565;
        }

        .btn-absent:hover,
        .btn-absent.active {
            background: #f56565;
            color: white;
        }

        .submit-section {
            text-align: center;
            margin-top: 40px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-submit {
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-cancel {
            padding: 15px 40px;
            background: #718096;
            color: white;
            border: none;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #4a5568;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 2px solid #fbbf24;
        }

        .quick-actions {
            text-align: center;
            margin-bottom: 20px;
        }

        .quick-btn {
            padding: 10px 20px;
            margin: 0 5px;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-present {
            background: #48bb78;
            color: white;
        }

        .quick-absent {
            background: #f56565;
            color: white;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .student-row {
                flex-direction: column;
                gap: 15px;
            }

            .attendance-buttons {
                width: 100%;
            }

            .attendance-btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Mark Attendance</h1>
            <p>Select attendance for each student</p>
        </div>

        <div class="date-selector">
            <label for="attendance_date"><strong>Select Date:</strong></label>
            <input type="date" id="attendance_date" value="<?php echo $selected_date; ?>" 
                   max="<?php echo date('Y-m-d'); ?>" onchange="changeDate(this.value)">
        </div>

        <?php if (count($already_marked) > 0): ?>
        <div class="alert alert-warning">
            ⚠️ Attendance for this date has already been marked. You can update it below.
        </div>
        <?php endif; ?>

        <div class="quick-actions">
            <strong>Quick Actions:</strong>
            <button class="quick-btn quick-present" onclick="markAllPresent()">✅ Mark All Present</button>
            <button class="quick-btn quick-absent" onclick="markAllAbsent()">❌ Mark All Absent</button>
        </div>

        <form id="attendanceForm" class="attendance-form">
            <?php foreach ($students as $student): 
                $current_status = $already_marked[$student['id']] ?? '';
            ?>
            <div class="student-row">
                <div class="student-info">
                    <img src="<?php 
                        $pic = "../../asset/img/dashboard/default-user.png";
                        if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])) {
                            $pic = $student['profile_picture'];
                        }
                        echo htmlspecialchars($pic); 
                    ?>" alt="Student" class="student-avatar">
                    <div class="student-details">
                        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                        <p><?php echo htmlspecialchars($student['email']); ?></p>
                    </div>
                </div>
                <div class="attendance-buttons">
                    <input type="hidden" name="students[]" value="<?php echo $student['id']; ?>">
                    <input type="hidden" name="student_names[]" value="<?php echo htmlspecialchars($student['name']); ?>">
                    <button type="button" class="attendance-btn btn-present <?php echo $current_status === 'present' ? 'active' : ''; ?>" 
                            onclick="markAttendance(this, <?php echo $student['id']; ?>, 'present')">
                        ✅ Present
                    </button>
                    <button type="button" class="attendance-btn btn-absent <?php echo $current_status === 'absent' ? 'active' : ''; ?>" 
                            onclick="markAttendance(this, <?php echo $student['id']; ?>, 'absent')">
                        ❌ Absent
                    </button>
                    <input type="hidden" name="attendance[<?php echo $student['id']; ?>]" id="attendance_<?php echo $student['id']; ?>" value="<?php echo $current_status; ?>">
                </div>
            </div>
            <?php endforeach; ?>

            <div class="submit-section">
                <button type="submit" class="btn-submit">💾 Save Attendance</button>
                <button type="button" class="btn-cancel" onclick="window.location.href='attendance.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>'">
                    Cancel
                </button>
            </div>
        </form>
    </div>

    <script>
        function markAttendance(btn, studentId, status) {
            const row = btn.closest('.student-row');
            const presentBtn = row.querySelector('.btn-present');
            const absentBtn = row.querySelector('.btn-absent');
            const input = document.getElementById('attendance_' + studentId);
            
            presentBtn.classList.remove('active');
            absentBtn.classList.remove('active');
            btn.classList.add('active');
            input.value = status;
        }

        function markAllPresent() {
            document.querySelectorAll('.btn-present').forEach(btn => {
                btn.click();
            });
        }

        function markAllAbsent() {
            document.querySelectorAll('.btn-absent').forEach(btn => {
                btn.click();
            });
        }

        function changeDate(date) {
            window.location.href = 'mark_attendance.php?date=' + date + '&month=<?php echo $month; ?>&year=<?php echo $year; ?>';
        }

        document.getElementById('attendanceForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('date', document.getElementById('attendance_date').value);
            
            // Check if all students have attendance marked
            let allMarked = true;
            const students = formData.getAll('students[]');
            students.forEach(studentId => {
                const attendance = formData.get('attendance[' + studentId + ']');
                if (!attendance) {
                    allMarked = false;
                }
            });
            
            if (!allMarked) {
                alert('Please mark attendance for all students!');
                return;
            }
            
            try {
                const response = await fetch('save_attendance.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Attendance saved successfully!');
                    window.location.href = 'attendance.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>';
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error saving attendance: ' + error.message);
            }
        });
    </script>
</body>
</html>