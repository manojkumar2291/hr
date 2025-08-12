<?php
require_once 'header.php';
require_once 'db.php';

$dashboard_data = null;
$message = '';
$is_error = false;
$selected_month = date('n'); // Default to current month
$selected_year = date('Y'); // Default to current year

// Try to find the last entry month/year to set as default
try {
    $last_entry_res = $conn->query("SELECT shift_year, Shift_Month FROM salarycal_table ORDER BY shift_year DESC, Shift_Month DESC LIMIT 1");
    if ($last_entry_res->num_rows > 0) {
        $last_entry = $last_entry_res->fetch_assoc();
        $selected_month = $last_entry['Shift_Month'];
        $selected_year = $last_entry['shift_year'];
    }
} catch (Exception $e) {
    // Silently fail, defaults are already set
}


// Handle form submission to view a specific month's report
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_month = $_POST['report_month'];
    $selected_year = $_POST['report_year'];
}

// Fetch dashboard data for the selected period
try {
    $stmt = $conn->prepare(
        "SELECT
            SUM(OverTime) as total_overtime_hours,
            SUM(overtime_earnings) as total_overtime_spend,
            SUM(Nationa_Festival_Holidays_Earnings) as total_holiday_spend,
            SUM(daysWorked) as total_days_worked,
            sum(total_earnings) as total_earnings,
            sum(actual_earnings) as total_actual_earnings
         FROM SalaryCal_Table
         WHERE Shift_Month = ? AND shift_year = ?"
    );
    $stmt->bind_param("ii", $selected_month, $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $dashboard_data = $result->fetch_assoc();
    } else {
        $message = "No data found for the selected period to generate a dashboard.";
        $is_error = true;
    }

} catch (Exception $e) {
    $message = "An error occurred while fetching dashboard data: " . $e->getMessage();
    $is_error = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>HR Dashboard</h2>
        
        <?php if ($message): ?>
            <p class="message <?php if ($is_error) echo 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </p>
        <?php endif; ?>

        <form action="" method="post" class="payroll-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="report_month">Select Month:</label>
                    <select name="report_month" id="report_month" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo ($selected_month == $m) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="report_year">Select Year:</label>
                    <select name="report_year" id="report_year" required>
                        <?php 
                        $current_year = date('Y');
                        for ($y = $current_year + 1; $y >= $current_year - 5; $y--): 
                        ?>
                            <option value="<?php echo $y; ?>" <?php echo ($selected_year == $y) ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="view_dashboard">View Dashboard</button>
        </form>

        <?php if ($dashboard_data && $dashboard_data['total_overtime_hours'] !== null): ?>
            <div class="dashboard-container"> 
                <h3>Dashboard for <?php echo date('F', mktime(0,0,0,$selected_month)) . ' ' . $selected_year; ?></h3>
                <div class="dashboard-grid" >
                    <div class="dashboard-card">
                        <label>Total Overtime Hours</label>
                        <span><?php echo number_format($dashboard_data['total_overtime_hours'], 2); ?> hrs</span>
                    </div>
                    <div class="dashboard-card">
                        <label>Amount Spent on Overtime</label>
                        <span>₹<?php echo number_format($dashboard_data['total_overtime_spend'], 2); ?></span>
                    </div>
                    <div class="dashboard-card">
                        <label>Total Holiday Earnings</label>
                        <span>₹<?php echo number_format($dashboard_data['total_holiday_spend'], 2); ?></span>
                    </div>
                    <div class="dashboard-card">
                    <label>Total Attended Days of all employees </label>
                        <span><?php echo number_format($dashboard_data['total_days_worked']); ?></span>
                    </div>
                    <div class="dashboard-card">
                    <label>Total Earnings as per government all employees </label>
                        <span>₹<?php echo number_format($dashboard_data['total_earnings']); ?></span>
                    </div>
                    <div class="dashboard-card">
                    <label>Total Actual Earnings of all employees </label>
                        <span>₹<?php echo number_format($dashboard_data['total_actual_earnings']); ?></span>
                    </div>

                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
