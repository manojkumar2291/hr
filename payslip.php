<?php
require_once 'header.php';
require_once 'db.php';

// Check if the user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$employees = [];
$payslip_details = null;
$message = '';
$form_data = $_POST;

// Fetch all employees for the dropdown
try {
    $emp_result = $conn->query("SELECT EMPID, Name FROM employeebasicdetails ORDER BY Name ASC");
    while ($row = $emp_result->fetch_assoc()) {
        $employees[] = $row;
    }
} catch (Exception $e) {
    $message = "Error fetching employees: " . $e->getMessage();
}

// Handle form submission to generate payslip
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_payslip'])) {
    try {
        $empid = $_POST['empid'];
        $shift_month = $_POST['shift_month'];
        $shift_year = $_POST['shift_year'];

        // Fetch all payslip data by joining the tables
        $stmt = $conn->prepare(
            "SELECT e.Name, e.Designation, e.bankAccNumber, e.IFSC_code, s.* FROM salarycal_table s
             JOIN employeebasicdetails e ON s.EMPID = e.EMPID
             WHERE s.EMPID = ? AND s.Shift_Month = ? AND s.shift_year = ?"
        );
        $stmt->bind_param("sii", $empid, $shift_month, $shift_year);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $payslip_details = $result->fetch_assoc();
        } else {
            $message = "No payslip record found for the selected employee and period.";
        }

    } catch (Exception $e) {
        $message = "An error occurred: " . $e->getMessage();
    }
}
?>
<div class="container no-print">
    <h2>Generate Employee Payslip</h2>

    <?php if ($message): ?>
        <p class="message error"><?php echo htmlspecialchars($message); ?></p>
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
        <button type="submit" name="generate_payslip">Generate Payslip</button>
    </form>
</div>

<?php if ($payslip_details): ?>
    <div class="container payslip-container">
        <div class="payslip-header">
            <h3>Payslip for <?php echo date('F Y', mktime(0,0,0,$payslip_details['Shift_Month'], 1, $payslip_details['shift_year'])); ?></h3>
            <button onclick="window.print()" class="print-btn no-print">Print Payslip</button>
        </div>
        <div class="employee-info">
            <div><strong>Employee ID:</strong> <?php echo htmlspecialchars($payslip_details['EMPID']); ?></div>
            <div><strong>Name:</strong> <?php echo htmlspecialchars($payslip_details['Name']); ?></div>
            <div><strong>Designation:</strong> <?php echo htmlspecialchars($payslip_details['Designation']); ?></div>
            <div><strong>Bank Acc No:</strong> <?php echo htmlspecialchars($payslip_details['bankAccNumber']); ?></div>
            <div><strong>IFSC Code:</strong> <?php echo htmlspecialchars($payslip_details['IFSC_code']); ?></div>
        </div>

        <div class="payslip-grid">
            <div class="workdetails">
                <h4>Work Details</h4>
                <div class="payslip-item"><span>Days Worked</span><span><?php echo htmlspecialchars($payslip_details['daysWorked']); ?></span></div>
                <div class="payslip-item"><span>Overtime (Hours)</span><span><?php echo htmlspecialchars($payslip_details['OverTime']); ?></span></div>

            </div>
            <div class="earnings">
                <h4>Earnings</h4>
                <div class="payslip-item"><span>Basic Wages Earned</span><span><?php echo number_format($payslip_details['BasicWages_Earned_perMonth'], 2); ?></span></div>
                <div class="payslip-item"><span>HRA Earned</span><span><?php echo number_format($payslip_details['HRAEarned_PerMonth'], 2); ?></span></div>
               <div class="payslip-item"> <span>OverTime Earnings</span><span><?php echo number_format($payslip_details['overtime_Earnings'], 2); ?></span></div>
                <div class="payslip-item"><span>Holiday Earnings</span><span><?php echo number_format($payslip_details['Nationa_Festival_Holidays_Earnings'], 2); ?></span></div>
                <div class="payslip-item total"><strong>Total Earnings</strong><strong><?php echo number_format($payslip_details['Total_Earnings'], 2); ?></strong></div>
            </div>
            <div class="deductions">
                <h4>Deductions</h4>
                <div class="payslip-item"><span>ESI (0.75%)</span><span><?php echo number_format($payslip_details['ESI_Deductions'], 2); ?></span></div>
                <div class="payslip-item"><span>EPF (12%)</span><span><?php echo number_format($payslip_details['EPF_deductions'], 2); ?></span></div>
                <div class="payslip-item"><span>Advances</span><span><?php echo number_format($payslip_details['advances_deductions'], 2); ?></span></div>
                <div class="payslip-item total"><strong>Total Deductions</strong><strong><?php echo number_format($payslip_details['Total_Deductions'], 2); ?></strong></div>
            </div>
        </div>
        <div class="payslip-summary">
            <strong>Net Payable: ₹<?php echo number_format($payslip_details['NET_Payable'], 2); ?></strong>
        </div>
    </div>
<?php endif; ?>
<?php
require_once 'footer.php';
?>
