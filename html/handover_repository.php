<?php
require_once 'db.php';

date_default_timezone_set('Asia/Kathmandu');

if (!function_exists('handover_e')) {
    function handover_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function ensureStaffHandoversTable()
{
    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS staff_handovers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guest_name VARCHAR(160) NOT NULL,
        room_number VARCHAR(20) NULL,
        request_type VARCHAR(80) NOT NULL,
        request_details TEXT NOT NULL,
        due_date DATE NULL,
        due_time TIME NULL,
        priority ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium',
        status ENUM('Pending', 'In Progress', 'Done', 'Cancelled') NOT NULL DEFAULT 'Pending',
        assigned_to VARCHAR(120) NULL,
        created_by VARCHAR(120) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    mysqli_query($conn, $sql);
}

function createStaffHandover($data)
{
    global $conn;
    ensureStaffHandoversTable();

    $roomNumber = $data['room_number'] !== '' ? $data['room_number'] : null;
    $dueDate = $data['due_date'] !== '' ? $data['due_date'] : null;
    $dueTime = $data['due_time'] !== '' ? $data['due_time'] : null;
    $assignedTo = $data['assigned_to'] !== '' ? $data['assigned_to'] : null;
    $createdBy = $data['created_by'] !== '' ? $data['created_by'] : null;

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO staff_handovers
            (guest_name, room_number, request_type, request_details, due_date, due_time, priority, status, assigned_to, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ssssssssss",
        $data['guest_name'],
        $roomNumber,
        $data['request_type'],
        $data['request_details'],
        $dueDate,
        $dueTime,
        $data['priority'],
        $data['status'],
        $assignedTo,
        $createdBy
    );

    return mysqli_stmt_execute($stmt);
}

function getStaffHandovers()
{
    global $conn;
    ensureStaffHandoversTable();

    $result = mysqli_query(
        $conn,
        "SELECT * FROM staff_handovers
         ORDER BY FIELD(status, 'Pending', 'In Progress', 'Done', 'Cancelled'), due_date IS NULL, due_date ASC, due_time IS NULL, due_time ASC, created_at DESC"
    );

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function updateStaffHandover($data)
{
    global $conn;
    ensureStaffHandoversTable();

    $roomNumber = $data['room_number'] !== '' ? $data['room_number'] : null;
    $dueDate = $data['due_date'] !== '' ? $data['due_date'] : null;
    $dueTime = $data['due_time'] !== '' ? $data['due_time'] : null;
    $assignedTo = $data['assigned_to'] !== '' ? $data['assigned_to'] : null;

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE staff_handovers
         SET guest_name = ?, room_number = ?, request_type = ?, request_details = ?, due_date = ?, due_time = ?, priority = ?, status = ?, assigned_to = ?
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "sssssssssi",
        $data['guest_name'],
        $roomNumber,
        $data['request_type'],
        $data['request_details'],
        $dueDate,
        $dueTime,
        $data['priority'],
        $data['status'],
        $assignedTo,
        $data['id']
    );

    return mysqli_stmt_execute($stmt);
}

function deleteStaffHandover($handoverId)
{
    global $conn;
    ensureStaffHandoversTable();

    $stmt = mysqli_prepare($conn, "DELETE FROM staff_handovers WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $handoverId);

    return mysqli_stmt_execute($stmt);
}
?>
