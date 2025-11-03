<?php
require_once '../config/database.php';
check_login('admin');

$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $agency_name = escape_string($conn, sanitize_input($_POST['agency_name']));
            $type_id = intval($_POST['type_id']);
            $contact_number = escape_string($conn, sanitize_input($_POST['contact_number']));
            
            $sql = "INSERT INTO funding_agency (agency_name, type_id, contact_number) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sis", $agency_name, $type_id, $contact_number);
            
            if ($stmt->execute()) {
                set_message("Funding agency added successfully");
            } else {
                set_message("Error adding funding agency: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('funding_agency_management.php');
        }
        elseif ($_POST['action'] === 'edit') {
            $agency_id = intval($_POST['agency_id']);
            $agency_name = escape_string($conn, sanitize_input($_POST['agency_name']));
            $type_id = intval($_POST['type_id']);
            $contact_number = escape_string($conn, sanitize_input($_POST['contact_number']));
            
            $sql = "UPDATE funding_agency SET agency_name=?, type_id=?, contact_number=? WHERE agency_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisi", $agency_name, $type_id, $contact_number, $agency_id);
            
            if ($stmt->execute()) {
                set_message("Funding agency updated successfully");
            } else {
                set_message("Error updating funding agency: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('funding_agency_management.php');
        }
        elseif ($_POST['action'] === 'delete') {
            $agency_id = intval($_POST['agency_id']);
            $sql = "DELETE FROM funding_agency WHERE agency_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $agency_id);
            
            if ($stmt->execute()) {
                set_message("Funding agency deleted successfully");
            } else {
                set_message("Error deleting funding agency: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('funding_agency_management.php');
        }
    }
}

// Get all funding agencies with type
$agencies_list = $conn->query("
    SELECT fa.*, at.type_name 
    FROM funding_agency fa 
    JOIN agency_type at ON fa.type_id = at.type_id 
    ORDER BY fa.agency_name
");

// Get agency types for dropdown
$agency_types = $conn->query("SELECT * FROM agency_type ORDER BY type_name");

// Get agency for editing if ID is provided
$edit_agency = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM funding_agency WHERE agency_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_agency = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funding Agency Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Funding Agency Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <!-- Add/Edit Form -->
            <div class="table-container" style="margin-bottom: 30px;">
                <h2><?php echo $edit_agency ? 'Edit' : 'Add'; ?> Funding Agency</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $edit_agency ? 'edit' : 'add'; ?>">
                    <?php if ($edit_agency): ?>
                        <input type="hidden" name="agency_id" value="<?php echo $edit_agency['agency_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Agency Name:</label>
                        <input type="text" name="agency_name" value="<?php echo $edit_agency ? htmlspecialchars($edit_agency['agency_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Type:</label>
                        <select name="type_id" required>
                            <option value="">Select Type</option>
                            <?php 
                            $agency_types->data_seek(0);
                            while ($type = $agency_types->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $type['type_id']; ?>" <?php echo ($edit_agency && $edit_agency['type_id'] == $type['type_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Number:</label>
                        <input type="text" name="contact_number" value="<?php echo $edit_agency ? htmlspecialchars($edit_agency['contact_number']) : ''; ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-small"><?php echo $edit_agency ? 'Update' : 'Add'; ?> Agency</button>
                    <?php if ($edit_agency): ?>
                        <a href="funding_agency_management.php" class="btn btn-small btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Agencies List -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Funding Agencies List</h2>
                </div>
                
                <?php if ($agencies_list->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Agency Name</th>
                                <th>Type</th>
                                <th>Contact Number</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($agency = $agencies_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $agency['agency_id']; ?></td>
                                    <td><?php echo htmlspecialchars($agency['agency_name']); ?></td>
                                    <td><?php echo htmlspecialchars($agency['type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($agency['contact_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $agency['agency_id']; ?>" class="btn-edit">Edit</a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this agency?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="agency_id" value="<?php echo $agency['agency_id']; ?>">
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
                        <h3>No Funding Agencies</h3>
                        <p>Add your first funding agency to get started</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>