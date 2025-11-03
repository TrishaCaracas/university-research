<?php
require_once '../config/database.php';
check_login('admin');

$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = escape_string($conn, sanitize_input($_POST['name']));
            $program = escape_string($conn, sanitize_input($_POST['program']));
            $year_level = intval($_POST['year_level']);
            $email = escape_string($conn, sanitize_input($_POST['email']));
            $password = hash_password($_POST['password']);
            
            $sql = "INSERT INTO student (name, program, year_level, email, password) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiss", $name, $program, $year_level, $email, $password);
            
            if ($stmt->execute()) {
                set_message("Student added successfully");
            } else {
                set_message("Error adding student: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('student_management.php');
        }
        elseif ($_POST['action'] === 'edit') {
            $student_id = intval($_POST['student_id']);
            $name = escape_string($conn, sanitize_input($_POST['name']));
            $program = escape_string($conn, sanitize_input($_POST['program']));
            $year_level = intval($_POST['year_level']);
            $email = escape_string($conn, sanitize_input($_POST['email']));
            
            if (!empty($_POST['password'])) {
                $password = hash_password($_POST['password']);
                $sql = "UPDATE student SET name=?, program=?, year_level=?, email=?, password=? WHERE student_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssissi", $name, $program, $year_level, $email, $password, $student_id);
            } else {
                $sql = "UPDATE student SET name=?, program=?, year_level=?, email=? WHERE student_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssisi", $name, $program, $year_level, $email, $student_id);
            }
            
            if ($stmt->execute()) {
                set_message("Student updated successfully");
            } else {
                set_message("Error updating student: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('student_management.php');
        }
        elseif ($_POST['action'] === 'delete') {
            $student_id = intval($_POST['student_id']);
            $sql = "DELETE FROM student WHERE student_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $student_id);
            
            if ($stmt->execute()) {
                set_message("Student deleted successfully");
            } else {
                set_message("Error deleting student: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('student_management.php');
        }
    }
}

// Get all students
$student_list = $conn->query("SELECT * FROM student ORDER BY name");

// Get student for editing if ID is provided
$edit_student = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM student WHERE student_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Student Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <!-- Add/Edit Form -->
            <div class="table-container" style="margin-bottom: 30px;">
                <h2><?php echo $edit_student ? 'Edit' : 'Add'; ?> Student</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $edit_student ? 'edit' : 'add'; ?>">
                    <?php if ($edit_student): ?>
                        <input type="hidden" name="student_id" value="<?php echo $edit_student['student_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Name:</label>
                        <input type="text" name="name" value="<?php echo $edit_student ? htmlspecialchars($edit_student['name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Program:</label>
                        <input type="text" name="program" value="<?php echo $edit_student ? htmlspecialchars($edit_student['program']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Year Level:</label>
                        <select name="year_level" required>
                            <option value="">Select Year</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($edit_student && $edit_student['year_level'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?php echo $edit_student ? htmlspecialchars($edit_student['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password: <?php echo $edit_student ? '(leave blank to keep current)' : ''; ?></label>
                        <input type="password" name="password" <?php echo !$edit_student ? 'required' : ''; ?>>
                    </div>
                    
                    <button type="submit" class="btn btn-small"><?php echo $edit_student ? 'Update' : 'Add'; ?> Student</button>
                    <?php if ($edit_student): ?>
                        <a href="student_management.php" class="btn btn-small btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Student List -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Student List</h2>
                </div>
                
                <?php if ($student_list->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Year Level</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $student_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $student['student_id']; ?></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['program']); ?></td>
                                    <td><?php echo $student['year_level']; ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $student['student_id']; ?>" class="btn-edit">Edit</a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this student?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
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
                        <h3>No Students</h3>
                        <p>Add your first student to get started</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>