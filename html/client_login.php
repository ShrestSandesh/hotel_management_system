<?php
session_start();
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_repository.php';

$message = '';
$redirectTarget = isset($_GET['redirect']) ? h($_GET['redirect']) : 'client_home.php';

if (!empty($_GET['redirect'])) {
    $message = "<div class='client-alert error'>Please log in first to continue.</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $message = "<div class='client-alert error'>Please fill in both email and password.</div>";
    } else {
        $user = authenticateUser($email, $password, 'client');

        if ($user) {
            $_SESSION['client_user'] = $user['email'];
            $_SESSION['client_user_name'] = $user['name'];
            $_SESSION['client_user_id'] = $user['id'];
            $_SESSION['client_user_role'] = $user['role'] ?? 'client';
            $redirect = $_POST['redirect'] ?? 'client_home.php';
            header('Location: ' . $redirect);
            exit;
        }

        $message = "<div class='client-alert error'>Invalid email or password.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel - Client Login</title>
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
            <h1>Client Login</h1>
            <p class="subtext">Sign in to view your bookings and submit a reservation.</p>
            <?= $message; ?>
            <form method="post">
                <input type="hidden" name="redirect" value="<?= $redirectTarget; ?>">
                <div class="client-input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="client-input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Log In</button>
            </form>
            <p style="margin-top:16px; text-align:center;">No account yet? <a href="client_signup.php">Create one</a>
            </p>
        </div>
    </main>
</body>

</html>