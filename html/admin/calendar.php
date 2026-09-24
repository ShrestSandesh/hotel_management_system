<?php
session_start();
require_once '../auth_repository.php';
require_once '../room_repository.php';
require_once '../reservation_repository.php';

requireAdminLogin();

// Read selected year and month from GET query parameters, defaulting to current year & month
$currentYear = (int) date('Y');
$currentMonth = (int) date('n');

$year = isset($_GET['year']) ? (int) $_GET['year'] : $currentYear;
$month = isset($_GET['month']) ? (int) $_GET['month'] : $currentMonth;

if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

$daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));

$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

// Fetch all rooms in ascending order by room number (103, 104, 105...)
$rooms = getAllRoomsOrderedByNumber();

// Fetch reservations for the selected date range
$reservations = getReservationsForDateRange($startDate, $endDate);

// Map booked dates per room for quick O(1) lookup
// A room is booked on date D if check_in_date <= D and check_out_date > D
$bookingGrid = []; // [room_id][date_str] = [...]
foreach ($reservations as $res) {
    $rId = (int) $res['room_id'];
    $cIn = new DateTime($res['check_in_date']);
    $cOut = new DateTime($res['check_out_date']);

    $cur = clone $cIn;
    while ($cur < $cOut) {
        $dateStr = $cur->format('Y-m-d');
        if (!isset($bookingGrid[$rId])) {
            $bookingGrid[$rId] = [];
        }
        $bookingGrid[$rId][$dateStr] = [
            'reservation_id' => $res['id'],
            'reservation_number' => $res['reservation_number'],
            'guest_name' => trim(($res['first_name'] ?? '') . ' ' . ($res['middle_name'] ?? '') . ' ' . ($res['last_name'] ?? '')),
            'first_name' => $res['first_name'] ?? '',
            'middle_name' => $res['middle_name'] ?? '',
            'last_name' => $res['last_name'] ?? '',
            'check_in_date' => $res['check_in_date'],
            'check_out_date' => $res['check_out_date'],
            'total_price' => $res['total_price'] ?? 0,
            'currency' => $res['currency'] ?? 'NPR',
            'payment_status' => $res['payment_status'] ?? 'UNPAID'
        ];
        $cur->modify('+1 day');
    }
}

