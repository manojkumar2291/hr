<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>HR Portal Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h1>Welcome, <?php echo $_SESSION['username']; ?>!</h1>
        <div class="card-container">
            <?php if ($role == 'employee') { ?>
                <a href="timesheet.php" class="card">Submit Timesheet</a>
            <?php } else { ?>
                <a href="payroll.php" class="card">Payroll Calculation</a>
                <a href="payslip.php" class="card">Generate Payslip</a>
                <a href="bank-transfer.php" class="card">Bank Transfer File</a>
            <?php } ?>
            <a href="logout.php" class="card logout">Logout</a>
        </div>
    </div>
</body>
</html>
