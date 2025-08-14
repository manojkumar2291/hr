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
$is_error = false;
$is_editing = false; // Flag to control edit mode
$form_data = $_POST;

// Fetch all employees for the dropdown
try {
    $emp_result = $conn->query("SELECT EMPID, Name FROM EmployeeBasicDetails ORDER BY Name ASC");
    while ($row = $emp_result->fetch_assoc()) {
        $employees[] = $row;
    }
} catch (Exception $e) {
    $message = "Error fetching employees: " . $e->getMessage();
    $is_error = true;
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // --- Handle View/Generate Payslip Request ---
        if (isset($_POST['generate_payslip']) || isset($_POST['edit'])) {
            $empid = $_POST['empid'];
            $shift_month = $_POST['shift_month'];
            $shift_year = $_POST['shift_year'];

            $stmt = $conn->prepare(
                "SELECT e.Name, e.Designation, e.bankAccNumber, e.IFSC_code, e.salary, s.* FROM SalaryCal_Table s
                 JOIN EmployeeBasicDetails e ON s.EMPID = e.EMPID
                 WHERE s.EMPID = ? AND s.Shift_Month = ? AND s.shift_year = ?"
            );
            $stmt->bind_param("sii", $empid, $shift_month, $shift_year);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $payroll_details = $result->fetch_assoc();
                if (isset($_POST['edit'])) {
                    $is_editing = true; // Enable edit mode
                }
            } else {
                $message = "No payslip record found for the selected employee and period.";
                $is_error = true;
            }
        }

        // --- Handle Update Request ---
        elseif (isset($_POST['update_payroll'])) {
            // Recalculate everything based on potentially edited values
            $empid = $_POST['empid'];
            $working_days = (int)$_POST['WorkingDays'];
            $days_attended = (float)$_POST['daysWorked'];
            $overtime = (float)$_POST['OverTime'];
            $festival_days = (float)$_POST['FestivalDays'];
            $advances_deductions = (float)$_POST['advances_deductions'];

            $stmt_emp = $conn->prepare("SELECT e.salary, e.salType, w.govt_salary FROM EmployeeBasicDetails e JOIN Wages w ON e.Designation = w.Designation WHERE e.EMPID = ?");
            $stmt_emp->bind_param("s", $empid);
            $stmt_emp->execute();
            $emp_details = $stmt_emp->get_result()->fetch_assoc();

            $day_rate = 0;
            if (strtolower($emp_details['salType']) == "daily") {
                $day_rate = (float)$emp_details['salary'];
            } elseif (strtolower($emp_details['salType']) == "monthly" && $working_days > 0) {
                $day_rate = (float)$emp_details['salary'] / $working_days;
            }

            $total_per_month = (float)$emp_details['govt_salary'];
            $hra_per_month = ($total_per_month / 100) * 40;
            $basic_per_month = ($total_per_month / 100) * 60;
            $holidays_earnings = ($working_days > 0) ? ($total_per_month / $working_days) * $festival_days : 0;
            $basic_earned_per_month = ($working_days > 0) ? ($basic_per_month / $working_days) * ($days_attended + $festival_days) : 0;
            $hra_earned_per_month = ($working_days > 0) ? ($hra_per_month / $working_days) * ($days_attended + $festival_days) : 0;
            $overtime_earnings = ($working_days > 0) ? ($total_per_month / $working_days) * ($overtime / 4) : 0;
            $total_shift = $days_attended + $festival_days + ($overtime / 4);
            $actual_earnings = $day_rate * $total_shift;
            $total_earnings = $basic_earned_per_month + $hra_earned_per_month + $holidays_earnings + $overtime_earnings;
            $esi_deductions = ($total_earnings / 100) * 0.75;
            $epf_deductions = ($basic_earned_per_month / 100) * 12;
            $total_deductions = $advances_deductions + $epf_deductions + $esi_deductions;
            $net_payable = $total_earnings - $total_deductions;
            $actual_paid = $actual_earnings - $net_payable;

            $stmt_update = $conn->prepare(
                "UPDATE SalaryCal_Table SET 
                 daysWorked = ?, OverTime = ?, FestivalDays = ?, advances_deductions = ?,
                 Total_Shift = ?, RatePerDay = ?, HRA_Per_Month = ?, Basic_Per_Month = ?, Total_Per_Month = ?, 
                 Nationa_Festival_Holidays_Earnings = ?, BasicWages_Earned_PerMonth = ?, HRAEarned_PerMonth = ?, overtime_earnings = ?,
                 Actual_Earnings = ?, Total_Earnings = ?, ESI_Deductions = ?, EPF_deductions = ?, Total_Deductions = ?, 
                 NET_Payable = ?, Actual_Paid = ?
                 WHERE SalaryID = ?"
            );
            $stmt_update->bind_param(
                "ddddddddiddddddddddds",
                $days_attended, $overtime, $festival_days, $advances_deductions,
                $total_shift, $day_rate, $hra_per_month, $basic_per_month, $total_per_month,
                $holidays_earnings, $basic_earned_per_month, $hra_earned_per_month, $overtime_earnings,
                $actual_earnings, $total_earnings, $esi_deductions, $epf_deductions, $total_deductions,
                $net_payable, $actual_paid, $_POST['SalaryID']
            );
            
            if ($stmt_update->execute()) {
                $message = "Payslip updated successfully!";
                $is_editing = false; // Exit edit mode
                $_POST['generate_payslip'] = true; 
            } else {
                throw new Exception("Failed to update payslip: " . $stmt_update->error);
            }
        }
        
        elseif (isset($_POST['delete_payroll'])) {
            $salary_id_to_delete = $_POST['SalaryID'];
            $stmt_delete = $conn->prepare("DELETE FROM SalaryCal_Table WHERE SalaryID = ?");
            $stmt_delete->bind_param("s", $salary_id_to_delete);
            if ($stmt_delete->execute()) {
                $message = "Payslip record deleted successfully.";
                $payroll_details = null; 
            } else {
                throw new Exception("Failed to delete record: " . $stmt_delete->error);
            }
        }

    } catch (Exception $e) {
        $message = "An error occurred: " . $e->getMessage();
        $is_error = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Employee Payslip</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .print-only { display: none; }

        @media print {
            body > .no-print { display: none !important; }
            .no-print { display: none !important; }
            .payslip-container {
                display: block !important;
                visibility: visible !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 1rem;
                border: none;
                box-shadow: none;
            }
            .screen-view { display: none !important; }
            .print-only { display: block !important; }
            .print-table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
            .print-table th, .print-table td { border: 1px solid #000; padding: 6px; text-align: left; font-size: 10px; }
            .print-table th { background-color: #f2f2f2; font-weight: bold; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <?php require 'header.php'; ?>
        <div class="container">
            <h2>Generate Employee Payslip</h2>
            
            <?php if ($message && !$is_editing): ?>
                <p class="message <?php if ($is_error) echo 'error'; ?>"><?php echo htmlspecialchars($message); ?></p>
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
                                <option value="<?php echo $m; ?>" <?php echo (isset($form_data['shift_month']) && $form_data['shift_month'] == $m) ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $y; ?>" <?php echo (isset($form_data['shift_year']) && $form_data['shift_year'] == $y) ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="generate_payslip">Generate Payslip</button>
            </form>
        </div>
    </div>

    <?php if ($payroll_details): ?>
        <div class="container payslip-container">
            <!-- Screen View -->
            <div class="screen-view">
                <div class="payslip-header">
                    <h3>Payslip for <?php echo date('F Y', mktime(0,0,0,$payroll_details['Shift_Month'], 1, $payroll_details['shift_year'])); ?></h3>
                    <div class="actions-container no-print">
                        <button onclick="window.print()" class="print-btn">Print</button>
                        <form action="" method="post" style="display: inline;">
                            <input type="hidden" name="empid" value="<?php echo htmlspecialchars($payroll_details['EMPID']); ?>">
                            <input type="hidden" name="shift_month" value="<?php echo htmlspecialchars($payroll_details['Shift_Month']); ?>">
                            <input type="hidden" name="shift_year" value="<?php echo htmlspecialchars($payroll_details['shift_year']); ?>">
                            <button type="submit" name="edit" class="edit-btn">Edit</button>
                        </form>
                         <form action="" method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this payslip record?');">
                            <input type="hidden" name="SalaryID" value="<?php echo htmlspecialchars($payroll_details['SalaryID']); ?>">
                            <button type="submit" name="delete_payroll" class="delete-btn">Delete</button>
                        </form>
                    </div>
                </div>

                <?php if ($is_editing): ?>
                    <!-- EDIT FORM -->
                    <form action="" method="post">
                        <input type="hidden" name="SalaryID" value="<?php echo htmlspecialchars($payroll_details['SalaryID']); ?>">
                        <input type="hidden" name="empid" value="<?php echo htmlspecialchars($payroll_details['EMPID']); ?>">
                        <input type="hidden" name="shift_month" value="<?php echo htmlspecialchars($payroll_details['Shift_Month']); ?>">
                        <input type="hidden" name="shift_year" value="<?php echo htmlspecialchars($payroll_details['shift_year']); ?>">
                        <input type="hidden" name="WorkingDays" value="<?php echo htmlspecialchars($payroll_details['WorkingDays']); ?>">
                        
                        <div class="edit-grid">
                            <div class="form-group"><label>Days Worked</label><input type="number" step="0.5" name="daysWorked" value="<?php echo htmlspecialchars($payroll_details['daysWorked']); ?>"></div>
                            <div class="form-group"><label>Overtime (Hours)</label><input type="number" step="0.5" name="OverTime" value="<?php echo htmlspecialchars($payroll_details['OverTime']); ?>"></div>
                            <div class="form-group"><label>Festival Days</label><input type="number" step="0.5" name="FestivalDays" value="<?php echo htmlspecialchars($payroll_details['FestivalDays']); ?>"></div>
                            <div class="form-group"><label>Advances/Deductions</label><input type="number" step="0.01" name="advances_deductions" value="<?php echo htmlspecialchars($payroll_details['advances_deductions']); ?>"></div>
                        </div>
                        <div class="actions-container">
                            <button type="submit" name="update_payroll">Recalculate & Save</button>
                            <a href="payslip.php" class="recalculate-btn">Cancel</a>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- DISPLAY VIEW -->
                    <div class="employee-info">
                        <div><strong>Employee ID:</strong> <?php echo htmlspecialchars($payroll_details['EMPID']); ?></div>
                        <div><strong>Name:</strong> <?php echo htmlspecialchars($payroll_details['Name']); ?></div>
                        <div><strong>Designation:</strong> <?php echo htmlspecialchars($payroll_details['Designation']); ?></div>
                        <div><strong>Bank Acc No:</strong> <?php echo htmlspecialchars($payroll_details['bankAccNumber']); ?></div>
                        <div><strong>IFSC Code:</strong> <?php echo htmlspecialchars($payroll_details['IFSC_code']); ?></div>
                    </div>
                    <div class="payslip-grid">
                        <div class="workdetails">
                            <h4>Work Details</h4>
                            <div class="payslip-item"><span>Days Worked</span><span><?php echo htmlspecialchars($payroll_details['daysWorked']); ?></span></div>
                            <div class="payslip-item"><span>Overtime (Hours)</span><span><?php echo htmlspecialchars($payroll_details['OverTime']); ?></span></div>
                        </div>
                        <div class="earnings">
                            <h4>Earnings</h4>
                            <div class="payslip-item"><span>Basic Wages Earned</span><span><?php echo number_format($payroll_details['BasicWages_Earned_PerMonth'], 2); ?></span></div>
                            <div class="payslip-item"><span>HRA Earned</span><span><?php echo number_format($payroll_details['HRAEarned_PerMonth'], 2); ?></span></div>
                            <div class="payslip-item"><span>Overtime Earnings</span><span><?php echo number_format($payroll_details['overtime_earnings'], 2); ?></span></div>
                            <div class="payslip-item"><span>Holiday Earnings</span><span><?php echo number_format($payroll_details['National_Festival_Holidays_Earnings'], 2); ?></span></div>
                            <div class="payslip-item total"><strong>Total Earnings</strong><strong><?php echo number_format($payroll_details['Total_Earnings'], 2); ?></strong></div>
                        </div>
                        <div class="deductions">
                            <h4>Deductions</h4>
                            <div class="payslip-item"><span>ESI (0.75%)</span><span><?php echo number_format($payroll_details['ESI_Deductions'], 2); ?></span></div>
                            <div class="payslip-item"><span>EPF (12%)</span><span><?php echo number_format($payroll_details['EPF_deductions'], 2); ?></span></div>
                            <div class="payslip-item"><span>Advances</span><span><?php echo htmlspecialchars($payroll_details['advances_deductions']); ?></span></div>
                            <div class="payslip-item total"><strong>Total Deductions</strong><strong><?php echo number_format($payroll_details['Total_Deductions'], 2); ?></strong></div>
                        </div>
                    </div>
                    <div class="payslip-summary">
                        <strong>Net Payable: ₹<?php echo number_format($payroll_details['NET_Payable'], 2); ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Print View (Separate Tables) -->
            <div class="print-only">
                <h4>Salary Details for <?php echo date('F Y', mktime(0,0,0,$payroll_details['Shift_Month'], 1, $payroll_details['shift_year'])); ?></h4>
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Salary</th>
                            <th>Shifts</th>
                            <th>OT Hours</th>
                            <th>Total Shift</th>
                            <th>Rate Per Day</th>
                            <th>Actual Earnings</th>
                            <th>ESI</th>
                            <th>EPF</th>
                            <th>Prof. Tax</th>
                            <th>Actual Payable</th>
                            <th>Paid (Wage Sheet)</th>
                            <th>Advances</th>
                            <th>Fines</th>
                            <th>Balance to Bank</th>
                            <th>Total Deductions</th>
                            <th>Net Payable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo htmlspecialchars($payroll_details['Name']); ?></td>
                            <td><?php echo number_format($payroll_details['salary'], 2); ?></td>
                            <td><?php echo htmlspecialchars($payroll_details['daysWorked']); ?></td>
                            <td><?php echo htmlspecialchars($payroll_details['OverTime']); ?></td>
                            <td><?php echo number_format($payroll_details['Total_Shift'], 2); ?></td>
                            <td><?php echo number_format($payroll_details['RatePerDay'], 2); ?></td>
                            <td><?php echo number_format($payroll_details['Actual_Earnings'], 2); ?></td>
                            <td><?php echo number_format($payroll_details['ESI_Deductions'], 2); ?></td>
                            <td><?php echo number_format($payroll_details['EPF_deductions'], 2); ?></td>
                            <td>0.00</td> <!-- Placeholder for Professional Tax -->
                            <td><?php echo number_format($payroll_details['Actual_Earnings'] - $payroll_details['Total_Deductions'], 2); ?></td>
                            <td><?php echo number_format($payroll_details['NET_Payable'], 2); ?></td>
                            <td><?php echo number_format($payroll_details['advances_deductions'], 2); ?></td>
                            <td>0.00</td> <!-- Placeholder for Fines -->
                            <td><?php echo number_format($payroll_details['Actual_Paid'], 2); ?></td>
                            <td><?php echo number_format($payroll_details['Total_Deductions'], 2); ?></td>
                            <td><?php echo number_format($payroll_details['NET_Payable'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>
