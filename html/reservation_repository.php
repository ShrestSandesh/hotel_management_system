<?php

require_once __DIR__ . '/db.php';

function generateReservationNumber()
{
    global $conn;

    $datePrefix = 'RES-' . date('Y-m-d');

    // Count existing reservations created today (same date prefix) to build the next
    // sequence number, e.g. RES-2026-06-28-001, RES-2026-06-28-002, ...
    $likePattern = $datePrefix . '-%';
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM reservations WHERE reservation_number LIKE ?");
    mysqli_stmt_bind_param($stmt, 's', $likePattern);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    $sequence = (int) ($row['c'] ?? 0) + 1;

    $reservationNumber = $datePrefix . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

    // Safety net in case of a race condition (two reservations created at the same instant)
    while (getReservationByNumber($reservationNumber)) {
        $sequence++;
        $reservationNumber = $datePrefix . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    return $reservationNumber;
}

function calculateTotalNights($checkIn, $checkOut)
{
    $start = new DateTime($checkIn);
    $end = new DateTime($checkOut);

    return (int) $start->diff($end)->days;
}

function findOverlappingReservation($roomId, $checkIn, $checkOut, $excludeReservationId = 0)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.*, rm.room_number
         FROM reservations r
         JOIN rooms rm ON rm.id = r.room_id
         WHERE r.room_id = ?
           AND r.id != ?
           AND r.check_out_status != 'CHECKED OUT'
           AND r.check_in_date < ?
           AND r.check_out_date > ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'isss', $roomId, $excludeReservationId, $checkOut, $checkIn);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_assoc($result) : null;
}

function createGuestRecord($data)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO guests
            (first_name, middle_name, last_name, country, contact_number, email, address, id_type, id_number)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        'sssssssss',
        $data['first_name'],
        $data['middle_name'],
        $data['last_name'],
        $data['country'],
        $data['contact_number'],
        $data['email'],
        $data['address'],
        $data['id_type'],
        $data['id_number']
    );

    if (!mysqli_stmt_execute($stmt)) {
        return false;
    }

    return mysqli_insert_id($conn);
}

function updateGuestRecord($guestId, $data)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE guests
         SET first_name = ?, middle_name = ?, last_name = ?, country = ?, contact_number = ?,
             email = ?, address = ?, id_type = ?, id_number = ?
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        'sssssssssi',
        $data['first_name'],
        $data['middle_name'],
        $data['last_name'],
        $data['country'],
        $data['contact_number'],
        $data['email'],
        $data['address'],
        $data['id_type'],
        $data['id_number'],
        $guestId
    );

    return mysqli_stmt_execute($stmt);
}

function deleteGuestRecord($guestId)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "DELETE FROM guests WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $guestId);

    return mysqli_stmt_execute($stmt);
}

