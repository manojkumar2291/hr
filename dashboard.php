<?php
require_once 'header.php';
require_once 'db.php';

$dashboard_data = null;
$message = '';
$is_error = false;
$selected_month = date('n');
$selected_year = date('Y');

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- WORK LOCATION MANAGEMENT ---
    if (isset($_POST['add_location'])) {
        $new_location = trim($_POST['new_location']);
        if (!empty($new_location)) {
            $stmt = $conn->prepare("INSERT INTO worklocations (locationName) VALUES (?)");
            $stmt->bind_param("s", $new_location);
            if ($stmt->execute()) {
                $message = "Successfully added new work location.";
            } else { $is_error = true; $message = "Error: Location might already exist."; }
        } else { $is_error = true; $message = "Work location cannot be empty."; }
    }
    elseif (isset($_POST['update_location'])) {
        $location_id = $_POST['location_id'];
        $location_name = trim($_POST['locationName']);
        if (!empty($location_name) && !empty($location_id)) {
            $stmt = $conn->prepare("UPDATE worklocations SET locationName = ? WHERE id = ?");
            $stmt->bind_param("si", $location_name, $location_id);
            if ($stmt->execute()) {
                $message = "Work location updated successfully.";
            } else { $is_error = true; $message = "Error updating location."; }
        } else { $is_error = true; $message = "Location name cannot be empty."; }
    }
    elseif (isset($_POST['delete_location'])) {
        $location_id = $_POST['location_id'];
        $stmt = $conn->prepare("DELETE FROM worklocations WHERE id = ?");
        $stmt->bind_param("i", $location_id);
        if ($stmt->execute()) {
            $message = "Work location deleted successfully.";
        } else { $is_error = true; $message = "Error deleting location."; }
    }

    // --- DESIGNATION MANAGEMENT ---
    elseif (isset($_POST['add_designation'])) {
        $new_designation = trim($_POST['new_designation']);
        $salary = $_POST['designation_salary'];
        if (!empty($new_designation) && is_numeric($salary) && $salary >= 0) {
            $stmt = $conn->prepare("INSERT INTO wages (Designation, govt_salary) VALUES (?, ?)");
            $stmt->bind_param("sd", $new_designation, $salary);
            if ($stmt->execute()) {
                $message = "Successfully added new designation.";
            } else { $is_error = true; $message = "Error: Designation might already exist."; }
        } else { $is_error = true; $message = "Please provide a valid designation and salary."; }
    }
    elseif (isset($_POST['update_designation'])) {
        $designation_id = $_POST['designation_id'];
        $salary = $_POST['designation_salary'];
        if (is_numeric($salary) && $salary >= 0 && !empty($designation_id)) {
            $stmt = $conn->prepare("UPDATE wages SET govt_salary = ? WHERE id = ?");
            $stmt->bind_param("di", $salary, $designation_id);
            if ($stmt->execute()) {
                $message = "Designation salary updated successfully.";
            } else { $is_error = true; $message = "Error updating designation salary."; }
        } else { $is_error = true; $message = "Please provide a valid salary."; }
    }

    // --- DASHBOARD VIEW ---
    elseif (isset($_POST['view_dashboard'])) {
        $selected_month = $_POST['report_month'];
        $selected_year = $_POST['report_year'];
    }

    // Redirect to clean the URL after POST to prevent re-submission
    if (isset($_POST['add_location']) || isset($_POST['update_location']) || isset($_POST['delete_location']) || isset($_POST['add_designation']) || isset($_POST['update_designation'])) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?message=" . urlencode($message) . "&is_error=" . $is_error);
        exit();
    }
}

// Display messages from redirect
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $is_error = isset($_GET['is_error']) && $_GET['is_error'] == '1';
}

// --- DATA FETCHING FOR DISPLAY ---

