<?php
require_once '../config/database.php';
check_login('admin');

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $project_id = intval($_POST['project_id']);
            $type = escape_string($conn, $_POST['type']);
            $reg_number = escape_string($conn, sanitize_input($_POST['registration_number']));
            $date = escape_string($conn, $_POST['date']);
            
            $sql = "INSERT INTO intellectual_property (project_id, type, registration_number, date) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $project_id, $type, $reg_number, $date);
            
            if ($stmt->execute()) {
                set_message("Intellectual Property added successfully");
            } else {
                set_message("Error: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('ip_management.php');
        }
        elseif ($_POST['action'] === 'delete') {
            $ip_id = intval($_POST['ip_id']);
            $stmt = $conn->prepare("DELETE FROM intellectual_property WHERE ip_id = ?");
            $stmt->bind_param("i", $ip_id);
            
            if ($stmt->execute()) {
                set_message("IP deleted successfully");
            } else {
                set_message("Error: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('ip_management.php');
        }
    }
}

$ips = $conn->query("
    SELECT ip.*, p.title as project_title 
    FROM intellectual_property ip
    JOIN projects p ON ip.project_id = p.project_id
    ORDER BY ip.date DESC
");

$projects = $conn->query("SELECT project_id, title FROM projects ORDER BY title");

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IP Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Intellectual Property Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <div class="table-container" style="margin-bottom:30px;">
                <h2>Add Intellectual Property</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label>Project:</label>
                        <select name="project_id" required>
                            <option value="">Select Project</option>
                            <?php while ($p = $projects->fetch_assoc()): ?>
                                <option value="<?php echo $p['project_id']; ?>"><?php echo htmlspecialchars($p['title']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Type:</label>
                        <select name="type" required>
                            <option value="Patent">Patent</option>
                            <option value="License">License</option>
                            <option value="Copyright">Copyright</option>
                            <option value="Trademark">Trademark</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Registration Number:</label>
                        <input type="text" name="registration_number">
                    </div>
                    
                    <div class="form-group">
                        <label>Registration Date:</label>
                        <input type="date" name="date" required>
                    </div>
                    
                    <button type="submit" class="btn btn-small">Add IP</button>
                </form>
            </div>
            
            <div class="table-container">
                <h2>Intellectual Property List</h2>
                
                <?php if ($ips->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project</th>
                                <th>Type</th>
                                <th>Registration #</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ip = $ips->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $ip['ip_id']; ?></td>
                                    <td><?php echo htmlspecialchars($ip['project_title']); ?></td>
                                    <td><?php echo $ip['type']; ?></td>
                                    <td><?php echo htmlspecialchars($ip['registration_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo format_date($ip['date']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this IP?');">
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
                        <h3>No Intellectual Property</h3>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>