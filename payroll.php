<?php
session_start();
require 'db.php';

// Check if the user is an admin
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$employees = [];
$payroll_details = null;
$message = '';
$form_data = $_POST;

// Fetch all employees for the dropdown
try {
    $emp_result = $conn->query("SELECT EMPID, Name FROM EmployeeBasicDetails ORDER BY Name ASC");
    while ($row = $emp_result->fetch_assoc()) {
        $employees[] = $row;
    }
} catch (Exception $e) {
    $message = "Error fetching employees: " . $e->getMessage();
}

// Handle form submission to view payroll
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['view_payroll'])) {
    try {
        $empid = $_POST['empid'];
        $shift_month = $_POST['shift_month'];
        $shift_year = $_POST['shift_year'];

        // Fetch payroll data by joining the two tables
        $stmt = $conn->prepare(
            "SELECT e.Name, s.* FROM SalaryCal_Table s
             JOIN EmployeeBasicDetails e ON s.EMPID = e.EMPID
             WHERE s.EMPID = ? AND s.Shift_Month = ? AND s.shift_year = ?"
        );
        $stmt->bind_param("sii", $empid, $shift_month, $shift_year);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $payroll_details = $result->fetch_assoc();
        } else {
            $message = "No payroll record found for the selected employee and period.";
        }

    } catch (Exception $e) {
        $message = "An error occurred: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Employee Payroll</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>View Employee Payroll</h2>
        
        <?php if ($message): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form action="" method="post" class="payroll-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="empid">Select Employee:</label>
                    <select name="empid" id="empid" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo htmlspecialchars($employee['EMPID']); ?>" <?php echo (isset($form_data['empid']) && $form_data['empid'] == $employee['EMPID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($employee['EMPID'] . ' - ' . $employee['Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="shift_month">Select Month:</label>
                    <select name="shift_month" id="shift_month" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo (isset($form_data['shift_month']) && $form_data['shift_month'] == $m) ? 'selected' : (($m == date('n')) && !isset($form_data['shift_month']) ? 'selected' : ''); ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="shift_year">Select Year:</label>
                    <select name="shift_year" id="shift_year" required>
                        <?php 
                        $current_year = date('Y');
                        for ($y = $current_year + 1; $y >= $current_year - 5; $y--): 
                        ?>
                            <option value="<?php echo $y; ?>" <?php echo (isset($form_data['shift_year']) && $form_data['shift_year'] == $y) ? 'selected' : (($y == $current_year) && !isset($form_data['shift_year']) ? 'selected' : ''); ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="view_payroll">View Payroll</button>
        </form>

        <?php if ($payroll_details): ?>
            <div class="results-container payroll-details">
                <h3>Payroll for <?php echo htmlspecialchars($payroll_details['Name']); ?> (<?php echo htmlspecialchars($payroll_details['EMPID']); ?>)</h3>
                <div class="details-grid">
                    <div class="detail-item">
                        <label>Salary ID</label>
                        <span><?php echo htmlspecialchars($payroll_details['SalaryID']); ?></span>
                    </div>
                     <div class="detail-item">
                        <label>Period</label>
                        <span><?php echo date('F', mktime(0,0,0,$payroll_details['Shift_Month'])) . ' ' . $payroll_details['shift_year']; ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Days Worked</label>
                        <span><?php echo htmlspecialchars($payroll_details['daysWorked']); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Overtime (Hours)</label>
                        <span><?php echo htmlspecialchars($payroll_details['OverTime']); ?></span>
                    </div>
                     <div class="detail-item">
                        <label>Total Earnings</label>
                        <span>₹<?php echo number_format($payroll_details['Total_Earnings'], 2); ?></span>
                    </div>
                     <div class="detail-item">
                        <label>Advances/Deductions</label>
                        <span>₹<?php echo number_format($payroll_details['advances_deductions'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>ESI Deduction</label>
                        <span>₹<?php echo number_format($payroll_details['ESI_Deductions'], 2); ?></span>
                    </div>
                     <div class="detail-item">
                        <label>EPF Deduction</label>
                        <span>₹<?php echo number_format($payroll_details['EPF_deductions'], 2); ?></span>
                    </div>
                     <div class="detail-item total">
                        <label>NET Payable</label>
                        <span>₹<?php echo number_format($payroll_details['NET_Payable'], 2); ?></span>
                    </div>
                     <div class="detail-item total">
                        <label>Actual Paid</label>
                        <span>₹<?php echo number_format($payroll_details['Actual_Paid'], 2); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
