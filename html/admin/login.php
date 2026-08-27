<?php
session_start();
require_once '../auth_repository.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $message = "<div class='error'>Please fill all fields!</div>";
    } else {
        $user = findUserByEmail($email);

        if ($user && ($user['password'] ?? '') === $password) {
            $role = $user['role'] ?? 'client';
            if ($role === 'admin' || $role === 'staff') {
                $_SESSION['user'] = $user['email'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $role;
                header('Location: dashboard.php');
                exit;
            } else {
                $message = "<div class='error'>This account does not have admin/staff privileges.</div>";
            }
        } else {
            $message = "<div class='error'>Invalid email or password!</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>HotelMS Login</title>
    <link rel="stylesheet" href="./admin_style.css?v=20260617">
</head>

<body class="page-login">
    <div class="login-box">
        <div class="icon">👤</div>
        <?php echo $message; ?>
        <form method="post">
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <button type="submit">LOGIN</button>
        </form>
        <p style="margin-top:16px;text-align:center;font-size:14px;">
            No account? <a href="signup.php">Sign up</a>
        </p>
        <!-- <p style="margin-top:8px;text-align:center;font-size:13px;color:#64748b;">
            Default: admin@hotel.com / admin123
        </p> -->
    </div>
</body>

</html>