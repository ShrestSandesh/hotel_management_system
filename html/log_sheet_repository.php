<?php

require_once __DIR__ . '/db.php';

function createLogSheet($title, $description, $writtenBy)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO log_sheets (title, description, written_by) VALUES (?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'sss', $title, $description, $writtenBy);

    return mysqli_stmt_execute($stmt);
}

function getLogSheets()
{
    global $conn;

    $result = mysqli_query($conn, "SELECT * FROM log_sheets ORDER BY created_at DESC, id DESC");

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function updateLogSheet($id, $title, $description)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "UPDATE log_sheets SET title = ?, description = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssi', $title, $description, $id);

    return mysqli_stmt_execute($stmt);
}

function deleteLogSheet($id)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "DELETE FROM log_sheets WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);

    return mysqli_stmt_execute($stmt);
}

if (!function_exists('log_e')) {
    function log_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
