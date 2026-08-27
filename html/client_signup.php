<?php
session_start();
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_repository.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $message = "<div class='client-alert error'>Please complete all fields.</div>";
    } elseif ($password !== $confirmPassword) {
        $message = "<div class='client-alert error'>Passwords do not match.</div>";
    } else {
        $created = createUser($name, $email, $password, 'client');
        if ($created) {
            $_SESSION['client_user'] = $email;
            $_SESSION['client_user_name'] = $name;
            $_SESSION['client_user_id'] = findUserByEmail($email)['id'];
            $_SESSION['client_user_role'] = 'client';
            header('Location: client_home.php');
            exit;
        }

        $message = "<div class='client-alert error'>An account with that email already exists.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel - Client Sign Up</title>
    <link rel="stylesheet" href="./client_side.css?v=20260716">
    <style>
        .client-auth-card {
            max-width: 460px;
            margin: 60px auto;
            padding: 32px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(15, 23, 42, 0.12);
        }

        .client-auth-card h1 {
            margin-bottom: 8px;
            font-size: 28px;
        }

        .client-auth-card .subtext {
            color: #64748b;
            margin-bottom: 24px;
        }

        .client-auth-card form {
            display: grid;
            gap: 14px;
        }

        .client-auth-card button {
            border: none;
            padding: 12px 18px;
            border-radius: 999px;
            background: #2563eb;
            color: #fff;
            cursor: pointer;
            font-weight: 700;
        }

        .client-auth-card a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>

<body class="page-client">
    <main class="client-main">
        <div class="client-auth-card">
            <h1>Create Client Account</h1>
            <p class="subtext">Register to manage your reservations.</p>
            <?= $message; ?>
            <form method="post">
                <div class="client-input-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="client-input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="client-input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="client-input-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit">Create Account</button>
            </form>
            <p style="margin-top:16px; text-align:center;">Already have an account? <a href="client_login.php">Log
                    in</a></p>
        </div>
    </main>
</body>

</html>