// Set default dashboard month/year from last entry
try {
    $last_entry_res = $conn->query("SELECT shift_year, Shift_Month FROM salarycal_table ORDER BY shift_year DESC, Shift_Month DESC LIMIT 1");
    if ($last_entry_res->num_rows > 0) {
        $last_entry = $last_entry_res->fetch_assoc();
        if (!isset($_POST['view_dashboard'])) { // Only set if not actively selecting
            $selected_month = $last_entry['Shift_Month'];
            $selected_year = $last_entry['shift_year'];
        }
    }
} catch (Exception $e) { /* Silently fail */ }

// Fetch dashboard data
try {
    $stmt = $conn->prepare("SELECT SUM(OverTime) as total_overtime_hours, SUM(overtime_earnings) as total_overtime_spend, SUM(Nationa_Festival_Holidays_Earnings) as total_holiday_spend, SUM(daysWorked) as total_days_worked, sum(total_earnings) as total_earnings, sum(actual_earnings) as total_actual_earnings FROM SalaryCal_Table WHERE Shift_Month = ? AND shift_year = ?");
    $stmt->bind_param("ii", $selected_month, $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) { $dashboard_data = $result->fetch_assoc(); }
} catch (Exception $e) { $message = "Error fetching dashboard data: " . $e->getMessage(); $is_error = true; }

// Fetch data for Admin Controls
$all_locations = $conn->query("SELECT * FROM worklocations ORDER BY locationName ASC");
$all_designations = $conn->query("SELECT * FROM wages ORDER BY designation ASC");

