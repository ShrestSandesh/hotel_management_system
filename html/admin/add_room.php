<?php
session_start();
require_once '../auth_repository.php';
require_once '../room_repository.php';

requireAdminLogin();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_type') {
        $name = trim($_POST['type_name'] ?? '');
        $occupancy = (int) ($_POST['max_occupancy'] ?? 2);
        $description = trim($_POST['description'] ?? '');
        $roomNumbers = array_filter(array_map('trim', explode(',', $_POST['room_numbers'] ?? '')));

        if ($name === '' || empty($roomNumbers)) {
            $message = 'Room type name and at least one room number are required.';
            $messageType = 'error';
        } else {
            $typeId = createRoomType($name, $occupancy, $description);
            if ($typeId) {
                foreach ($roomNumbers as $roomNumber) {
                    createRoom($typeId, $roomNumber);
                }
                header('Location: add_room.php?created=1');
                exit;
            }
            $message = 'Could not create room type. Name may already exist.';
            $messageType = 'error';
        }
    }

    if ($action === 'update_type') {
        $typeId = (int) ($_POST['type_id'] ?? 0);
        $name = trim($_POST['type_name'] ?? '');
        $occupancy = (int) ($_POST['max_occupancy'] ?? 2);
        $description = trim($_POST['description'] ?? '');

        if ($typeId > 0 && updateRoomType($typeId, $name, $occupancy, $description)) {
            header('Location: add_room.php?updated=1');
            exit;
        }
        $message = 'Could not update room type.';
        $messageType = 'error';
    }

    if ($action === 'delete_type') {
        if (!isAdmin()) {
            $message = 'Unauthorized action: Staff members cannot delete room types.';
            $messageType = 'error';
        } else {
            if (deleteRoomType((int) ($_POST['type_id'] ?? 0))) {
                header('Location: add_room.php?deleted=1');
                exit;
            }
            $message = 'Could not delete room type.';
            $messageType = 'error';
        }
    }

    if ($action === 'add_room') {
        if (createRoom((int) ($_POST['type_id'] ?? 0), trim($_POST['room_number'] ?? ''))) {
            header('Location: add_room.php?room_added=1');
            exit;
        }
        $message = 'Could not add room. Number may already exist.';
        $messageType = 'error';
    }

    if ($action === 'delete_room') {
        if (!isAdmin()) {
            $message = 'Unauthorized action: Staff members cannot delete rooms.';
            $messageType = 'error';
        } else {
            if (deleteRoom((int) ($_POST['room_id'] ?? 0))) {
                header('Location: add_room.php?room_deleted=1');
                exit;
            }
            $message = 'Could not delete room.';
            $messageType = 'error';
        }
    }

    if ($action === 'sync_room_plan') {
        $summary = reconcileRoomNumbering();
        $parts = [];
        if (!empty($summary['added'])) {
            $parts[] = count($summary['added']) . ' room(s) added';
        }
        if (!empty($summary['reassigned'])) {
            $parts[] = count($summary['reassigned']) . ' room(s) reassigned';
        }
        if (!empty($summary['removed'])) {
            $parts[] = count($summary['removed']) . ' room(s) removed';
        }
        if (!empty($summary['kept_but_deactivated'])) {
            $parts[] = count($summary['kept_but_deactivated']) . ' room(s) deactivated (has reservation history: '
                . implode(', ', $summary['kept_but_deactivated']) . ')';
        }
        $message = $parts ? ('Room numbering plan applied: ' . implode(', ', $parts) . '.') : 'Room numbers already match the plan. Nothing to change.';
        $messageType = 'success';
    }
}

if (isset($_GET['created'])) {
    $message = 'Room type and rooms added successfully.';
}
if (isset($_GET['updated'])) {
    $message = 'Room type updated successfully.';
}
if (isset($_GET['deleted'])) {
    $message = 'Room type deleted successfully.';
}
if (isset($_GET['room_added'])) {
    $message = 'Room number added successfully.';
}
if (isset($_GET['room_deleted'])) {
    $message = 'Room removed successfully.';
}

$roomTypes = getRoomTypes();
$allRooms = getAllRoomsWithTypes();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Room</title>
    <script src="https://kit.fontawesome.com/8aab9e126a.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./admin_style.css?v=20260617">
