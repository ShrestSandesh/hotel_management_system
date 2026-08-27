<?php
session_start();
require_once '../auth_repository.php';
require_once '../reservation_repository.php';
require_once '../complaint_repository.php';

requireAdminLogin();

$stats = getDashboardStats();
$complaintCount = count(getComplaintTickets());
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="./admin_style.css?v=20260617">
</head>

<body class="page-dashboard">
    <div class="topbar">HOTEL MATE</div>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="content">
            <h3>Dashboard</h3>
            <div class="grid">
                <div class="card"><span class="emoji">🛏️</span>
                    <h2><?= h($stats['total_rooms']); ?></h2>
                    <p>Total Rooms</p>
                </div>
                <div class="card"><span class="emoji">📑</span>
                    <h2><?= h($stats['total_reservations']); ?></h2>
                    <p>Total Reservations</p>
                </div>
                <div class="card"><span class="emoji">⏳</span>
                    <h2><?= h($stats['pending_payments_count']); ?></h2>
                    <p>Pending Payments</p>
                </div>
                <div class="card"><span class="emoji">💬</span>
                    <h2><?= e($complaintCount); ?></h2>
                    <p>Complaints</p>
                </div>
            </div>
            <div class="grid dashboard-grid-spaced">
                <div class="card"><span class="emoji">📋</span>
                    <h2><?= h($stats['booked_today']); ?></h2>
                    <p>Today's Booked Rooms</p>
                </div>
                <div class="card"><span class="emoji">✅</span>
                    <h2><?= h($stats['available_today']); ?></h2>
                    <p>Today's Available Rooms</p>
                </div>
                <div class="card"><span class="emoji">✔️</span>
                    <h2><?= h($stats['checked_in']); ?></h2>
                    <p>Checked In</p>
                </div>
                <div class="card"><span class="emoji">💳</span>
                    <h2>NPR <?= h(number_format($stats['pending_payment_amount_npr'], 0)); ?></h2>
                    <p>NPR Pending Amount</p>
                </div>
                <div class="card"><span class="emoji">💵</span>
                    <h2>USD <?= h(number_format($stats['pending_payment_amount_usd'], 0)); ?></h2>
                    <p>USD Pending Amount</p>
                </div>
            </div>
            <?php if (isset($_SESSION['last_reservation'])): ?>
                <div class="large-grid">
                    <div class="large-card">
                        📋 Last Reservation: <?= h($_SESSION['last_reservation']['number']); ?><br>
                        <small>Customer: <?= h($_SESSION['last_reservation']['name']); ?> | Room:
                            <?= h($_SESSION['last_reservation']['room_type'] . ' - ' . $_SESSION['last_reservation']['room_number']); ?></small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'includes/high_priority_alert.php'; ?>
</body>

</html>