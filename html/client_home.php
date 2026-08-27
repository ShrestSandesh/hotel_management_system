<?php require_once __DIR__ . '/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel - Client Home</title>
    <link rel="stylesheet" href="./client_side.css?v=20260527">
</head>

<body class="page-client">
    <?php include 'client_sidebar.php'; ?>

    <section class="client-hero">
        <div class="client-hero-content">
            <p class="client-kicker">Welcome to HotelMate</p>
            <h1>Comfortable rooms, easy booking, and quick service.</h1>
            <p>Book your room online, check your booking details, and contact reception directly for service requests.
            </p>
            <div class="client-hero-actions">
                <a class="client-primary-btn" href="client_reservation.php">Book a Room</a>
                <a class="client-secondary-btn" href="client_rooms.php">View Rooms</a>
            </div>
        </div>
    </section>

    <main class="client-main">
        <section class="client-section-title">
            <h2>Our Services</h2>
        </section>

        <section class="client-feature-grid">
            <div class="client-feature-card">
                <span>🛏️</span>
                <h3>Room Booking</h3>
                <p>Customers can select room type, room number, check-in date, check-out date, and submit a booking.</p>
            </div>
            <div class="client-feature-card">
                <span>📋</span>
                <h3>Booking Tracking</h3>
                <p>After booking, customers can view their latest booking details from the My Booking page.</p>
            </div>
            <div class="client-feature-card">
                <span>💬</span>
                <h3>Reception Support</h3>
                <p>Customers can call reception directly, and staff can record any complaint from the admin panel.</p>
            </div>
        </section>
    </main>
</body>

</html>