<?php
$currentPage = basename($_SERVER['PHP_SELF']);

if (!function_exists('h')) {
    require_once __DIR__ . '/../db.php';
}
if (!function_exists('getAdminName')) {
    require_once __DIR__ . '/../auth_repository.php';
}

function isActivePage($page, $currentPage)
{
    return $page === $currentPage ? 'active' : '';
}
?>
<div class="sidebar">
    <div class="profile">
        <h2><?= isAdmin() ? 'Hotel Manager' : 'Hotel Staff'; ?></h2>
        <small><?= h(getAdminName()); ?></small>
    </div>
    <ul class="menu">
        <li class="<?= isActivePage('dashboard.php', $currentPage); ?>"><a href="dashboard.php">Dashboard</a></li>
        <li class="<?= isActivePage('calendar.php', $currentPage); ?>"><a href="calendar.php">Calendar</a></li>
        <li class="<?= isActivePage('reservation.php', $currentPage); ?>"><a href="reservation.php">Reservation</a></li>
        <li class="<?= isActivePage('add_room.php', $currentPage); ?>"><a href="add_room.php">Add Room</a></li>
        <li class="<?= isActivePage('room_status.php', $currentPage); ?>"><a href="room_status.php">Room Status</a></li>
        <li class="<?= isActivePage('current_guests.php', $currentPage); ?>"><a href="current_guests.php">Current Guests</a></li>
        <li class="<?= isActivePage('guest_stays.php', $currentPage); ?>"><a href="guest_stays.php">All Guests</a></li>
        <li class="<?= isActivePage('manage_complaints.php', $currentPage); ?>"><a
                href="manage_complaints.php">Complaint Tickets</a></li>
        <li class="<?= isActivePage('log_sheet.php', $currentPage); ?>"><a href="log_sheet.php">Log Sheet</a></li>
        <?php if (isAdmin()): ?>
            <li><a href="../client_home.php">Client Site</a></li>
        <?php endif; ?>
    </ul>
    <div class="logout-section">
        <button class="logout-btn" onclick="window.location.href='../logout.php'">Logout</button>
    </div>
</div>