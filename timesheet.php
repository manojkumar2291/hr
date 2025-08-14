<?php
require_once 'header.php';
require_once 'db.php';

$employees = [];
$message = '';
$is_error = false;
$calculated_results = null; // To hold calculated data for display
$form_data = []; // To hold user's input after submission
function getWorkingDays($year, $month) {
    $totalDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $sundays = 0;
    for ($day = 1; $day <= $totalDays; $day++) {
        // The 'w' format character returns 0 for Sunday
        if (date('w', strtotime("$year-$month-$day")) == 0) {
            $sundays++;
        }
    }
    return $totalDays - $sundays;
}

// Fetch all employees to populate the dropdown
try {
    $emp_result = $conn->query("SELECT EMPID, Name FROM employeebasicdetails");
    while ($row = $emp_result->fetch_assoc()) {
        $employees[] = $row;
    }
} catch (Exception $e) {
    $message = "Error fetching employees: " . $e->getMessage();
    $is_error = true;
}

// Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Store submitted form data to repopulate the form
    $form_data = $_POST;

    try {
        $empid = $_POST['empid'];
        $shift_month = $_POST['shift_month'];
        $shift_year = $_POST['shift_year'];
        
        // --- Check for existing record BEFORE calculating ---
        $stmt_check = $conn->prepare("SELECT SalaryID FROM salarycal_table WHERE EMPID = ? AND Shift_Month = ? AND shift_year = ?");
        $stmt_check->bind_param("sii", $empid, $shift_month, $shift_year);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception("A salary record for this employee for the selected month and year already exists.");
        }

        // --- Start Calculations ---
        $working_days = getWorkingDays($shift_year, $shift_month);
        $work_location = $_POST['work_location'] ;
        $days_attended = (float)$_POST['days_attended'];
        $overtime = (float)$_POST['overtime'];
        $festival_days = (float)$_POST['festival_days'];
        $advances_deductions = (float)$_POST['advances_deductions'];

        // Fetch employee details including designation and govt_salary
        $stmt_emp = $conn->prepare("SELECT e.Name, e.salary, e.salType,e.professionaltax, w.govt_salary FROM employeebasicdetails e JOIN wages w ON e.Designation = w.Designation WHERE e.EMPID = ?");
        $stmt_emp->bind_param("s", $empid);
        $stmt_emp->execute();
        $emp_details = $stmt_emp->get_result()->fetch_assoc();

        if (!$emp_details) {
            throw new Exception("Employee not found or designation not linked correctly.");
        }

        $salary = (float)$emp_details['salary'];
        $sal_type = $emp_details['salType'];
        $total_per_month = (float)$emp_details['govt_salary'];
        $professionaltax = (float)$emp_details['professionaltax'];

        // Calculate Day Rate
        $day_rate = 0;
        if (strtolower($sal_type) == "daily") {
            $day_rate = $salary;
        } elseif (strtolower($sal_type) == "monthly" && $working_days > 0) {
            $day_rate = $salary / $working_days;
        }

        // Perform all detailed calculations
        $hra_per_month = ($total_per_month / 100) * 40;
        $basic_per_month = ($total_per_month / 100) * 60;
        $holidays_earnings = ($working_days > 0) ? ($total_per_month / $working_days) * $festival_days : 0;
        $basic_earned_per_month = ($working_days > 0) ? ($basic_per_month / $working_days) * ($days_attended + $festival_days) : 0;
        $hra_earned_per_month = ($working_days > 0) ? ($hra_per_month / $working_days) * ($days_attended + $festival_days) : 0;
        $overtime_earnings = ($working_days > 0) ? ($total_per_month / $working_days) * ($overtime / 4) : 0;
        
        $total_shift = $days_attended + $festival_days + ($overtime / 4);
        $actual_earnings = $day_rate * $total_shift;
        
        $total_earnings = $basic_earned_per_month + $hra_earned_per_month + $holidays_earnings ;

        $esi_deductions = ($total_earnings / 100) * 0.75;
        $epf_deductions = ($basic_earned_per_month / 100) * 12;
        $total_deductions = $advances_deductions + $epf_deductions + $esi_deductions+$professionaltax;
        
        $net_payable = $total_earnings - $total_deductions;
        $actual_paid = abs($actual_earnings - $net_payable);
        
        $salary_id = "SAL" . substr($shift_year, -2) . str_pad($shift_month, 2, '0', STR_PAD_LEFT). substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4);

        // --- Save to Database ---
        $stmt_insert = $conn->prepare(
            "INSERT INTO salarycal_table (
                SalaryID, EMPID, Shift_Month, shift_year, daysWorked, WorkingDays,working_location, OverTime, FestivalDays, 
                Total_Shift, RatePerDay, HRA_Per_Month, Basic_Per_Month, Total_Per_Month, 
                Nationa_Festival_Holidays_Earnings, BasicWages_Earned_PerMonth, HRAEarned_PerMonth,overtime_earnings, 
                Actual_Earnings, Total_Earnings, ESI_Deductions, EPF_deductions, Total_Deductions, 
                advances_deductions, NET_Payable, Actual_Paid
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?, ?, ?, ?,?, ?)"
        );
        
        $stmt_insert->bind_param(
            "ssiiidsddddddidddddddddddd",
            $salary_id, $empid, $shift_month, $shift_year, 
            $days_attended, $working_days,$work_location, $overtime, $festival_days, 
            $total_shift, $day_rate, $hra_per_month, $basic_per_month, 
            $total_per_month, $holidays_earnings, $basic_earned_per_month, 
            $hra_earned_per_month,$overtime_earnings, $actual_earnings, $total_earnings, 
            $esi_deductions, $epf_deductions, $total_deductions, 
            $advances_deductions, $net_payable, $actual_paid
        );

        if ($stmt_insert->execute()) {
            $message = "Salary for " . $empid . " saved successfully!";
            // Store results for display after successful save
            $calculated_results = compact(
                'total_earnings', 'total_deductions', 'net_payable', 'actual_paid'
            );
            $form_data = []; // Clear form for next entry
        } else {
            throw new Exception("Database Insert Error: " . $stmt_insert->error);
        }

    } catch (Exception $e) {
        $message = "An error occurred: " . $e->getMessage();
        $is_error = true;
    }
}
?>
<h2>Calculate Employee Salary</h2>

