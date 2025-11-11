<?php
require_once '../config/database.php';
check_login('admin');

$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $title = escape_string($conn, sanitize_input($_POST['title']));
            $status = escape_string($conn, $_POST['status']);
            $start_date = escape_string($conn, $_POST['start_date']);
            $end_date = !empty($_POST['end_date']) ? escape_string($conn, $_POST['end_date']) : null;
            $funding_agency_id = !empty($_POST['funding_agency_id']) ? intval($_POST['funding_agency_id']) : null;
            
            // Validate dates
            if ($end_date && strtotime($start_date) > strtotime($end_date)) {
                set_message("Start date must be before end date", "error");
                redirect('project_management.php');
            }
            
            // Insert project
            if ($end_date && $funding_agency_id) {
                $sql = "INSERT INTO projects (title, status, start_date, end_date, funding_agency_id) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $title, $status, $start_date, $end_date, $funding_agency_id);
            } elseif ($end_date) {
                $sql = "INSERT INTO projects (title, status, start_date, end_date) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $title, $status, $start_date, $end_date);
            } elseif ($funding_agency_id) {
                $sql = "INSERT INTO projects (title, status, start_date, funding_agency_id) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $title, $status, $start_date, $funding_agency_id);
            } else {
                $sql = "INSERT INTO projects (title, status, start_date) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $title, $status, $start_date);
            }
            
            if ($stmt->execute()) {
                $project_id = $stmt->insert_id;
                $stmt->close();
                
                // Assign faculty if provided
                if (isset($_POST['faculty_ids']) && is_array($_POST['faculty_ids'])) {
                    $has_lead = false;
                    $faculty_assigned = false;
                    foreach ($_POST['faculty_ids'] as $index => $faculty_id) {
                        $faculty_id = intval($faculty_id);
                        if ($faculty_id > 0) { // Only process if faculty is selected
                            $faculty_assigned = true;
                            $role = isset($_POST['faculty_roles'][$index]) ? escape_string($conn, $_POST['faculty_roles'][$index]) : 'Member';
                            
                            if ($role === 'Lead') {
                                $has_lead = true;
                            }
                            
                            $faculty_sql = "INSERT INTO faculty_project (faculty_id, project_id, role) VALUES (?, ?, ?)";
                            $faculty_stmt = $conn->prepare($faculty_sql);
                            $faculty_stmt->bind_param("iis", $faculty_id, $project_id, $role);
                            $faculty_stmt->execute();
                            $faculty_stmt->close();
                        }
                    }
                    
                    if ($faculty_assigned && !$has_lead) {
                        set_message("Project created but no Lead faculty assigned. Please assign a Lead faculty.", "error");
                    } elseif ($faculty_assigned) {
                        set_message("Project created successfully");
                    } else {
                        set_message("Project created but no faculty assigned. Please assign faculty to the project.", "error");
                    }
                } else {
                    set_message("Project created but no faculty assigned. Please assign faculty to the project.", "error");
                }
            } else {
                set_message("Error creating project: " . $conn->error, "error");
            }
            redirect('project_management.php');
        }
        elseif ($_POST['action'] === 'edit') {
            $project_id = intval($_POST['project_id']);
            $title = escape_string($conn, sanitize_input($_POST['title']));
            $status = escape_string($conn, $_POST['status']);
            $start_date = escape_string($conn, $_POST['start_date']);
            $end_date = !empty($_POST['end_date']) ? escape_string($conn, $_POST['end_date']) : null;
            $funding_agency_id = !empty($_POST['funding_agency_id']) ? intval($_POST['funding_agency_id']) : null;
            
            // Validate dates
            if ($end_date && strtotime($start_date) > strtotime($end_date)) {
                set_message("Start date must be before end date", "error");
                redirect('project_management.php?edit=' . $project_id);
            }
            
            // Update project
            if ($end_date && $funding_agency_id) {
                $sql = "UPDATE projects SET title=?, status=?, start_date=?, end_date=?, funding_agency_id=? WHERE project_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssii", $title, $status, $start_date, $end_date, $funding_agency_id, $project_id);
            } elseif ($end_date) {
                $sql = "UPDATE projects SET title=?, status=?, start_date=?, end_date=?, funding_agency_id=NULL WHERE project_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $title, $status, $start_date, $end_date, $project_id);
            } elseif ($funding_agency_id) {
                $sql = "UPDATE projects SET title=?, status=?, start_date=?, end_date=NULL, funding_agency_id=? WHERE project_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssii", $title, $status, $start_date, $funding_agency_id, $project_id);
            } else {
                $sql = "UPDATE projects SET title=?, status=?, start_date=?, end_date=NULL, funding_agency_id=NULL WHERE project_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $title, $status, $start_date, $project_id);
            }
            
            if ($stmt->execute()) {
                set_message("Project updated successfully");
            } else {
                set_message("Error updating project: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('project_management.php?edit=' . $project_id);
        }
        elseif ($_POST['action'] === 'delete') {
            $project_id = intval($_POST['project_id']);
            
            // Check for related records
            $check_grants = $conn->prepare("SELECT COUNT(*) as count FROM grants WHERE project_id = ?");
            $check_grants->bind_param("i", $project_id);
            $check_grants->execute();
            $grants_count = $check_grants->get_result()->fetch_assoc()['count'];
            $check_grants->close();
            
            $check_students = $conn->prepare("SELECT COUNT(*) as count FROM student_project WHERE project_id = ?");
            $check_students->bind_param("i", $project_id);
            $check_students->execute();
            $students_count = $check_students->get_result()->fetch_assoc()['count'];
            $check_students->close();
            
            $check_publications = $conn->prepare("SELECT COUNT(*) as count FROM publication WHERE project_id = ?");
            $check_publications->bind_param("i", $project_id);
            $check_publications->execute();
            $publications_count = $check_publications->get_result()->fetch_assoc()['count'];
            $check_publications->close();
            
            $check_ips = $conn->prepare("SELECT COUNT(*) as count FROM intellectual_property WHERE project_id = ?");
            $check_ips->bind_param("i", $project_id);
            $check_ips->execute();
            $ips_count = $check_ips->get_result()->fetch_assoc()['count'];
            $check_ips->close();
            
            $check_reports = $conn->prepare("SELECT COUNT(*) as count FROM report WHERE project_id = ?");
            $check_reports->bind_param("i", $project_id);
            $check_reports->execute();
            $reports_count = $check_reports->get_result()->fetch_assoc()['count'];
            $check_reports->close();
            
            if ($grants_count > 0 || $students_count > 0 || $publications_count > 0 || $ips_count > 0 || $reports_count > 0) {
                set_message("Cannot delete project. It has related records: $grants_count grants, $students_count students, $publications_count publications, $ips_count IPs, $reports_count reports. Please remove these records first.", "error");
            } else {
                // Delete faculty assignments first
                $delete_faculty = $conn->prepare("DELETE FROM faculty_project WHERE project_id = ?");
                $delete_faculty->bind_param("i", $project_id);
                $delete_faculty->execute();
                $delete_faculty->close();
                
                // Delete project
                $delete_stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ?");
                $delete_stmt->bind_param("i", $project_id);
                
                if ($delete_stmt->execute()) {
                    set_message("Project deleted successfully");
                } else {
                    set_message("Error deleting project: " . $conn->error, "error");
                }
                $delete_stmt->close();
            }
            redirect('project_management.php');
        }
        elseif ($_POST['action'] === 'assign_faculty') {
            $project_id = intval($_POST['project_id']);
            $faculty_id = intval($_POST['faculty_id']);
            $role = escape_string($conn, $_POST['role']);
            
            // Check if already assigned
            $check = $conn->prepare("SELECT * FROM faculty_project WHERE project_id = ? AND faculty_id = ?");
            $check->bind_param("ii", $project_id, $faculty_id);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                set_message("Faculty member is already assigned to this project", "error");
            } else {
                $sql = "INSERT INTO faculty_project (faculty_id, project_id, role) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $faculty_id, $project_id, $role);
                
                if ($stmt->execute()) {
                    set_message("Faculty assigned successfully");
                } else {
                    set_message("Error assigning faculty: " . $conn->error, "error");
                }
                $stmt->close();
            }
            $check->close();
            redirect('project_management.php?edit=' . $project_id);
        }
        elseif ($_POST['action'] === 'remove_faculty') {
            $project_id = intval($_POST['project_id']);
            $faculty_id = intval($_POST['faculty_id']);
            
            // Check if this is the only Lead faculty
            $check_lead = $conn->prepare("SELECT COUNT(*) as count FROM faculty_project WHERE project_id = ? AND role = 'Lead'");
            $check_lead->bind_param("i", $project_id);
            $check_lead->execute();
            $lead_count = $check_lead->get_result()->fetch_assoc()['count'];
            $check_lead->close();
            
            $check_current = $conn->prepare("SELECT role FROM faculty_project WHERE project_id = ? AND faculty_id = ?");
            $check_current->bind_param("ii", $project_id, $faculty_id);
            $check_current->execute();
            $current_role = $check_current->get_result()->fetch_assoc()['role'];
            $check_current->close();
            
            if ($current_role === 'Lead' && $lead_count <= 1) {
                set_message("Cannot remove the last Lead faculty. Assign another Lead faculty first.", "error");
            } else {
                $delete = $conn->prepare("DELETE FROM faculty_project WHERE project_id = ? AND faculty_id = ?");
                $delete->bind_param("ii", $project_id, $faculty_id);
                
                if ($delete->execute()) {
                    set_message("Faculty removed from project");
                } else {
                    set_message("Error removing faculty: " . $conn->error, "error");
                }
                $delete->close();
            }
            redirect('project_management.php?edit=' . $project_id);
        }
        elseif ($_POST['action'] === 'update_faculty_role') {
            $project_id = intval($_POST['project_id']);
            $faculty_id = intval($_POST['faculty_id']);
            $new_role = escape_string($conn, $_POST['new_role']);
            
            // If changing from Lead to Member, check if there's another Lead
            $check_current = $conn->prepare("SELECT role FROM faculty_project WHERE project_id = ? AND faculty_id = ?");
            $check_current->bind_param("ii", $project_id, $faculty_id);
            $check_current->execute();
            $current_role = $check_current->get_result()->fetch_assoc()['role'];
            $check_current->close();
            
            if ($current_role === 'Lead' && $new_role === 'Member') {
                $check_lead = $conn->prepare("SELECT COUNT(*) as count FROM faculty_project WHERE project_id = ? AND role = 'Lead' AND faculty_id != ?");
                $check_lead->bind_param("ii", $project_id, $faculty_id);
                $check_lead->execute();
                $lead_count = $check_lead->get_result()->fetch_assoc()['count'];
                $check_lead->close();
                
                if ($lead_count < 1) {
                    set_message("Cannot change role. This is the only Lead faculty. Assign another Lead first.", "error");
                    redirect('project_management.php?edit=' . $project_id);
                }
            }
            
            $update = $conn->prepare("UPDATE faculty_project SET role = ? WHERE project_id = ? AND faculty_id = ?");
            $update->bind_param("sii", $new_role, $project_id, $faculty_id);
            
            if ($update->execute()) {
                set_message("Faculty role updated successfully");
            } else {
                set_message("Error updating faculty role: " . $conn->error, "error");
            }
            $update->close();
            redirect('project_management.php?edit=' . $project_id);
        }
    }
}

