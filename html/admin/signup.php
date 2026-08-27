<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once '../auth_repository.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $message = "<div class='error'>Please fill all fields!</div>";
    } elseif ($password !== $confirm) {
        $message = "<div class='error'>Password confirmation does not match!</div>";
    } elseif (findUserByEmail($email)) {
        $message = "<div class='error'>Email is already registered!</div>";
    } elseif (createUser($name, $email, $password, 'admin')) {
        header('Location: login.php?registered=1');
        exit;
    } else {
        $message = "<div class='error'>Could not create account. Please try again.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HotelMS Signup</title>
    <link rel="stylesheet" href="./admin_style.css?v=20260617">
</head>
<body class="page-login">
    <div class="login-box">
        <div class="icon">📝</div>
        <?php echo $message; ?>
        <form method="post">
            <label>Name</label>
            <input type="text" name="name" required>
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
            <button type="submit">SIGN UP</button>
        </form>
        <p style="margin-top:16px;text-align:center;font-size:14px;">
            Already have an account? <a href="login.php">Login</a>
        </p>
    </div>
</body>
</html>
