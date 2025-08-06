<?php
$servername = "localhost";
$username = "root";
$password = "1234";
$database = "hrapp";
$port = 3306;

$conn = new mysqli($servername, $username, $password, $database, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set charset
$conn->set_charset("utf8");
?>
