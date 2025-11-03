<?php
require_once '../config/database.php';
check_login('faculty');

$conn = getDBConnection();
$faculty_id = $_SESSION['user_id'];

// Get statistics
$stats = [];

// My Projects Count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM faculty_project 
    WHERE faculty_id = ?
");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$stats['my_projects'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Projects I Lead
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM faculty_project 
    WHERE faculty_id = ? AND role = 'Lead'
");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$stats['lead_projects'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// My Publications
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM publication pub
    JOIN faculty_project fp ON pub.project_id = fp.project_id
    WHERE fp.faculty_id = ?
");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$stats['my_publications'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// My IPs
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM intellectual_property ip
    JOIN faculty_project fp ON ip.project_id = fp.project_id
    WHERE fp.faculty_id = ?
");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$stats['my_ips'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// My Active Projects
$stmt = $conn->prepare("
    SELECT p.*, fp.role, fa.agency_name
    FROM projects p
    JOIN faculty_project fp ON p.project_id = fp.project_id
    LEFT JOIN funding_agency fa ON p.funding_agency_id = fa.agency_id
    WHERE fp.faculty_id = ?
    ORDER BY p.start_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$my_projects = $stmt->get_result();
$stmt->close();

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Faculty Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <div class="cards">
                <div class="card">
                    <h3>My Projects</h3>
                    <div class="number"><?php echo $stats['my_projects']; ?></div>
                </div>
                
                <div class="card">
                    <h3>Projects I Lead</h3>
                    <div class="number"><?php echo $stats['lead_projects']; ?></div>
                </div>
                
                <div class="card">
                    <h3>My Publications</h3>
                    <div class="number"><?php echo $stats['my_publications']; ?></div>
                </div>
                
                <div class="card">
                    <h3>My Intellectual Property</h3>
                    <div class="number"><?php echo $stats['my_ips']; ?></div>
                </div>
            </div>
            
            <div class="table-container">
                <div class="table-header">
                    <h2>My Projects</h2>
                    <a href="my_projects.php" class="btn btn-small">View All</a>
                </div>
                
                <?php if ($my_projects->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Role</th>
                                <th>Funding Agency</th>
                                <th>Start Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($project = $my_projects->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['title']); ?></td>
                                    <td><span class="status status-<?php echo strtolower($project['role']); ?>"><?php echo $project['role']; ?></span></td>
                                    <td><?php echo htmlspecialchars($project['agency_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo format_date($project['start_date']); ?></td>
                                    <td><span class="status status-<?php echo strtolower($project['status']); ?>"><?php echo $project['status']; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Projects Assigned</h3>
                        <p>Contact admin to be assigned to a project</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>