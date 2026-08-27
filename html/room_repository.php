<?php

require_once __DIR__ . '/db.php';

function getRoomTypes()
{
    global $conn;

    $result = mysqli_query($conn, "SELECT * FROM room_types ORDER BY name ASC");

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function getRoomTypeById($id)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "SELECT * FROM room_types WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_assoc($result) : null;
}

function getRoomById($id)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.*, rt.name AS room_type_name, rt.max_occupancy, rt.rate_per_night
         FROM rooms r
         JOIN room_types rt ON rt.id = r.room_type_id
         WHERE r.id = ? AND r.is_active = 1
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_assoc($result) : null;
}

function getRoomsByTypeId($roomTypeId)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.*, rt.name AS room_type_name, rt.max_occupancy, rt.rate_per_night
         FROM rooms r
         JOIN room_types rt ON rt.id = r.room_type_id
         WHERE r.room_type_id = ? AND r.is_active = 1
         ORDER BY r.room_number ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $roomTypeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function getAllRoomsWithTypes()
{
    global $conn;

    $result = mysqli_query(
        $conn,
        "SELECT r.*, rt.name AS room_type_name, rt.max_occupancy, rt.rate_per_night
         FROM rooms r
         JOIN room_types rt ON rt.id = r.room_type_id
         WHERE r.is_active = 1
         ORDER BY rt.name ASC, r.room_number ASC"
    );

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function getRoomNumbersList()
{
    global $conn;

    $result = mysqli_query($conn, "SELECT room_number FROM rooms WHERE is_active = 1 ORDER BY room_number ASC");

    return $result ? array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'room_number') : [];
}

function createRoomType($name, $maxOccupancy, $description = '', $ratePerNight = 0.0)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "INSERT INTO room_types (name, max_occupancy, description, rate_per_night) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sisd', $name, $maxOccupancy, $description, $ratePerNight);

    if (!mysqli_stmt_execute($stmt)) {
        return false;
    }

    return mysqli_insert_id($conn);
}

function updateRoomType($id, $name, $maxOccupancy, $description = '', $ratePerNight = 0.0)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE room_types SET name = ?, max_occupancy = ?, description = ?, rate_per_night = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'sisdi', $name, $maxOccupancy, $description, $ratePerNight, $id);

    return mysqli_stmt_execute($stmt);
}

function deleteRoomType($id)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "DELETE FROM room_types WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);

    return mysqli_stmt_execute($stmt);
}

function createRoom($roomTypeId, $roomNumber)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "INSERT INTO rooms (room_type_id, room_number) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, 'is', $roomTypeId, $roomNumber);

    if (!mysqli_stmt_execute($stmt)) {
        return false;
    }

    return mysqli_insert_id($conn);
}

function updateRoom($id, $roomTypeId, $roomNumber)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "UPDATE rooms SET room_type_id = ?, room_number = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'isi', $roomTypeId, $roomNumber, $id);

    return mysqli_stmt_execute($stmt);
}

function deleteRoom($id)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "UPDATE rooms SET is_active = 0 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);

    return mysqli_stmt_execute($stmt);
}

function updateRoomStatus($id, $status)
{
    global $conn;

    $allowedStatuses = ['Available', 'Occupied', 'Dirty', 'Out of Order'];
    if (!in_array($status, $allowedStatuses, true)) {
        return false;
    }

    $stmt = mysqli_prepare($conn, "UPDATE rooms SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);

    return mysqli_stmt_execute($stmt);
}

function getRoomStatusCounts()
{
    global $conn;

    $counts = [
        'Available' => 0,
        'Occupied' => 0,
        'Dirty' => 0,
        'Out of Order' => 0,
        'Total' => 0
    ];

    $result = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM rooms WHERE is_active = 1 GROUP BY status");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['count'];
            }
            $counts['Total'] += (int) $row['count'];
        }
    }

    return $counts;
}

function getRoomTypesWithRoomsForClient()
{
    global $conn;

    $types = getRoomTypes();
    $output = [];

    foreach ($types as $type) {
        $rooms = getRoomsByTypeId((int) $type['id']);
        $output[] = [
            'id' => (int) $type['id'],
            'name' => $type['name'],
            'max_occupancy' => (int) $type['max_occupancy'],
            'description' => $type['description'],
            'rate_per_night' => (float) ($type['rate_per_night'] ?? 0),
            'rooms' => array_map(static function ($room) {
                return $room['room_number'];
            }, $rooms)
        ];
    }

    return $output;
}

