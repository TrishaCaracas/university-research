<?php
require_once '../config/database.php';
check_login('admin');

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $report_id = intval($_POST['report_id']);
        $stmt = $conn->prepare("DELETE FROM report WHERE report_id = ?");
        $stmt->bind_param("i", $report_id);
        
        if ($stmt->execute()) {
            set_message("Report deleted successfully");
        } else {
            set_message("Error: " . $conn->error, "error");
        }
        $stmt->close();
        redirect('report_management.php');
    }
}

$reports = $conn->query("
    SELECT r.*, p.title as project_title, fa.agency_name 
    FROM report r
    JOIN projects p ON r.project_id = p.project_id
    LEFT JOIN funding_agency fa ON r.funding_agency_id = fa.agency_id
    ORDER BY r.submission_date DESC
");

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Report Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <div class="table-container">
                <h2>Project Reports</h2>
                
                <?php if ($reports->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project</th>
                                <th>Milestone</th>
                                <th>Financial Usage</th>
                                <th>Funding Agency</th>
                                <th>Submission Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($report = $reports->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $report['report_id']; ?></td>
                                    <td><?php echo htmlspecialchars($report['project_title']); ?></td>
                                    <td><?php echo htmlspecialchars($report['milestone']); ?></td>
                                    <td><?php echo format_currency($report['financial_usage']); ?></td>
                                    <td><?php echo htmlspecialchars($report['agency_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo format_date($report['submission_date']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this report?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                            <button type="submit" class="btn-delete">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Reports</h3>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>