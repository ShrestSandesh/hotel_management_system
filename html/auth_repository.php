<?php

require_once __DIR__ . '/db.php';

function findUserByEmail($email)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_assoc($result) : null;
}

function authenticateUser($email, $password, $role = null)
{
    $user = findUserByEmail(trim($email));

    if (!$user) {
        return null;
    }

    if (($user['password'] ?? '') !== $password) {
        return null;
    }

    $userRole = $user['role'] ?? 'client';
    if ($role !== null && $userRole !== $role) {
        return null;
    }

    return $user;
}

function createUser($name, $email, $password, $role = 'client')
{
    global $conn;

    if (findUserByEmail($email)) {
        return false;
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $password, $role);

    return mysqli_stmt_execute($stmt);
}

function isAdmin()
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isStaff()
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'staff';
}

function requireAdminLogin()
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }

    $role = $_SESSION['user_role'] ?? '';
    if (!isset($_SESSION['user']) || ($role !== 'admin' && $role !== 'staff')) {
        header('Location: login.php');
        exit;
    }
}

function getAdminName()
{
    return $_SESSION['user_name'] ?? $_SESSION['user'] ?? 'User';
}
