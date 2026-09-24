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

    case 'calendar_quick_book':
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $checkIn = trim($_POST['check_in_date'] ?? '');
        $checkOut = trim($_POST['check_out_date'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');

        if ($roomId <= 0 || $checkIn === '' || $checkOut === '' || $firstName === '') {
            echo json_encode(['success' => false, 'message' => 'First Name, Room, Check-In and Check-Out dates are required.']);
            break;
        }

        if (strtotime($checkOut) <= strtotime($checkIn)) {
            echo json_encode(['success' => false, 'message' => 'Check-out date must be after check-in date.']);
            break;
        }

        if ($lastName === '') {
            $lastName = '-';
        }

        $room = getRoomById($roomId);
        $pricePerNight = (float) ($room['rate_per_night'] ?? 0);

        $result = createQuickGuestReservation([
            'room_id' => $roomId,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'occupancy' => 1,
            'currency' => 'NPR',
            'price_per_night' => $pricePerNight,
            'booked_via' => 'Walk-in',
            'room_plan' => 'EP',
            'payment_mode' => 'Cash',
            'guest' => [
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'country' => 'Nepal',
                'contact_number' => '',
                'email' => '',
                'address' => '',
                'id_type' => 'Citizenship',
                'id_number' => 'N/A'
            ]
        ]);

        echo json_encode($result);
        break;

    case 'calendar_make_available':
        $resId = (int) ($_POST['reservation_id'] ?? 0);
        $resNum = trim($_POST['reservation_number'] ?? '');
        if ($resId <= 0 && $resNum !== '') {
            $res = getReservationByNumber($resNum);
            if ($res) {
                $resId = (int) $res['id'];
            }
        }

        if ($resId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid reservation identifier.']);
            break;
        }

        $deleted = deleteReservation($resId);
        echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Room is now available.' : 'Could not make room available.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}
