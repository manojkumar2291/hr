<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    die("Access denied. You must be an admin to access this page.");
}

$success = '';
$error = '';
$editing_employee = null; // To hold data of the employee being edited

// Fetch designations from the wages table
$designations = [];
$sql_designations = "SELECT DISTINCT Designation FROM wages";
$result_designations = $conn->query($sql_designations);
if ($result_designations->num_rows > 0) {
    while($row = $result_designations->fetch_assoc()) {
        $designations[] = $row['Designation'];
    }
}

// Handle POST requests for Add, Update, and Delete
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Handle DELETE request ---
    if (isset($_POST['delete_employee'])) {
        $empid_to_delete = $_POST['EMPID'];
        $stmt = $conn->prepare("DELETE FROM EmployeeBasicDetails WHERE EMPID = ?");
        $stmt->bind_param("s", $empid_to_delete);
        if ($stmt->execute()) {
            $success = "Employee deleted successfully.";
        } else {
            $error = "Error deleting employee: " . $stmt->error;
        }
    }
    // --- Handle UPDATE request ---
    elseif (isset($_POST['update_employee'])) {
        $EMPID = $_POST['EMPID'];
        $Name = $_POST['Name'];
        $Designation = $_POST['Designation'];
        $salary = $_POST['salary'];
        $salType = $_POST['salType'];
        $joiningDate = $_POST['joiningDate'];
        $Esi_Numbers = $_POST['Esi_Numbers'];
        $Epf_number = $_POST['Epf_number'];
        $bankAccNumber = $_POST['bankAccNumber'];
        $IFSC_code = $_POST['IFSC_code'];
        $Branch = $_POST['Branch'];
        $rateperday = $_POST['rateperday'];

        $stmt = $conn->prepare("UPDATE EmployeeBasicDetails SET Name=?, Designation=?, salary=?, salType=?, joiningDate=?, Esi_Numbers=?, Epf_number=?, bankAccNumber=?, IFSC_code=?, Branch=?, rateperday=? WHERE EMPID=?");
        $stmt->bind_param("ssisssssssds", $Name, $Designation, $salary, $salType, $joiningDate, $Esi_Numbers, $Epf_number, $bankAccNumber, $IFSC_code, $Branch, $rateperday, $EMPID);
        
        if ($stmt->execute()) {
            $success = "Employee updated successfully.";
        } else {
            $error = "Error updating employee: " . $stmt->error;
        }
    }
    // --- Handle ADD request ---
    else {
        $EMPID = $_POST['EMPID'];
        $Name = $_POST['Name'];
        $Designation = $_POST['Designation'];
        $salary = $_POST['salary'];
        $salType = $_POST['salType'];
        $joiningDate = $_POST['joiningDate'];
        $Esi_Numbers = $_POST['Esi_Numbers'];
        $Epf_number = $_POST['Epf_number'];
        $bankAccNumber = $_POST['bankAccNumber'];
        $IFSC_code = $_POST['IFSC_code'];
        $Branch = $_POST['Branch'];
        $rateperday = $_POST['rateperday'];

        $stmt = $conn->prepare("INSERT INTO employeebasicdetails (EMPID, Name, Designation, salary, salType, joiningDate, Esi_Numbers, Epf_number, bankAccNumber, IFSC_code, Branch, rateperday) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisssssssd", $EMPID, $Name, $Designation, $salary, $salType, $joiningDate, $Esi_Numbers, $Epf_number, $bankAccNumber, $IFSC_code, $Branch, $rateperday);

        if ($stmt->execute()) {
            $success = "Employee added successfully.";
        } else {
            $error = "Error adding employee: " . $stmt->error;
        }
    }
}

// Handle GET request for editing
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['edit_employee'])) {
    $empid_to_edit = $_GET['EMPID'];
    $stmt = $conn->prepare("SELECT * FROM EmployeeBasicDetails WHERE EMPID = ?");
    $stmt->bind_param("s", $empid_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $editing_employee = $result->fetch_assoc();
    }
}