<?php if ($message): ?>
    <p class="message <?php if ($is_error) echo 'error'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </p>
<?php endif; ?>

<form action="" method="post">
    <div class="form-grid">
        <div class="form-group">
            <label for="empid">Employee:</label>
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
            <label for="shift_month">Month:</label>
            <select name="shift_month" id="shift_month" required>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo (isset($form_data['shift_month']) && $form_data['shift_month'] == $m) ? 'selected' : (($m == date('n')) && !isset($form_data['shift_month']) ? 'selected' : ''); ?>>
                        <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="shift_year">Year:</label>
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

        <div class="form-group">
            <label for="working_days">Working Days in Month:</label>
            <select name="work_location" id="work_location" required>
                <option value="">-- Select Work Location --</option>
                <?php
                // Fetch work locations from a separate table (e.g., worklocations)
                $locations_result = $conn->query("SELECT id, LocationName FROM worklocations ");
                while ($loc = $locations_result->fetch_assoc()):
                    $selected = (isset($form_data['work_location']) && $form_data['work_location'] == $loc['id']) ? 'selected' : '';
                ?>
                    <option value="<?php echo htmlspecialchars($loc['id']); ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($loc['LocationName']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="days_attended">Days Attended:</label>
            <input type="number" step="0.5" name="days_attended" id="days_attended" value="<?php echo htmlspecialchars($form_data['days_attended'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="overtime">Overtime (Hours):</label>
            <input type="number" step="0.5" name="overtime" id="overtime" value="<?php echo htmlspecialchars($form_data['overtime'] ?? '0'); ?>">
        </div>

        <div class="form-group">
            <label for="festival_days">Festival Days:</label>
            <input type="number" step="0.5" name="festival_days" id="festival_days" value="<?php echo htmlspecialchars($form_data['festival_days'] ?? '0'); ?>">
        </div>

        <div class="form-group">
            <label for="advances_deductions">Advances/Deductions:</label>
            <input type="number" step="0.01" name="advances_deductions" id="advances_deductions" value="<?php echo htmlspecialchars($form_data['advances_deductions'] ?? '0'); ?>">
        </div>
    </div>

    <button type="submit" name="calculate_and_save">Calculate & Save Salary</button>
</form>

<!-- Display Calculated Results After Save -->
<?php if ($calculated_results && !$is_error): ?>
    <div class="results-container">
        <h3>Last Saved Salary Details</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Total Earnings:</label>
                <input type="text" value="₹<?php echo number_format($calculated_results['total_earnings'], 2); ?>" readonly>
            </div>
             <div class="form-group">
                <label>Total Deductions:</label>
                <input type="text" value="₹<?php echo number_format($calculated_results['total_deductions'], 2); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Net Payable:</label>
                <input type="text" value="₹<?php echo number_format($calculated_results['net_payable'], 2); ?>" readonly>
            </div>
             <div class="form-group">
                <label>Actual Paid:</label>
                <input type="text" value="₹<?php echo number_format($calculated_results['actual_paid'], 2); ?>" readonly>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php
require_once 'footer.php';
?>
