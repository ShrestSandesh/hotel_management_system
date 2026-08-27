<?php
require_once 'db.php';

date_default_timezone_set('Asia/Kathmandu');

if (!function_exists('guest_e')) {
    function guest_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function ensureGuestStaysTable()
{
    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS guest_stays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reservation_number VARCHAR(24) NOT NULL UNIQUE,
        full_name VARCHAR(160) NOT NULL,
        room_type VARCHAR(80) NOT NULL,
        room_number VARCHAR(20) NOT NULL,
        checkin_date DATE NOT NULL,
        checkout_date DATE NOT NULL,
        contact_number VARCHAR(30) NULL,
        email VARCHAR(120) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    mysqli_query($conn, $sql);
}

function createGuestStay($data)
{
    global $conn;
    ensureGuestStaysTable();

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO guest_stays
            (reservation_number, full_name, room_type, room_number, checkin_date, checkout_date, contact_number, email)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ssssssss",
        $data['reservation_number'],
        $data['full_name'],
        $data['room_type'],
        $data['room_number'],
        $data['checkin_date'],
        $data['checkout_date'],
        $data['contact_number'],
        $data['email']
    );

    return mysqli_stmt_execute($stmt);
}

function getGuestStaysUpToToday()
{
    global $conn;
    ensureGuestStaysTable();

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM guest_stays
         WHERE checkin_date <= CURDATE()
         ORDER BY checkin_date DESC, id DESC"
    );
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function updateGuestStay($data)
{
    global $conn;
    ensureGuestStaysTable();

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE guest_stays
         SET full_name = ?, room_type = ?, room_number = ?, checkin_date = ?, checkout_date = ?, contact_number = ?, email = ?
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "sssssssi",
        $data['full_name'],
        $data['room_type'],
        $data['room_number'],
        $data['checkin_date'],
        $data['checkout_date'],
        $data['contact_number'],
        $data['email'],
        $data['id']
    );

    return mysqli_stmt_execute($stmt);
}

function deleteGuestStay($guestStayId)
{
    global $conn;
    ensureGuestStaysTable();

    $stmt = mysqli_prepare($conn, "DELETE FROM guest_stays WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $guestStayId);

    return mysqli_stmt_execute($stmt);
}
