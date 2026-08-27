<?php

require_once __DIR__ . '/db.php';

function generateTicketNumber()
{
    return 'TKT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function createComplaintTicket($data)
{
    global $conn;

    $ticketNumber = generateTicketNumber();
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO complaint_tickets
            (ticket_number, ticket_title, room_number, complaint_description, priority, status)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        'ssssss',
        $ticketNumber,
        $data['ticket_title'],
        $data['room_number'],
        $data['complaint_description'],
        $data['priority'],
        $data['status']
    );

    return mysqli_stmt_execute($stmt) ? $ticketNumber : false;
}

function getComplaintTickets()
{
    global $conn;

    $result = mysqli_query($conn, "SELECT * FROM complaint_tickets ORDER BY created_at DESC, id DESC");

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function updateComplaintTicket($data)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE complaint_tickets SET ticket_title = ?, room_number = ?, complaint_description = ?, priority = ?, status = ? WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        'sssssi',
        $data['ticket_title'],
        $data['room_number'],
        $data['complaint_description'],
        $data['priority'],
        $data['status'],
        $data['id']
    );

    return mysqli_stmt_execute($stmt);
}

function deleteComplaintTicket($ticketId)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "DELETE FROM complaint_tickets WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $ticketId);

    return mysqli_stmt_execute($stmt);
}

function getHighPriorityOpenTickets()
{
    global $conn;

    $result = mysqli_query(
        $conn,
        "SELECT * FROM complaint_tickets WHERE priority = 'High' AND status != 'Resolved' ORDER BY created_at DESC"
    );

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
