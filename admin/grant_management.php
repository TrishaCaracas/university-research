<?php
require_once '../config/database.php';
check_login('admin');

$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $project_id = intval($_POST['project_id']);
            $funding_agency_id = intval($_POST['funding_agency_id']);
            $amount = floatval($_POST['amount']);
            $grant_date = escape_string($conn, $_POST['grant_date']);
            
            $sql = "INSERT INTO grants (project_id, funding_agency_id, amount, grant_date) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iids", $project_id, $funding_agency_id, $amount, $grant_date);
            
            if ($stmt->execute()) {
                set_message("Grant added successfully");
            } else {
                set_message("Error adding grant: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('grant_management.php');
        }
        elseif ($_POST['action'] === 'add_disbursement') {
            $grant_id = intval($_POST['grant_id']);
            $amount = floatval($_POST['amount']);
            $date = escape_string($conn, $_POST['date']);
            $tranche_number = intval($_POST['tranche_number']);
            
            $sql = "INSERT INTO disbursement (grant_id, amount, date, tranche_number) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idsi", $grant_id, $amount, $date, $tranche_number);
            
            if ($stmt->execute()) {
                set_message("Disbursement added successfully");
            } else {
                set_message("Error adding disbursement: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('grant_management.php');
        }
        elseif ($_POST['action'] === 'delete') {
            $grant_id = intval($_POST['grant_id']);
            $sql = "DELETE FROM grants WHERE grant_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $grant_id);
            
            if ($stmt->execute()) {
                set_message("Grant deleted successfully");
            } else {
                set_message("Error deleting grant: " . $conn->error, "error");
            }
            $stmt->close();
            redirect('grant_management.php');
        }
    }
}

// Get all grants with project and agency info
$grants_list = $conn->query("
    SELECT g.*, p.title as project_title, fa.agency_name,
    (SELECT COALESCE(SUM(amount), 0) FROM disbursement WHERE grant_id = g.grant_id) as total_disbursed
    FROM grants g
    JOIN projects p ON g.project_id = p.project_id
    JOIN funding_agency fa ON g.funding_agency_id = fa.agency_id
    ORDER BY g.grant_date DESC
");

// Get projects for dropdown
$projects = $conn->query("SELECT project_id, title FROM projects ORDER BY title");

// Get funding agencies for dropdown
$agencies = $conn->query("SELECT agency_id, agency_name FROM funding_agency ORDER BY agency_name");

// Get grant details if viewing disbursements
$disbursements = null;
$grant_info = null;
if (isset($_GET['view'])) {
    $grant_id = intval($_GET['view']);
    $stmt = $conn->prepare("
        SELECT g.*, p.title as project_title, fa.agency_name
        FROM grants g
        JOIN projects p ON g.project_id = p.project_id
        JOIN funding_agency fa ON g.funding_agency_id = fa.agency_id
        WHERE g.grant_id = ?
    ");
    $stmt->bind_param("i", $grant_id);
    $stmt->execute();
    $grant_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT * FROM disbursement WHERE grant_id = ? ORDER BY date DESC");
    $stmt->bind_param("i", $grant_id);
    $stmt->execute();
    $disbursements = $stmt->get_result();
    $stmt->close();
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grant Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Grant Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php display_message(); ?>
            
            <?php if ($grant_info): ?>
                <!-- Disbursement Section -->
                <div class="table-container" style="margin-bottom: 30px;">
                    <div class="table-header">
                        <h2>Disbursements for Grant #<?php echo $grant_info['grant_id']; ?></h2>
                        <a href="grant_management.php" class="btn btn-small btn-secondary">Back to Grants</a>
                    </div>
                    
                    <p><strong>Project:</strong> <?php echo htmlspecialchars($grant_info['project_title']); ?></p>
                    <p><strong>Funding Agency:</strong> <?php echo htmlspecialchars($grant_info['agency_name']); ?></p>
                    <p><strong>Total Grant:</strong> <?php echo format_currency($grant_info['amount']); ?></p>
                    
                    <hr style="margin: 20px 0;">
                    
                    <h3>Add Disbursement</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_disbursement">
                        <input type="hidden" name="grant_id" value="<?php echo $grant_info['grant_id']; ?>">
                        
                        <div class="form-group">
                            <label>Amount:</label>
                            <input type="number" step="0.01" name="amount" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Tranche Number:</label>
                            <input type="number" name="tranche_number" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Date:</label>
                            <input type="date" name="date" required>
                        </div>
                        
                        <button type="submit" class="btn btn-small">Add Disbursement</button>
                    </form>
                    
                    <hr style="margin: 20px 0;">
                    
                    <?php if ($disbursements->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Tranche #</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($d = $disbursements->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $d['tranche_number']; ?></td>
                                        <td><?php echo format_currency($d['amount']); ?></td>
                                        <td><?php echo format_date($d['date']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>No Disbursements Yet</h3>
                            <p>Add the first disbursement for this grant</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Add Grant Form -->
                <div class="table-container" style="margin-bottom: 30px;">
                    <h2>Add Grant</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-group">
                            <label>Project:</label>
                            <select name="project_id" required>
                                <option value="">Select Project</option>
                                <?php 
                                $projects->data_seek(0);
                                while ($p = $projects->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $p['project_id']; ?>"><?php echo htmlspecialchars($p['title']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Funding Agency:</label>
                            <select name="funding_agency_id" required>
                                <option value="">Select Agency</option>
                                <?php 
                                $agencies->data_seek(0);
                                while ($a = $agencies->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $a['agency_id']; ?>"><?php echo htmlspecialchars($a['agency_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Amount:</label>
                            <input type="number" step="0.01" name="amount" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Grant Date:</label>
                            <input type="date" name="grant_date" required>
                        </div>
                        
                        <button type="submit" class="btn btn-small">Add Grant</button>
                    </form>
                </div>
                
                <!-- Grants List -->
                <div class="table-container">
                    <div class="table-header">
                        <h2>Grants List</h2>
                    </div>
                    
                    <?php if ($grants_list->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Project</th>
                                    <th>Funding Agency</th>
                                    <th>Amount</th>
                                    <th>Disbursed</th>
                                    <th>Grant Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($grant = $grants_list->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $grant['grant_id']; ?></td>
                                        <td><?php echo htmlspecialchars($grant['project_title']); ?></td>
                                        <td><?php echo htmlspecialchars($grant['agency_name']); ?></td>
                                        <td><?php echo format_currency($grant['amount']); ?></td>
                                        <td><?php echo format_currency($grant['total_disbursed']); ?></td>
                                        <td><?php echo format_date($grant['grant_date']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?view=<?php echo $grant['grant_id']; ?>" class="btn-view">Disbursements</a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure? This will delete all disbursements too.');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="grant_id" value="<?php echo $grant['grant_id']; ?>">
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
                            <h3>No Grants</h3>
                            <p>Add your first grant to get started</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>