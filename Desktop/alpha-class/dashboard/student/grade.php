<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: signin.html");
    exit();
}

require_once('../../config.php');

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

// Fetch all marks for the student
$marks_data = [];
$semester_stats = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM marks WHERE student_id = ? ORDER BY semester, subject_name");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $marks_data[$row['semester']][] = $row;
            
            // Calculate semester statistics
            if (!isset($semester_stats[$row['semester']])) {
                $semester_stats[$row['semester']] = [
                    'total_marks' => 0,
                    'total_subjects' => 0,
                    'passed' => 0,
                    'failed' => 0,
                    'highest' => 0,
                    'lowest' => 100,
                    'grades' => []
                ];
            }
            
            $semester_stats[$row['semester']]['total_marks'] += $row['total_mark'];
            $semester_stats[$row['semester']]['total_subjects']++;
            
            if ($row['total_mark'] >= 40) {
                $semester_stats[$row['semester']]['passed']++;
            } else {
                $semester_stats[$row['semester']]['failed']++;
            }
            
            if ($row['total_mark'] > $semester_stats[$row['semester']]['highest']) {
                $semester_stats[$row['semester']]['highest'] = $row['total_mark'];
            }
            
            if ($row['total_mark'] < $semester_stats[$row['semester']]['lowest']) {
                $semester_stats[$row['semester']]['lowest'] = $row['total_mark'];
            }
            
            $semester_stats[$row['semester']]['grades'][] = $row['grade'];
        }
        $stmt->close();
    }
}

// Calculate averages
foreach ($semester_stats as $sem => &$stats) {
    if ($stats['total_subjects'] > 0) {
        $stats['average'] = $stats['total_marks'] / $stats['total_subjects'];
    } else {
        $stats['average'] = 0;
    }
}

// Function to generate suggestions
function generateSuggestions($semester_data, $stats) {
    $suggestions = [];
    
    if ($stats['average'] >= 90) {
        $suggestions[] = [
            'icon' => '🌟',
            'type' => 'success',
            'title' => 'Outstanding Performance!',
            'message' => 'You are excelling in all subjects. Keep up the excellent work and consider helping your peers.'
        ];
    } elseif ($stats['average'] >= 75) {
        $suggestions[] = [
            'icon' => '👍',
            'type' => 'good',
            'title' => 'Great Job!',
            'message' => 'Your performance is commendable. Focus on maintaining consistency across all subjects.'
        ];
    } elseif ($stats['average'] >= 60) {
        $suggestions[] = [
            'icon' => '📚',
            'type' => 'warning',
            'title' => 'Room for Improvement',
            'message' => 'You are doing okay, but there is potential to improve. Focus on subjects where you scored lower.'
        ];
    } else {
        $suggestions[] = [
            'icon' => '⚠️',
            'type' => 'danger',
            'title' => 'Needs Attention',
            'message' => 'Your overall performance needs improvement. Consider seeking help from teachers or tutors.'
        ];
    }
    
    // Check for failed subjects
    if ($stats['failed'] > 0) {
        $failed_subjects = [];
        foreach ($semester_data as $subject) {
            if ($subject['total_mark'] < 40) {
                $failed_subjects[] = $subject['subject_name'];
            }
        }
        $suggestions[] = [
            'icon' => '❌',
            'type' => 'danger',
            'title' => 'Failed Subjects',
            'message' => 'You need to improve in: ' . implode(', ', $failed_subjects) . '. Schedule extra study time and consult with your teachers.'
        ];
    }
    
    // Check for weak subjects (marks < 50)
    $weak_subjects = [];
    foreach ($semester_data as $subject) {
        if ($subject['total_mark'] >= 40 && $subject['total_mark'] < 50) {
            $weak_subjects[] = $subject['subject_name'];
        }
    }
    if (!empty($weak_subjects)) {
        $suggestions[] = [
            'icon' => '💡',
            'type' => 'info',
            'title' => 'Focus Areas',
            'message' => 'These subjects need more attention: ' . implode(', ', $weak_subjects) . '. Consider forming study groups or attending extra classes.'
        ];
    }
    
    // Check internal vs external performance
    $internal_avg = 0;
    $external_avg = 0;
    foreach ($semester_data as $subject) {
        $internal_avg += $subject['internal_mark'];
        $external_avg += $subject['external_mark'];
    }
    $internal_avg = $internal_avg / count($semester_data);
    $external_avg = $external_avg / count($semester_data);
    
    $internal_percentage = ($internal_avg / 20) * 100;
    $external_percentage = ($external_avg / 80) * 100;
    
    if ($internal_percentage > $external_percentage + 15) {
        $suggestions[] = [
            'icon' => '📝',
            'type' => 'info',
            'title' => 'Exam Performance',
            'message' => 'Your internal marks are better than external marks. Focus more on exam preparation and time management during tests.'
        ];
    } elseif ($external_percentage > $internal_percentage + 15) {
        $suggestions[] = [
            'icon' => '📖',
            'type' => 'info',
            'title' => 'Continuous Assessment',
            'message' => 'Your exam performance is good, but internal assessment needs improvement. Complete assignments on time and participate more in class.'
        ];
    }
    
    // Check for consistency
    $variance = 0;
    foreach ($semester_data as $subject) {
        $variance += pow($subject['total_mark'] - $stats['average'], 2);
    }
    $variance = $variance / count($semester_data);
    $std_dev = sqrt($variance);
    
    if ($std_dev > 15) {
        $suggestions[] = [
            'icon' => '⚖️',
            'type' => 'warning',
            'title' => 'Inconsistent Performance',
            'message' => 'Your marks vary significantly across subjects. Try to maintain consistency by allocating study time evenly.'
        ];
    }
    
    return $suggestions;
}

