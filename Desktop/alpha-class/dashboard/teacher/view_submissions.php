<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../main/signin.html");
    exit();
}

require_once('../../config.php');

$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch assignment details
$assignment = null;
$stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ? AND teacher_id = ?");
if ($stmt) {
    $stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();
}

if (!$assignment) {
    die("Assignment not found or unauthorized");
}

// Fetch submissions
$submissions = [];
$stmt = $conn->prepare("SELECT s.*, u.name as student_name, u.email as student_email 
                        FROM assignment_submissions s
                        JOIN users u ON s.student_id = u.id
                        WHERE s.assignment_id = ?
                        ORDER BY s.submitted_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submissions</title>
    <link rel="stylesheet" href="../../asset/style_sheet/dashboard.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; }
        .header h1 { margin: 0 0 10px 0; }
        .card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .submission-item { border: 2px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 20px; transition: all 0.3s; }
        .submission-item:hover { border-color: #667eea; box-shadow: 0 4px 12px rgba(102,126,234,0.2); }
        .submission-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .student-name { font-size: 1.2em; font-weight: 600; color: #2d3748; }
        .submission-meta { color: #718096; font-size: 0.9em; margin: 5px 0; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-success { background: #48bb78; color: white; }
        .btn-success:hover { background: #38a169; }
        .grading-form { margin-top: 15px; padding: 15px; background: #f7fafc; border-radius: 8px; }
        .grading-form input, .grading-form textarea { width: 100%; padding: 10px; margin: 8px 0; border: 2px solid #e2e8f0; border-radius: 6px; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
        .status-submitted { background: #bee3f8; color: #2c5282; }
        .status-graded { background: #c6f6d5; color: #22543d; }
        .graded-info { background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin-top: 10px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Assignment Submissions</h1>
            <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
            <p>Subject: <?php echo htmlspecialchars($assignment['subject_name']); ?> | Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?> | Total Marks: <?php echo $assignment['total_marks']; ?></p>
        </div>

        <div class="card">
            <h2>Submissions (<?php echo count($submissions); ?>)</h2>
            <?php if (count($submissions) > 0): ?>
                <?php foreach ($submissions as $submission): ?>
                <div class="submission-item">
                    <div class="submission-header">
                        <div>
                            <div class="student-name">👤 <?php echo htmlspecialchars($submission['student_name']); ?></div>
                            <div class="submission-meta"><?php echo htmlspecialchars($submission['student_email']); ?></div>
                            <div class="submission-meta">Submitted: <?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?></div>
                        </div>
                        <span class="status-badge status-<?php echo $submission['status']; ?>"><?php echo ucfirst($submission['status']); ?></span>
                    </div>

                    <div style="margin: 15px 0;">
                        <strong>📄 File:</strong> <?php echo htmlspecialchars($submission['original_filename']); ?>
                        <br>
                        <a href="../../<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" class="btn btn-primary" style="margin-top: 10px; display: inline-block;">View PDF</a>
                        <a href="../../<?php echo htmlspecialchars($submission['file_path']); ?>" download class="btn" style="background: #4299e1; color: white; margin-top: 10px; display: inline-block;">Download</a>
                    </div>

                    <?php if ($submission['marks_obtained'] !== null): ?>
                    <div class="graded-info">
                        <strong>✅ Graded</strong><br>
                        <strong>Marks:</strong> <?php echo $submission['marks_obtained']; ?> / <?php echo $assignment['total_marks']; ?><br>
                        <?php if ($submission['feedback']): ?>
                        <strong>Feedback:</strong> <?php echo htmlspecialchars($submission['feedback']); ?><br>
                        <?php endif; ?>
                        <strong>Graded on:</strong> <?php echo date('M d, Y h:i A', strtotime($submission['graded_at'])); ?>
                    </div>
                    <?php else: ?>
                    <button class="btn btn-success" onclick="toggleGrading(<?php echo $submission['id']; ?>)">Grade This Submission</button>
                    <div id="grading-<?php echo $submission['id']; ?>" class="grading-form" style="display: none;">
                        <form onsubmit="submitGrade(event, <?php echo $submission['id']; ?>, <?php echo $assignment['total_marks']; ?>)">
                            <label><strong>Marks Obtained (out of <?php echo $assignment['total_marks']; ?>):</strong></label>
                            <input type="number" name="marks" min="0" max="<?php echo $assignment['total_marks']; ?>" required>
                            <label><strong>Feedback (optional):</strong></label>
                            <textarea name="feedback" rows="3" placeholder="Write feedback for the student..."></textarea>
                            <button type="submit" class="btn btn-success">Submit Grade</button>
                            <button type="button" class="btn" style="background: #718096; color: white;" onclick="toggleGrading(<?php echo $submission['id']; ?>)">Cancel</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #718096; padding: 40px;">No submissions yet.</p>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <button class="btn" style="background: #4a5568; color: white;" onclick="window.location.href='assignments.php'">← Back to Assignments</button>
        </div>
    </div>

    <script>
        function toggleGrading(submissionId) {
            const form = document.getElementById('grading-' + submissionId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        async function submitGrade(event, submissionId, totalMarks) {
            event.preventDefault();
            const formData = new FormData(event.target);
            const marks = parseInt(formData.get('marks'));

            if (marks > totalMarks) {
                alert(`Marks cannot exceed ${totalMarks}`);
                return;
            }

            const data = {
                submission_id: submissionId,
                marks: marks,
                feedback: formData.get('feedback')
            };

            try {
                const response = await fetch('grade_submission.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('Grade submitted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error submitting grade: ' + error.message);
            }
        }
    </script>
</body>
</html>