// Check if we are in "edit" mode
$location_to_edit = null;
if (isset($_GET['edit_location_id'])) {
    $stmt = $conn->prepare("SELECT * FROM worklocations WHERE id = ?");
    $stmt->bind_param("i", $_GET['edit_location_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) { $location_to_edit = $result->fetch_assoc(); }
}

$designation_to_edit = null;
if (isset($_GET['edit_designation_id'])) {
    $stmt = $conn->prepare("SELECT * FROM wages WHERE id = ?");
    $stmt->bind_param("i", $_GET['edit_designation_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) { $designation_to_edit = $result->fetch_assoc(); }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-controls { margin-top: 40px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
        .admin-controls h2 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .admin-section { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 20px; }
        .message.success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .item-list { list-style: none; padding: 0; }
        .item-list li { display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #eee; }
        .item-list li:last-child { border-bottom: none; }
        .item-actions a, .item-actions button { margin-left: 10px; text-decoration: none; border: none; background: none; cursor: pointer; }
        .item-actions .edit-btn { color: #007bff; }
        .item-actions .delete-btn { color: #dc3545; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container" style="margin-top: 0px;">
        
        <?php if ($message): ?>
            <p class="message <?php echo $is_error ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </p>
        <?php endif; ?>

        <form action="" method="post" class="payroll-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="report_month">Select Month:</label>
                    <select name="report_month" id="report_month" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m; ?>" <?= ($selected_month == $m) ? 'selected' : ''; ?>><?= date('F', mktime(0, 0, 0, $m, 10)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="report_year">Select Year:</label>
                    <select name="report_year" id="report_year" required>
                        <?php $current_year = date('Y'); for ($y = $current_year + 1; $y >= $current_year - 5; $y--): ?>
                            <option value="<?= $y; ?>" <?= ($selected_year == $y) ? 'selected' : ''; ?>><?= $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="view_dashboard">View Dashboard</button>
        </form>

        <?php if ($dashboard_data && $dashboard_data['total_overtime_hours'] !== null): ?>
            <div class="dashboard-container"> 
                <h3>Dashboard for <?= date('F', mktime(0,0,0,$selected_month)) . ' ' . $selected_year; ?></h3>
                <div class="dashboard-grid" >
                    <div class="dashboard-card"><label>Total Overtime Hours</label><span><?= number_format($dashboard_data['total_overtime_hours'], 2); ?> hrs</span></div>
                    <div class="dashboard-card"><label>Amount Spent on Overtime</label><span>₹<?= number_format($dashboard_data['total_overtime_spend'], 2); ?></span></div>
                    <div class="dashboard-card"><label>Total Holiday Earnings</label><span>₹<?= number_format($dashboard_data['total_holiday_spend'], 2); ?></span></div>
                    <div class="dashboard-card"><label>Total Attended Days</label><span><?= number_format($dashboard_data['total_days_worked']); ?></span></div>
                    <div class="dashboard-card"><label>Total Earnings (Govt.)</label><span>₹<?= number_format($dashboard_data['total_earnings']); ?></span></div>
                    <div class="dashboard-card"><label>Total Actual Earnings</label><span>₹<?= number_format($dashboard_data['total_actual_earnings']); ?></span></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="admin-controls">
            <h2>Admin Controls</h2>
            <div class="admin-section">
                <div>
                    <h4>Manage Work Locations</h4>
                    <?php if ($location_to_edit): ?>
                        <form action="" method="post" class="payroll-form">
                            <input type="hidden" name="location_id" value="<?= $location_to_edit['id']; ?>">
                            <div class="form-group">
                                <label for="location_name">Edit Location Name:</label>
                                <input type="text" name="locationName" id="locationName" value="<?= htmlspecialchars($location_to_edit['locationName']); ?>" required>
                            </div>
                            <button type="submit" name="update_location">Update Location</button>
                            <a href="<?= $_SERVER['PHP_SELF']; ?>" style="margin-left: 10px;">Cancel</a>
                        </form>
                    <?php else: ?>
                        <form action="" method="post" class="payroll-form">
                            <div class="form-group">
                                <label for="new_location">Add New Location:</label>
                                <input type="text" name="new_location" id="new_location" required>
                            </div>
                            <button type="submit" name="add_location">Add Location</button>
                        </form>
                    <?php endif; ?>
                    
                    <ul class="item-list" style="margin-top: 20px;">
                        <?php while($row = $all_locations->fetch_assoc()): ?>
                        <li>
                            <span><?= htmlspecialchars($row['locationName']); ?></span>
                            <span class="item-actions">
                                <a href="?edit_location_id=<?= $row['id']; ?>" class="edit-btn">Edit</a>
                                <form action="" method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this location?');">
                                    <input type="hidden" name="location_id" value="<?= $row['id']; ?>">
                                    <button type="submit" name="delete_location" class="delete-btn">Delete</button>
                                </form>
                            </span>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                </div>

                <div>
                    <h4>Manage Designations & Wages</h4>
                     <?php if ($designation_to_edit): ?>
                        <form action="" method="post" class="payroll-form">
                            <input type="hidden" name="designation_id" value="<?= $designation_to_edit['id']; ?>">
                            <div class="form-group">
                                <label for="designation_name">Designation:</label>
                                <input type="text" name="designation" id="designation" value="<?= htmlspecialchars($designation_to_edit['Designation']); ?>" disabled>
                            </div>
                             <div class="form-group">
                                <label for="designation_salary">Edit Salary (₹):</label>
                                <input type="number" name="designation_salary" id="designation_salary" step="0.01" min="0" value="<?= $designation_to_edit['govt_salary']; ?>" required>
                            </div>
                            <button type="submit" name="update_designation">Update Salary</button>
                            <a href="<?= $_SERVER['PHP_SELF']; ?>" style="margin-left: 10px;">Cancel</a>
                        </form>
                    <?php else: ?>
                        <form action="" method="post" class="payroll-form">
                            <div class="form-group">
                                <label for="new_designation">New Designation:</label>
                                <input type="text" name="new_designation" id="new_designation" required>
                            </div>
                            <div class="form-group">
                                <label for="designation_salary">Salary (₹):</label>
                                <input type="number" name="designation_salary" id="designation_salary" step="0.01" min="0" required>
                            </div>
                            <button type="submit" name="add_designation">Add Designation</button>
                        </form>
                    <?php endif; ?>

                    <table style="margin-top: 20px;">
                        <tr><th>Designation</th><th>Salary (₹)</th><th>Action</th></tr>
                        <?php while($row = $all_designations->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['Designation']); ?></td>
                            <td><?= number_format($row['govt_salary'], 2); ?></td>
                            <td class="item-actions">
                                <a href="?edit_designation_id=<?= $row['id']; ?>" class="edit-btn">Edit Salary</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>