// Get filter parameters
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'all';
$filter_agency = isset($_GET['filter_agency']) ? intval($_GET['filter_agency']) : 0;

// Validate status filter against whitelist
$valid_statuses = ['Planning', 'Active', 'Completed', 'Suspended'];
if ($filter_status !== 'all' && !in_array($filter_status, $valid_statuses)) {
    $filter_status = 'all';
}

// Build query for projects list
$sql = "SELECT p.*, fa.agency_name,
    (SELECT COUNT(*) FROM faculty_project fp WHERE fp.project_id = p.project_id) as faculty_count,
    (SELECT COUNT(*) FROM student_project sp WHERE sp.project_id = p.project_id) as student_count,
    (SELECT COUNT(*) FROM publication pub WHERE pub.project_id = p.project_id) as publication_count,
    (SELECT COUNT(*) FROM intellectual_property ip WHERE ip.project_id = p.project_id) as ip_count,
    (SELECT COUNT(*) FROM grants g WHERE g.project_id = p.project_id) as grants_count
    FROM projects p
    LEFT JOIN funding_agency fa ON p.funding_agency_id = fa.agency_id
    WHERE 1=1";

if ($filter_status !== 'all') {
    $sql .= " AND p.status = ?";
}

if ($filter_agency > 0) {
    $sql .= " AND p.funding_agency_id = ?";
}

