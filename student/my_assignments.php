<?php
require_once '../config/database.php';
check_login('student');

$conn = getDBConnection();
$student_id = $_SESSION['user_id'];

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$sql = "
    SELECT sp.*, p.title, p.status, p.start_date, p.end_date, fa.agency_name,
    (SELECT COUNT(*) FROM publication WHERE project_id = p.project_id) as publication_count,
    (SELECT COUNT(*) FROM intellectual_property WHERE project_id = p.project_id) as ip_count,
    (SELECT GROUP_CONCAT(f.name SEPARATOR ', ') FROM faculty_project fp 
     JOIN faculty f ON fp.faculty_id = f.faculty_id 
     WHERE fp.project_id = p.project_id AND fp.role = 'Lead') as project_leads
    FROM student_project sp
    JOIN projects p ON sp.project_id = p.project_id
    LEFT JOIN funding_agency fa ON p.funding_agency_id = fa.agency_id
    WHERE sp.student_id = ?
";

if ($filter === 'active') {
    $sql .= " AND p.status = 'Active'";
} elseif ($filter === 'completed') {
    $sql .= " AND p.status = 'Completed'";
} elseif ($filter === 'high_hours') {
    $sql .= " AND sp.hours_worked > 100";
}

$sql .= " ORDER BY sp.assigned_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$assignments = $stmt->get_result();
$stmt->close();

// Get summary statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_assignments,
        COALESCE(SUM(hours_worked), 0) as total_hours,
        COALESCE(SUM(compensation), 0) as total_compensation,
        COALESCE(AVG(hours_worked), 0) as avg_hours
    FROM student_project 
    WHERE student_id = ?
");
$stats_stmt->bind_param("i", $student_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #2563eb;
            background: white;
            color: #2563eb;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: #2563eb;
            color: white;
        }
        .assignment-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .assignment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            gap: 20px;
        }
        .assignment-title {
            font-size: 20px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 8px;
        }
        .assignment-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        .meta-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .meta-value {
            font-size: 16px;
            color: #2d3748;
            font-weight: 600;
        }
        .assignment-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 10px;
        }
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            color: #667eea;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .summary-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .summary-label {
            font-size: 14px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>My Project Assignments</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <!-- Summary Statistics -->
            <div class="stats-summary">
                <div class="summary-card">
                    <div class="summary-number"><?php echo $stats['total_assignments']; ?></div>
                    <div class="summary-label">Total Assignments</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?php echo number_format($stats['total_hours'], 2); ?></div>
                    <div class="summary-label">Total Hours Worked</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?php echo format_currency($stats['total_compensation']); ?></div>
                    <div class="summary-label">Total Compensation</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?php echo number_format($stats['avg_hours'], 2); ?></div>
                    <div class="summary-label">Average Hours per Project</div>
                </div>
            </div>
            
            <!-- Filter Buttons -->
            <div class="table-container" style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px;">Filter Assignments:</h3>
                <div class="filter-buttons">
                    <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Assignments</a>
                    <a href="?filter=active" class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">Active Projects</a>
                    <a href="?filter=completed" class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
                    <a href="?filter=high_hours" class="filter-btn <?php echo $filter === 'high_hours' ? 'active' : ''; ?>">High Hours (>100)</a>
                </div>
            </div>
            
            <!-- Assignments List -->
            <?php if ($assignments->num_rows > 0): ?>
                <?php while ($assignment = $assignments->fetch_assoc()): ?>
                    <div class="assignment-card">
                        <div class="assignment-header">
                            <div style="flex: 1;">
                                <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <span class="status status-<?php echo strtolower($assignment['status']); ?>">
                                        <?php echo $assignment['status']; ?>
                                    </span>
                                    <?php if ($assignment['agency_name']): ?>
                                        <span style="color: #718096; font-size: 14px;">
                                            Funded by: <?php echo htmlspecialchars($assignment['agency_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($assignment['project_leads']): ?>
                            <div style="margin-bottom: 15px; color: #4a5568;">
                                <strong>Project Lead(s):</strong> <?php echo htmlspecialchars($assignment['project_leads']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="assignment-meta">
                            <div class="meta-item">
                                <div class="meta-label">Hours Worked</div>
                                <div class="meta-value"><?php echo number_format($assignment['hours_worked'], 2); ?> hrs</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Compensation</div>
                                <div class="meta-value"><?php echo format_currency($assignment['compensation']); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Assigned Date</div>
                                <div class="meta-value"><?php echo format_date($assignment['assigned_date']); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Project Duration</div>
                                <div class="meta-value">
                                    <?php echo format_date($assignment['start_date']); ?>
                                    <?php if ($assignment['end_date']): ?>
                                        <br><small>to <?php echo format_date($assignment['end_date']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="assignment-footer">
                            <div style="display: flex; gap: 15px; align-items: center;">
                                <span style="color: #718096; font-size: 14px;">
                                    📚 <?php echo $assignment['publication_count']; ?> Publications
                                </span>
                                <span style="color: #718096; font-size: 14px;">
                                    💡 <?php echo $assignment['ip_count']; ?> IPs
                                </span>
                            </div>
                            <a href="update_hours.php" class="btn btn-small" style="width: auto;">Update Hours</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="table-container">
                    <div class="empty-state">
                        <h3>No Assignments Found</h3>
                        <p>
                            <?php if ($filter !== 'all'): ?>
                                No assignments match this filter. <a href="my_assignments.php">View all assignments</a>
                            <?php else: ?>
                                You haven't been assigned to any projects yet. Check back later!
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>