<?php
require_once '../config/database.php';
check_login('faculty');

$conn = getDBConnection();
$faculty_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $project_id = intval($_POST['project_id']);
    $title = escape_string($conn, sanitize_input($_POST['title']));
    $type = escape_string($conn, $_POST['type']);
    $date = escape_string($conn, $_POST['date']);
    
    // Verify faculty is assigned to this project
    $check_stmt = $conn->prepare("SELECT * FROM faculty_project WHERE faculty_id = ? AND project_id = ?");
    $check_stmt->bind_param("ii", $faculty_id, $project_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $sql = "INSERT INTO publication (project_id, title, type, date) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $project_id, $title, $type, $date);
        
        if ($stmt->execute()) {
            set_message("Publication added successfully");
        } else {
            set_message("Error adding publication: " . $conn->error, "error");
        }
        $stmt->close();
    } else {
        set_message("You are not assigned to this project", "error");
    }
    $check_stmt->close();
    redirect('add_publication.php');
}

// Delete publication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $pub_id = intval($_POST['publication_id']);
    
    // Verify faculty owns this publication (through project assignment)
    $check_stmt = $conn->prepare("
        SELECT pub.* FROM publication pub
        JOIN faculty_project fp ON pub.project_id = fp.project_id
        WHERE pub.publication_id = ? AND fp.faculty_id = ?
    ");
    $check_stmt->bind_param("ii", $pub_id, $faculty_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM publication WHERE publication_id = ?");
        $delete_stmt->bind_param("i", $pub_id);
        
        if ($delete_stmt->execute()) {
            set_message("Publication deleted successfully");
        } else {
            set_message("Error deleting publication: " . $conn->error, "error");
        }
        $delete_stmt->close();
    } else {
        set_message("You don't have permission to delete this publication", "error");
    }
    $check_stmt->close();
    redirect('add_publication.php');
}

// Get faculty's projects
$projects_stmt = $conn->prepare("
    SELECT p.project_id, p.title, p.status, fp.role
    FROM projects p
    JOIN faculty_project fp ON p.project_id = fp.project_id
    WHERE fp.faculty_id = ?
    ORDER BY p.title
");
$projects_stmt->bind_param("i", $faculty_id);
$projects_stmt->execute();
$my_projects = $projects_stmt->get_result();
$projects_stmt->close();

// Get faculty's publications
$pubs_stmt = $conn->prepare("
    SELECT pub.*, p.title as project_title
    FROM publication pub
    JOIN projects p ON pub.project_id = p.project_id
    JOIN faculty_project fp ON p.project_id = fp.project_id
    WHERE fp.faculty_id = ?
    ORDER BY pub.date DESC
");
$pubs_stmt->bind_param("i", $faculty_id);
$pubs_stmt->execute();
$my_publications = $pubs_stmt->get_result();
$pubs_stmt->close();

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Publication</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Add Publication</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <!-- Add Publication Form -->
            <div class="table-container" style="margin-bottom: 30px;">
                <h2>Add New Publication</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    
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
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small style="color: #64748b;">You can only add publications to projects you're assigned to.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Publication Title:</label>
                        <input type="text" name="title" required placeholder="Enter publication title">
                    </div>
                    
                    <div class="form-group">
                        <label>Publication Type:</label>
                        <select name="type" required>
                            <option value="">Select Type</option>
                            <option value="Journal">Journal</option>
                            <option value="Conference">Conference</option>
                            <option value="Book">Book</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Publication Date:</label>
                        <input type="date" name="date" required>
                    </div>
                    
                    <button type="submit" class="btn btn-small">Add Publication</button>
                </form>
            </div>
            
            <!-- My Publications List -->
            <div class="table-container">
                <div class="table-header">
                    <h2>My Publications</h2>
                </div>
                
                <?php if ($my_publications->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Publication Title</th>
                                <th>Project</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($pub = $my_publications->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $pub['publication_id']; ?></td>
                                    <td><?php echo htmlspecialchars($pub['title']); ?></td>
                                    <td><?php echo htmlspecialchars($pub['project_title']); ?></td>
                                    <td>
                                        <span class="status status-<?php echo strtolower($pub['type']); ?>">
                                            <?php echo $pub['type']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_date($pub['date']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this publication?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="publication_id" value="<?php echo $pub['publication_id']; ?>">
                                            <button type="submit" class="btn-delete">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Publications Yet</h3>
                        <p>Add your first publication to get started</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>