<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: signin.html");
    exit();
}

require_once(__DIR__ . '/../config.php');

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Handle profile picture upload
    $profilePicPath = $_SESSION['profile_picture'] ?? null;
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/profiles/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            // Check file size (5MB limit)
            if ($_FILES['profile_picture']['size'] > 5 * 1024 * 1024) {
                $message = 'File size must be less than 5MB';
                $messageType = 'error';
            } else {
                $newFileName = 'teacher_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                    // Delete old profile picture if exists
                    if (!empty($profilePicPath) && file_exists($profilePicPath) && $profilePicPath != '../assets/img/dashboard/default_profile.png') {
                        unlink($profilePicPath);
                    }
                    $profilePicPath = $uploadPath;
                } else {
                    $message = 'Failed to upload profile picture';
                    $messageType = 'error';
                }
            }
        } else {
            $message = 'Invalid file type. Please upload JPEG, PNG, or GIF files only';
            $messageType = 'error';
        }
    }
    
    // Update profile information
    if (!empty($newPassword)) {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!password_verify($currentPassword, $user['password'])) {
            $message = 'Current password is incorrect';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $message = 'New password must be at least 6 characters';
            $messageType = 'error';
        } else {
            // Update with new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, profile_picture = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $phone, $profilePicPath, $hashedPassword, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $_SESSION['name'] = $name;
                $_SESSION['phone'] = $phone;
                $_SESSION['profile_picture'] = $profilePicPath;
                $message = 'Profile updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error updating profile: ' . $stmt->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    } else {
        // Update without password change
        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, profile_picture = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $phone, $profilePicPath, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $_SESSION['name'] = $name;
            $_SESSION['phone'] = $phone;
            $_SESSION['profile_picture'] = $profilePicPath;
            $message = 'Profile updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error updating profile: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

if (!$userData) {
    die("Error: User data not found");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher Profile</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="teacher/editprofile.css">
</head>
<body>
    <div class="edit-profile-container">
        <a href="teacherMain.php" class="back-btn">
            ← Back to Dashboard
        </a>

        <h1>Edit Teacher Profile</h1>

        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="profile-preview">
                <?php 
                $profilePic = "../assets/img/dashboard/default-profile.png";
                if (!empty($userData['profile_picture']) && file_exists($userData['profile_picture'])) {
                    $profilePic = $userData['profile_picture'];
                }
                ?>
                <img id="profilePreview" src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile Picture">
                <button type="button" class="pic-upload-btn" onclick="document.getElementById('profile_picture').click()">
                    <svg viewBox="0 0 20 20">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                    </svg>
                </button>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" style="display: none;" onchange="previewImage(this)">
                <p class="info-text">Click the edit icon to change your profile picture</p>
            </div>

            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($userData['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="readonly-field" value="<?php echo htmlspecialchars($userData['email']); ?>" disabled>
                <p class="info-text">Email cannot be changed</p>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
            </div>

            <div class="password-section">
                <h3>Change Password (Optional)</h3>
                
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password">
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password">
                    <p class="info-text">Minimum 6 characters</p>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password">
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="save-btn">Save Changes</button>
                <a href="teacherMain.php" class="cancel-btn">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                // Check file size (5MB limit)
                if (input.files[0].size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    input.value = '';
                    return;
                }
                
                // Check file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(input.files[0].type)) {
                    alert('Please select a valid image file (JPEG, PNG, or GIF)');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.substring(0, 10);
            }
            e.target.value = value;
        });
    </script>
</body>
</html>