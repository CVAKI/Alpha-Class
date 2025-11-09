<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../main/signin.html");
    exit();
}

require_once('../../config.php');

// Fetch teacher's class information to get current semester
$class_info = null;
$stmt = $conn->prepare("SELECT * FROM reference_code WHERE referencecode = ?");
if ($stmt) {
    $stmt->bind_param("s", $_SESSION['reference_code']);
    $stmt->execute();
    $result = $stmt->get_result();
    $class_info = $result->fetch_assoc();
    $stmt->close();
}

// Fetch teacher's assignments
$assignments = [];
$stmt = $conn->prepare("SELECT a.*, COUNT(DISTINCT s.id) as total_submissions, 
                        COUNT(DISTINCT CASE WHEN s.marks_obtained IS NOT NULL THEN s.id END) as graded_count
                        FROM assignments a
                        LEFT JOIN assignment_submissions s ON a.id = s.assignment_id
                        WHERE a.teacher_id = ?
                        GROUP BY a.id
                        ORDER BY a.due_date DESC");
if ($stmt) {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
}

// Fetch ALL subjects for CURRENT SEMESTER (not just teacher's subjects)
$subjects = [];
if ($class_info) {
    $stmt = $conn->prepare("SELECT DISTINCT subject_name 
                            FROM class_subjects 
                            WHERE referencecode = ? 
                            AND semester = ?
                            ORDER BY subject_name ASC");
    if ($stmt) {
        $stmt->bind_param("si", $_SESSION['reference_code'], $class_info['currentSem']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row['subject_name'];
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
    <title>Manage Assignments - Teacher</title>
    <link rel="stylesheet" href="../../asset/style_sheet/dashboard.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; }
        .header h1 { margin: 0 0 10px 0; }
        .header p { margin: 5px 0; opacity: 0.95; }
        .semester-badge { 
            display: inline-block; 
            background: rgba(255,255,255,0.2); 
            padding: 6px 15px; 
            border-radius: 20px; 
            font-weight: 600;
            margin-top: 10px;
        }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-success { background: #48bb78; color: white; }
        .btn-success:hover { background: #38a169; }
        .card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .assignment-item { border-bottom: 1px solid #e2e8f0; padding: 20px 0; }
        .assignment-item:last-child { border-bottom: none; }
        .assignment-title { font-size: 1.3em; font-weight: 600; color: #2d3748; margin-bottom: 8px; }
        .assignment-meta { color: #718096; font-size: 0.95em; margin: 8px 0; }
        .assignment-meta span { margin-right: 20px; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
        .status-pending { background: #fed7d7; color: #c53030; }
        .status-active { background: #c6f6d5; color: #22543d; }
        .status-expired { background: #e2e8f0; color: #4a5568; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); }
        .modal-content { background: white; margin: 5% auto; padding: 30px; width: 90%; max-width: 600px; border-radius: 15px; max-height: 85vh; overflow-y: auto; }
        .close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #718096; }
        .close:hover { color: #2d3748; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: 'Poppins', sans-serif; box-sizing: border-box; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group select { cursor: pointer; }
        .form-group select:focus, .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .action-buttons { display: flex; gap: 10px; margin-top: 15px; }
        .btn-small { padding: 8px 16px; font-size: 0.9em; }
        .no-subjects-warning {
            background: #fff5e6;
            border: 2px solid #ff9800;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #663c00;
        }
        .no-subjects-warning strong {
            color: #e65100;
        }
        .subject-info {
            background: #e6f2ff;
            border-left: 4px solid #667eea;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 0.9em;
            color: #2d5a8c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Assignment Management</h1>
            <p>Create and manage assignments for your students</p>
            <?php if ($class_info): ?>
                <span class="semester-badge">📖 Current Semester: <?php echo htmlspecialchars($class_info['currentSem']); ?></span>
            <?php endif; ?>
            <br><br>
            <?php if (count($subjects) > 0): ?>
                <button class="btn btn-primary" onclick="openCreateModal()">+ Create New Assignment</button>
            <?php else: ?>
                <button class="btn" style="background: #cbd5e0; color: #4a5568; cursor: not-allowed;" disabled>+ Create New Assignment</button>
            <?php endif; ?>
        </div>

        <?php if (count($subjects) == 0): ?>
            <div class="no-subjects-warning">
                <strong>⚠️ No Subjects Available</strong>
                <p>There are no subjects configured for the current semester. Please contact your administrator to add subjects.</p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Your Assignments</h2>
            <?php if (count($assignments) > 0): ?>
                <?php foreach ($assignments as $assignment): 
                    $dueDate = new DateTime($assignment['due_date']);
                    $today = new DateTime();
                    $isExpired = $dueDate < $today;
                    $statusClass = $isExpired ? 'status-expired' : 'status-active';
                    $statusText = $isExpired ? 'Expired' : 'Active';
                ?>
                <div class="assignment-item">
                    <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                    <div class="assignment-meta">
                        <span>📖 <?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                        <span>📅 Due: <?php echo $dueDate->format('M d, Y'); ?></span>
                        <span>💯 Total Marks: <?php echo $assignment['total_marks']; ?></span>
                        <span class="<?php echo $statusClass; ?> status-badge"><?php echo $statusText; ?></span>
                    </div>
                    <div class="assignment-meta">
                        <span>📝 Submissions: <?php echo $assignment['total_submissions']; ?></span>
                        <span>✅ Graded: <?php echo $assignment['graded_count']; ?></span>
                    </div>
                    <p style="color: #4a5568; margin: 10px 0;"><?php echo htmlspecialchars($assignment['description']); ?></p>
                    <div class="action-buttons">
                        <button class="btn btn-success btn-small" onclick="viewSubmissions(<?php echo $assignment['id']; ?>)">View Submissions</button>
                        <button class="btn btn-small" style="background: #ed8936; color: white;" onclick="deleteAssignment(<?php echo $assignment['id']; ?>)">Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #718096; padding: 40px;">No assignments created yet. Click "Create New Assignment" to get started.</p>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <button class="btn" style="background: #4a5568; color: white;" onclick="window.location.href='../teacherMain.php'">← Back to Dashboard</button>
        </div>
    </div>

    <!-- Create Assignment Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCreateModal()">&times;</span>
            <h2>Create New Assignment</h2>
            <?php if ($class_info): ?>
                <div class="subject-info">
                    <strong>📖 Current Semester:</strong> Semester <?php echo htmlspecialchars($class_info['currentSem']); ?><br>
                    <strong>📚 Available Subjects:</strong> <?php echo count($subjects); ?> subject(s) available for assignment creation
                </div>
            <?php endif; ?>
            <form id="createAssignmentForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="subject">Subject * <small style="color: #718096;">(All subjects in Semester <?php echo htmlspecialchars($class_info['currentSem'] ?? 'N/A'); ?>)</small></label>
                    <select id="subject" name="subject" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="title">Assignment Title *</label>
                    <input type="text" id="title" name="title" required placeholder="e.g., Chapter 5 Exercise">
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Assignment instructions and details..."></textarea>
                </div>
                <div class="form-group">
                    <label for="dueDate">Due Date *</label>
                    <input type="date" id="dueDate" name="dueDate" required>
                </div>
                <div class="form-group">
                    <label for="totalMarks">Total Marks *</label>
                    <input type="number" id="totalMarks" name="totalMarks" value="100" min="1" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Assignment</button>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            <?php if (count($subjects) == 0): ?>
                alert('No subjects available for the current semester. Please contact your administrator.');
                return;
            <?php endif; ?>
            document.getElementById('createModal').style.display = 'block';
            document.getElementById('dueDate').min = new Date().toISOString().split('T')[0];
        }

        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
            document.getElementById('createAssignmentForm').reset();
        }

        document.getElementById('createAssignmentForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);

            try {
                const response = await fetch('create_assignment.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('Assignment created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error creating assignment: ' + error.message);
            }
        });

        function viewSubmissions(assignmentId) {
            window.location.href = 'view_submissions.php?id=' + assignmentId;
        }

        async function deleteAssignment(assignmentId) {
            if (!confirm('Are you sure you want to delete this assignment? All submissions will be lost.')) return;

            try {
                const response = await fetch('delete_assignment.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({assignment_id: assignmentId})
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('Assignment deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error deleting assignment: ' + error.message);
            }
        }

        window.onclick = (e) => {
            if (e.target.className === 'modal') {
                closeCreateModal();
            }
        }
    </script>
</body>
</html>