<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>HR Portal</title>
    <link rel="stylesheet" href="assets/style.css">
    <nav>
        <ul>
            <li><a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Home</a></li>
            <?php if ($role == 'admin'): ?>
                <li><a href="add_employee.php" class="<?php echo ($current_page == 'add_employee.php') ? 'active' : ''; ?>">Add Employee</a></li>
                <li><a href="payroll.php" class="<?php echo ($current_page == 'payroll.php') ? 'active' : ''; ?>">Payroll</a></li>
                <!-- <li><a href="payslip.php" class="<?php echo ($current_page == 'payslip.php') ? 'active' : ''; ?>">Payslip</a></li> -->
                <li><a href="bank-transfer.php" class="<?php echo ($current_page == 'bank-transfer.php') ? 'active' : ''; ?>">Bank Transfer</a></li>
            <?php else: ?>
                <li><a href="timesheet.php" class="<?php echo ($current_page == 'timesheet.php') ? 'active' : ''; ?>">Timesheet</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</head>
<body>
<div class="container">
