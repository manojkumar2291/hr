<?php
// Include existing database connection
include 'db.php';

// Get form inputs
$employee_id = $_POST['employee_id'];
$month = $_POST['month'];
$year = $_POST['year'];
$shifts = $_POST['shifts'];
$ot_hours = $_POST['ot_hours'];

// Fetch employee data
$query = "SELECT designation, salary FROM employees WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    echo "Employee not found.";
    exit;
}

$designation = strtolower($employee['designation']);
$salary = $employee['salary'];

// Calculate total days and Sundays
$total_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$sundays = 0;
for ($day = 1; $day <= $total_days; $day++) {
    $date = "$year-$month-$day";
    if (date('w', strtotime($date)) == 0) {
        $sundays++;
    }
}

$working_days = $total_days - $sundays;

// Salary calculation for Helper
if ($designation == 'helper') {
    $rate_per_day = $salary / $working_days;
    $total_shifts = $shifts + ($ot_hours / 4);
    $final_salary = round($total_shifts * $rate_per_day, 2);

    echo "<h3>Salary Calculation Result</h3>";
    echo "Employee ID: $employee_id<br>";
    echo "Designation: Helper<br>";
    echo "Month: $month/$year<br>";
    echo "Shifts: $shifts<br>";
    echo "OT Hours: $ot_hours<br>";
    echo "Total Shifts: $total_shifts<br>";
    echo "Rate per Day: ₹" . number_format($rate_per_day, 2) . "<br>";
    echo "<strong>Final Salary: ₹" . number_format($final_salary, 2) . "</strong>";
} else {
    echo "Salary calculation logic not defined for designation: $designation";
}

$conn->close();
?>
