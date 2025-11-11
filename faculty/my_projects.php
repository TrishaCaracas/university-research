<?php
require_once '../config/database.php';
check_login('faculty');

$conn = getDBConnection();
$faculty_id = $_SESSION['user_id'];

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$sql = "
    SELECT p.*, fp.role, fa.agency_name,
    (SELECT COUNT(*) FROM student_project sp WHERE sp.project_id = p.project_id) as student_count,
    (SELECT COUNT(*) FROM publication pub WHERE pub.project_id = p.project_id) as publication_count,
    (SELECT COUNT(*) FROM intellectual_property ip WHERE ip.project_id = p.project_id) as ip_count,
    (SELECT COALESCE(SUM(amount), 0) FROM grants g WHERE g.project_id = p.project_id) as total_grants
    FROM projects p
    JOIN faculty_project fp ON p.project_id = fp.project_id
    LEFT JOIN funding_agency fa ON p.funding_agency_id = fa.agency_id
    WHERE fp.faculty_id = ?
";

if ($filter === 'lead') {
    $sql .= " AND fp.role = 'Lead'";
} elseif ($filter === 'member') {
    $sql .= " AND fp.role = 'Member'";
} elseif ($filter === 'active') {
    $sql .= " AND p.status = 'Active'";
} elseif ($filter === 'completed') {
    $sql .= " AND p.status = 'Completed'";
}

$sql .= " ORDER BY p.start_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$projects = $stmt->get_result();
$stmt->close();

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Projects</title>
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
        .project-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .project-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .project-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }
        .project-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            font-size: 14px;
            color: #64748b;
        }
        .project-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .project-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }
        .stat-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>My Projects</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <!-- Filter Buttons -->
            <div class="table-container" style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px;">Filter Projects:</h3>
                <div class="filter-buttons">
                    <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Projects</a>
                    <a href="?filter=lead" class="filter-btn <?php echo $filter === 'lead' ? 'active' : ''; ?>">Lead</a>
                    <a href="?filter=member" class="filter-btn <?php echo $filter === 'member' ? 'active' : ''; ?>">Member</a>
                    <a href="?filter=active" class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">Active</a>
                    <a href="?filter=completed" class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
                </div>
            </div>
            
            <!-- Projects List -->
            <?php if ($projects->num_rows > 0): ?>
                <?php while ($project = $projects->fetch_assoc()): ?>
                    <div class="project-card">
                        <div class="project-header">
                            <div style="flex: 1;">
                                <div class="project-title"><?php echo htmlspecialchars($project['title']); ?></div>
                                <div class="project-meta">
                                    <div class="project-meta-item">
                                        <strong>Role:</strong>
                                        <span class="status status-<?php echo strtolower($project['role']); ?>">
                                            <?php echo $project['role']; ?>
                                        </span>
                                    </div>
                                    <div class="project-meta-item">
                                        <strong>Status:</strong>
                                        <span class="status status-<?php echo strtolower($project['status']); ?>">
                                            <?php echo $project['status']; ?>
                                        </span>
                                    </div>
                                    <div class="project-meta-item">
                                        <strong>Agency:</strong>
                                        <?php echo htmlspecialchars($project['agency_name'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <div class="project-meta">
                                    <div class="project-meta-item">
                                        <strong>Start Date:</strong> <?php echo format_date($project['start_date']); ?>
                                    </div>
                                    <?php if ($project['end_date']): ?>
                                        <div class="project-meta-item">
                                            <strong>End Date:</strong> <?php echo format_date($project['end_date']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="project-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $project['student_count']; ?></div>
                                <div class="stat-label">Students</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $project['publication_count']; ?></div>
                                <div class="stat-label">Publications</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $project['ip_count']; ?></div>
                                <div class="stat-label">IP</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo format_currency($project['total_grants']); ?></div>
                                <div class="stat-label">Total Grants</div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="table-container">
                    <div class="empty-state">
                        <h3>No Projects Found</h3>
                        <p>
                            <?php if ($filter !== 'all'): ?>
                                No projects match this filter. <a href="my_projects.php">View all projects</a>
                            <?php else: ?>
                                You haven't been assigned to any projects yet.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>