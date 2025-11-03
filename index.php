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

redirect('login.php');
?>