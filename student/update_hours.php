<?php
require_once '../config/database.php';
check_login('student');

$conn = getDBConnection();
$student_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $project_id = intval($_POST['project_id']);
    $hours_worked = floatval($_POST['hours_worked']);
    
    $stmt = $conn->prepare("
        UPDATE student_project 
        SET hours_worked = hours_worked + ? 
        WHERE student_id = ? AND project_id = ?
    ");
    $stmt->bind_param("dii", $hours_worked, $student_id, $project_id);
    
    if ($stmt->execute()) {
        set_message("Hours updated successfully");
    } else {
        set_message("Error updating hours: " . $conn->error, "error");
    }
    $stmt->close();
    redirect('update_hours.php');
}

// Get student's project assignments
$assignments = $conn->prepare("
    SELECT sp.*, p.title, p.status
    FROM student_project sp
    JOIN projects p ON sp.project_id = p.project_id
    WHERE sp.student_id = ?
    ORDER BY p.title
");
$assignments->bind_param("i", $student_id);
$assignments->execute();
$assignments = $assignments->get_result();

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Work Hours</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Update Work Hours</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <div class="table-container">
                <h2>My Project Assignments</h2>
                
                <?php if ($assignments->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Current Hours</th>
                                <th>Compensation</th>
                                <th>Status</th>
                                <th>Add Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($assignment = $assignments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                    <td><?php echo number_format($assignment['hours_worked'], 2); ?> hrs</td>
                                    <td><?php echo format_currency($assignment['compensation']); ?></td>
                                    <td><span class="status status-<?php echo strtolower($assignment['status']); ?>"><?php echo $assignment['status']; ?></span></td>
                                    <td>
                                        <form method="POST" action="" style="display:flex; gap:10px; align-items:center;">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="project_id" value="<?php echo $assignment['project_id']; ?>">
                                            <input type="number" step="0.01" name="hours_worked" placeholder="Hours" style="width:100px; padding:5px;" required>
                                            <button type="submit" class="btn-view" style="padding:5px 15px;">Add</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Project Assignments</h3>
                        <p>You haven't been assigned to any projects yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>