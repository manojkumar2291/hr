<?php
$plain_password = 'password123'; // <-- Change this to the password you want to hash
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
echo $hashed_password;
?>