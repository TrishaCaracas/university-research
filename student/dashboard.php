<?php
require_once '../config/database.php';
check_login('student');

$conn = getDBConnection();
$student_id = $_SESSION['user_id'];

// Get statistics
$stats = [];

// My Project Assignments Count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM student_project WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats['my_assignments'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Total Hours Worked
$stmt = $conn->prepare("SELECT COALESCE(SUM(hours_worked), 0) as total FROM student_project WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats['total_hours'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Total Compensation
$stmt = $conn->prepare("SELECT COALESCE(SUM(compensation), 0) as total FROM student_project WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats['total_compensation'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// My Project Assignments
$stmt = $conn->prepare("
    SELECT sp.*, p.title, p.status, p.start_date, p.end_date
    FROM student_project sp
    JOIN projects p ON sp.project_id = p.project_id
    WHERE sp.student_id = ?
    ORDER BY sp.assigned_date DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$my_assignments = $stmt->get_result();
$stmt->close();

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Student Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <div class="cards">
                <div class="card">
                    <h3>My Assignments</h3>
                    <div class="number"><?php echo $stats['my_assignments']; ?></div>
                </div>
                
                <div class="card">
                    <h3>Total Hours Worked</h3>
                    <div class="number"><?php echo number_format($stats['total_hours'], 2); ?></div>
                </div>
                
                <div class="card">
                    <h3>Total Compensation</h3>
                    <div class="number"><?php echo format_currency($stats['total_compensation']); ?></div>
                </div>
            </div>
            
            <div class="table-container">
                <div class="table-header">
                    <h2>My Project Assignments</h2>
                    <a href="my_assignments.php" class="btn btn-small">View All</a>
                </div>
                
                <?php if ($my_assignments->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Hours Worked</th>
                                <th>Compensation</th>
                                <th>Start Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($assignment = $my_assignments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                    <td><?php echo number_format($assignment['hours_worked'], 2); ?> hrs</td>
                                    <td><?php echo format_currency($assignment['compensation']); ?></td>
                                    <td><?php echo format_date($assignment['start_date']); ?></td>
                                    <td><span class="status status-<?php echo strtolower($assignment['status']); ?>"><?php echo $assignment['status']; ?></span></td>
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