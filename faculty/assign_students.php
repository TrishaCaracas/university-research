<?php
require_once '../config/database.php';
check_login('faculty');

$conn = getDBConnection();
$faculty_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'assign') {
        $project_id = intval($_POST['project_id']);
        $student_id = intval($_POST['student_id']);
        $hours_worked = floatval($_POST['hours_worked']);
        $compensation = floatval($_POST['compensation']);
        
        // Check if faculty is lead on this project
        $stmt = $conn->prepare("SELECT role FROM faculty_project WHERE faculty_id = ? AND project_id = ?");
        $stmt->bind_param("ii", $faculty_id, $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $role_data = $result->fetch_assoc();
            
            if ($role_data['role'] === 'Lead') {
                // Check if student already assigned
                $check = $conn->prepare("SELECT * FROM student_project WHERE student_id = ? AND project_id = ?");
                $check->bind_param("ii", $student_id, $project_id);
                $check->execute();
                
                if ($check->get_result()->num_rows === 0) {
                    $sql = "INSERT INTO student_project (student_id, project_id, hours_worked, compensation) VALUES (?, ?, ?, ?)";
                    $insert = $conn->prepare($sql);
                    $insert->bind_param("iidd", $student_id, $project_id, $hours_worked, $compensation);
                    
                    if ($insert->execute()) {
                        set_message("Student assigned successfully");
                    } else {
                        set_message("Error assigning student: " . $conn->error, "error");
                    }
                    $insert->close();
                } else {
                    set_message("Student already assigned to this project", "error");
                }
                $check->close();
            } else {
                set_message("Only project leads can assign students", "error");
            }
        } else {
            set_message("You are not assigned to this project", "error");
        }
        $stmt->close();
        redirect('assign_students.php');
    }
    elseif (isset($_POST['action']) && $_POST['action'] === 'remove') {
        $student_id = intval($_POST['student_id']);
        $project_id = intval($_POST['project_id']);
        
        // Check if faculty is lead
        $stmt = $conn->prepare("SELECT role FROM faculty_project WHERE faculty_id = ? AND project_id = ?");
        $stmt->bind_param("ii", $faculty_id, $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0 && $result->fetch_assoc()['role'] === 'Lead') {
            $delete = $conn->prepare("DELETE FROM student_project WHERE student_id = ? AND project_id = ?");
            $delete->bind_param("ii", $student_id, $project_id);
            
            if ($delete->execute()) {
                set_message("Student removed from project");
            } else {
                set_message("Error removing student: " . $conn->error, "error");
            }
            $delete->close();
        }
        $stmt->close();
        redirect('assign_students.php');
    }
}

// Get projects where faculty is lead
$lead_projects = $conn->prepare("
    SELECT p.* 
    FROM projects p
    JOIN faculty_project fp ON p.project_id = fp.project_id
    WHERE fp.faculty_id = ? AND fp.role = 'Lead'
    ORDER BY p.title
");
$lead_projects->bind_param("i", $faculty_id);
$lead_projects->execute();
$lead_projects = $lead_projects->get_result();

// Get all students
$students = $conn->query("SELECT * FROM student ORDER BY name");

// Get assigned students for lead projects
$assigned_students = $conn->prepare("
    SELECT sp.*, s.name as student_name, s.program, p.title as project_title
    FROM student_project sp
    JOIN student s ON sp.student_id = s.student_id
    JOIN projects p ON sp.project_id = p.project_id
    JOIN faculty_project fp ON p.project_id = fp.project_id
    WHERE fp.faculty_id = ? AND fp.role = 'Lead'
    ORDER BY p.title, s.name
");
$assigned_students->bind_param("i", $faculty_id);
$assigned_students->execute();
$assigned_students = $assigned_students->get_result();

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Students</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Assign Students to Projects</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <!-- Assign Student Form -->
            <div class="table-container" style="margin-bottom: 30px;">
                <h2>Assign Student to Project</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="assign">
                    
                    <div class="form-group">
                        <label>Project (You are Lead):</label>
                        <select name="project_id" required>
                            <option value="">Select Project</option>
                            <?php while ($project = $lead_projects->fetch_assoc()): ?>
                                <option value="<?php echo $project['project_id']; ?>"><?php echo htmlspecialchars($project['title']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Student:</label>
                        <select name="student_id" required>
                            <option value="">Select Student</option>
                            <?php while ($student = $students->fetch_assoc()): ?>
                                <option value="<?php echo $student['student_id']; ?>">
                                    <?php echo htmlspecialchars($student['name']) . ' - ' . htmlspecialchars($student['program']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Initial Hours Worked:</label>
                        <input type="number" step="0.01" name="hours_worked" value="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Compensation Amount:</label>
                        <input type="number" step="0.01" name="compensation" value="0" required>
                    </div>
                    
                    <button type="submit" class="btn btn-small">Assign Student</button>
                </form>
            </div>
            
            <!-- Assigned Students List -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Assigned Students</h2>
                </div>
                
                <?php if ($assigned_students->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Student Name</th>
                                <th>Program</th>
                                <th>Hours Worked</th>
                                <th>Compensation</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($assignment = $assigned_students->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['project_title']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['program']); ?></td>
                                    <td><?php echo number_format($assignment['hours_worked'], 2); ?> hrs</td>
                                    <td><?php echo format_currency($assignment['compensation']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this student from the project?');">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="student_id" value="<?php echo $assignment['student_id']; ?>">
                                            <input type="hidden" name="project_id" value="<?php echo $assignment['project_id']; ?>">
                                            <button type="submit" class="btn-delete">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Students Assigned</h3>
                        <p>Assign students to projects you lead</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>