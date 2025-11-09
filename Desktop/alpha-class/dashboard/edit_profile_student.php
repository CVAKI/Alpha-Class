<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.html");
    exit();
}

// Include database configuration
require_once('../config.php');

$update_success = false;
$error_message = "";

// Handle form submission
if ($_POST) {
    $user_email = $_SESSION['email']; // Using email from session to identify user
    
    // Handle profile photo upload
    $profile_picture_path = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_photo']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = "../asset/img/profiles/";
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Delete old profile picture if it exists and is not the default
            $old_pic_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE email = ?");
            if ($old_pic_stmt) {
                $old_pic_stmt->bind_param("s", $user_email);
                $old_pic_stmt->execute();
                $old_pic_result = $old_pic_stmt->get_result();
                $old_pic_data = $old_pic_result->fetch_assoc();
                
                if ($old_pic_data && !empty($old_pic_data['profile_picture'])) {
                    $old_pic_path = $old_pic_data['profile_picture'];
                    if (file_exists($old_pic_path) && $old_pic_path != "../asset/img/dashboard/default-user.png") {
                        unlink($old_pic_path); // Delete old file
                    }
                }
                $old_pic_stmt->close();
            }
            
            $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $new_filename = "profile_" . preg_replace('/[^a-zA-Z0-9]/', '_', $user_email) . "_" . time() . "." . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                $profile_picture_path = $upload_path;
            } else {
                $error_message = "Failed to upload profile picture.";
            }
        } else {
            $error_message = "Invalid file type. Please upload JPEG, PNG, or GIF files only.";
        }
    }
    
    // Prepare update query using mysqli
    $update_fields = [];
    $update_values = [];
    $param_types = "";
    
    if (isset($_POST['name']) && !empty($_POST['name'])) {
        $update_fields[] = "name = ?";
        $update_values[] = $_POST['name'];
        $param_types .= "s";
    }
    
    if (isset($_POST['phone']) && !empty($_POST['phone'])) {
        $update_fields[] = "phone = ?";
        $update_values[] = $_POST['phone'];
        $param_types .= "s";
    }
    
    if ($profile_picture_path) {
        $update_fields[] = "profile_picture = ?";
        $update_values[] = $profile_picture_path;
        $param_types .= "s";
    }
    
    if (!empty($update_fields) && empty($error_message)) {
        $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE email = ?";
        $update_values[] = $user_email;
        $param_types .= "s";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($param_types, ...$update_values);
            if ($stmt->execute()) {
                $update_success = true;
                
                // Update session variables
                if (isset($_POST['name']) && !empty($_POST['name'])) {
                    $_SESSION['name'] = $_POST['name'];
                }
                if (isset($_POST['phone']) && !empty($_POST['phone'])) {
                    $_SESSION['phone'] = $_POST['phone'];
                }
                if ($profile_picture_path) {
                    $_SESSION['profile_picture'] = $profile_picture_path;
                }
            } else {
                $error_message = "Update failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Prepare failed: " . $conn->error;
        }
    } elseif (empty($update_fields) && empty($error_message)) {
        $error_message = "No changes detected.";
    }
}

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
if ($stmt) {
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    
    if ($user_data) {
        // Update session with latest data from database
        $_SESSION['name'] = $user_data['name'];
        $_SESSION['phone'] = $user_data['phone'];
        $_SESSION['profile_picture'] = $user_data['profile_picture'];
        $_SESSION['role'] = $user_data['role'];
    }
    $stmt->close();
} else {
    $error_message = "Error fetching user data: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Alpha-Class</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/style_sheet/editprofile.css">
    
</head>
<body>
    <div class="edit-container">
        <div class="header">
            <h1>Edit Profile</h1>
            <p>Update your personal information</p>
        </div>

        <?php if ($update_success): ?>
        <div class="success-message">
            ✅ Profile updated successfully!
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="error-message">
            ❌ <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="editForm" enctype="multipart/form-data">
            <div class="profile-pic-section">
                <div class="profile-pic-container">
                    <?php 
                    $profile_pic_src = "../asset/img/dashboard/default-user.png";
                    if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture']) && file_exists($_SESSION['profile_picture'])) {
                        $profile_pic_src = $_SESSION['profile_picture'];
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($profile_pic_src); ?>" alt="Profile Picture" class="profile-pic" id="profilePic">
                    <button type="button" class="pic-upload-btn" onclick="document.getElementById('fileInput').click()">
                        <svg viewBox="0 0 20 20">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                        </svg>
                    </button>
                    <input type="file" id="fileInput" name="profile_photo" accept="image/*" style="display: none;" onchange="previewImage(this)">
                </div>
                <p class="info-text">Click the edit icon to change your profile picture</p>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-input" 
                           value="<?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : ''; ?>" 
                           placeholder="Enter your full name" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-input" 
                           value="<?php echo isset($_SESSION['phone']) ? htmlspecialchars($_SESSION['phone']) : ''; ?>" 
                           placeholder="Enter your phone number">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input readonly-field" 
                           value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>" 
                           readonly>
                    <p class="info-text">Email cannot be changed</p>
                </div>

                <!-- Student ID field removed: not needed for editing -->
            </div>

            <div class="button-group">
                <a href="studentMain.php" class="btn btn-secondary">
                    <svg class="btn-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"></path>
                    </svg>
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <svg class="btn-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    Save Changes
                </button>
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
                    document.getElementById('profilePic').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Form submission with loading state
        document.getElementById('editForm').addEventListener('submit', function() {
            const container = document.querySelector('.edit-container');
            const saveBtn = document.getElementById('saveBtn');
            
            container.classList.add('loading');
            saveBtn.innerHTML = `
                <svg class="btn-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"></path>
                </svg>
                Saving...
            `;
        });

        // Phone number formatting (optional)
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.substring(0, 10);
                e.target.value = value.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
            }
        });
    </script>
</body>
</html>