// Calculate previous/next month values for navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$todayStr = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Room Availability Calendar</title>
    <script src="https://kit.fontawesome.com/8aab9e126a.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./admin_style.css?v=20260714">
    <style>
        body, body.page-calendar {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
            background: #f3f6fb;
            color: #1f2937;
        }

        .calendar-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        .calendar-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .calendar-controls select {
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #1e293b;
            cursor: pointer;
            font-family: inherit;
        }

        .calendar-controls .btn-nav {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #334155;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .calendar-controls .btn-nav:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .calendar-controls .btn-today {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
        }

        .calendar-controls .btn-today:hover {
            background: #1d4ed8;
        }

        .calendar-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .calendar-wrapper {
            width: 100%;
            overflow-x: auto;
            position: relative;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .calendar-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            font-size: 13px;
            font-family: inherit;
        }

        .calendar-table th,
        .calendar-table td {
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            box-sizing: border-box;
        }

        /* Left Column: Room Listings */
        .room-col-header {
            position: sticky;
            left: 0;
            top: 0;
            z-index: 30;
            background: #ffffff;
            min-width: 240px;
            width: 240px;
            padding: 12px 16px;
            border-right: 2px solid #cbd5e1 !important;
            border-bottom: 2px solid #cbd5e1 !important;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.04);
        }

        .room-col-header .listings-count {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .room-col-header input {
            width: 100%;
            padding: 6px 10px;
            font-size: 12.5px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
            font-family: inherit;
        }

        .room-cell {
            position: sticky;
            left: 0;
            z-index: 20;
            background: #ffffff;
            min-width: 240px;
            width: 240px;
            padding: 12px 16px;
            border-right: 2px solid #cbd5e1 !important;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.04);
        }

        .room-title {
            font-weight: 800;
            font-size: 13.5px;
            color: #1e293b;
            line-height: 1.2;
            margin-bottom: 3px;
        }

        .room-number-sub {
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
        }

        /* Timeline Header Cells */
        .date-col-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #ffffff;
            min-width: 52px;
            width: 52px;
            text-align: center;
            padding: 8px 4px;
            border-bottom: 2px solid #cbd5e1 !important;
        }

        .day-name {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 2px;
        }

        .day-num {
            font-size: 14px;
            font-weight: 800;
            color: #1e293b;
            display: inline-block;
            width: 28px;
            height: 28px;
            line-height: 28px;
            border-radius: 50%;
        }

        .day-num.today-badge {
            background: #0f766e;
            color: #ffffff;
        }

        /* Matrix Grid Cells */
        .grid-cell {
            min-width: 52px;
            width: 52px;
            height: 54px;
            text-align: center;
            vertical-align: middle;
            position: relative;
            background: #ffffff;
            user-select: none;
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .grid-cell.cell-available {
            cursor: pointer;
        }

        .grid-cell.cell-available:hover {
            background-color: #f0f9ff;
            border: 2px solid #0284c7 !important;
        }

        .grid-cell.cell-selected {
            background-color: #e0f2fe !important;
            border: 2px solid #0284c7 !important;
            z-index: 5;
        }

        /* Booked cell styling: Light grey shaded background + diagonal line pattern */
        .grid-cell.cell-booked {
            background-color: #f1f5f9;
            background-image: linear-gradient(to top right, transparent calc(50% - 1px), #cbd5e1 50%, transparent calc(50% + 1px));
            cursor: pointer;
        }

        .grid-cell.cell-booked:hover {
            border: 2px solid #64748b !important;
        }

        .grid-cell.cell-booked:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: #ffffff;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            white-space: nowrap;
            z-index: 40;
            pointer-events: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .legend-bar {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 12px 18px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            font-weight: 700;
            color: #475569;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-box-available {
            width: 22px;
            height: 22px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            border-radius: 4px;
        }

        .legend-box-booked {
            width: 22px;
            height: 22px;
            border: 1px solid #cbd5e1;
            background-color: #f1f5f9;
            background-image: linear-gradient(to top right, transparent calc(50% - 1px), #cbd5e1 50%, transparent calc(50% + 1px));
            border-radius: 4px;
        }

        /* ===== AIRBNB STYLE SIDE DRAWER ===== */
        .airbnb-drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.35);
            z-index: 2000;
            display: none;
            justify-content: flex-end;
            backdrop-filter: blur(2px);
        }

        .airbnb-drawer {
            width: min(400px, 92vw);
            height: 100%;
            background: #ffffff;
            box-shadow: -6px 0 28px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            animation: slideInRight 0.22s ease-out;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        .drawer-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .drawer-close-btn {
            background: none;
            border: none;
            font-size: 24px;
            color: #64748b;
            cursor: pointer;
            line-height: 1;
            padding: 0 4px;
        }
        .drawer-close-btn:hover { color: #0f172a; }

        .drawer-header-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .drawer-body {
            padding: 22px 24px;
            flex: 1;
            overflow-y: auto;
        }

        .drawer-section {
            margin-bottom: 18px;
        }

        .drawer-label {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 6px;
        }

        .drawer-room-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .drawer-dates-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
        }

        .drawer-nights-sub {
            font-size: 12.5px;
            color: #64748b;
            font-weight: 600;
            margin-top: 4px;
        }

        .drawer-radio-group {
            display: flex;
            gap: 12px;
        }

        .drawer-radio-label {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13.5px;
            font-weight: 700;
            color: #334155;
            transition: all 0.15s ease;
        }

        .drawer-radio-label:has(input:checked) {
            border-color: #0f766e;
            background: #f0fdf4;
            color: #0f766e;
        }

        .drawer-field-group {
            margin-bottom: 14px;
        }

        .drawer-field-group label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 5px;
        }

        .drawer-field-group input,
        .drawer-field-group select,
        .drawer-select,
        .drawer-date-input {
            width: 100%;
            padding: 9px 12px;
            font-size: 13.5px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: inherit;
            background-color: #ffffff;
        }

        .drawer-field-group input:focus,
        .drawer-field-group select:focus,
        .drawer-select:focus,
        .drawer-date-input:focus {
            outline: none;
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
        }

        .drawer-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .drawer-status-pill.booked {
            background: #fee2e2;
            color: #991b1b;
        }

        .drawer-info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
        }

        .drawer-info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
            border-bottom: 1px dashed #e2e8f0;
        }

        .drawer-info-row:last-child { border-bottom: none; }
        .drawer-info-row label { color: #64748b; font-weight: 600; }
        .drawer-info-row span { color: #0f172a; font-weight: 700; }

        .drawer-footer {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
            display: flex;
            gap: 12px;
        }

        .drawer-btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            font-family: inherit;
        }
        .drawer-btn-secondary:hover { background: #e2e8f0; }

        .drawer-btn-primary {
            flex: 1;
            background: #0f766e;
            color: #ffffff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            transition: background 0.15s ease;
            font-family: inherit;
        }
        .drawer-btn-primary:hover { background: #0d9488; }

        .drawer-btn-danger {
            flex: 1;
            background: #dc2626;
            color: #ffffff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            transition: background 0.15s ease;
            font-family: inherit;
        }
        .drawer-btn-danger:hover { background: #b91c1c; }
    </style>
</head>

<body class="page-calendar">
    <div class="topbar">HOTEL MATE</div>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="main">
            <div class="calendar-page-header">
                <h1 class="page-title">Room Availability Calendar</h1>

                <div class="calendar-controls">
                    <form method="get" id="monthForm" style="display:inline-flex; align-items:center; gap:8px;">
                        <select name="month" onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m; ?>" <?= $m === $month ? 'selected' : ''; ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>

                        <select name="year" onchange="this.form.submit()">
                            <?php for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++): ?>
                                <option value="<?= $y; ?>" <?= $y === $year ? 'selected' : ''; ?>>
                                    <?= $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </form>

                    <a href="calendar.php" class="btn-today">Today</a>
                    <a href="calendar.php?year=<?= $prevYear; ?>&month=<?= $prevMonth; ?>" class="btn-nav" title="Previous Month">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="calendar.php?year=<?= $nextYear; ?>&month=<?= $nextMonth; ?>" class="btn-nav" title="Next Month">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>

            <div class="calendar-card">
                <div class="calendar-wrapper">
                    <table class="calendar-table">
                        <thead>
                            <tr>
                                <th class="room-col-header">
                                    <div class="listings-count" id="listingsCountHeader"><?= count($rooms); ?> rooms</div>
                                    <input type="text" id="searchRooms" placeholder="Search rooms..." oninput="filterRooms()">
                                </th>
                                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                    <?php
                                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                    $dayOfWeek = date('D', strtotime($dateStr)); // Mon, Tue, Wed...
                                    $dayLetter = substr($dayOfWeek, 0, 1);
                                    $isToday = ($dateStr === $todayStr);
                                    ?>
                                    <th class="date-col-header">
                                        <div class="day-name"><?= h($dayLetter); ?></div>
                                        <div class="day-num <?= $isToday ? 'today-badge' : ''; ?>"><?= $d; ?></div>
                                    </th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody id="roomsTableBody">
                            <?php foreach ($rooms as $room): ?>
                                <tr class="room-row" data-room-id="<?= h($room['id']); ?>" data-room-number="<?= h($room['room_number']); ?>" data-room-type="<?= h(strtolower($room['room_type_name'])); ?>" data-room-name="<?= h($room['room_type_name']); ?>">
                                    <td class="room-cell">
                                        <div class="room-title"><?= h($room['room_type_name']); ?></div>
                                        <div class="room-number-sub"><?= h($room['room_number']); ?></div>
                                    </td>
                                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                        <?php
                                        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                        $booking = $bookingGrid[$room['id']][$dateStr] ?? null;
                                        ?>
                                        <?php if ($booking): ?>
                                            <td class="grid-cell cell-booked"
                                                data-room-id="<?= h($room['id']); ?>"
                                                data-room-number="<?= h($room['room_number']); ?>"
                                                data-room-name="<?= h($room['room_type_name']); ?>"
                                                data-date="<?= $dateStr; ?>"
                                                data-reservation-id="<?= h($booking['reservation_id']); ?>"
                                                data-reservation-number="<?= h($booking['reservation_number']); ?>"
                                                data-guest-name="<?= h($booking['guest_name']); ?>"
                                                data-first-name="<?= h($booking['first_name']); ?>"
                                                data-middle-name="<?= h($booking['middle_name']); ?>"
                                                data-last-name="<?= h($booking['last_name']); ?>"
                                                data-checkin="<?= h($booking['check_in_date']); ?>"
                                                data-checkout="<?= h($booking['check_out_date']); ?>"
                                                data-total-price="<?= h($booking['total_price']); ?>"
                                                data-currency="<?= h($booking['currency']); ?>"
                                                data-tooltip="<?= h($booking['reservation_number'] . ' - ' . $booking['guest_name']); ?>"></td>
                                        <?php else: ?>
                                            <td class="grid-cell cell-available"
                                                data-room-id="<?= h($room['id']); ?>"
                                                data-room-number="<?= h($room['room_number']); ?>"
                                                data-room-name="<?= h($room['room_type_name']); ?>"
                                                data-rate-per-night="<?= h($room['rate_per_night']); ?>"
                                                data-date="<?= $dateStr; ?>"></td>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="legend-bar">
                    <div class="legend-item">
                        <div class="legend-box-available"></div>
                        <span>Available (Empty Box)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-box-booked"></div>
                        <span>Booked / Unavailable (Diagonal Line)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AIRBNB STYLE SIDE DRAWER -->
    <div class="airbnb-drawer-overlay" id="airbnbDrawerOverlay">
        <div class="airbnb-drawer" id="airbnbDrawer">
            <div class="drawer-header">
                <span class="drawer-header-title">Selected dates</span>
                <button type="button" class="drawer-close-btn" onclick="closeSideDrawer()">×</button>
            </div>
            <div class="drawer-body">
                <div class="drawer-section">
                    <div class="drawer-label">Selected Listing</div>
                    <div class="drawer-room-name" id="drawerRoomName">Room 105 - Heritage Twin</div>
                </div>
                
                <div class="drawer-section">
                    <div class="drawer-label">Dates</div>
                    <div class="drawer-dates-inputs" style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                        <div style="flex:1;">
                            <label style="display:block; font-size:11px; font-weight:700; color:#64748b; margin-bottom:3px;">Check-In</label>
                            <input type="date" id="quickCheckInDate" class="drawer-date-input" onchange="onQuickDatesChanged()">
                        </div>
                        <span style="color:#94a3b8; font-size:12px; margin-top:16px;"><i class="fas fa-arrow-right"></i></span>
                        <div style="flex:1;">
                            <label style="display:block; font-size:11px; font-weight:700; color:#64748b; margin-bottom:3px;">Check-Out</label>
                            <input type="date" id="quickCheckOutDate" class="drawer-date-input" onchange="onQuickDatesChanged()">
                        </div>
                    </div>
                    <div class="drawer-nights-sub" id="drawerNightsSub">1 night stay</div>
                </div>

                <!-- Mode 1: Booking Form (Vacant Slot) -->
                <div id="drawerVacantFormGroup">
                    <div class="drawer-label">Availability</div>
                    <div class="drawer-radio-group">
                        <label class="drawer-radio-label">
                            <input type="radio" name="drawerAvailability" value="Available" id="radioAvailable" onchange="toggleDrawerAvailabilityMode()">
                            <span>Available</span>
                        </label>
                        <label class="drawer-radio-label">
                            <input type="radio" name="drawerAvailability" value="Blocked" id="radioBlocked" checked onchange="toggleDrawerAvailabilityMode()">
                            <span>Booked</span>
                        </label>
                    </div>

                    <div id="drawerGuestDetailsFields" style="margin-top:18px;">
                        <div class="drawer-field-group">
                            <label>First Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" id="quickFirstName" placeholder="First Name">
                        </div>
                        <div class="drawer-field-group">
                            <label>Middle Name <span style="color:#94a3b8; font-weight:normal;">(Optional)</span></label>
                            <input type="text" id="quickMiddleName" placeholder="Middle Name">
                        </div>
                        <div class="drawer-field-group">
                            <label>Last Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" id="quickLastName" placeholder="Last Name">
                        </div>

                        <div style="display:flex; gap:10px; margin-top:12px;">
                            <div class="drawer-field-group" style="flex:2;">
                                <label>Price Per Night</label>
                                <input type="number" step="0.01" min="0" id="quickPricePerNight" placeholder="0.00">
                            </div>
                            <div class="drawer-field-group" style="flex:1;">
                                <label>Currency</label>
                                <select id="quickCurrency" class="drawer-select">
                                    <option value="NPR" selected>NPR</option>
                                    <option value="USD">USD</option>
                                </select>
                            </div>
                        </div>

                        <div class="drawer-field-group" style="margin-top:12px;">
                            <label>Booked Via</label>
                            <select id="quickBookedVia" class="drawer-select">
                                <option value="Walk-in" selected>Walk-in</option>
                                <option value="Booking.com">Booking.com</option>
                                <option value="Airbnb">Airbnb</option>
                                <option value="Agoda">Agoda</option>
                                <option value="Expedia">Expedia</option>
                                <option value="Direct / Website">Direct / Website</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Mode 2: Existing Reservation Details (Booked Slot) -->
                <div id="drawerBookedInfoGroup" style="display:none;">
                    <div class="drawer-label">Current Status</div>
                    <div style="margin-bottom:14px;">
                        <span class="drawer-status-pill booked"><i class="fas fa-lock"></i> Booked / Blocked</span>
                    </div>

                    <div class="drawer-info-card">
                        <div class="drawer-info-row"><label>Reservation #</label><span id="drawerResNumber">RES-123</span></div>
                        <div class="drawer-info-row"><label>Guest Name</label><span id="drawerGuestName">John Doe</span></div>
                        <div class="drawer-info-row"><label>Total Price</label><span id="drawerTotalPrice">NPR 2,000.00</span></div>
                    </div>
                </div>
            </div>

            <div class="drawer-footer">
                <button type="button" class="drawer-btn-secondary" onclick="closeSideDrawer()">Cancel</button>
                <button type="button" class="drawer-btn-primary" id="btnDrawerSave" onclick="saveDrawerAction()">Save</button>
                <button type="button" class="drawer-btn-danger" id="btnDrawerMakeAvailable" style="display:none;" onclick="makeRoomAvailable()">Make Available</button>
            </div>
        </div>
    </div>

    <?php include 'includes/high_priority_alert.php'; ?>
    <script>
        let isMouseDown = false;
        let selectedRoomId = null;
        let selectedCells = [];
        let activeMode = 'vacant'; // 'vacant' or 'booked'
        let currentBookingData = null;

        function filterRooms() {
            const query = document.getElementById('searchRooms').value.trim().toLowerCase();
            const rows = document.querySelectorAll('#roomsTableBody tr.room-row');
            let visible = 0;

            rows.forEach(row => {
                const roomNo = (row.dataset.roomNumber || '').toLowerCase();
                const roomType = (row.dataset.roomType || '').toLowerCase();

                if (!query || roomNo.includes(query) || roomType.includes(query)) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('listingsCountHeader').textContent = visible + ' rooms';
        }

        function clearSelection() {
            document.querySelectorAll('.grid-cell.cell-selected').forEach(c => c.classList.remove('cell-selected'));
            selectedCells = [];
            selectedRoomId = null;
        }

        function addDaysDateStr(dateStr, days) {
            const d = new Date(dateStr + 'T00:00:00');
            d.setDate(d.getDate() + days);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }

        function onQuickDatesChanged() {
            const inVal = document.getElementById('quickCheckInDate').value;
            const outVal = document.getElementById('quickCheckOutDate').value;
            const nightsSub = document.getElementById('drawerNightsSub');
            if (!inVal || !outVal) {
                nightsSub.textContent = 'Please select valid dates';
                nightsSub.style.color = '#ef4444';
                return;
            }

            const dIn = new Date(inVal + 'T00:00:00');
            const dOut = new Date(outVal + 'T00:00:00');
            const diffTime = dOut - dIn;
            const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

            if (isNaN(diffDays) || diffDays <= 0) {
                nightsSub.textContent = 'Invalid range (check-out must be after check-in)';
                nightsSub.style.color = '#ef4444';
            } else {
                nightsSub.textContent = `${diffDays} night${diffDays > 1 ? 's' : ''} stay`;
                nightsSub.style.color = '#64748b';
            }
        }

        function toggleDrawerAvailabilityMode() {
            const radioAvailable = document.getElementById('radioAvailable');
            const fields = document.getElementById('drawerGuestDetailsFields');
            if (radioAvailable && radioAvailable.checked) {
                if (fields) fields.style.display = 'none';
            } else {
                if (fields) fields.style.display = 'block';
            }
        }

        function openDrawerForVacantSelection(cells) {
            activeMode = 'vacant';
            currentBookingData = null;
            clearSelection();

            cells.forEach(c => c.classList.add('cell-selected'));
            selectedCells = cells;

            const firstCell = cells[0];
            const lastCell = cells[cells.length - 1];

            const roomId = firstCell.dataset.roomId;
            const roomNo = firstCell.dataset.roomNumber;
            const roomName = firstCell.dataset.roomName;
            const ratePerNight = firstCell.dataset.ratePerNight || '0';
            const checkInDate = firstCell.dataset.date;
            const checkOutDate = addDaysDateStr(lastCell.dataset.date, 1);

            document.getElementById('drawerRoomName').textContent = `${roomName} (${roomNo})`;
            document.getElementById('quickCheckInDate').value = checkInDate;
            document.getElementById('quickCheckOutDate').value = checkOutDate;
            onQuickDatesChanged();

            document.getElementById('drawerVacantFormGroup').style.display = 'block';
            document.getElementById('drawerBookedInfoGroup').style.display = 'none';
            document.getElementById('radioBlocked').checked = true;
            document.getElementById('drawerGuestDetailsFields').style.display = 'block';

            document.getElementById('quickFirstName').value = '';
            document.getElementById('quickMiddleName').value = '';
            document.getElementById('quickLastName').value = '';
            document.getElementById('quickPricePerNight').value = parseFloat(ratePerNight) > 0 ? parseFloat(ratePerNight) : '';
            document.getElementById('quickCurrency').value = 'NPR';
            document.getElementById('quickBookedVia').value = 'Walk-in';

            document.getElementById('btnDrawerSave').style.display = 'inline-block';
            document.getElementById('btnDrawerMakeAvailable').style.display = 'none';

            document.getElementById('airbnbDrawerOverlay').style.display = 'flex';
            document.getElementById('quickFirstName').focus();
        }

        function openDrawerForBookedCell(cell) {
            activeMode = 'booked';
            clearSelection();
            cell.classList.add('cell-selected');

            currentBookingData = {
                reservation_id: cell.dataset.reservationId,
                reservation_number: cell.dataset.reservationNumber,
                guest_name: cell.dataset.guestName,
                room_id: cell.dataset.roomId,
                room_number: cell.dataset.roomNumber,
                room_name: cell.dataset.roomName,
                check_in_date: cell.dataset.checkin,
                check_out_date: cell.dataset.checkout,
                total_price: cell.dataset.totalPrice,
                currency: cell.dataset.currency || 'NPR'
            };

            const roomNo = cell.dataset.roomNumber;
            const roomName = cell.dataset.roomName;
            const checkInDate = cell.dataset.checkin;
            const checkOutDate = cell.dataset.checkout;

            document.getElementById('drawerRoomName').textContent = `${roomName} (${roomNo})`;
            document.getElementById('quickCheckInDate').value = checkInDate || '';
            document.getElementById('quickCheckOutDate').value = checkOutDate || '';
            onQuickDatesChanged();

            document.getElementById('drawerVacantFormGroup').style.display = 'none';
            document.getElementById('drawerBookedInfoGroup').style.display = 'block';

            document.getElementById('drawerResNumber').textContent = currentBookingData.reservation_number || ('#' + currentBookingData.reservation_id);
            document.getElementById('drawerGuestName').textContent = currentBookingData.guest_name || 'N/A';
            document.getElementById('drawerTotalPrice').textContent = `${currentBookingData.currency} ${parseFloat(currentBookingData.total_price || 0).toFixed(2)}`;

            document.getElementById('btnDrawerSave').style.display = 'none';
            document.getElementById('btnDrawerMakeAvailable').style.display = 'inline-block';

            document.getElementById('airbnbDrawerOverlay').style.display = 'flex';
        }

        function closeSideDrawer() {
            document.getElementById('airbnbDrawerOverlay').style.display = 'none';
            clearSelection();
        }

        function handleOverlayClick(e) {
            if (e.target.id === 'airbnbDrawerOverlay') {
                closeSideDrawer();
            }
        }

        async function saveDrawerAction() {
            if (activeMode === 'vacant') {
                const radioAvailable = document.getElementById('radioAvailable');
                if (radioAvailable && radioAvailable.checked) {
                    closeSideDrawer();
                    return;
                }

                const checkIn = document.getElementById('quickCheckInDate').value;
                const checkOut = document.getElementById('quickCheckOutDate').value;
                const fName = document.getElementById('quickFirstName').value.trim();
                const mName = document.getElementById('quickMiddleName').value.trim();
                const lName = document.getElementById('quickLastName').value.trim();
                const pricePerNight = document.getElementById('quickPricePerNight').value.trim();
                const currency = document.getElementById('quickCurrency').value;
                const bookedVia = document.getElementById('quickBookedVia').value;

                if (!checkIn || !checkOut) {
                    alert('Please select valid Check-In and Check-Out dates.');
                    return;
                }

                const dIn = new Date(checkIn + 'T00:00:00');
                const dOut = new Date(checkOut + 'T00:00:00');
                if (dOut <= dIn) {
                    alert('Check-out date must be after check-in date.');
                    return;
                }

                if (!fName) {
                    alert('Please enter at least First Name to book/block the room.');
                    document.getElementById('quickFirstName').focus();
                    return;
                }

                if (selectedCells.length === 0) return;

                const firstCell = selectedCells[0];
                const roomId = firstCell.dataset.roomId;

                const body = new FormData();
                body.append('action', 'calendar_quick_book');
                body.append('room_id', roomId);
                body.append('check_in_date', checkIn);
                body.append('check_out_date', checkOut);
                body.append('first_name', fName);
                body.append('middle_name', mName);
                body.append('last_name', lName);
                body.append('price_per_night', pricePerNight);
                body.append('currency', currency);
                body.append('booked_via', bookedVia);

                try {
                    const res = await fetch('../api.php', { method: 'POST', body });
                    const data = await res.json();

                    if (!data.success) {
                        alert(data.message || 'Could not save booking.');
                        return;
                    }

                    // Dynamically update cells to booked state across grid row
                    const resId = data.reservation_id;
                    const resNum = data.reservation_number;
                    const guestFullName = [fName, mName, lName].filter(Boolean).join(' ');

                    const roomRow = document.querySelector(`tr.room-row[data-room-id="${roomId}"]`);
                    if (roomRow) {
                        const allCells = roomRow.querySelectorAll('.grid-cell');
                        allCells.forEach(cell => {
                            const cellDate = cell.dataset.date;
                            if (cellDate >= checkIn && cellDate < checkOut) {
                                cell.className = 'grid-cell cell-booked';
                                cell.dataset.reservationId = resId;
                                cell.dataset.reservationNumber = resNum;
                                cell.dataset.guestName = guestFullName;
                                cell.dataset.checkin = checkIn;
                                cell.dataset.checkout = checkOut;
                                cell.dataset.totalPrice = data.total_price || 0;
                                cell.dataset.currency = currency;
                                cell.dataset.tooltip = `${resNum} - ${guestFullName}`;
                            }
                        });
                    }

                    closeSideDrawer();
                } catch(e) {
                    alert('Failed to save booking. Please try again.');
                }
            }
        }

        async function makeRoomAvailable() {
            if (!currentBookingData || !currentBookingData.reservation_id) return;

            if (!confirm(`Are you sure you want to make Room ${currentBookingData.room_number} available?\nThis will remove reservation ${currentBookingData.reservation_number}.`)) {
                return;
            }

            const body = new FormData();
            body.append('action', 'calendar_make_available');
            body.append('reservation_id', currentBookingData.reservation_id);

            try {
                const res = await fetch('../api.php', { method: 'POST', body });
                const data = await res.json();

                if (!data.success) {
                    alert(data.message || 'Could not make room available.');
                    return;
                }

                // Dynamically update cells back to available
                const resId = currentBookingData.reservation_id;
                document.querySelectorAll(`.grid-cell[data-reservation-id="${resId}"]`).forEach(cell => {
                    cell.className = 'grid-cell cell-available';
                    cell.removeAttribute('data-reservation-id');
                    cell.removeAttribute('data-reservation-number');
                    cell.removeAttribute('data-guest-name');
                    cell.removeAttribute('data-first-name');
                    cell.removeAttribute('data-middle-name');
                    cell.removeAttribute('data-last-name');
                    cell.removeAttribute('data-checkin');
                    cell.removeAttribute('data-checkout');
                    cell.removeAttribute('data-total-price');
                    cell.removeAttribute('data-currency');
                    cell.removeAttribute('data-tooltip');
                });

                closeSideDrawer();
            } catch(e) {
                alert('An error occurred. Please try again.');
            }
        }

        // Setup cell click & drag selection
        document.addEventListener('DOMContentLoaded', () => {
            const tbody = document.getElementById('roomsTableBody');
            if (!tbody) return;

            tbody.addEventListener('click', (e) => {
                const cell = e.target.closest('.grid-cell');
                if (!cell) return;

                if (cell.classList.contains('cell-booked')) {
                    openDrawerForBookedCell(cell);
                } else if (cell.classList.contains('cell-available')) {
                    openDrawerForVacantSelection([cell]);
                }
            });
        });
    </script>
</body>

</html>

