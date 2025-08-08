<?php
session_start();
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - HR Portal</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div style="min-height: 10vh; width: 30%; margin: 50px auto; padding: 20px; background-color: #fff; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); display: flex; flex-direction: column;">
        <h2 style="text-align:center">Login</h2>
        <form method="POST" style="display: flex; flex-direction: column;justify-content: center; alignitems: center; margin: 0 auto;">
            <label for="username" >UserName :</label>
            <input type="text" name="username" placeholder="Username" required style="width:300px ;margin:5px 0px;"><br>
            <label for="password">Password :</label>
            <input type="password" name="password" placeholder="Password" required  style="width:300px ;margin:5px 0px;"><br>
            <button type="submit" style="width:100%" >Login</button>
        </form>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
    </div>
</body>
</html>
