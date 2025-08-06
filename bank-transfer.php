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
    <title>Bank Transfer File</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h2>Bank Transfer File</h2>
        <p>This module will generate bank transfer files for salary disbursement.</p>
    </div>
</body>
</html>
