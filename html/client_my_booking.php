<?php
session_start();
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/reservation_repository.php';
require_once __DIR__ . '/room_repository.php';

if (empty($_SESSION['client_user'])) {
    header('Location: client_login.php?redirect=client_my_booking.php');
    exit;
}

$clientUser = $_SESSION['client_user'];
$clientUserId = (int) ($_SESSION['client_user_id'] ?? 0);
$clientUserName = $_SESSION['client_user_name'] ?? $clientUser;
$message = '';
$reservations = getReservationsForClient($clientUserId, $clientUser);
$roomTypes = getRoomTypes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_reservation') {
        $reservationId = (int) ($_POST['reservation_id'] ?? 0);
        $result = updateClientReservation($reservationId, $clientUserId, $clientUser, [
            'room_id' => (int) ($_POST['room_id'] ?? 0),
            'check_in_date' => trim($_POST['check_in_date'] ?? ''),
            'check_out_date' => trim($_POST['check_out_date'] ?? ''),
            'occupancy' => (int) ($_POST['occupancy'] ?? 1),
            'currency' => $_POST['currency'] ?? 'NPR',
            'price_per_night' => (float) ($_POST['price_per_night'] ?? 0),
            'payment_status' => $_POST['payment_status'] ?? 'UNPAID',
            'guest' => [
                'first_name' => trim($_POST['first_name'] ?? ''),
                'middle_name' => trim($_POST['middle_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'country' => trim($_POST['country'] ?? ''),
                'contact_number' => trim($_POST['contact_number'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'address' => trim($_POST['address'] ?? ''),
                'id_type' => trim($_POST['id_type'] ?? ''),
                'id_number' => trim($_POST['id_number'] ?? '')
            ]
        ]);
        $message = $result['success'] ? '<div class="client-alert success">Reservation updated.</div>' : '<div class="client-alert error">' . h($result['message']) . '</div>';
        $reservations = getReservationsForClient($clientUserId, $clientUser);
    } elseif ($action === 'delete_reservation') {
        $reservationId = (int) ($_POST['reservation_id'] ?? 0);
        $result = deleteClientReservation($reservationId, $clientUserId, $clientUser);
        $message = $result['success'] ? '<div class="client-alert success">Reservation deleted.</div>' : '<div class="client-alert error">' . h($result['message']) . '</div>';
        $reservations = getReservationsForClient($clientUserId, $clientUser);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel - My Booking</title>
    <link rel="stylesheet" href="./client_side.css?v=20260716">
    <style>
        .client-booking-actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        .client-booking-actions button,
        .client-booking-actions a {
            border: none;
            padding: 10px 14px;
            border-radius: 999px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
            font-weight: 700;
        }

        .client-booking-actions .danger {
            background: #dc2626;
        }

        .client-booking-form {
            display: grid;
            gap: 14px;
            margin-top: 18px;
        }

        .client-booking-form .client-form-row {
            margin-bottom: 0;
        }
    </style>
</head>

<body class="page-client">
    <?php include 'client_sidebar.php'; ?>
    <main class="client-main">
        <section class="client-section-title">
            <h1>My Booking</h1>
            <p>Here are all of your reservations after you sign in.</p>
            <p style="margin-top:10px; font-weight:600; color:#0f766e;">Signed in as <?= h($clientUserName); ?></p>
        </section>
        <?= $message; ?>
        <div class="client-booking-list">
            <?php if (empty($reservations)): ?>
                <div class="client-empty">You have no reservations yet. Visit the booking page to create one.</div>
            <?php else: ?>
                <?php foreach ($reservations as $booking): ?>
                    <div class="client-booking-card">
                        <h3><?= h($booking['reservation_number']); ?></h3>
                        <p><strong>Name:</strong> <?= h(guestFullName($booking)); ?></p>
                        <p><strong>Room:</strong> <?= h($booking['room_type_name'] . ' - ' . $booking['room_number']); ?></p>
                        <p><strong>Check In:</strong> <?= h($booking['check_in_date']); ?></p>
                        <p><strong>Check Out:</strong> <?= h($booking['check_out_date']); ?></p>
                        <p><strong>Total:</strong>
                            <?= h($booking['currency'] . ' ' . number_format((float) $booking['total_price'], 2)); ?></p>
                        <p><strong>Payment:</strong> <?= h($booking['payment_status']); ?></p>
                        <p><strong>Check-In Status:</strong> <span
                                class="client-status"><?= h($booking['check_in_status']); ?></span></p>
                        <div class="client-booking-actions">
                            <button type="button" onclick="toggleEditForm(<?= (int) $booking['id']; ?>)">Edit</button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this reservation?');">
                                <input type="hidden" name="action" value="delete_reservation">
                                <input type="hidden" name="reservation_id" value="<?= (int) $booking['id']; ?>">
                                <button type="submit" class="danger">Delete</button>
                            </form>
                        </div>
                        <form method="post" class="client-booking-form" id="edit-form-<?= (int) $booking['id']; ?>"
                            style="display:none;">
                            <input type="hidden" name="action" value="update_reservation">
                            <input type="hidden" name="reservation_id" value="<?= (int) $booking['id']; ?>">
                            <div class="client-form-row">
                                <div class="client-input-group">
                                    <label>Room Type</label>
                                    <select name="room_id" required>
                                        <?php foreach ($roomTypes as $type): ?>
                                            <option value="<?= (int) $type['id']; ?>" <?= ((int) $booking['room_id'] === (int) $type['id']) ? 'selected' : ''; ?>><?= h($type['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="client-input-group">
                                    <label>Occupancy</label>
                                    <select name="occupancy" required>
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <option value="<?= $i; ?>" <?= ((int) $booking['occupancy'] === $i) ? 'selected' : ''; ?>>
                                                <?= $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="client-form-row">
                                <div class="client-input-group"><label>Check In</label><input type="date" name="check_in_date"
                                        value="<?= h($booking['check_in_date']); ?>" required></div>
                                <div class="client-input-group"><label>Check Out</label><input type="date" name="check_out_date"
                                        value="<?= h($booking['check_out_date']); ?>" required></div>
                            </div>
                            <div class="client-form-row">
                                <div class="client-input-group"><label>Price Per Night</label><input type="number"
                                        name="price_per_night" value="<?= h($booking['price_per_night']); ?>" step="0.01"
                                        required></div>
                                <div class="client-input-group"><label>Currency</label><select name="currency">
                                        <option value="NPR" <?= $booking['currency'] === 'NPR' ? 'selected' : ''; ?>>NPR</option>
                                        <option value="USD" <?= $booking['currency'] === 'USD' ? 'selected' : ''; ?>>USD</option>
                                    </select></div>
                            </div>
                            <div class="client-form-row">
                                <div class="client-input-group"><label>First Name</label><input type="text" name="first_name"
                                        value="<?= h($booking['first_name']); ?>" required></div>
                                <div class="client-input-group"><label>Last Name</label><input type="text" name="last_name"
                                        value="<?= h($booking['last_name']); ?>" required></div>
                            </div>
                            <div class="client-form-row">
                                <div class="client-input-group"><label>Contact Number</label><input type="text"
                                        name="contact_number" value="<?= h($booking['contact_number']); ?>"></div>
                                <div class="client-input-group"><label>Email</label><input type="email" name="email"
                                        value="<?= h($booking['email']); ?>" required></div>
                            </div>
                            <div class="client-form-row">
                                <div class="client-input-group"><label>Country</label><input type="text" name="country"
                                        value="<?= h($booking['country']); ?>" required></div>
                                <div class="client-input-group"><label>ID Type</label><input type="text" name="id_type"
                                        value="<?= h($booking['id_type']); ?>" required></div>
                            </div>
                            <div class="client-form-row">
                                <div class="client-input-group full"><label>Address</label><textarea
                                        name="address"><?= h($booking['address']); ?></textarea></div>
                            </div>
                            <div class="client-form-row">
                                <div class="client-input-group"><label>ID Number</label><input type="text" name="id_number"
                                        value="<?= h($booking['id_number']); ?>" required></div>
                                <div class="client-input-group"><label>Payment</label><select name="payment_status">
                                        <option value="UNPAID" <?= $booking['payment_status'] === 'UNPAID' ? 'selected' : ''; ?>>
                                            UNPAID</option>
                                        <option value="PAID" <?= $booking['payment_status'] === 'PAID' ? 'selected' : ''; ?>>PAID
                                        </option>
                                    </select></div>
                            </div>
                            <button type="submit" class="client-primary-btn">Save Changes</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    <script>
        function toggleEditForm(id) {
            const form = document.getElementById('edit-form-' + id);
            if (form) {
                form.style.display = form.style.display === 'none' ? 'grid' : 'none';
            }
        }
    </script>
</body>

</html>