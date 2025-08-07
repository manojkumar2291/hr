<?php
require 'db.php';

// SQL to create users table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql_users) === TRUE) {
    echo "Table 'users' created successfully or already exists.\n";
} else {
    echo "Error creating table 'users': " . $conn->error . "\n";
}

// SQL to create user_details table
$sql_user_details = "CREATE TABLE IF NOT EXISTS user_details (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(100) UNIQUE,
    phone_number VARCHAR(20),
    address TEXT,
    date_of_birth DATE,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

if ($conn->query($sql_user_details) === TRUE) {
    echo "Table 'user_details' created successfully or already exists.\n";
} else {
    echo "Error creating table 'user_details': " . $conn->error . "\n";
}

// Insert a sample user
$username = 'testuser';
$password = 'password123';
$role = 'employee';

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check if the user already exists
$stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt_check->bind_param("s", $username);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows === 0) {
    // Insert the new user
    $stmt_insert = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt_insert->bind_param("sss", $username, $hashed_password, $role);

    if ($stmt_insert->execute() === TRUE) {
        echo "Sample user 'testuser' inserted successfully.\n";
    } else {
        echo "Error inserting sample user: " . $stmt_insert->error . "\n";
    }
    $stmt_insert->close();
} else {
    echo "Sample user 'testuser' already exists.\n";
}

$stmt_check->close();
$conn->close();
?>
