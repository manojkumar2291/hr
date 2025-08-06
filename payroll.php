<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'management') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payroll Calculation</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h2>Payroll Calculation</h2>
        <p>This module will calculate payroll based on timesheet data.</p>
    </div>
</body>
</html>
