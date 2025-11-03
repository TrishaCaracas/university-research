<?php
require_once '../config/database.php';
check_login('admin');

$conn = getDBConnection();

// Get statistics
$stats = [];

// Total Faculty
$result = $conn->query("SELECT COUNT(*) as count FROM faculty");
$stats['faculty'] = $result->fetch_assoc()['count'];

// Total Students
$result = $conn->query("SELECT COUNT(*) as count FROM student");
$stats['students'] = $result->fetch_assoc()['count'];

// Total Projects
$result = $conn->query("SELECT COUNT(*) as count FROM projects");
$stats['projects'] = $result->fetch_assoc()['count'];

// Active Projects
$result = $conn->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Active'");
$stats['active_projects'] = $result->fetch_assoc()['count'];

// Total Grants
$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM grants");
$stats['total_grants'] = $result->fetch_assoc()['total'];

// Total Publications
$result = $conn->query("SELECT COUNT(*) as count FROM publication");
$stats['publications'] = $result->fetch_assoc()['count'];

// Total IPs
$result = $conn->query("SELECT COUNT(*) as count FROM intellectual_property");
$stats['ips'] = $result->fetch_assoc()['count'];

// Total Funding Agencies
$result = $conn->query("SELECT COUNT(*) as count FROM funding_agency");
$stats['agencies'] = $result->fetch_assoc()['count'];

// Recent Projects
$recent_projects = $conn->query("
    SELECT p.*, fa.agency_name 
    FROM projects p 
    LEFT JOIN funding_agency fa ON p.funding_agency_id = fa.agency_id 
    ORDER BY p.created_at DESC 
    LIMIT 5
");

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <div class="cards">
                <div class="card">
                    <h3>Total Faculty</h3>
                    <div class="number"><?php echo $stats['faculty']; ?></div>
                </div>
                
                <div class="card">
                    <h3>Total Students</h3>
                    <div class="number"><?php echo $stats['students']; ?></div>
                </div>
                
                <div class="card">
                    <h3>Total Projects</h3>
                    <div class="number"><?php echo $stats['projects']; ?></div>
                </div>
                
                <div class="card">
                    <h3>Active Projects</h3>
                    <div class="number"><?php echo $stats['active_projects']; ?></div>
                </div>
                
                <div class="card">
                    <h3>Total Grants</h3>
                    <div class="number"><?php echo format_currency($stats['total_grants']); ?></div>
                </div>
                
                <div class="card">
                    <h3>Publications</h3>
                    <div class="number"><?php echo $stats['publications']; ?></div>
                </div>
                
                <div class="card">
                    <h3>Intellectual Property</h3>
                    <div class="number"><?php echo $stats['ips']; ?></div>
                </div>
                
                <div class="card">
                    <h3>Funding Agencies</h3>
                    <div class="number"><?php echo $stats['agencies']; ?></div>
                </div>
            </div>
            
            <div class="table-container">
                <div class="table-header">
                    <h2>Recent Projects</h2>
                </div>
                
                <?php if ($recent_projects->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Funding Agency</th>
                                <th>Start Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($project = $recent_projects->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['title']); ?></td>
                                    <td><?php echo htmlspecialchars($project['agency_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo format_date($project['start_date']); ?></td>
                                    <td><span class="status status-<?php echo strtolower($project['status']); ?>"><?php echo $project['status']; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Projects Yet</h3>
                        <p>Create your first project to get started</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>