</head>

<body class="page-manage-rooms">
    <div class="topbar">HOTEL MATE</div>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="main">
            <div class="page-header">
                <h3>Add / Manage Rooms</h3>
                <form method="post"
                    onsubmit="return confirm('This will reassign room numbers to match the standard plan (Heritage Twin: 103,105,106 · Heritage Queen: 104 · Heritage Family: 201,203,303 · Heritage Deluxe: 202,302 · Durbar Suite: 301,401 · Legendary Suite: 402) and remove any other room numbers. Continue?');">
                    <input type="hidden" name="action" value="sync_room_plan">
                    <button class="btn-add" type="submit"><i class="fas fa-sync-alt"></i> Apply Standard Room
                        Numbering</button>
                </form>
            </div>

            <?php if ($message): ?>
                <div class="admin-alert <?= h($messageType); ?>"><?= h($message); ?></div>
            <?php endif; ?>

            <div class="card">
                <h2>Add Room Type</h2>
                <form method="post">
                    <input type="hidden" name="action" value="add_type">
                    <div class="row">
                        <div class="input-group">
                            <label>Room Type</label>
                            <input type="text" name="type_name" placeholder="e.g. Heritage Twin" required>
                        </div>
                        <div class="input-group">
                            <label>Max Occupancy</label>
                            <select name="max_occupancy" required>
                                <option value="1">1</option>
                                <option value="2" selected>2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Room Numbers (comma separated)</label>
                            <input type="text" name="room_numbers" placeholder="401, 402, 403" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Description</label>
                            <textarea name="description"></textarea>
                        </div>
                    </div>
                    <button class="btn" type="submit">Add Room Type</button>
                </form>
            </div>

            <div class="table-card" style="margin-top:24px;">
                <h3 style="margin-bottom:16px;">Existing Room Types & Numbers</h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Room Type</th>
                                <th>Occupancy</th>
                                <th>Room Numbers</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roomTypes as $type): ?>
                                <?php
                                $roomsForType = array_filter($allRooms, function ($room) use ($type) {
                                    return (int) $room['room_type_id'] === (int) $type['id'];
                                });
                                $roomNumbers = implode(', ', array_column($roomsForType, 'room_number'));
                                ?>
                                <tr>
                                    <td><?= h($type['name']); ?></td>
                                    <td><?= h($type['max_occupancy']); ?></td>
                                    <td><?= h($roomNumbers); ?></td>
                                    <td class="action-buttons">
                                        <?php if (isAdmin()): ?>
                                            <form method="post"
                                                onsubmit="return confirm('Delete this room type and all its rooms?');">
                                                <input type="hidden" name="action" value="delete_type">
                                                <input type="hidden" name="type_id" value="<?= h($type['id']); ?>">
                                                <button class="action-delete" type="submit" title="Delete"><i
                                                        class="fas fa-trash-alt"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4">
                                        <form method="post" class="row" style="align-items:end;">
                                            <input type="hidden" name="action" value="add_room">
                                            <input type="hidden" name="type_id" value="<?= h($type['id']); ?>">
                                            <div class="input-group">
                                                <label>Add Room Number to <?= h($type['name']); ?></label>
                                                <input type="text" name="room_number" placeholder="e.g. 404" required>
                                            </div>
                                            <button class="btn-add-modal" type="submit">Add Room</button>
                                        </form>
                                        <?php foreach ($roomsForType as $room): ?>
                                            <?php if (isAdmin()): ?>
                                                <form method="post" style="display:inline-block;margin:4px 8px 0 0;"
                                                    onsubmit="return confirm('Remove room <?= h($room['room_number']); ?>?');">
                                                    <input type="hidden" name="action" value="delete_room">
                                                    <input type="hidden" name="room_id" value="<?= h($room['id']); ?>">
                                                    <button type="submit" class="btn-cancel"><?= h($room['room_number']); ?>
                                                        ×</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="btn-cancel" style="display:inline-block;margin:4px 8px 0 0;cursor:default;"><?= h($room['room_number']); ?></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/high_priority_alert.php'; ?>
</body>

</html>