<?php
session_start();
require_once '../auth_repository.php';
require_once '../room_repository.php';

requireAdminLogin();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_room_status') {
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'Available');

        if ($roomId > 0 && updateRoomStatus($roomId, $newStatus)) {
            header('Location: room_status.php?updated=1');
            exit;
        } else {
            $message = 'Failed to update room status.';
            $messageType = 'error';
        }
    }
}

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = 'Room status updated successfully.';
    $messageType = 'success';
}

$rooms = getAllRoomsWithTypes();
$statusCounts = getRoomStatusCounts();
$statusOptions = ['Available', 'Occupied', 'Dirty', 'Out of Order'];

function getStatusBadgeClass($status)
{
    switch ($status) {
        case 'Available':
            return 'available';
        case 'Occupied':
            return 'occupied';
        case 'Dirty':
            return 'dirty';
        case 'Out of Order':
            return 'out-of-order';
        default:
            return 'available';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Room Status - Hotel Admin</title>
    <script src="https://kit.fontawesome.com/8aab9e126a.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./admin_style.css?v=20260811">
    <style>
        .status-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px 20px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .summary-card .info label {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .summary-card .info span {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
        }

        .summary-card .icon-box {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .summary-card.available .icon-box {
            background: #dcfce7;
            color: #15803d;
        }

        .summary-card.occupied .icon-box {
            background: #fee2e2;
            color: #b91c1c;
        }

        .summary-card.dirty .icon-box {
            background: #fef3c7;
            color: #b45309;
        }

        .summary-card.out-of-order .icon-box {
            background: #f1f5f9;
            color: #475569;
        }

        .summary-card.total .icon-box {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-select {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-weight: 600;
            background-color: #ffffff;
            color: #1e293b;
            min-width: 150px;
            cursor: pointer;
        }

        .status-select:focus {
            border-color: #2563eb;
            outline: none;
        }

        .status-update-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-update-status {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .btn-update-status:hover {
            background: #1d4ed8;
        }
    </style>
</head>

<body class="page-manage-rooms">
    <div class="topbar">HOTEL MATE</div>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="main">
            <div class="page-header">
                <h1 class="page-title">Room Status</h1>
            </div>

            <?php if ($message): ?>
                <div class="admin-alert <?= h($messageType); ?>"><?= h($message); ?></div>
            <?php endif; ?>

            <div class="status-summary">
                <div class="summary-card total">
                    <div class="info">
                        <label>Total Rooms</label>
                        <span><?= h($statusCounts['Total']); ?></span>
                    </div>
                    <div class="icon-box"><i class="fas fa-bed"></i></div>
                </div>
                <div class="summary-card available">
                    <div class="info">
                        <label>Available</label>
                        <span><?= h($statusCounts['Available']); ?></span>
                    </div>
                    <div class="icon-box"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="summary-card occupied">
                    <div class="info">
                        <label>Occupied</label>
                        <span><?= h($statusCounts['Occupied']); ?></span>
                    </div>
                    <div class="icon-box"><i class="fas fa-user-check"></i></div>
                </div>
                <div class="summary-card dirty">
                    <div class="info">
                        <label>Dirty</label>
                        <span><?= h($statusCounts['Dirty']); ?></span>
                    </div>
                    <div class="icon-box"><i class="fas fa-broom"></i></div>
                </div>
                <div class="summary-card out-of-order">
                    <div class="info">
                        <label>Out of Order</label>
                        <span><?= h($statusCounts['Out of Order']); ?></span>
                    </div>
                    <div class="icon-box"><i class="fas fa-tools"></i></div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-controls">
                    <div class="search-box">
                        <label for="searchRoom">Search:</label>
                        <input type="text" id="searchRoom" placeholder="Search room number or type...">
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Room Number</th>
                                <th>Room Type</th>
                                <th>Max Occupancy</th>
                                <!-- <th>Rate Per Night</th> -->
                                <th>Current Status</th>
                                <th>Update Room Status</th>
                            </tr>
                        </thead>
                        <tbody id="roomTableBody">
                            <?php if (empty($rooms)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #64748b;">No active rooms found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rooms as $room): ?>
                                    <?php
                                    $currentStatus = $room['status'] ?? 'Available';
                                    $badgeClass = getStatusBadgeClass($currentStatus);
                                    ?>
                                    <tr data-room-number="<?= h($room['room_number']); ?>"
                                        data-room-type="<?= h($room['room_type_name']); ?>">
                                        <td><strong><?= h($room['room_number']); ?></strong></td>
                                        <td><?= h($room['room_type_name']); ?></td>
                                        <td><?= h($room['max_occupancy']); ?> Guests</td>
                                        <!-- <td>NPR <?= h(number_format((float) $room['rate_per_night'], 2)); ?></td> -->
                                        <td>
                                            <span class="badge <?= h($badgeClass); ?>">
                                                <?= h($currentStatus); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="post" class="status-update-form">
                                                <input type="hidden" name="action" value="update_room_status">
                                                <input type="hidden" name="room_id" value="<?= h($room['id']); ?>">
                                                <select name="status" class="status-select" onchange="this.form.submit()">
                                                    <?php foreach ($statusOptions as $option): ?>
                                                        <option value="<?= h($option); ?>" <?= $option === $currentStatus ? 'selected' : ''; ?>>
                                                            <?= h($option); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn-update-status">Update</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/high_priority_alert.php'; ?>

    <script>
        document.getElementById('searchRoom').addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            const rows = document.querySelectorAll('#roomTableBody tr');

            rows.forEach(row => {
                const roomNumber = (row.dataset.roomNumber || '').toLowerCase();
                const roomType = (row.dataset.roomType || '').toLowerCase();

                if (roomNumber.includes(query) || roomType.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>

</html>
