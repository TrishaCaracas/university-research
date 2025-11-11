<?php
require_once '../config/database.php';
check_login('faculty');

$conn = getDBConnection();
$faculty_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $project_id = intval($_POST['project_id']);
    $type = escape_string($conn, $_POST['type']);
    $reg_number = escape_string($conn, sanitize_input($_POST['registration_number']));
    $date = escape_string($conn, $_POST['date']);
    
    // Verify faculty is assigned to this project
    $check_stmt = $conn->prepare("SELECT * FROM faculty_project WHERE faculty_id = ? AND project_id = ?");
    $check_stmt->bind_param("ii", $faculty_id, $project_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $sql = "INSERT INTO intellectual_property (project_id, type, registration_number, date) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $project_id, $type, $reg_number, $date);
        
        if ($stmt->execute()) {
            set_message("Intellectual Property added successfully");
        } else {
            set_message("Error adding IP: " . $conn->error, "error");
        }
        $stmt->close();
    } else {
        set_message("You are not assigned to this project", "error");
    }
    $check_stmt->close();
    redirect('add_ip.php');
}

// Delete IP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $ip_id = intval($_POST['ip_id']);
    
    // Verify faculty owns this IP (through project assignment)
    $check_stmt = $conn->prepare("
        SELECT ip.* FROM intellectual_property ip
        JOIN faculty_project fp ON ip.project_id = fp.project_id
        WHERE ip.ip_id = ? AND fp.faculty_id = ?
    ");
    $check_stmt->bind_param("ii", $ip_id, $faculty_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM intellectual_property WHERE ip_id = ?");
        $delete_stmt->bind_param("i", $ip_id);
        
        if ($delete_stmt->execute()) {
            set_message("Intellectual Property deleted successfully");
        } else {
            set_message("Error deleting IP: " . $conn->error, "error");
        }
        $delete_stmt->close();
    } else {
        set_message("You don't have permission to delete this IP", "error");
    }
    $check_stmt->close();
    redirect('add_ip.php');
}

// Get faculty's projects
$projects_stmt = $conn->prepare("
    SELECT p.project_id, p.title, p.status, fp.role
    FROM projects p
    JOIN faculty_project fp ON p.project_id = fp.project_id
    WHERE fp.faculty_id = ?
    ORDER BY p.title
");
$projects_stmt->bind_param("i", $faculty_id);
$projects_stmt->execute();
$my_projects = $projects_stmt->get_result();
$projects_stmt->close();

// Get faculty's IPs
$ips_stmt = $conn->prepare("
    SELECT ip.*, p.title as project_title
    FROM intellectual_property ip
    JOIN projects p ON ip.project_id = p.project_id
    JOIN faculty_project fp ON p.project_id = fp.project_id
    WHERE fp.faculty_id = ?
    ORDER BY ip.date DESC
");
$ips_stmt->bind_param("i", $faculty_id);
$ips_stmt->execute();
$my_ips = $ips_stmt->get_result();
$ips_stmt->close();

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Intellectual Property</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Add Intellectual Property</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <!-- Add IP Form -->
            <div class="table-container" style="margin-bottom: 30px;">
                <h2>Register New Intellectual Property</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label>Select Project:</label>
                        <select name="project_id" required>
                            <option value="">Choose a project</option>
                            <?php 
                            $my_projects->data_seek(0);
                            while ($project = $my_projects->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $project['project_id']; ?>">
                                    <?php echo htmlspecialchars($project['title']); ?> 
                                    (<?php echo $project['role']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small style="color: #64748b;">You can only add IP to projects you're assigned to.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>IP Type:</label>
                        <select name="type" required>
                            <option value="">Select Type</option>
                            <option value="Patent">Patent</option>
                            <option value="License">License</option>
                            <option value="Copyright">Copyright</option>
                            <option value="Trademark">Trademark</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Registration Number:</label>
                        <input type="text" name="registration_number" placeholder="e.g., PAT-2024-001 (Optional)">
                        <small style="color: #64748b;">Enter the official registration number if available.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Registration Date:</label>
                        <input type="date" name="date" required>
                    </div>
                    
                    <button type="submit" class="btn btn-small">Register IP</button>
                </form>
            </div>
            
            <!-- My IPs List -->
            <div class="table-container">
                <div class="table-header">
                    <h2>My Intellectual Property</h2>
                </div>
                
                <?php if ($my_ips->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project</th>
                                <th>Type</th>
                                <th>Registration Number</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ip = $my_ips->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $ip['ip_id']; ?></td>
                                    <td><?php echo htmlspecialchars($ip['project_title']); ?></td>
                                    <td>
                                        <span class="status status-<?php echo strtolower($ip['type']); ?>">
                                            <?php echo $ip['type']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ip['registration_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo format_date($ip['date']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this IP?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="ip_id" value="<?php echo $ip['ip_id']; ?>">
                                            <button type="submit" class="btn-delete">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Intellectual Property Yet</h3>
                        <p>Register your first IP to get started</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>