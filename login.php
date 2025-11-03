<?php
require_once 'config/database.php';
start_session_secure();

// Redirect if already logged in
if (is_logged_in()) {
    $user_type = $_SESSION['user_type'];
    if ($user_type === 'admin') {
        redirect('admin/dashboard.php');
    } elseif ($user_type === 'faculty') {
        redirect('faculty/dashboard.php');
    } elseif ($user_type === 'student') {
        redirect('student/dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $user_type = sanitize_input($_POST['user_type']);
    
    $conn = getDBConnection();
    
    if ($user_type === 'admin') {
        $sql = "SELECT admin_id as id, username as name, password FROM admin WHERE email = ?";
    } elseif ($user_type === 'faculty') {
        $sql = "SELECT faculty_id as id, name, password FROM faculty WHERE email = ?";
    } elseif ($user_type === 'student') {
        $sql = "SELECT student_id as id, name, password FROM student WHERE email = ?";
    } else {
        $error = "Invalid user type selected";
    }
    
    if (!$error) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (verify_password($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_type'] = $user_type;
                
                if ($user_type === 'admin') {
                    redirect('admin/dashboard.php');
                } elseif ($user_type === 'faculty') {
                    redirect('faculty/dashboard.php');
                } elseif ($user_type === 'student') {
                    redirect('student/dashboard.php');
                }
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
        
        $stmt->close();
    }
    
    closeDBConnection($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - University Research Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>University Research Management</h2>
            
            <?php if ($error): ?>
                <div class="message error-msg"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_type">Login As:</label>
                    <select name="user_type" id="user_type" required>
                        <option value="">Select User Type</option>
                        <option value="admin">Administrator</option>
                        <option value="faculty">Faculty</option>
                        <option value="student">Student</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div style="margin-top: 20px; text-align: center; color: #666; font-size: 12px;">
                <p><strong>Test Accounts:</strong></p>
                <p>Admin: admin@university.edu / admin123</p>
                <p>Faculty: john.smith@university.edu / password123</p>
                <p>Student: alice.johnson@student.edu / password123</p>
            </div>
        </div>
    </div>
</body>
</html>