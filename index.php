<?php
require_once 'header.php';
?>
<h1>Welcome, <?php echo $_SESSION['username']; ?>!</h1>
<div class="card-container">
    <?php if ($role == 'employee') { ?>
        <a href="timesheet.php" class="card">Submit Timesheet</a>
    <?php } else { ?>
        <a href="add_employee.php" class="card">Add Employee</a>
        <a href="payroll.php" class="card">Payroll Calculation</a>
        <a href="payslip.php" class="card">Generate Payslip</a>
        <a href="bank-transfer.php" class="card">Bank Transfer File</a>
    <?php } ?>
    <a href="logout.php" class="card logout">Logout</a>
</div>
<?php
require_once 'footer.php';
?>
