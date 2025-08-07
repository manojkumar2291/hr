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

// Fetch designations from the wages table
$designations = [];
$sql_designations = "SELECT DISTINCT Designation FROM wages";
$result_designations = $conn->query($sql_designations);
if ($result_designations->num_rows > 0) {
    while($row = $result_designations->fetch_assoc()) {
        $designations[] = $row['Designation'];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Employee - HR Portal</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h2>Add New Employee</h2>
        <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <label for="EMPID">Employee ID:</label>
            <input type="text" name="EMPID" id="EMPID" required><br>

            <label for="Name">Name:</label>
            <input type="text" name="Name" id="Name" required><br>

            <label for="Designation">Designation:</label>
            <select name="Designation" id="Designation">
                <?php foreach ($designations as $designation) { ?>
                    <option value="<?php echo htmlspecialchars($designation); ?>"><?php echo htmlspecialchars($designation); ?></option>
                <?php } ?>
            </select><br>

            <label for="salary">Salary:</label>
            <input type="number" name="salary" id="salary"><br>

            <label for="salType">Salary Type:</label>
            <select name="salType" id="salType">
                <option value="monthly">Monthly</option>
                <option value="Daily">Daily</option>
            </select><br>

            <label for="joiningDate">Joining Date:</label>
            <input type="date" name="joiningDate" id="joiningDate"><br>

            <label for="Esi_Numbers">ESI Numbers:</label>
            <input type="text" name="Esi_Numbers" id="Esi_Numbers"><br>

            <label for="Epf_number">EPF Number:</label>
            <input type="text" name="Epf_number" id="Epf_number"><br>

            <label for="bankAccNumber">Bank Account Number:</label>
            <input type="text" name="bankAccNumber" id="bankAccNumber"><br>

            <label for="IFSC_code">IFSC Code:</label>
            <input type="text" name="IFSC_code" id="IFSC_code"><br>

            <label for="Branch">Branch:</label>
            <input type="text" name="Branch" id="Branch"><br>

            <label for="rateperday">Rate Per Day:</label>
            <input type="text" name="rateperday" id="rateperday"><br>

            <button type="submit">Add Employee</button>
        </form>
    </div>
</body>
</html>
