<?php
// File: api.php - Debug Version
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json');

// Check if connection exists
if (!isset($conn)) {
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit;
}

// Auto-create tables if they don't exist
try {
    createTablesIfNotExist();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Table creation error: ' . $e->getMessage()]);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_references':
        getReferences();
        break;
    case 'get_reference':
        getReference();
        break;
    case 'create_reference':
        createReference();
        break;
    case 'update_reference':
        updateReference();
        break;
    case 'delete_reference':
        deleteReference();
        break;
    case 'get_subjects':
        getSubjects();
        break;
    case 'save_subjects':
        saveSubjects();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
}

function createTablesIfNotExist() {
    global $conn;
    
    // Create reference_code table
    $sql1 = "CREATE TABLE IF NOT EXISTS reference_code (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referencecode VARCHAR(50) UNIQUE NOT NULL,
        class VARCHAR(100) NOT NULL,
        department VARCHAR(100) NOT NULL,
        class_teacher VARCHAR(100) NOT NULL,
        total_strength INT NOT NULL,
        total_subjects_in_all_sem INT NOT NULL,
        currentSem INT NOT NULL,
        starting_year INT NOT NULL,
        ending_year INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($sql1)) {
        throw new Exception("Error creating reference_code table: " . $conn->error);
    }
    
    // Create class_subjects table
    $sql2 = "CREATE TABLE IF NOT EXISTS class_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referencecode VARCHAR(50) NOT NULL,
        semester INT NOT NULL,
        subject_name VARCHAR(200) NOT NULL,
        teaching_teacher VARCHAR(100) NOT NULL,
        FOREIGN KEY (referencecode) REFERENCES reference_code(referencecode) ON DELETE CASCADE,
        UNIQUE KEY unique_subject (referencecode, semester, subject_name)
    )";
    
    if (!$conn->query($sql2)) {
        throw new Exception("Error creating class_subjects table: " . $conn->error);
    }
}

function getReferences() {
    global $conn;
    
    try {
        $sql = "SELECT * FROM reference_code ORDER BY id DESC";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        
        $data = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getReference() {
    global $conn;
    $id = intval($_GET['id']);
    
    $sql = "SELECT * FROM reference_code WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Reference not found']);
    }
}

function createReference() {
    global $conn;
    
    $referencecode = $_POST['referencecode'];
    $class = $_POST['class'];
    $department = $_POST['department'];
    $class_teacher = $_POST['class_teacher'];
    $total_strength = intval($_POST['total_strength']);
    $total_subjects_in_all_sem = intval($_POST['total_subjects_in_all_sem']);
    $currentSem = intval($_POST['currentSem']);
    $starting_year = intval($_POST['starting_year']);
    $ending_year = intval($_POST['ending_year']);
    
    $sql = "INSERT INTO reference_code (referencecode, class, department, class_teacher, total_strength, total_subjects_in_all_sem, currentSem, starting_year, ending_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssiiiii", $referencecode, $class, $department, $class_teacher, $total_strength, $total_subjects_in_all_sem, $currentSem, $starting_year, $ending_year);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Reference code created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
    }
}

function updateReference() {
    global $conn;
    
    $id = intval($_POST['editId']);
    $class = $_POST['class'];
    $department = $_POST['department'];
    $class_teacher = $_POST['class_teacher'];
    $total_strength = intval($_POST['total_strength']);
    $total_subjects_in_all_sem = intval($_POST['total_subjects_in_all_sem']);
    $currentSem = intval($_POST['currentSem']);
    $starting_year = intval($_POST['starting_year']);
    $ending_year = intval($_POST['ending_year']);
    
    $sql = "UPDATE reference_code SET class=?, department=?, class_teacher=?, total_strength=?, total_subjects_in_all_sem=?, currentSem=?, starting_year=?, ending_year=? WHERE id=?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssiiiiii", $class, $department, $class_teacher, $total_strength, $total_subjects_in_all_sem, $currentSem, $starting_year, $ending_year, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Reference code updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
    }
}

function deleteReference() {
    global $conn;
    $id = intval($_POST['id']);
    
    $sql = "DELETE FROM reference_code WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Reference code deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
    }
}

function getSubjects() {
    global $conn;
    $referencecode = $_GET['referencecode'];
    
    $sql = "SELECT * FROM class_subjects WHERE referencecode = ? ORDER BY semester, id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $referencecode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function saveSubjects() {
    global $conn;
    $referencecode = $_POST['referencecode'];
    
    // Delete existing subjects for this reference code
    $sql = "DELETE FROM class_subjects WHERE referencecode = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $referencecode);
    $stmt->execute();
    
    // Insert new subjects
    $sql = "INSERT INTO class_subjects (referencecode, semester, subject_name, teaching_teacher) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    $success = true;
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'sem') === 0 && strpos($key, '_subject') !== false) {
            preg_match('/sem(\d+)_subject/', $key, $matches);
            $semester = intval($matches[1]);
            $teacherKey = "sem{$semester}_teacher";
            
            if (isset($_POST[$teacherKey])) {
                $subjects = $_POST[$key];
                $teachers = $_POST[$teacherKey];
                
                for ($i = 0; $i < count($subjects); $i++) {
                    if (!empty($subjects[$i]) && !empty($teachers[$i])) {
                        $stmt->bind_param("siss", $referencecode, $semester, $subjects[$i], $teachers[$i]);
                        if (!$stmt->execute()) {
                            $success = false;
                            break;
                        }
                    }
                }
            }
        }
    }
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Subjects saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error saving subjects: ' . $conn->error]);
    }
}
?>