function createReservation($data)
{
    global $conn;

    $overlap = findOverlappingReservation(
        (int) $data['room_id'],
        $data['check_in_date'],
        $data['check_out_date']
    );

    if ($overlap) {
        return [
            'success' => false,
            'message' => 'Room ' . $overlap['room_number'] . ' is already booked between '
                . date('j M Y', strtotime($overlap['check_in_date'])) . ' and '
                . date('j M Y', strtotime($overlap['check_out_date']))
        ];
    }

    $guestId = createGuestRecord($data['guest']);
    if (!$guestId) {
        return ['success' => false, 'message' => 'Could not save guest details.'];
    }

    $reservationNumber = $data['reservation_number'] ?? generateReservationNumber();
    $totalNights = calculateTotalNights($data['check_in_date'], $data['check_out_date']);

    $occupants = $data['occupants'] ?? [];
    $extraOccupantsPrice = 0.0;
    if (is_array($occupants)) {
        foreach ($occupants as $occ) {
            $extraOccupantsPrice += (float) ($occ['price_per_night'] ?? 0);
        }
    }
    $mainPricePerNight = (float) $data['price_per_night'];
    $totalPrice = $totalNights * ($mainPricePerNight + $extraOccupantsPrice);
    $paymentStatus = 'UNPAID';
    $source = $data['source'] ?? 'admin';
    $checkInStatus = ($source === 'admin') ? 'CHECKED IN' : 'NOT CHECKED IN';
    $checkOutStatus = 'NOT CHECKED OUT';
    $bookedVia = trim($data['booked_via'] ?? 'Walk-in');
    $guestRequest = trim($data['guest_request'] ?? '');
    $roomPlan = trim($data['room_plan'] ?? 'EP');
    $paymentMode = trim($data['payment_mode'] ?? 'Cash');

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO reservations
            (reservation_number, guest_id, room_id, check_in_date, check_out_date, occupancy, currency,
             price_per_night, total_nights, total_price, payment_status, check_in_status, check_out_status, source, user_id,
             booked_via, guest_request, room_plan, payment_mode)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        'siissisdidssssissss',
        $reservationNumber,
        $guestId,
        $data['room_id'],
        $data['check_in_date'],
        $data['check_out_date'],
        $data['occupancy'],
        $data['currency'],
        $data['price_per_night'],
        $totalNights,
        $totalPrice,
        $paymentStatus,
        $checkInStatus,
        $checkOutStatus,
        $source,
        $userId,
        $bookedVia,
        $guestRequest,
        $roomPlan,
        $paymentMode
    );

    if (!mysqli_stmt_execute($stmt)) {
        deleteGuestRecord($guestId);
        return ['success' => false, 'message' => 'Could not save reservation.'];
    }

    $reservationId = mysqli_insert_id($conn);
    saveReservationOccupants($reservationId, $data['occupants'] ?? []);
    if (array_key_exists('extra_charges', $data)) {
        saveExtraCharges($reservationId, $data['extra_charges']);
    }

    $paymentStmt = mysqli_prepare(
        $conn,
        "INSERT INTO payments (reservation_id, amount, currency, status) VALUES (?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($paymentStmt, 'idss', $reservationId, $totalPrice, $data['currency'], $paymentStatus);
    mysqli_stmt_execute($paymentStmt);

    return [
        'success' => true,
        'reservation_id' => $reservationId,
        'reservation_number' => $reservationNumber,
        'guest_id' => $guestId,
        'total_nights' => $totalNights,
        'total_price' => $totalPrice,
        'payment_status' => $paymentStatus
    ];
}

function getReservationById($id)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.*, g.first_name, g.middle_name, g.last_name, g.country, g.contact_number, g.email,
                g.address, g.id_type, g.id_number, rm.room_number, rt.name AS room_type_name, rt.max_occupancy
         FROM reservations r
         JOIN guests g ON g.id = r.guest_id
         JOIN rooms rm ON rm.id = r.room_id
         JOIN room_types rt ON rt.id = rm.room_type_id
         WHERE r.id = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_assoc($result) : null;
}

function getReservationByNumber($number)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.*, g.first_name, g.middle_name, g.last_name, g.country, g.contact_number, g.email,
                g.address, g.id_type, g.id_number, rm.room_number, rt.name AS room_type_name, rt.max_occupancy
         FROM reservations r
         JOIN guests g ON g.id = r.guest_id
         JOIN rooms rm ON rm.id = r.room_id
         JOIN room_types rt ON rt.id = rm.room_type_id
         WHERE r.reservation_number = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 's', $number);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_assoc($result) : null;
}