$current_semester = $class_info['currentSem'] ?? 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Analysis - Alpha-Class</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 2em;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .semester-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .semester-tab {
            background: white;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .semester-tab:hover {
            transform: translateY(-2px);
        }

        .semester-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-card h3 {
            color: #667eea;
            font-size: 0.9em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: 700;
            color: #333;
        }

        .stat-card.average {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-card.average h3,
        .stat-card.average .stat-value {
            color: white;
        }

        .charts-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            width: 100%;
        }

        .chart-card h3 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.3em;
        }
        
        .chart-card canvas {
            max-height: 400px;
        }

        .marks-table-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .marks-table {
            width: 100%;
            border-collapse: collapse;
        }

        .marks-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .marks-table th,
        .marks-table td {
            padding: 15px;
            text-align: left;
        }

        .marks-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }

        .marks-table tbody tr:hover {
            background: #f8f9fa;
        }

        .grade-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
        }

        .grade-S { background: #d4edda; color: #155724; }
        .grade-A-plus { background: #cce5ff; color: #004085; }
        .grade-A { background: #d1ecf1; color: #0c5460; }
        .grade-B-plus { background: #fff3cd; color: #856404; }
        .grade-B { background: #f8d7da; color: #721c24; }
        .grade-C { background: #e2e3e5; color: #383d41; }
        .grade-D { background: #f5c6cb; color: #721c24; }
        .grade-F { background: #f8d7da; color: #721c24; font-weight: 700; }

        .suggestions-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .suggestion-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-left: 5px solid;
            transition: all 0.3s;
        }

        .suggestion-card:hover {
            transform: translateX(5px);
        }

        .suggestion-card.success { border-color: #28a745; }
        .suggestion-card.good { border-color: #17a2b8; }
        .suggestion-card.warning { border-color: #ffc107; }
        .suggestion-card.danger { border-color: #dc3545; }
        .suggestion-card.info { border-color: #667eea; }

        .suggestion-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .suggestion-icon {
            font-size: 2.5em;
        }

        .suggestion-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
        }

        .suggestion-message {
            color: #666;
            line-height: 1.6;
        }

        .no-data {
            background: white;
            padding: 60px;
            border-radius: 15px;
            text-align: center;
            color: #666;
            font-size: 1.2em;
        }

        @media (max-width: 768px) {
            .charts-container,
            .suggestions-container {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 15px;
            }

            .stat-value {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏅 Grade Analysis & Performance Report</h1>
            <a href="../dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>

        <?php if (!empty($marks_data)): ?>
            <div class="semester-tabs">
                <?php foreach ($marks_data as $semester => $subjects): ?>
                    <div class="semester-tab <?php echo $semester == $current_semester ? 'active' : ''; ?>" 
                         onclick="showSemester(<?php echo $semester; ?>)">
                        Semester <?php echo $semester; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php foreach ($marks_data as $semester => $subjects): 
                $stats = $semester_stats[$semester];
                $suggestions = generateSuggestions($subjects, $stats);
            ?>
                <div class="content-section <?php echo $semester == $current_semester ? 'active' : ''; ?>" 
                     id="semester-<?php echo $semester; ?>">
                    
                    <!-- Statistics Cards -->
                    <div class="stats-grid">
                        <div class="stat-card average">
                            <h3>Average Percentage</h3>
                            <div class="stat-value"><?php echo number_format($stats['average'], 1); ?>%</div>
                        </div>
                        <div class="stat-card">
                            <h3>Total Subjects</h3>
                            <div class="stat-value"><?php echo $stats['total_subjects']; ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Passed Subjects</h3>
                            <div class="stat-value" style="color: #28a745;"><?php echo $stats['passed']; ?></div>
                        </div>
                        <?php if ($stats['failed'] > 0): ?>
                        <div class="stat-card">
                            <h3>Failed Subjects</h3>
                            <div class="stat-value" style="color: #dc3545;"><?php echo $stats['failed']; ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="stat-card">
                            <h3>Highest Mark</h3>
                            <div class="stat-value" style="color: #28a745;"><?php echo number_format($stats['highest'], 1); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Lowest Mark</h3>
                            <div class="stat-value" style="color: #dc3545;"><?php echo number_format($stats['lowest'], 1); ?></div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="charts-container">
                        <div class="chart-card">
                            <h3>📊 Subject-wise Performance</h3>
                            <canvas id="barChart-<?php echo $semester; ?>"></canvas>
                        </div>
                        <div class="chart-card">
                            <h3>🎯 Grade Distribution</h3>
                            <canvas id="pieChart-<?php echo $semester; ?>"></canvas>
                        </div>
                        <div class="chart-card">
                            <h3>📈 Internal vs External Marks</h3>
                            <canvas id="radarChart-<?php echo $semester; ?>"></canvas>
                        </div>
                        <div class="chart-card">
                            <h3>📉 Performance Trend</h3>
                            <canvas id="lineChart-<?php echo $semester; ?>"></canvas>
                        </div>
                    </div>

                    <!-- Suggestions -->
                    <h2 style="color: white; margin-bottom: 20px; font-size: 1.8em;">💡 Personalized Suggestions</h2>
                    <div class="suggestions-container">
                        <?php foreach ($suggestions as $suggestion): ?>
                            <div class="suggestion-card <?php echo $suggestion['type']; ?>">
                                <div class="suggestion-header">
                                    <span class="suggestion-icon"><?php echo $suggestion['icon']; ?></span>
                                    <h3 class="suggestion-title"><?php echo $suggestion['title']; ?></h3>
                                </div>
                                <p class="suggestion-message"><?php echo $suggestion['message']; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Marks Table -->
                    <div class="marks-table-container">
                        <h3 style="color: #667eea; margin-bottom: 20px; font-size: 1.5em;">📋 Detailed Marks</h3>
                        <table class="marks-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Internal (20)</th>
                                    <th>External (80)</th>
                                    <th>Total (100)</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                                        <td><?php echo number_format($subject['internal_mark'], 1); ?></td>
                                        <td><?php echo number_format($subject['external_mark'], 1); ?></td>
                                        <td><strong><?php echo number_format($subject['total_mark'], 1); ?></strong></td>
                                        <td>
                                            <span class="grade-badge grade-<?php echo str_replace('+', '-plus', $subject['grade']); ?>">
                                                <?php echo $subject['grade']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($subject['total_mark'] >= 40): ?>
                                                <span style="color: #28a745; font-weight: 600;">✓ Pass</span>
                                            <?php else: ?>
                                                <span style="color: #dc3545; font-weight: 600;">✗ Fail</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="no-data">
                <h2>📊 No grade data available yet</h2>
                <p>Your marks will appear here once your teachers enter them.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Store chart data globally - ALL DATA COLLECTED FIRST
        const chartDataStore = {};

        <?php 
        // Build JavaScript object with all semester data
        foreach ($marks_data as $semester => $subjects): 
            $stats = $semester_stats[$semester];
            
            // Prepare data arrays
            $subject_names = array_column($subjects, 'subject_name');
            $total_marks = array_column($subjects, 'total_mark');
            $internal_marks = array_column($subjects, 'internal_mark');
            $external_marks = array_column($subjects, 'external_mark');
            $grades = $stats['grades'];
        ?>
        
        chartDataStore[<?php echo $semester; ?>] = {
            subjects: <?php echo json_encode($subject_names); ?>,
            totalMarks: <?php echo json_encode($total_marks); ?>,
            internalMarks: <?php echo json_encode($internal_marks); ?>,
            externalMarks: <?php echo json_encode($external_marks); ?>,
            grades: <?php echo json_encode($grades); ?>,
            chartsCreated: false
        };
        
        <?php endforeach; ?>

        // Function to create charts for a specific semester
        function createChartsForSemester(semester) {
            const data = chartDataStore[semester];
            
            if (!data || data.chartsCreated) {
                return;
            }
            
            const barCanvas = document.getElementById('barChart-' + semester);
            const pieCanvas = document.getElementById('pieChart-' + semester);
            const radarCanvas = document.getElementById('radarChart-' + semester);
            const lineCanvas = document.getElementById('lineChart-' + semester);
            
            if (!barCanvas || !pieCanvas || !radarCanvas || !lineCanvas) {
                return;
            }
            
            // Bar Chart - Subject-wise Performance
            new Chart(barCanvas, {
                type: 'bar',
                data: {
                    labels: data.subjects,
                    datasets: [{
                        label: 'Total Marks',
                        data: data.totalMarks,
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Pie Chart - Grade Distribution
            const gradeCount = data.grades.reduce(function(acc, grade) {
                acc[grade] = (acc[grade] || 0) + 1;
                return acc;
            }, {});

            new Chart(pieCanvas, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(gradeCount),
                    datasets: [{
                        data: Object.values(gradeCount),
                        backgroundColor: [
                            '#28a745', '#17a2b8', '#ffc107', 
                            '#fd7e14', '#dc3545', '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Radar Chart - Internal vs External
            new Chart(radarCanvas, {
                type: 'radar',
                data: {
                    labels: data.subjects,
                    datasets: [
                        {
                            label: 'Internal Marks',
                            data: data.internalMarks,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'External Marks',
                            data: data.externalMarks,
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        r: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Line Chart - Performance Trend
            new Chart(lineCanvas, {
                type: 'line',
                data: {
                    labels: data.subjects,
                    datasets: [{
                        label: 'Marks Trend',
                        data: data.totalMarks,
                        borderColor: 'rgba(102, 126, 234, 1)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
            
            data.chartsCreated = true;
        }

        // Function to show semester and create charts
        function showSemester(semester) {
            document.querySelectorAll('.content-section').forEach(function(section) {
                section.classList.remove('active');
            });
            
            document.querySelectorAll('.semester-tab').forEach(function(tab) {
                tab.classList.remove('active');
            });
            
            document.getElementById('semester-' + semester).classList.add('active');
            event.target.classList.add('active');
            
            setTimeout(function() {
                createChartsForSemester(semester);
            }, 100);
        }

        // Initialize charts for the default active semester on page load
        window.addEventListener('DOMContentLoaded', function() {
            const activeSemester = <?php echo $current_semester; ?>;
            setTimeout(function() {
                createChartsForSemester(activeSemester);
            }, 300);
        });
    </script>
</body>
</html>