<?php
$servername = "localhost";
$username = "root";
$password = "newpassword";
$database = "hrapp";


$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set charset
$conn->set_charset("utf8");
?>