function getReservationsByEmail($email)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.*, g.first_name, g.middle_name, g.last_name, g.country, g.contact_number, g.email,
                g.address, g.id_type, g.id_number, rm.room_number, rt.name AS room_type_name
         FROM reservations r
         JOIN guests g ON g.id = r.guest_id
         JOIN rooms rm ON rm.id = r.room_id
         JOIN room_types rt ON rt.id = rm.room_type_id
         WHERE g.email = ?
         ORDER BY r.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function getReservationsForClient($userId, $userEmail = '')
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.*, g.first_name, g.middle_name, g.last_name, g.country, g.contact_number, g.email,
                g.address, g.id_type, g.id_number, rm.room_number, rt.name AS room_type_name, rt.max_occupancy
         FROM reservations r
         JOIN guests g ON g.id = r.guest_id
         JOIN rooms rm ON rm.id = r.room_id
         JOIN room_types rt ON rt.id = rm.room_type_id
         WHERE (r.user_id = ? OR (r.user_id IS NULL AND g.email = ?))
         ORDER BY r.check_in_date ASC, r.id DESC"
    );
    mysqli_stmt_bind_param($stmt, 'is', $userId, $userEmail);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function getAllReservations()
{
    global $conn;

    $result = mysqli_query(
        $conn,
        "SELECT r.*, g.first_name, g.middle_name, g.last_name, g.country, g.contact_number, g.email,
                g.address, g.id_type, g.id_number,
                rm.room_number, rt.name AS room_type_name, rt.max_occupancy
         FROM reservations r
         JOIN guests g ON g.id = r.guest_id
         JOIN rooms rm ON rm.id = r.room_id
         JOIN room_types rt ON rt.id = rm.room_type_id
         ORDER BY r.check_in_date DESC, r.id DESC"
    );

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function getAllGuestsFromReservations()
{
    global $conn;

    $result = mysqli_query(
        $conn,
        "SELECT r.id AS reservation_id, r.reservation_number, r.check_in_date, r.check_out_date,
                r.occupancy, r.currency, r.total_price, r.payment_status, r.source, r.created_at,
                g.id AS guest_id, g.first_name, g.middle_name, g.last_name, g.country, g.contact_number,
                g.email, g.address, g.id_type, g.id_number, rm.room_number, rt.name AS room_type_name
         FROM reservations r
         JOIN guests g ON g.id = r.guest_id
         JOIN rooms rm ON rm.id = r.room_id
         JOIN room_types rt ON rt.id = rm.room_type_id
         ORDER BY r.created_at DESC"
    );

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

/**
 * Reservations that have not checked in yet. This is what powers the
 * "Manage Rooms" page: a new booking (from the client site or the admin
 * Reservation page) shows up here first, and only here, until the front
 * desk marks it as checked in.
 */
function getPendingCheckInReservations()
{
    global $conn;

    $result = mysqli_query(
        $conn,
        "SELECT r.*, g.first_name, g.middle_name, g.last_name, g.country, g.contact_number, g.email,
                g.address, g.id_type, g.id_number,
                rm.room_number, rt.name AS room_type_name, rt.max_occupancy
         FROM reservations r
         JOIN guests g ON g.id = r.guest_id
         JOIN rooms rm ON rm.id = r.room_id
         JOIN room_types rt ON rt.id = rm.room_type_id
         WHERE r.check_in_status = 'NOT CHECKED IN'
         ORDER BY r.check_in_date ASC, r.id DESC"
    );

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

/**
 * Guest records for reservations that HAVE checked in. This is what powers
 * the "All Guests" page: a reservation only appears here once the front
 * desk has checked the guest in on the Manage Rooms page (or it was logged
 * directly here via "Add Guest Record", which is created already checked in).
 */
function getCheckedInGuestsFromReservations()
{
    global $conn;

    $result = mysqli_query(
        $conn,
        "SELECT r.id AS reservation_id, r.room_id, r.price_per_night, r.reservation_number, r.check_in_date, r.check_out_date,
                r.occupancy, r.currency, r.total_price, r.payment_status, r.check_in_status,
                r.check_out_status, r.source, r.created_at, r.booked_via, r.guest_request, r.room_plan, r.payment_mode,
                g.id AS guest_id, g.first_name, g.middle_name, g.last_name, g.country, g.contact_number,
                g.email, g.address, g.id_type, g.id_number, rm.room_number, rt.name AS room_type_name
         FROM reservations r
         JOIN guests g ON g.id = r.guest_id
         JOIN rooms rm ON rm.id = r.room_id
         JOIN room_types rt ON rt.id = rm.room_type_id
         ORDER BY r.created_at DESC"
    );

    if (!$result) {
        return [];
    }

    $guests = mysqli_fetch_all($result, MYSQLI_ASSOC);
    if (empty($guests)) {
        return [];
    }

    $extraChargesMap = [];
    $ecResult = mysqli_query($conn, "SELECT reservation_id, service_name, price FROM extra_charges ORDER BY id ASC");
    if ($ecResult) {
        while ($row = mysqli_fetch_assoc($ecResult)) {
            $resId = (int) $row['reservation_id'];
            if (!isset($extraChargesMap[$resId])) {
                $extraChargesMap[$resId] = [];
            }
            $extraChargesMap[$resId][] = [
                'service_name' => $row['service_name'],
                'price' => (float) $row['price']
            ];
        }
    }

    $occupantsMap = [];
    $occResult = mysqli_query($conn, "SELECT * FROM reservation_occupants ORDER BY reservation_id ASC, occupant_order ASC");
    if ($occResult) {
        while ($row = mysqli_fetch_assoc($occResult)) {
            $resId = (int) $row['reservation_id'];
            if (!isset($occupantsMap[$resId])) {
                $occupantsMap[$resId] = [];
            }
            $occupantsMap[$resId][] = $row;
        }
    }

    foreach ($guests as &$guest) {
        $resId = (int) $guest['reservation_id'];
        $guest['extra_charges'] = $extraChargesMap[$resId] ?? [];
        $guest['occupants'] = $occupantsMap[$resId] ?? [];
    }
    unset($guest);

    return $guests;
}

/**
 * Guest records for guests who are CURRENTLY staying at the hotel today
 * (check_in_date <= CURDATE() AND check_out_date >= CURDATE() AND check_out_status != 'CHECKED OUT').
 */
function getCurrentGuestsFromReservations()
{
    global $conn;

    $result = mysqli_query(
        $conn,
        "SELECT r.id AS reservation_id, r.room_id, r.price_per_night, r.reservation_number, r.check_in_date, r.check_out_date,
                r.occupancy, r.currency, r.total_price, r.payment_status, r.check_in_status,
                r.check_out_status, r.source, r.created_at, r.booked_via, r.guest_request, r.room_plan, r.payment_mode,
                g.id AS guest_id, g.first_name, g.middle_name, g.last_name, g.country, g.contact_number,
                g.email, g.address, g.id_type, g.id_number, rm.room_number, rt.name AS room_type_name
         FROM reservations r
         JOIN guests g ON g.id = r.guest_id
         JOIN rooms rm ON rm.id = r.room_id
         JOIN room_types rt ON rt.id = rm.room_type_id
         WHERE r.check_in_date <= CURDATE()
           AND r.check_out_date >= CURDATE()
           AND r.check_out_status != 'CHECKED OUT'
         ORDER BY r.created_at DESC"
    );

    if (!$result) {
        return [];
    }

    $guests = mysqli_fetch_all($result, MYSQLI_ASSOC);
    if (empty($guests)) {
        return [];
    }

    $extraChargesMap = [];
    $ecResult = mysqli_query($conn, "SELECT reservation_id, service_name, price FROM extra_charges ORDER BY id ASC");
    if ($ecResult) {
        while ($row = mysqli_fetch_assoc($ecResult)) {
            $resId = (int) $row['reservation_id'];
            if (!isset($extraChargesMap[$resId])) {
                $extraChargesMap[$resId] = [];
            }
            $extraChargesMap[$resId][] = [
                'service_name' => $row['service_name'],
                'price' => (float) $row['price']
            ];
        }
    }

    $occupantsMap = [];
    $occResult = mysqli_query($conn, "SELECT * FROM reservation_occupants ORDER BY reservation_id ASC, occupant_order ASC");
    if ($occResult) {
        while ($row = mysqli_fetch_assoc($occResult)) {
            $resId = (int) $row['reservation_id'];
            if (!isset($occupantsMap[$resId])) {
                $occupantsMap[$resId] = [];
            }
            $occupantsMap[$resId][] = $row;
        }
    }

    foreach ($guests as &$guest) {
        $resId = (int) $guest['reservation_id'];
        $guest['extra_charges'] = $extraChargesMap[$resId] ?? [];
        $guest['occupants'] = $occupantsMap[$resId] ?? [];
    }
    unset($guest);

    return $guests;
}

/**
 * Creates a minimal reservation + guest record from just the handful of
 * fields the admin/staff "Add Guest Record" quick-entry form collects,
 * instead of the full reservation workflow. Used for logging past stays.
 */
function createQuickGuestReservation($data)
{
    global $conn;

    $roomId = (int) $data['room_id'];
    $checkIn = $data['check_in_date'];
    $checkOut = $data['check_out_date'];

    $overlap = findOverlappingReservation($roomId, $checkIn, $checkOut);
    if ($overlap) {
        return [
            'success' => false,
            'message' => 'Room ' . $overlap['room_number'] . ' is already booked between '
                . date('j M Y', strtotime($overlap['check_in_date'])) . ' and '
                . date('j M Y', strtotime($overlap['check_out_date']))
        ];
    }

    $guestId = createGuestRecord($data['guest']);
    if (!$guestId) {
        return ['success' => false, 'message' => 'Could not save guest details.'];
    }

    $reservationNumber = generateReservationNumber();
    $totalNights = max(1, calculateTotalNights($checkIn, $checkOut));

    $occupants = $data['occupants'] ?? [];
    $extraOccupantsPrice = 0.0;
    if (is_array($occupants)) {
        foreach ($occupants as $occ) {
            $extraOccupantsPrice += (float) ($occ['price_per_night'] ?? 0);
        }
    }
    $mainPricePerNight = (float) ($data['price_per_night'] ?? 0);
    $totalPrice = $totalNights * ($mainPricePerNight + $extraOccupantsPrice);
    $currency = $data['currency'] ?? 'NPR';
    $occupancy = (int) ($data['occupancy'] ?? 1);
    $paymentStatus = 'PAID';
    $checkInStatus = 'CHECKED IN';
    $todayStr = date('Y-m-d');
    $checkOutStatus = ($checkOut <= $todayStr) ? 'CHECKED OUT' : 'NOT CHECKED OUT';
    $source = $data['source'] ?? 'admin';
    $bookedVia = trim($data['booked_via'] ?? 'Walk-in');
    $guestRequest = trim($data['guest_request'] ?? '');
    $roomPlan = trim($data['room_plan'] ?? 'EP');
    $paymentMode = trim($data['payment_mode'] ?? 'Cash');

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO reservations
            (reservation_number, guest_id, room_id, check_in_date, check_out_date, occupancy, currency,
             price_per_night, total_nights, total_price, payment_status, check_in_status, check_out_status, source,
             booked_via, guest_request, room_plan, payment_mode)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        deleteGuestRecord($guestId);
        return ['success' => false, 'message' => 'Could not save the guest record: ' . mysqli_error($conn)];
    }

    mysqli_stmt_bind_param(
        $stmt,
        'siissisdidssssssss',
        $reservationNumber,
        $guestId,
        $roomId,
        $checkIn,
        $checkOut,
        $occupancy,
        $currency,
        $pricePerNight,
        $totalNights,
        $totalPrice,
        $paymentStatus,
        $checkInStatus,
        $checkOutStatus,
        $source,
        $bookedVia,
        $guestRequest,
        $roomPlan,
        $paymentMode
    );

    if (!mysqli_stmt_execute($stmt)) {
        $dbErr = mysqli_stmt_error($stmt) ?: mysqli_error($conn);
        deleteGuestRecord($guestId);
        return ['success' => false, 'message' => 'Could not save the guest record: ' . $dbErr];
    }

    $reservationId = mysqli_insert_id($conn);
    saveReservationOccupants($reservationId, $data['occupants'] ?? []);
    if (array_key_exists('extra_charges', $data)) {
        saveExtraCharges($reservationId, $data['extra_charges']);
    }

    $paymentStmt = mysqli_prepare(
        $conn,
        "INSERT INTO payments (reservation_id, amount, currency, status) VALUES (?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($paymentStmt, 'idss', $reservationId, $totalPrice, $currency, $paymentStatus);
    mysqli_stmt_execute($paymentStmt);

    return [
        'success' => true,
        'reservation_id' => $reservationId,
        'reservation_number' => $reservationNumber
    ];
}

function getExtraCharges($reservationId)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "SELECT * FROM extra_charges WHERE reservation_id = ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt, 'i', $reservationId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function getExtraChargesTotal($reservationId)
{
    $charges = getExtraCharges($reservationId);
    $total = 0.0;

    foreach ($charges as $charge) {
        $total += (float) $charge['price'];
    }

    return $total;
}

function saveExtraCharges($reservationId, $charges)
{
    global $conn;

    $deleteStmt = mysqli_prepare($conn, "DELETE FROM extra_charges WHERE reservation_id = ?");
    mysqli_stmt_bind_param($deleteStmt, 'i', $reservationId);
    mysqli_stmt_execute($deleteStmt);

    if (empty($charges)) {
        return true;
    }

    $insertStmt = mysqli_prepare(
        $conn,
        "INSERT INTO extra_charges (reservation_id, service_name, price) VALUES (?, ?, ?)"
    );

    foreach ($charges as $charge) {
        $service = trim($charge['service_name'] ?? '');
        $price = (float) ($charge['price'] ?? 0);

        if ($service === '' || $price < 0) {
            continue;
        }

        mysqli_stmt_bind_param($insertStmt, 'isd', $reservationId, $service, $price);
        mysqli_stmt_execute($insertStmt);
    }

    return true;
}

function updateReservation($reservationId, $data)
{
    global $conn;

    $existing = getReservationById($reservationId);
    if (!$existing) {
        return ['success' => false, 'message' => 'Reservation not found.'];
    }

    $overlap = findOverlappingReservation(
        (int) $data['room_id'],
        $data['check_in_date'],
        $data['check_out_date'],
        (int) $reservationId
    );

    if ($overlap) {
        return [
            'success' => false,
            'message' => 'Room ' . $overlap['room_number'] . ' is already booked between '
                . date('j M Y', strtotime($overlap['check_in_date'])) . ' and '
                . date('j M Y', strtotime($overlap['check_out_date']))
        ];
    }

    updateGuestRecord((int) $existing['guest_id'], $data['guest']);

    $totalNights = calculateTotalNights($data['check_in_date'], $data['check_out_date']);

    $occupants = $data['occupants'] ?? [];
    $extraOccupantsPrice = 0.0;
    if (is_array($occupants)) {
        foreach ($occupants as $occ) {
            $extraOccupantsPrice += (float) ($occ['price_per_night'] ?? 0);
        }
    }
    $mainPricePerNight = (float) $data['price_per_night'];
    $totalPrice = $totalNights * ($mainPricePerNight + $extraOccupantsPrice);

    $bookedVia = trim($data['booked_via'] ?? 'Walk-in');
    $guestRequest = trim($data['guest_request'] ?? '');
    $roomPlan = trim($data['room_plan'] ?? 'EP');
    $paymentMode = trim($data['payment_mode'] ?? 'Cash');

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE reservations
         SET room_id = ?, check_in_date = ?, check_out_date = ?, occupancy = ?, currency = ?,
             price_per_night = ?, total_nights = ?, total_price = ?, payment_status = ?,
             booked_via = ?, guest_request = ?, room_plan = ?, payment_mode = ?
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        'issisdidsssssi',
        $data['room_id'],
        $data['check_in_date'],
        $data['check_out_date'],
        $data['occupancy'],
        $data['currency'],
        $data['price_per_night'],
        $totalNights,
        $totalPrice,
        $data['payment_status'],
        $bookedVia,
        $guestRequest,
        $roomPlan,
        $paymentMode,
        $reservationId
    );

    if (!mysqli_stmt_execute($stmt)) {
        return ['success' => false, 'message' => 'Could not update reservation.'];
    }

    if (array_key_exists('extra_charges', $data)) {
        saveExtraCharges($reservationId, $data['extra_charges']);
    }

    saveReservationOccupants($reservationId, $data['occupants'] ?? []);

    $paymentStmt = mysqli_prepare(
        $conn,
        "UPDATE payments SET amount = ?, currency = ?, status = ? WHERE reservation_id = ?"
    );
    mysqli_stmt_bind_param(
        $paymentStmt,
        'dssi',
        $totalPrice,
        $data['currency'],
        $data['payment_status'],
        $reservationId
    );
    mysqli_stmt_execute($paymentStmt);

    return ['success' => true];
}

function updateClientReservation($reservationId, $userId, $userEmail, $data)
{
    global $conn;

    $existing = getReservationById($reservationId);
    if (!$existing) {
        return ['success' => false, 'message' => 'Reservation not found.'];
    }

    $isOwner = ((int) ($existing['user_id'] ?? 0) === (int) $userId)
        || (trim($userEmail) !== '' && strtolower(trim((string) ($existing['email'] ?? ''))) === strtolower(trim($userEmail)));

    if (!$isOwner) {
        return ['success' => false, 'message' => 'You can only edit your own reservations.'];
    }

    $overlap = findOverlappingReservation(
        (int) $data['room_id'],
        $data['check_in_date'],
        $data['check_out_date'],
        (int) $reservationId
    );

    if ($overlap) {
        return [
            'success' => false,
            'message' => 'Room ' . $overlap['room_number'] . ' is already booked between '
                . date('j M Y', strtotime($overlap['check_in_date'])) . ' and '
                . date('j M Y', strtotime($overlap['check_out_date']))
        ];
    }

    updateGuestRecord((int) $existing['guest_id'], $data['guest']);

    $totalNights = calculateTotalNights($data['check_in_date'], $data['check_out_date']);
    $totalPrice = $totalNights * (float) $data['price_per_night'];

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE reservations
         SET room_id = ?, check_in_date = ?, check_out_date = ?, occupancy = ?, currency = ?,
             price_per_night = ?, total_nights = ?, total_price = ?, payment_status = ?
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        'issisdidsi',
        $data['room_id'],
        $data['check_in_date'],
        $data['check_out_date'],
        $data['occupancy'],
        $data['currency'],
        $data['price_per_night'],
        $totalNights,
        $totalPrice,
        $data['payment_status'],
        $reservationId
    );

    if (!mysqli_stmt_execute($stmt)) {
        return ['success' => false, 'message' => 'Could not update reservation.'];
    }

    $paymentStmt = mysqli_prepare(
        $conn,
        "UPDATE payments SET amount = ?, currency = ?, status = ? WHERE reservation_id = ?"
    );
    mysqli_stmt_bind_param(
        $paymentStmt,
        'dssi',
        $totalPrice,
        $data['currency'],
        $data['payment_status'],
        $reservationId
    );
    mysqli_stmt_execute($paymentStmt);

    return ['success' => true];
}

function deleteClientReservation($reservationId, $userId, $userEmail)
{
    global $conn;

    $reservation = getReservationById($reservationId);
    if (!$reservation) {
        return ['success' => false, 'message' => 'Reservation not found.'];
    }

    $isOwner = ((int) ($reservation['user_id'] ?? 0) === (int) $userId)
        || (trim($userEmail) !== '' && strtolower(trim((string) ($reservation['email'] ?? ''))) === strtolower(trim($userEmail)));

    if (!$isOwner) {
        return ['success' => false, 'message' => 'You can only delete your own reservations.'];
    }

    $guestId = (int) $reservation['guest_id'];
    $stmt = mysqli_prepare($conn, "DELETE FROM reservations WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $reservationId);
    $deleted = mysqli_stmt_execute($stmt);

    if ($deleted) {
        deleteGuestRecord($guestId);
    }

    return ['success' => $deleted];
}

function updateReservationCheckInStatus($reservationId, $status)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "UPDATE reservations SET check_in_status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $status, $reservationId);

    return mysqli_stmt_execute($stmt);
}

function updateReservationCheckOutStatus($reservationId, $status)
{
    global $conn;

    $stmt = mysqli_prepare($conn, "UPDATE reservations SET check_out_status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $status, $reservationId);

    return mysqli_stmt_execute($stmt);
}

function deleteReservation($reservationId)
{
    global $conn;

    $reservation = getReservationById($reservationId);
    if (!$reservation) {
        return false;
    }

    $guestId = (int) $reservation['guest_id'];

    $stmt = mysqli_prepare($conn, "DELETE FROM reservations WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $reservationId);
    $deleted = mysqli_stmt_execute($stmt);

    if ($deleted) {
        deleteGuestRecord($guestId);
    }

    return $deleted;
}

function getDashboardStats()
{
    global $conn;

    $totalRooms = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rooms WHERE is_active = 1"))['c'];
    $totalReservations = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM reservations"))['c'];
    $pendingPayments = (int) mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT COUNT(*) AS c FROM reservations WHERE payment_status = 'UNPAID'")
    )['c'];

    // Pending amounts must be totalled per currency so a USD reservation's amount
    // isn't mixed into the NPR total (and vice versa).
    $pendingByCurrencyResult = mysqli_query(
        $conn,
        "SELECT currency, COALESCE(SUM(total_price), 0) AS total
         FROM reservations
         WHERE payment_status = 'UNPAID'
         GROUP BY currency"
    );

    $nprPendingAmount = 0.0;
    $usdPendingAmount = 0.0;
    if ($pendingByCurrencyResult) {
        while ($row = mysqli_fetch_assoc($pendingByCurrencyResult)) {
            if ($row['currency'] === 'USD') {
                $usdPendingAmount = (float) $row['total'];
            } else {
                $nprPendingAmount = (float) $row['total'];
            }
        }
    }

    $today = date('Y-m-d');
    $bookedToday = (int) mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(DISTINCT room_id) AS c FROM reservations
         WHERE check_in_date <= '$today' AND check_out_date > '$today'"
    ))['c'];
    $availableToday = max(0, $totalRooms - $bookedToday);
    $checkedIn = (int) mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT COUNT(*) AS c FROM reservations WHERE check_in_status = 'CHECKED IN' AND check_out_status = 'NOT CHECKED OUT'")
    )['c'];

    return [
        'total_rooms' => $totalRooms,
        'total_reservations' => $totalReservations,
        'pending_payments_count' => $pendingPayments,
        'pending_payment_amount_npr' => $nprPendingAmount,
        'pending_payment_amount_usd' => $usdPendingAmount,
        'available_today' => $availableToday,
        'booked_today' => $bookedToday,
        'checked_in' => $checkedIn
    ];
}

