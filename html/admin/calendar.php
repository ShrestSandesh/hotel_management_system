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
$bookingGrid = []; // [room_id][date_str] = true
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
            'reservation_number' => $res['reservation_number'],
            'guest_name' => trim($res['first_name'] . ' ' . $res['last_name'])
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
        }

        /* Booked cell styling: Light grey shaded background + diagonal line pattern */
        .grid-cell.cell-booked {
            background-color: #f1f5f9;
            background-image: linear-gradient(to top right, transparent calc(50% - 1px), #cbd5e1 50%, transparent calc(50% + 1px));
            cursor: pointer;
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
                                <tr class="room-row" data-room-number="<?= h($room['room_number']); ?>" data-room-type="<?= h(strtolower($room['room_type_name'])); ?>">
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
                                            <td class="grid-cell cell-booked" data-tooltip="<?= h($booking['reservation_number'] . ' - ' . $booking['guest_name']); ?>"></td>
                                        <?php else: ?>
                                            <td class="grid-cell cell-available"></td>
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

    <?php include 'includes/high_priority_alert.php'; ?>
    <script>
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
    </script>
</body>

</html>
