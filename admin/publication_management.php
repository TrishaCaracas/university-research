<?php
require_once '../config/database.php';
check_login('admin');

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $project_id = intval($_POST['project_id']);
            $title = escape_string($conn, sanitize_input($_POST['title']));
            $type = escape_string($conn, $_POST['type']);
            $date = escape_string($conn, $_POST['date']);
            
            $sql = "INSERT INTO publication (project_id, title, type, date) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $project_id, $title, $type, $date);
            
            if ($stmt->execute()) {
                set_message("Publication added successfully");
            } else {
                set_message("Error: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('publication_management.php');
        }
        elseif ($_POST['action'] === 'delete') {
            $pub_id = intval($_POST['publication_id']);
            $stmt = $conn->prepare("DELETE FROM publication WHERE publication_id = ?");
            $stmt->bind_param("i", $pub_id);
            
            if ($stmt->execute()) {
                set_message("Publication deleted successfully");
            } else {
                set_message("Error: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('publication_management.php');
        }
    }
}

$publications = $conn->query("
    SELECT pub.*, p.title as project_title 
    FROM publication pub
    JOIN projects p ON pub.project_id = p.project_id
    ORDER BY pub.date DESC
");

$projects = $conn->query("SELECT project_id, title FROM projects ORDER BY title");

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Publication Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Publication Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <div class="table-container" style="margin-bottom:30px;">
                <h2>Add Publication</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label>Project:</label>
                        <select name="project_id" required>
                            <option value="">Select Project</option>
                            <?php while ($p = $projects->fetch_assoc()): ?>
                                <option value="<?php echo $p['project_id']; ?>"><?php echo htmlspecialchars($p['title']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Title:</label>
                        <input type="text" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Type:</label>
                        <select name="type" required>
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
            
            <div class="table-container">
                <h2>Publications List</h2>
                
                <?php if ($publications->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($pub = $publications->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $pub['publication_id']; ?></td>
                                    <td><?php echo htmlspecialchars($pub['project_title']); ?></td>
                                    <td><?php echo htmlspecialchars($pub['title']); ?></td>
                                    <td><?php echo $pub['type']; ?></td>
                                    <td><?php echo format_date($pub['date']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this publication?');">
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
                        <h3>No Publications</h3>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>