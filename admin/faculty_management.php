<?php
require_once '../config/database.php';
check_login('admin');

$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = escape_string($conn, sanitize_input($_POST['name']));
            $department = escape_string($conn, sanitize_input($_POST['department']));
            $email = escape_string($conn, sanitize_input($_POST['email']));
            $password = hash_password($_POST['password']);
            
            $sql = "INSERT INTO faculty (name, department, email, password) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $name, $department, $email, $password);
            
            if ($stmt->execute()) {
                set_message("Faculty member added successfully");
            } else {
                set_message("Error adding faculty member: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('faculty_management.php');
        }
        elseif ($_POST['action'] === 'edit') {
            $faculty_id = intval($_POST['faculty_id']);
            $name = escape_string($conn, sanitize_input($_POST['name']));
            $department = escape_string($conn, sanitize_input($_POST['department']));
            $email = escape_string($conn, sanitize_input($_POST['email']));
            
            if (!empty($_POST['password'])) {
                $password = hash_password($_POST['password']);
                $sql = "UPDATE faculty SET name=?, department=?, email=?, password=? WHERE faculty_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $name, $department, $email, $password, $faculty_id);
            } else {
                $sql = "UPDATE faculty SET name=?, department=?, email=? WHERE faculty_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $name, $department, $email, $faculty_id);
            }
            
            if ($stmt->execute()) {
                set_message("Faculty member updated successfully");
            } else {
                set_message("Error updating faculty member: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('faculty_management.php');
        }
        elseif ($_POST['action'] === 'delete') {
            $faculty_id = intval($_POST['faculty_id']);
            $sql = "DELETE FROM faculty WHERE faculty_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $faculty_id);
            
            if ($stmt->execute()) {
                set_message("Faculty member deleted successfully");
            } else {
                set_message("Error deleting faculty member: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('faculty_management.php');
        }
    }
}

// Get all faculty
$faculty_list = $conn->query("SELECT * FROM faculty ORDER BY name");

// Get faculty for editing if ID is provided
$edit_faculty = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM faculty WHERE faculty_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_faculty = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Faculty Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <!-- Add/Edit Form -->
            <div class="table-container" style="margin-bottom: 30px;">
                <h2><?php echo $edit_faculty ? 'Edit' : 'Add'; ?> Faculty Member</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $edit_faculty ? 'edit' : 'add'; ?>">
                    <?php if ($edit_faculty): ?>
                        <input type="hidden" name="faculty_id" value="<?php echo $edit_faculty['faculty_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Name:</label>
                        <input type="text" name="name" value="<?php echo $edit_faculty ? htmlspecialchars($edit_faculty['name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Department:</label>
                        <input type="text" name="department" value="<?php echo $edit_faculty ? htmlspecialchars($edit_faculty['department']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?php echo $edit_faculty ? htmlspecialchars($edit_faculty['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password: <?php echo $edit_faculty ? '(leave blank to keep current)' : ''; ?></label>
                        <input type="password" name="password" <?php echo !$edit_faculty ? 'required' : ''; ?>>
                    </div>
                    
                    <button type="submit" class="btn btn-small"><?php echo $edit_faculty ? 'Update' : 'Add'; ?> Faculty</button>
                    <?php if ($edit_faculty): ?>
                        <a href="faculty_management.php" class="btn btn-small btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Faculty List -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Faculty List</h2>
                </div>
                
                <?php if ($faculty_list->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($faculty = $faculty_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $faculty['faculty_id']; ?></td>
                                    <td><?php echo htmlspecialchars($faculty['name']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['department']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['email']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $faculty['faculty_id']; ?>" class="btn-edit">Edit</a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this faculty member?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="faculty_id" value="<?php echo $faculty['faculty_id']; ?>">
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
                        <h3>No Faculty Members</h3>
                        <p>Add your first faculty member to get started</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>