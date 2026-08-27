<?php

require_once __DIR__ . '/room_repository.php';
require_once __DIR__ . '/reservation_repository.php';
require_once __DIR__ . '/complaint_repository.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'room_types':
        echo json_encode(['success' => true, 'data' => getRoomTypes()]);
        break;

    case 'rooms_by_type':
        $roomTypeId = (int) ($_GET['room_type_id'] ?? 0);
        echo json_encode(['success' => true, 'data' => getRoomsByTypeId($roomTypeId)]);
        break;

    case 'room_types_client':
        echo json_encode(['success' => true, 'data' => getRoomTypesWithRoomsForClient()]);
        break;

    case 'check_availability':
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $checkIn = trim($_POST['check_in'] ?? '');
        $checkOut = trim($_POST['check_out'] ?? '');
        $excludeId = (int) ($_POST['exclude_reservation_id'] ?? 0);

        if ($roomId <= 0 || $checkIn === '' || $checkOut === '') {
            echo json_encode(['success' => false, 'available' => false, 'message' => 'Missing required fields.']);
            break;
        }

        $overlap = findOverlappingReservation($roomId, $checkIn, $checkOut, $excludeId);

        if ($overlap) {
            echo json_encode([
                'success' => true,
                'available' => false,
                'message' => 'Room ' . $overlap['room_number'] . ' is already booked between '
                    . date('j M Y', strtotime($overlap['check_in_date'])) . ' and '
                    . date('j M Y', strtotime($overlap['check_out_date']))
            ]);
            break;
        }

        echo json_encode(['success' => true, 'available' => true]);
        break;

    case 'high_priority_tickets':
        echo json_encode(['success' => true, 'data' => getHighPriorityOpenTickets()]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}
