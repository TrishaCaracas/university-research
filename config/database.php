<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'university_research_db');

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Close connection
function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

// Security functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function escape_string($conn, $data) {
    return $conn->real_escape_string($data);
}

// Session management
function start_session_secure() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
        
        // Regenerate session ID to prevent session fixation
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }
}

function check_login($user_type) {
    start_session_secure();
    
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== $user_type) {
        header("Location: ../login.php");
        exit();
    }
}

function is_logged_in() {
    start_session_secure();
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function logout_user() {
    start_session_secure();
    
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    
    // Destroy the session
    session_destroy();
}

// Password hashing
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

// Display success/error messages
function set_message($message, $type = 'success') {
    start_session_secure();
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function display_message() {
    start_session_secure();
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'success';
        $class = $type === 'success' ? 'success-msg' : 'error-msg';
        echo "<div class='message {$class}'>{$_SESSION['message']}</div>";
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

// Format date
function format_date($date) {
    if (!$date || $date === '0000-00-00') return 'N/A';
    return date('F d, Y', strtotime($date));
}

// Format currency
function format_currency($amount) {
    return '₱' . number_format($amount, 2);
}
?>