function guestFullName($row)
{
    return trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

function getReservationsForDateRange($startDate, $endDate)
{
    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.id, r.room_id, r.check_in_date, r.check_out_date, r.reservation_number, r.check_out_status,
                g.first_name, g.middle_name, g.last_name
         FROM reservations r
         JOIN guests g ON g.id = r.guest_id
         WHERE r.check_in_date <= ?
           AND r.check_out_date >= ?
           AND r.check_out_status != 'CHECKED OUT'
         ORDER BY r.check_in_date ASC"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $endDate, $startDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function saveReservationOccupants($reservationId, $occupants)
{
    global $conn;

    $reservationId = (int) $reservationId;
    if ($reservationId <= 0) {
        return;
    }

    mysqli_query($conn, "DELETE FROM reservation_occupants WHERE reservation_id = $reservationId");

    if (!is_array($occupants) || empty($occupants)) {
        return;
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO reservation_occupants
            (reservation_id, occupant_order, first_name, middle_name, last_name, contact_number, email, id_type, id_number, address, country, price_per_night)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        return;
    }

    foreach ($occupants as $idx => $occ) {
        $order = isset($occ['occupant_order']) ? (int) $occ['occupant_order'] : ($idx + 2);
        $fName = trim($occ['first_name'] ?? '');
        $mName = trim($occ['middle_name'] ?? '');
        $lName = trim($occ['last_name'] ?? '');
        $contact = trim($occ['contact_number'] ?? '');
        $email = trim($occ['email'] ?? '');
        $idType = trim($occ['id_type'] ?? '');
        $idNumber = trim($occ['id_number'] ?? '');
        $address = trim($occ['address'] ?? '');
        $country = trim($occ['country'] ?? '');
        $price = (float) ($occ['price_per_night'] ?? 0);

        if ($fName !== '' || $lName !== '' || $contact !== '' || $email !== '' || $idNumber !== '' || $price > 0) {
            mysqli_stmt_bind_param(
                $stmt,
                'iisssssssssd',
                $reservationId,
                $order,
                $fName,
                $mName,
                $lName,
                $contact,
                $email,
                $idType,
                $idNumber,
                $address,
                $country,
                $price
            );
            mysqli_stmt_execute($stmt);
        }
    }
}

function getReservationOccupants($reservationId)
{
    global $conn;

    $reservationId = (int) $reservationId;
    if ($reservationId <= 0) {
        return [];
    }

    $result = mysqli_query($conn, "SELECT * FROM reservation_occupants WHERE reservation_id = $reservationId ORDER BY occupant_order ASC");
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}
