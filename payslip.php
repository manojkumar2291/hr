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
    <title>Generate Payslip</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h2>Generate Payslip</h2>
        <p>This module will generate payslips for employees.</p>
    </div>
</body>
</html>
