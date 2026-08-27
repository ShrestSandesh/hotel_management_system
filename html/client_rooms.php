<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/room_repository.php';
$roomTypes = getRoomTypesWithRoomsForClient();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel - Rooms</title>
    <link rel="stylesheet" href="./client_side.css?v=20260617">
</head>

<body class="page-client">
    <?php include 'client_sidebar.php'; ?>
    <main class="client-main">
        <section class="client-section-title">
            <h1>Available Room Types</h1>
            <p>Room types and numbers are loaded from the hotel database.</p>
        </section>
        <section class="client-room-grid" id="roomGrid">
            <?php foreach ($roomTypes as $room): ?>
                <div class="client-room-card">
                    <div class="client-room-image">🏨</div>
                    <div class="client-room-body">
                        <h3><?= h($room['name']); ?></h3>
                        <p><?= h($room['description'] ?: 'Comfortable room for your stay.'); ?></p>
                        <p><strong>Occupancy:</strong> Up to <?= h($room['max_occupancy']); ?> guests</p>
                        <p><strong>Rate per Night:</strong> <?= h($room['rate_per_night']); ?> NPR</p>
                        <p><strong>Room Numbers:</strong> <?= h(implode(', ', $room['rooms'])); ?></p>
                        <a href="client_reservation.php?roomType=<?= urlencode($room['name']); ?>"
                            class="client-primary-btn small">Book Now</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
</body>

</html>