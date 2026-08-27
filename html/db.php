<?php

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

date_default_timezone_set('Asia/Kathmandu');

// PHP 8.1+ makes mysqli throw exceptions (mysqli_sql_exception) on errors by default.
// This codebase's repository functions are written to check return values instead
// (e.g. "if (!mysqli_stmt_execute($stmt))"), so we switch back to the classic
// "return false on error" behavior to avoid uncaught fatal errors crashing the page.
mysqli_report(MYSQLI_REPORT_OFF);

$dbHost = 'db';
$dbUser = 'root';
$dbPass = 'rootpassword';
$dbName = 'my_database';

// $dbHost = 'hotelmysql.mysql.database.azure.com';
// $dbUser = 'hoteladmin';
// $dbPass = '#BBIS2023';
// $dbName = 'my_database';

$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

require_once __DIR__ . '/bootstrap.php';