// --- Fetch all employee details for the table ---
$all_employees = [];
$sql_all_employees = "SELECT * FROM EmployeeBasicDetails ORDER BY Name ASC";
$result_all_employees = $conn->query($sql_all_employees);
if ($result_all_employees->num_rows > 0) {
    while($row = $result_all_employees->fetch_assoc()) {
        $all_employees[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $editing_employee ? 'Edit' : 'Add'; ?> Employee - HR Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2><?php echo $editing_employee ? 'Edit Employee Details' : 'Add New Employee'; ?></h2>
        <?php if ($success) echo "<p class='message'>$success</p>"; ?>
        <?php if ($error) echo "<p class='message error'>$error</p>"; ?>
        <form method="POST" action="add_employee.php">
            <div class="form-grid">
                <div class="form-group">
                    <label for="EMPID">Employee ID:</label>
                    <input type="text" name="EMPID" id="EMPID" value="<?php echo htmlspecialchars($editing_employee['EMPID'] ?? ''); ?>" <?php echo $editing_employee ? 'readonly' : 'required'; ?>>
                </div>
                <div class="form-group"><label for="Name">Name:</label><input type="text" name="Name" id="Name" value="<?php echo htmlspecialchars($editing_employee['Name'] ?? ''); ?>" required></div>
                <div class="form-group">
                    <label for="Designation">Designation:</label>
                    <select name="Designation" id="Designation">
                        <?php foreach ($designations as $designation) { ?>
                            <option value="<?php echo htmlspecialchars($designation); ?>" <?php echo (isset($editing_employee) && $editing_employee['Designation'] == $designation) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($designation); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group"><label for="salary">Salary:</label><input type="number" name="salary" id="salary" value="<?php echo htmlspecialchars($editing_employee['salary'] ?? ''); ?>"></div>
                <div class="form-group">
                    <label for="salType">Salary Type:</label>
                    <select name="salType" id="salType">
                        <option value="monthly" <?php echo (isset($editing_employee) && $editing_employee['salType'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                        <option value="Daily" <?php echo (isset($editing_employee) && $editing_employee['salType'] == 'Daily') ? 'selected' : ''; ?>>Daily</option>
                    </select>
                </div>
                <div class="form-group"><label for="joiningDate">Joining Date:</label><input type="date" name="joiningDate" id="joiningDate" value="<?php echo htmlspecialchars($editing_employee['joiningDate'] ?? ''); ?>"></div>
                <div class="form-group"><label for="Esi_Numbers">ESI Numbers:</label><input type="text" name="Esi_Numbers" id="Esi_Numbers" value="<?php echo htmlspecialchars($editing_employee['Esi_Numbers'] ?? ''); ?>"></div>
                <div class="form-group"><label for="Epf_number">EPF Number:</label><input type="text" name="Epf_number" id="Epf_number" value="<?php echo htmlspecialchars($editing_employee['Epf_number'] ?? ''); ?>"></div>
                <div class="form-group"><label for="bankAccNumber">Bank Account Number:</label><input type="text" name="bankAccNumber" id="bankAccNumber" value="<?php echo htmlspecialchars($editing_employee['bankAccNumber'] ?? ''); ?>"></div>
                <div class="form-group"><label for="IFSC_code">IFSC Code:</label><input type="text" name="IFSC_code" id="IFSC_code" value="<?php echo htmlspecialchars($editing_employee['IFSC_code'] ?? ''); ?>"></div>
                <div class="form-group"><label for="Branch">Branch:</label><input type="text" name="Branch" id="Branch" value="<?php echo htmlspecialchars($editing_employee['Branch'] ?? ''); ?>"></div>
                <div class="form-group"><label for="rateperday">Rate Per Day:</label><input type="text" name="rateperday" id="rateperday" value="<?php echo htmlspecialchars($editing_employee['rateperday'] ?? ''); ?>"></div>
            </div>
            <?php if ($editing_employee): ?>
                <button type="submit" name="update_employee">Update Employee</button>
                <a href="add_employee.php" class="recalculate-btn">Cancel Edit</a>
            <?php else: ?>
                <button type="submit">Add Employee</button>
            <?php endif; ?>
        </form>
    </div>

    <div class="container">
        <h2>All Employees</h2>
        <div class="report-table-container">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>EMPID</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Salary</th>
                        <th>Salary Type</th>
                        <th>Joining Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_employees)): ?>
                        <?php foreach ($all_employees as $employee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['EMPID']); ?></td>
                                <td><?php echo htmlspecialchars($employee['Name']); ?></td>
                                <td><?php echo htmlspecialchars($employee['Designation']); ?></td>
                                <td><?php echo htmlspecialchars($employee['salary']); ?></td>
                                <td><?php echo htmlspecialchars($employee['salType']); ?></td>
                                <td><?php echo htmlspecialchars($employee['joiningDate']); ?></td>
                                <td class="actions-cell">
                                    <a href="add_employee.php?edit_employee=1&EMPID=<?php echo htmlspecialchars($employee['EMPID']); ?>" class="edit-btn">Edit</a>
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this employee?');" style="display:inline;">
                                        <input type="hidden" name="EMPID" value="<?php echo htmlspecialchars($employee['EMPID']); ?>">
                                        <button type="submit" name="delete_employee" class="delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">No employees found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