function countTotalRooms()
{
    global $conn;

    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM rooms WHERE is_active = 1");
    $row = mysqli_fetch_assoc($result);

    return (int) ($row['total'] ?? 0);
}

/**
 * The single source of truth for which room numbers belong to which room type.
 * Used both to seed a fresh database (bootstrap.php) and to reconcile an
 * existing database via reconcileRoomNumbering() below.
 */
function getStandardRoomPlan()
{
    return [
        'Heritage Twin' => ['103', '105', '106'],
        'Heritage Queen' => ['104'],
        'Heritage Family' => ['201', '203', '303'],
        'Heritage Deluxe' => ['202', '302'],
        'Durbar Suite' => ['301', '401'],
        'Legendary Suite' => ['402']
    ];
}

/**
 * Brings the rooms table in line with getStandardRoomPlan():
 * - Any room number not in the plan is removed (or deactivated if it still
 *   has reservation history and can't be deleted because of the foreign key).
 * - Any room number in the plan that's missing is created.
 * - Any room number that exists but is linked to the wrong room type (or was
 *   previously deactivated) is corrected/reactivated.
 *
 * Safe to run more than once; it only changes rows that don't already match.
 */
function reconcileRoomNumbering()
{
    global $conn;

    $plan = getStandardRoomPlan();

    $desired = [];
    foreach ($plan as $typeName => $numbers) {
        foreach ($numbers as $roomNumber) {
            $desired[$roomNumber] = $typeName;
        }
    }

    $typeIds = [];
    foreach (array_keys($plan) as $typeName) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM room_types WHERE name = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $typeName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;

        $typeIds[$typeName] = $row ? (int) $row['id'] : createRoomType($typeName, 2, '');
    }

    $existingRooms = [];
    $result = mysqli_query($conn, "SELECT id, room_number, room_type_id, is_active FROM rooms");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $existingRooms[$row['room_number']] = $row;
        }
    }

    $summary = ['removed' => [], 'kept_but_deactivated' => [], 'added' => [], 'reassigned' => []];

    foreach ($existingRooms as $roomNumber => $room) {
        if (isset($desired[$roomNumber])) {
            continue;
        }

        $deleteStmt = mysqli_prepare($conn, "DELETE FROM rooms WHERE id = ?");
        mysqli_stmt_bind_param($deleteStmt, 'i', $room['id']);

        if (mysqli_stmt_execute($deleteStmt)) {
            $summary['removed'][] = $roomNumber;
        } else {
            // Foreign key restriction: this room number still has reservation
            // history attached to it, so it can't be hard-deleted. Deactivate
            // it instead so it disappears from booking screens.
            $deactivateStmt = mysqli_prepare($conn, "UPDATE rooms SET is_active = 0 WHERE id = ?");
            mysqli_stmt_bind_param($deactivateStmt, 'i', $room['id']);
            mysqli_stmt_execute($deactivateStmt);
            $summary['kept_but_deactivated'][] = $roomNumber;
        }
    }

    foreach ($desired as $roomNumber => $typeName) {
        $typeId = $typeIds[$typeName];

        if (!isset($existingRooms[$roomNumber])) {
            createRoom($typeId, $roomNumber);
            $summary['added'][] = $roomNumber . ' (' . $typeName . ')';
            continue;
        }

        $room = $existingRooms[$roomNumber];
        if ((int) $room['room_type_id'] !== $typeId || (int) $room['is_active'] !== 1) {
            $updateStmt = mysqli_prepare($conn, "UPDATE rooms SET room_type_id = ?, is_active = 1 WHERE id = ?");
            mysqli_stmt_bind_param($updateStmt, 'ii', $typeId, $room['id']);
            mysqli_stmt_execute($updateStmt);
            $summary['reassigned'][] = $roomNumber . ' -> ' . $typeName;
        }
    }

    return $summary;
}

function getAllRoomsOrderedByNumber()
{
    global $conn;

    $result = mysqli_query(
        $conn,
        "SELECT r.*, rt.name AS room_type_name, rt.max_occupancy, rt.rate_per_night
         FROM rooms r
         JOIN room_types rt ON rt.id = r.room_type_id
         WHERE r.is_active = 1
         ORDER BY CAST(r.room_number AS UNSIGNED) ASC, r.room_number ASC"
    );

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}
