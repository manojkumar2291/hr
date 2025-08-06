<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';
session_start();

// Allow both employee and supervisor roles
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['employee', 'supervisor'])) {
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $employee_id = $_POST['employee_id'];
    $date = $_POST['date'];
    $hours = $_POST['hours'];
    $notes = $_POST['notes'];

    if (!empty($employee_id) && !empty($date) && !empty($hours)) {
        $query = "INSERT INTO timesheets (employee_id, date, hours, notes) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isis", $employee_id, $date, $hours, $notes);

        if ($stmt->execute()) {
            $message = "✅ Timesheet submitted successfully.";
        } else {
            $message = "❌ Error: " . $conn->error;
        }
    } else {
        $message = "⚠️ Please fill in all required fields.";
    }
}

// Fetch employee list for dropdown
$employees_query = "SELECT id, name, designation, location FROM employees ORDER BY name ASC";
$employees_result = $conn->query($employees_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Submit Timesheet</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h2>Submit Timesheet</h2>

        <?php if (isset($message)) echo "<p>$message</p>"; ?>

        <form method="POST" action="">
            <label for="employee_name">Employee Name:</label>
            <select id="employee_name" onchange="fillEmployeeDetails()" required>
                <option value="">Select Employee</option>
                <?php while($row = $employee_result->fetch_assoc()) { ?>
                    <option 
                        value="<?php echo $row['id']; ?>" 
                        data-designation="<?php echo $row['designation']; ?>" 
                        data-department="<?php echo $row['department']; ?>"
                    >
                        <?php echo $row['name']; ?>
                    </option>
                <?php } ?>
            </select><br><br>

            <label for="employee_id_display">Employee ID:</label>
            <input type="text" id="employee_id_display" readonly><br><br>

            <label for="designation_display">Designation:</label>
            <input type="text" id="designation_display" readonly><br><br>

            <label for="department_display">Department:</label>
            <input type="text" id="department_display" readonly><br><br>

            <!-- Hidden field for form submission -->
            <input type="hidden" id="employee_id" name="employee_id">

            <label for="date">Date:</label>
            <input type="date" name="date" required><br><br>

            <label for="hours">Hours Worked:</label>
            <input type="number" name="hours" required><br><br>

            <label for="notes">Notes:</label>
            <textarea name="notes" placeholder="Notes"></textarea><br><br>

            <button type="submit">Submit</button>
        </form>
    </div>

    <script>
        function fillEmployeeDetails() {
            var dropdown = document.getElementById("employee_name");
            var selectedOption = dropdown.options[dropdown.selectedIndex];

            var employeeId = selectedOption.value;
            var designation = selectedOption.getAttribute("data-designation");
            var department = selectedOption.getAttribute("data-department");

            document.getElementById("employee_id").value = employeeId;
            document.getElementById("employee_id_display").value = employeeId;
            document.getElementById("designation_display").value = designation;
            document.getElementById("department_display").value = department;
        }
    </script>
</body>
</html>