$sql .= " ORDER BY p.created_at DESC";

// Execute query with prepared statement if filters are applied
if ($filter_status !== 'all' && $filter_agency > 0) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $filter_status, $filter_agency);
    $stmt->execute();
    $projects_list = $stmt->get_result();
    $stmt->close();
} elseif ($filter_status !== 'all') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $filter_status);
    $stmt->execute();
    $projects_list = $stmt->get_result();
    $stmt->close();
} elseif ($filter_agency > 0) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $filter_agency);
    $stmt->execute();
    $projects_list = $stmt->get_result();
    $stmt->close();
} else {
    $projects_list = $conn->query($sql);
}

// Get all funding agencies for filter and dropdown
$agencies = $conn->query("SELECT agency_id, agency_name FROM funding_agency ORDER BY agency_name");

// Get all faculty for assignment
$faculty_list = $conn->query("SELECT faculty_id, name, department FROM faculty ORDER BY name");

// Get project for editing if ID is provided
$edit_project = null;
$project_faculty = null;
$project_students = null;
$project_publication_count = 0;
$project_ip_count = 0;
$project_grant_count = 0;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM projects WHERE project_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_project = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get assigned faculty
    $faculty_stmt = $conn->prepare("
        SELECT fp.*, f.name, f.department 
        FROM faculty_project fp
        JOIN faculty f ON fp.faculty_id = f.faculty_id
        WHERE fp.project_id = ?
        ORDER BY fp.role DESC, f.name
    ");
    $faculty_stmt->bind_param("i", $edit_id);
    $faculty_stmt->execute();
    $project_faculty = $faculty_stmt->get_result();
    $faculty_stmt->close();
    
    // Get assigned students
    $students_stmt = $conn->prepare("
        SELECT sp.*, s.name, s.program
        FROM student_project sp
        JOIN student s ON sp.student_id = s.student_id
        WHERE sp.project_id = ?
        ORDER BY s.name
    ");
    $students_stmt->bind_param("i", $edit_id);
    $students_stmt->execute();
    $project_students = $students_stmt->get_result();
    $students_stmt->close();
    
    // Get project statistics for details view
    $pub_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM publication WHERE project_id = ?");
    $pub_count_stmt->bind_param("i", $edit_id);
    $pub_count_stmt->execute();
    $project_publication_count = $pub_count_stmt->get_result()->fetch_assoc()['count'];
    $pub_count_stmt->close();
    
    $ip_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM intellectual_property WHERE project_id = ?");
    $ip_count_stmt->bind_param("i", $edit_id);
    $ip_count_stmt->execute();
    $project_ip_count = $ip_count_stmt->get_result()->fetch_assoc()['count'];
    $ip_count_stmt->close();
    
    $grant_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM grants WHERE project_id = ?");
    $grant_count_stmt->bind_param("i", $edit_id);
    $grant_count_stmt->execute();
    $project_grant_count = $grant_count_stmt->get_result()->fetch_assoc()['count'];
    $grant_count_stmt->close();
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .filter-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group .form-group {
            flex: 1;
            min-width: 200px;
            margin-bottom: 0 !important;
            display: flex;
            flex-direction: column;
        }
        .filter-group .form-group label {
            margin-bottom: 8px;
            margin-top: 0;
        }
        .filter-group > button,
        .filter-group > a.btn {
            margin: 0 !important;
            height: 46px;
            display: inline-flex;
            align-items: center;
            box-sizing: border-box;
            padding: 12px 20px;
            flex-shrink: 0;
        }
        .faculty-assignment-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        .faculty-assignment-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: flex-end;
        }
        .faculty-assignment-row .form-group {
            margin-bottom: 0;
        }
        .faculty-remove-btn {
            background: #e53e3e;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 16px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            height: 46px;
            box-sizing: border-box;
            transition: background 0.3s;
            white-space: nowrap;
        }
        .faculty-remove-btn:hover {
            background: #c53030;
        }
        .add-faculty-btn-wrapper {
            margin-top: 10px;
        }
        .form-action-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .form-action-buttons .btn {
            margin: 0;
        }
        .faculty-list {
            margin-top: 15px;
        }
        .faculty-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f7fafc;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .faculty-info {
            flex: 1;
        }
        .faculty-name {
            font-weight: 600;
            color: #1e40af;
        }
        .faculty-department {
            font-size: 14px;
            color: #64748b;
        }
        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 0 10px;
        }
        .role-lead {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #eab308;
        }
        .role-member {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }
        .project-details-section {
            margin-top: 20px;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .detail-card {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
        }
        .detail-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .detail-value {
            font-size: 18px;
            font-weight: 600;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Project Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <?php if ($edit_project): ?>
                <!-- Edit Project Form -->
                <div class="table-container" style="margin-bottom: 30px;">
                    <div class="table-header">
                        <h2>Edit Project</h2>
                        <a href="project_management.php" class="btn btn-small btn-secondary">Back to Projects</a>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="project_id" value="<?php echo $edit_project['project_id']; ?>">
                        
                        <div class="form-group">
                            <label>Title:</label>
                            <input type="text" name="title" value="<?php echo htmlspecialchars($edit_project['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Status:</label>
                            <select name="status" required>
                                <option value="Planning" <?php echo $edit_project['status'] === 'Planning' ? 'selected' : ''; ?>>Planning</option>
                                <option value="Active" <?php echo $edit_project['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Completed" <?php echo $edit_project['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Suspended" <?php echo $edit_project['status'] === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Start Date:</label>
                            <input type="date" name="start_date" value="<?php echo $edit_project['start_date']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>End Date:</label>
                            <input type="date" name="end_date" value="<?php echo $edit_project['end_date'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Funding Agency:</label>
                            <select name="funding_agency_id">
                                <option value="">None</option>
                                <?php 
                                $agencies->data_seek(0);
                                while ($agency = $agencies->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $agency['agency_id']; ?>" <?php echo ($edit_project['funding_agency_id'] == $agency['agency_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($agency['agency_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-small">Update Project</button>
                    </form>
                    
                    <!-- Faculty Assignment Section -->
                    <div class="faculty-assignment-section">
                        <h3>Assigned Faculty</h3>
                        
                        <!-- Add Faculty Form -->
                        <form method="POST" action="" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="assign_faculty">
                            <input type="hidden" name="project_id" value="<?php echo $edit_project['project_id']; ?>">
                            
                            <div style="display: flex; gap: 10px; align-items: flex-end;">
                                <div class="form-group" style="flex: 2;">
                                    <label>Faculty Member:</label>
                                    <select name="faculty_id" required>
                                        <option value="">Select Faculty</option>
                                        <?php 
                                        $faculty_list->data_seek(0);
                                        while ($faculty = $faculty_list->fetch_assoc()): 
                                            // Check if already assigned
                                            $project_faculty->data_seek(0);
                                            $already_assigned = false;
                                            while ($pf = $project_faculty->fetch_assoc()) {
                                                if ($pf['faculty_id'] == $faculty['faculty_id']) {
                                                    $already_assigned = true;
                                                    break;
                                                }
                                            }
                                            $project_faculty->data_seek(0);
                                            if (!$already_assigned):
                                        ?>
                                            <option value="<?php echo $faculty['faculty_id']; ?>">
                                                <?php echo htmlspecialchars($faculty['name']) . ' - ' . htmlspecialchars($faculty['department']); ?>
                                            </option>
                                        <?php 
                                            endif;
                                        endwhile; 
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="flex: 1;">
                                    <label>Role:</label>
                                    <select name="role" required>
                                        <option value="Lead">Lead</option>
                                        <option value="Member">Member</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-small">Assign Faculty</button>
                            </div>
                        </form>
                        
                        <!-- Faculty List -->
                        <div class="faculty-list">
                            <?php if ($project_faculty && $project_faculty->num_rows > 0): ?>
                                <?php while ($pf = $project_faculty->fetch_assoc()): ?>
                                    <div class="faculty-item">
                                        <div class="faculty-info">
                                            <div class="faculty-name"><?php echo htmlspecialchars($pf['name']); ?></div>
                                            <div class="faculty-department"><?php echo htmlspecialchars($pf['department']); ?></div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="action" value="update_faculty_role">
                                                <input type="hidden" name="project_id" value="<?php echo $edit_project['project_id']; ?>">
                                                <input type="hidden" name="faculty_id" value="<?php echo $pf['faculty_id']; ?>">
                                                <select name="new_role" onchange="this.form.submit()" style="padding: 5px;">
                                                    <option value="Lead" <?php echo $pf['role'] === 'Lead' ? 'selected' : ''; ?>>Lead</option>
                                                    <option value="Member" <?php echo $pf['role'] === 'Member' ? 'selected' : ''; ?>>Member</option>
                                                </select>
                                            </form>
                                            <span class="role-badge role-<?php echo strtolower($pf['role']); ?>"><?php echo $pf['role']; ?></span>
                                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Remove this faculty member from the project?');">
                                                <input type="hidden" name="action" value="remove_faculty">
                                                <input type="hidden" name="project_id" value="<?php echo $edit_project['project_id']; ?>">
                                                <input type="hidden" name="faculty_id" value="<?php echo $pf['faculty_id']; ?>">
                                                <button type="submit" class="btn-delete" style="padding: 5px 10px; font-size: 12px;">Remove</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="color: #64748b; padding: 15px;">No faculty assigned to this project</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Project Details Section -->
                    <div class="project-details-section">
                        <h3>Project Details</h3>
                        <div class="detail-grid">
                            <div class="detail-card">
                                <div class="detail-label">Students</div>
                                <div class="detail-value"><?php echo $project_students ? $project_students->num_rows : 0; ?></div>
                            </div>
                            <div class="detail-card">
                                <div class="detail-label">Publications</div>
                                <div class="detail-value"><?php echo $project_publication_count; ?></div>
                            </div>
                            <div class="detail-card">
                                <div class="detail-label">Intellectual Property</div>
                                <div class="detail-value"><?php echo $project_ip_count; ?></div>
                            </div>
                            <div class="detail-card">
                                <div class="detail-label">Grants</div>
                                <div class="detail-value"><?php echo $project_grant_count; ?></div>
                            </div>
                        </div>
                        
                        <?php if ($project_students && $project_students->num_rows > 0): ?>
                            <div style="margin-top: 20px;">
                                <h4>Assigned Students</h4>
                                <table style="margin-top: 10px;">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Program</th>
                                            <th>Hours Worked</th>
                                            <th>Compensation</th>
                                            <th>Assigned Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $project_students->data_seek(0);
                                        while ($student = $project_students->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['program']); ?></td>
                                                <td><?php echo number_format($student['hours_worked'], 2); ?> hrs</td>
                                                <td><?php echo format_currency($student['compensation']); ?></td>
                                                <td><?php echo format_date($student['assigned_date']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Add Project Form -->
                <div class="table-container" style="margin-bottom: 30px;">
                    <h2>Add New Project</h2>
                    <form method="POST" action="" id="addProjectForm">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-group">
                            <label>Title:</label>
                            <input type="text" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Status:</label>
                            <select name="status" required>
                                <option value="Planning">Planning</option>
                                <option value="Active">Active</option>
                                <option value="Completed">Completed</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Start Date:</label>
                            <input type="date" name="start_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label>End Date:</label>
                            <input type="date" name="end_date">
                        </div>
                        
                        <div class="form-group">
                            <label>Funding Agency:</label>
                            <select name="funding_agency_id">
                                <option value="">None</option>
                                <?php 
                                $agencies->data_seek(0);
                                while ($agency = $agencies->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $agency['agency_id']; ?>"><?php echo htmlspecialchars($agency['agency_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Faculty Assignment on Create -->
                        <div class="faculty-assignment-section">
                            <h3>Assign Faculty (Optional - can be assigned later)</h3>
                            <div id="facultyContainer">
                                <div class="faculty-assignment-row">
                                    <div class="form-group" style="flex: 2;">
                                        <label>Faculty Member:</label>
                                        <select name="faculty_ids[]" class="faculty-select">
                                            <option value="">Select Faculty</option>
                                            <?php 
                                            $faculty_list->data_seek(0);
                                            while ($faculty = $faculty_list->fetch_assoc()): 
                                            ?>
                                                <option value="<?php echo $faculty['faculty_id']; ?>">
                                                    <?php echo htmlspecialchars($faculty['name']) . ' - ' . htmlspecialchars($faculty['department']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="form-group" style="flex: 1;">
                                        <label>Role:</label>
                                        <select name="faculty_roles[]">
                                            <option value="Lead">Lead</option>
                                            <option value="Member">Member</option>
                                        </select>
                                    </div>
                                    <button type="button" class="faculty-remove-btn" onclick="removeFacultyRow(this)">Remove</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-action-buttons">
                            <button type="button" class="btn btn-small btn-secondary" onclick="addFacultyRow()">Add Another Faculty</button>
                            <button type="submit" class="btn btn-small">Create Project</button>
                        </div>
                    </form>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <h3>Filter Projects</h3>
                    <form method="GET" action="">
                        <div class="filter-group">
                            <div class="form-group">
                                <label>Status:</label>
                                <select name="filter_status">
                                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="Planning" <?php echo $filter_status === 'Planning' ? 'selected' : ''; ?>>Planning</option>
                                    <option value="Active" <?php echo $filter_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Completed" <?php echo $filter_status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Suspended" <?php echo $filter_status === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Funding Agency:</label>
                                <select name="filter_agency">
                                    <option value="0" <?php echo $filter_agency == 0 ? 'selected' : ''; ?>>All Agencies</option>
                                    <?php 
                                    $agencies->data_seek(0);
                                    while ($agency = $agencies->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $agency['agency_id']; ?>" <?php echo $filter_agency == $agency['agency_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agency['agency_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-small">Filter</button>
                            <a href="project_management.php" class="btn btn-small btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
                
                <!-- Projects List -->
                <div class="table-container">
                    <div class="table-header">
                        <h2>Projects List</h2>
                    </div>
                    
                    <?php if ($projects_list->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Funding Agency</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Faculty</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($project = $projects_list->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $project['project_id']; ?></td>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td><span class="status status-<?php echo strtolower($project['status']); ?>"><?php echo $project['status']; ?></span></td>
                                        <td><?php echo htmlspecialchars($project['agency_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo format_date($project['start_date']); ?></td>
                                        <td><?php echo $project['end_date'] ? format_date($project['end_date']) : 'N/A'; ?></td>
                                        <td><?php echo $project['faculty_count']; ?></td>
                                        <td><?php echo $project['student_count']; ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit=<?php echo $project['project_id']; ?>" class="btn-edit">Edit</a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this project? This will also remove all faculty assignments.');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="project_id" value="<?php echo $project['project_id']; ?>">
                                                    <button type="submit" class="btn-delete">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>No Projects Found</h3>
                            <p>Create your first project to get started</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function addFacultyRow() {
            const container = document.getElementById('facultyContainer');
            const firstRow = container.querySelector('.faculty-assignment-row');
            const newRow = firstRow.cloneNode(true);
            
            // Clear selected values
            newRow.querySelector('.faculty-select').value = '';
            newRow.querySelector('select[name="faculty_roles[]"]').value = 'Member';
            
            container.appendChild(newRow);
        }
        
        function removeFacultyRow(button) {
            const container = document.getElementById('facultyContainer');
            if (container.querySelectorAll('.faculty-assignment-row').length > 1) {
                button.closest('.faculty-assignment-row').remove();
            } else {
                alert('At least one faculty assignment row is required. Clear the selection instead.');
            }
        }
    </script>
</body>
</html>

