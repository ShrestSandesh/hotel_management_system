<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);

function isClientActivePage($page, $currentPage)
{
    return $page === $currentPage ? 'active' : '';
}

$clientLoggedIn = !empty($_SESSION['client_user']);
?>
<div class="client-navbar">
    <div class="client-brand">🏨 Hotel</div>
    <button class="client-menu-toggle" type="button" onclick="toggleClientMenu()">☰</button>
    <ul class="client-menu" id="clientMenu">
        <li class="<?= isClientActivePage('client_home.php', $currentPage); ?>"><a href="client_home.php">Home</a></li>
        <li class="<?= isClientActivePage('client_rooms.php', $currentPage); ?>"><a href="client_rooms.php">Rooms</a>
        </li>
        <li class="<?= isClientActivePage('client_reservation.php', $currentPage); ?>"><a
                href="client_reservation.php">Book Room</a></li>
        <li class="<?= isClientActivePage('client_my_booking.php', $currentPage); ?>"><a href="client_my_booking.php">My
                Booking</a></li>
        <?php if ($clientLoggedIn): ?>
            <li><a href="client_logout.php" class="admin-link">Logout</a></li>
        <?php else: ?>
            <li><a href="client_login.php" class="admin-link">Client Login</a></li>
        <?php endif; ?>
    </ul>
</div>
<script>
    function toggleClientMenu() {
        document.getElementById('clientMenu').classList.toggle('show');
    }
</script>