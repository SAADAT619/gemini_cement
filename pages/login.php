<?php
session_start();
include '../config/database.php';
include '../core/functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitizeInput($_POST['email']);
    $password = sanitizeInput($_POST['password']);

    $sql = "SELECT * FROM users WHERE email = '$email' AND password = '$password'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $_SESSION['user_email'] = $email;
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-header">
            <img src="../assets/images/logo.png" alt="Shop Logo" class="logo">
            <h1>Cement Shop Login</h1>
        </div>
        <form method="post" class="login-form">
            <?php if (isset($error)) { echo '<p class="error">' . $error . '</p>'; } ?>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
            <a href="forgot_password.php">Forgot Password?</a>
            <a href="register.php">Register</a>
        </form>
    </div>
</body>
</html>