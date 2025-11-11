<?php
require_once '../config/database.php';
check_login('faculty');

$conn = getDBConnection();
$faculty_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit') {
    $project_id = intval($_POST['project_id']);
    $funding_agency_id = !empty($_POST['funding_agency_id']) ? intval($_POST['funding_agency_id']) : null;
    $milestone = escape_string($conn, sanitize_input($_POST['milestone']));
    $financial_usage = floatval($_POST['financial_usage']);
    $submission_date = escape_string($conn, $_POST['submission_date']);
    
    // Verify faculty is assigned to this project
    $check_stmt = $conn->prepare("SELECT role FROM faculty_project WHERE faculty_id = ? AND project_id = ?");
    $check_stmt->bind_param("ii", $faculty_id, $project_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        if ($funding_agency_id) {
            $sql = "INSERT INTO report (project_id, funding_agency_id, milestone, financial_usage, submission_date) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisds", $project_id, $funding_agency_id, $milestone, $financial_usage, $submission_date);
        } else {
            $sql = "INSERT INTO report (project_id, milestone, financial_usage, submission_date) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isds", $project_id, $milestone, $financial_usage, $submission_date);
        }
        
        if ($stmt->execute()) {
            set_message("Report submitted successfully");
        } else {
            set_message("Error submitting report: " . $conn->error, "error");
        }
        $stmt->close();
    } else {
        set_message("You are not assigned to this project", "error");
    }
    $check_stmt->close();
    redirect('submit_report.php');
}

// Delete report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $report_id = intval($_POST['report_id']);
    
    // Verify faculty owns this report (through project assignment)
    $check_stmt = $conn->prepare("
        SELECT r.* FROM report r
        JOIN faculty_project fp ON r.project_id = fp.project_id
        WHERE r.report_id = ? AND fp.faculty_id = ?
    ");
    $check_stmt->bind_param("ii", $report_id, $faculty_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM report WHERE report_id = ?");
        $delete_stmt->bind_param("i", $report_id);
        
        if ($delete_stmt->execute()) {
            set_message("Report deleted successfully");
        } else {
            set_message("Error deleting report: " . $conn->error, "error");
        }
        $delete_stmt->close();
    } else {
        set_message("You don't have permission to delete this report", "error");
    }
    $check_stmt->close();
    redirect('submit_report.php');
}

// Get faculty's projects (preferably lead projects)
$projects_stmt = $conn->prepare("
    SELECT p.project_id, p.title, p.status, fp.role, fa.agency_name
    FROM projects p
    JOIN faculty_project fp ON p.project_id = fp.project_id
    LEFT JOIN funding_agency fa ON p.funding_agency_id = fa.agency_id
    WHERE fp.faculty_id = ?
    ORDER BY fp.role DESC, p.title
");
$projects_stmt->bind_param("i", $faculty_id);
$projects_stmt->execute();
$my_projects = $projects_stmt->get_result();
$projects_stmt->close();

// Get all funding agencies
$agencies = $conn->query("SELECT agency_id, agency_name FROM funding_agency ORDER BY agency_name");

// Get faculty's reports
$reports_stmt = $conn->prepare("
    SELECT r.*, p.title as project_title, fa.agency_name
    FROM report r
    JOIN projects p ON r.project_id = p.project_id
    JOIN faculty_project fp ON p.project_id = fp.project_id
    LEFT JOIN funding_agency fa ON r.funding_agency_id = fa.agency_id
    WHERE fp.faculty_id = ?
    ORDER BY r.submission_date DESC
");
$reports_stmt->bind_param("i", $faculty_id);
$reports_stmt->execute();
$my_reports = $reports_stmt->get_result();
$reports_stmt->close();

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Report</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Submit Project Report</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <!-- Submit Report Form -->
            <div class="table-container" style="margin-bottom: 30px;">
                <h2>Submit New Report</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="submit">
                    
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
                                    <?php if ($project['agency_name']): ?>
                                        - <?php echo htmlspecialchars($project['agency_name']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small style="color: #718096;">Select the project you're reporting on.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Funding Agency (Optional):</label>
                        <select name="funding_agency_id">
                            <option value="">Select Agency (Optional)</option>
                            <?php while ($agency = $agencies->fetch_assoc()): ?>
                                <option value="<?php echo $agency['agency_id']; ?>">
                                    <?php echo htmlspecialchars($agency['agency_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small style="color: #718096;">Select the agency you're reporting to (if applicable).</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Milestone/Achievement:</label>
                        <textarea name="milestone" rows="4" required placeholder="Describe the milestone or achievement being reported"></textarea>
                        <small style="color: #718096;">Examples: "Completed Phase 1 Testing", "Published Initial Findings", "Achieved 50% Project Completion"</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Financial Usage:</label>
                        <input type="number" step="0.01" name="financial_usage" required placeholder="0.00">
                        <small style="color: #718096;">Total amount spent for this reporting period.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Submission Date:</label>
                        <input type="date" name="submission_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-small">Submit Report</button>
                </form>
            </div>
            
            <!-- My Reports List -->
            <div class="table-container">
                <div class="table-header">
                    <h2>My Submitted Reports</h2>
                </div>
                
                <?php if ($my_reports->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project</th>
                                <th>Milestone</th>
                                <th>Financial Usage</th>
                                <th>Agency</th>
                                <th>Submission Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($report = $my_reports->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $report['report_id']; ?></td>
                                    <td><?php echo htmlspecialchars($report['project_title']); ?></td>
                                    <td style="max-width: 250px;">
                                        <?php 
                                        $milestone = htmlspecialchars($report['milestone']);
                                        echo strlen($milestone) > 100 ? substr($milestone, 0, 100) . '...' : $milestone;
                                        ?>
                                    </td>
                                    <td><?php echo format_currency($report['financial_usage']); ?></td>
                                    <td><?php echo htmlspecialchars($report['agency_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo format_date($report['submission_date']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this report?');">
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
                        <h3>No Reports Submitted Yet</h3>
                        <p>Submit your first report to get started</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>