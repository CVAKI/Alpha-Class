<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../main/signin.html");
    exit();
}

require_once('../../config.php');

// Fetch student's assignments based on reference code
$assignments = [];
$stmt = $conn->prepare("SELECT a.*, 
                        s.id as submission_id, s.file_path, s.original_filename, s.submitted_at, 
                        s.marks_obtained, s.feedback, s.status
                        FROM assignments a
                        LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
                        WHERE a.reference_code = ?
                        ORDER BY a.due_date DESC");
if ($stmt) {
    $stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['reference_code']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments</title>
    <link rel="stylesheet" href="../../asset/style_sheet/dashboard.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; }
        .header h1 { margin: 0 0 10px 0; }
        .card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .assignment-item { border: 2px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 20px; transition: all 0.3s; }
        .assignment-item:hover { border-color: #667eea; box-shadow: 0 4px 12px rgba(102,126,234,0.2); }
        .assignment-title { font-size: 1.3em; font-weight: 600; color: #2d3748; margin-bottom: 8px; }
        .assignment-meta { color: #718096; font-size: 0.95em; margin: 8px 0; }
        .assignment-meta span { margin-right: 20px; }
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
        .status-pending { background: #fed7d7; color: #c53030; }
        .status-submitted { background: #bee3f8; color: #2c5282; }
        .status-graded { background: #c6f6d5; color: #22543d; }
        .status-overdue { background: #feb2b2; color: #742a2a; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s; display: inline-block; text-decoration: none; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-success { background: #48bb78; color: white; }
        .upload-form { margin-top: 15px; padding: 15px; background: #f7fafc; border-radius: 8px; }
        .file-input { margin: 10px 0; }
        .graded-info { background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin-top: 10px; border-radius: 6px; }
        .submitted-info { background: #ebf8ff; border-left: 4px solid #4299e1; padding: 15px; margin-top: 10px; border-radius: 6px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .tab { padding: 12px 24px; cursor: pointer; border: none; background: none; font-weight: 600; color: #718096; transition: all 0.3s; }
        .tab.active { color: #667eea; border-bottom: 3px solid #667eea; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 My Assignments</h1>
            <p>View and submit your assignments</p>
        </div>

        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('pending')">Pending</button>
                <button class="tab" onclick="switchTab('submitted')">Submitted</button>
                <button class="tab" onclick="switchTab('graded')">Graded</button>
            </div>

            <div id="pending-tab" class="tab-content active">
                <h2>Pending Assignments</h2>
                <?php 
                $pendingCount = 0;
                foreach ($assignments as $assignment): 
                    if ($assignment['submission_id']) continue;
                    $pendingCount++;
                    $dueDate = new DateTime($assignment['due_date']);
                    $today = new DateTime();
                    $isOverdue = $dueDate < $today;
                    $statusClass = $isOverdue ? 'status-overdue' : 'status-pending';
                    $statusText = $isOverdue ? 'Overdue' : 'Pending';
                ?>
                <div class="assignment-item">
                    <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                    <div class="assignment-meta">
                        <span>📖 <?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                        <span>📅 Due: <?php echo $dueDate->format('M d, Y'); ?></span>
                        <span>💯 Total Marks: <?php echo $assignment['total_marks']; ?></span>
                        <span class="<?php echo $statusClass; ?> status-badge"><?php echo $statusText; ?></span>
                    </div>
                    <p style="color: #4a5568; margin: 10px 0;"><?php echo htmlspecialchars($assignment['description']); ?></p>
                    <button class="btn btn-primary" onclick="toggleUpload(<?php echo $assignment['id']; ?>)">Submit Assignment</button>
                    
                    <div id="upload-<?php echo $assignment['id']; ?>" class="upload-form" style="display: none;">
                        <form onsubmit="uploadAssignment(event, <?php echo $assignment['id']; ?>)" enctype="multipart/form-data">
                            <label style="font-weight: 600; display: block; margin-bottom: 8px;">Upload PDF File:</label>
                            <input type="file" name="assignment_file" accept=".pdf" required class="file-input">
                            <div style="margin-top: 15px;">
                                <button type="submit" class="btn btn-success">Upload</button>
                                <button type="button" class="btn" style="background: #718096; color: white;" onclick="toggleUpload(<?php echo $assignment['id']; ?>)">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($pendingCount === 0): ?>
                <p style="text-align: center; color: #718096; padding: 40px;">No pending assignments! 🎉</p>
                <?php endif; ?>
            </div>

            <div id="submitted-tab" class="tab-content">
                <h2>Submitted Assignments</h2>
                <?php 
                $submittedCount = 0;
                foreach ($assignments as $assignment): 
                    if (!$assignment['submission_id'] || $assignment['status'] !== 'submitted') continue;
                    $submittedCount++;
                ?>
                <div class="assignment-item">
                    <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                    <div class="assignment-meta">
                        <span>📖 <?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                        <span>📅 Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></span>
                        <span>💯 Total Marks: <?php echo $assignment['total_marks']; ?></span>
                        <span class="status-badge status-submitted">Submitted</span>
                    </div>
                    <div class="submitted-info">
                        <strong>✅ Submitted on:</strong> <?php echo date('M d, Y h:i A', strtotime($assignment['submitted_at'])); ?><br>
                        <strong>📄 File:</strong> <?php echo htmlspecialchars($assignment['original_filename']); ?><br>
                        <a href="../../<?php echo htmlspecialchars($assignment['file_path']); ?>" target="_blank" class="btn btn-primary" style="margin-top: 10px;">View Submission</a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($submittedCount === 0): ?>
                <p style="text-align: center; color: #718096; padding: 40px;">No submitted assignments awaiting grading.</p>
                <?php endif; ?>
            </div>

            <div id="graded-tab" class="tab-content">
                <h2>Graded Assignments</h2>
                <?php 
                $gradedCount = 0;
                foreach ($assignments as $assignment): 
                    if (!$assignment['submission_id'] || $assignment['status'] !== 'graded') continue;
                    $gradedCount++;
                    $percentage = ($assignment['marks_obtained'] / $assignment['total_marks']) * 100;
                ?>
                <div class="assignment-item">
                    <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                    <div class="assignment-meta">
                        <span>📖 <?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                        <span>📅 Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></span>
                        <span class="status-badge status-graded">Graded</span>
                    </div>
                    <div class="graded-info">
                        <strong>🎯 Score:</strong> <?php echo $assignment['marks_obtained']; ?> / <?php echo $assignment['total_marks']; ?> (<?php echo number_format($percentage, 2); ?>%)<br>
                        <?php if ($assignment['feedback']): ?>
                        <strong>💬 Feedback:</strong> <?php echo htmlspecialchars($assignment['feedback']); ?><br>
                        <?php endif; ?>
                        <strong>📄 Your Submission:</strong> <?php echo htmlspecialchars($assignment['original_filename']); ?><br>
                        <a href="../../<?php echo htmlspecialchars($assignment['file_path']); ?>" target="_blank" class="btn btn-primary" style="margin-top: 10px;">View Submission</a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($gradedCount === 0): ?>
                <p style="text-align: center; color: #718096; padding: 40px;">No graded assignments yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <button class="btn" style="background: #4a5568; color: white;" onclick="window.location.href='../studentMain.php'">← Back to Dashboard</button>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }

        function toggleUpload(assignmentId) {
            const form = document.getElementById('upload-' + assignmentId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        async function uploadAssignment(event, assignmentId) {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append('assignment_id', assignmentId);

            const fileInput = event.target.querySelector('input[type="file"]');
            if (!fileInput.files[0]) {
                alert('Please select a PDF file');
                return;
            }

            if (fileInput.files[0].type !== 'application/pdf') {
                alert('Only PDF files are allowed');
                return;
            }

            try {
                const response = await fetch('submit_assignment.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('Assignment submitted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error uploading assignment: ' + error.message);
            }
        }
    </script>
</body>
</html>