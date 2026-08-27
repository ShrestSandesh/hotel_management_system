<?php
session_start();
require_once '../auth_repository.php';
require_once '../room_repository.php';
require_once '../reservation_repository.php';

requireAdminLogin();

$roomTypes = getRoomTypes();
$bookedViaOptions = ["Booking.com", "Agoda.com", "Ctrip", "Expedia", "Airbnb", "Walk-in", "Whatsapp", "Website", "Travel Agency", "Referral"];
$roomPlanOptions = ["EP", "BB", "MAP", "AP"];
$paymentModeOptions = ["Cash", "Card", "QR"];
$errorMessage = '';
$showPopup = false;
$reservationSummary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomTypeId = (int) ($_POST['room_type_id'] ?? 0);
    $roomId = (int) ($_POST['room_id'] ?? 0);
    $checkIn = trim($_POST['checkin'] ?? '');
    $checkOut = trim($_POST['checkout'] ?? '');
    $currency = $_POST['currency'] ?? 'NPR';
    $pricePerNight = (float) ($_POST['price_per_night'] ?? 0);
    $occupancy = (int) ($_POST['occupancy'] ?? 1);

    $guestData = [
        'first_name' => trim($_POST['fname'] ?? ''),
        'middle_name' => trim($_POST['mname'] ?? ''),
        'last_name' => trim($_POST['lname'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'contact_number' => trim($_POST['contact'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'id_type' => trim($_POST['idType'] ?? ''),
        'id_number' => trim($_POST['idNumber'] ?? '')
    ];

    $bookedVia = trim($_POST['booked_via'] ?? '');
    $roomPlan = trim($_POST['room_plan'] ?? '');
    $guestRequest = trim($_POST['guest_request'] ?? '');
    $paymentMode = trim($_POST['payment_mode'] ?? '');

    if ($roomId <= 0 || $checkIn === '' || $checkOut === '' || $pricePerNight <= 0 || $bookedVia === '' || $roomPlan === '') {
        $errorMessage = 'Please complete all required room, pricing, Booked via, and Room Plan fields.';
    } elseif (strtotime($checkOut) <= strtotime($checkIn)) {
        $errorMessage = 'Check out date must be after check in date.';
    } elseif ($guestData['first_name'] === '' || $guestData['last_name'] === '' || $guestData['country'] === '') {
        $errorMessage = 'Please fill in required guest details.';
    } elseif ($guestData['id_type'] === '' || $guestData['id_number'] === '') {
        $errorMessage = 'ID Card Type and ID Card Number are required.';
    } else {
        $roomType = getRoomTypeById($roomTypeId);
        if ($roomType && $occupancy > (int) $roomType['max_occupancy']) {
            $errorMessage = 'Occupancy exceeds the limit for this room type.';
        } else {
            $result = createReservation([
                'room_id' => $roomId,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'occupancy' => $occupancy,
                'currency' => $currency,
                'price_per_night' => $pricePerNight,
                'source' => 'admin',
                'booked_via' => $bookedVia,
                'room_plan' => $roomPlan,
                'guest_request' => $guestRequest,
                'payment_mode' => $paymentMode,
                'occupants' => $_POST['occupants'] ?? [],
                'guest' => $guestData
            ]);

            if (!$result['success']) {
                $errorMessage = $result['message'];
            } else {
                $reservation = getReservationById((int) $result['reservation_id']);
                $reservationSummary = $reservation;
                $showPopup = true;

                $_SESSION['last_reservation'] = [
                    'number' => $result['reservation_number'],
                    'name' => guestFullName($reservation),
                    'room_type' => $reservation['room_type_name'],
                    'room_number' => $reservation['room_number']
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reservation</title>
    <script src="https://kit.fontawesome.com/8aab9e126a.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./admin_style.css?v=20260617">
</head>

<body class="page-reservation">
    <div class="topbar">HOTEL MATE</div>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="main">
            <?php if ($errorMessage): ?>
                <div class="admin-alert error"><?= h($errorMessage); ?></div>
            <?php endif; ?>

            <div class="popup <?= $showPopup ? 'show' : ''; ?>">
                <?php if ($showPopup && $reservationSummary): ?>
                    <button class="close-btn"
                        onclick="document.querySelector('.popup').style.display='none'; window.location.href='dashboard.php';">×</button>
                    <h3>Reservation Details</h3>
                    <table border="1" class="reservation-summary-table">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tr>
                            <td>Reservation Number</td>
                            <td><?= h($reservationSummary['reservation_number']); ?></td>
                        </tr>
                        <tr>
                            <td>Name</td>
                            <td><?= h(guestFullName($reservationSummary)); ?></td>
                        </tr>
                        <tr>
                            <td>Room Type</td>
                            <td><?= h($reservationSummary['room_type_name']); ?></td>
                        </tr>
                        <tr>
                            <td>Room Number</td>
                            <td><?= h($reservationSummary['room_number']); ?></td>
                        </tr>
                        <tr>
                            <td>Occupancy</td>
                            <td><?= h($reservationSummary['occupancy']); ?></td>
                        </tr>
                        <tr>
                            <td>Check In</td>
                            <td><?= h($reservationSummary['check_in_date']); ?></td>
                        </tr>
                        <tr>
                            <td>Check Out</td>
                            <td><?= h($reservationSummary['check_out_date']); ?></td>
                        </tr>
                        <tr>
                            <td>Total Nights</td>
                            <td><?= h($reservationSummary['total_nights']); ?></td>
                        </tr>
                        <tr>
                            <td>Total Price</td>
                            <td><?= h($reservationSummary['currency'] . ' ' . number_format((float) $reservationSummary['total_price'], 2)); ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Payment Status</td>
                            <td><?= h($reservationSummary['payment_status']); ?></td>
                        </tr>
                        <tr>
                            <td>Booked via</td>
                            <td><?= h($reservationSummary['booked_via']); ?></td>
                        </tr>
                        <tr>
                            <td>Room Plan</td>
                            <td><?= h($reservationSummary['room_plan']); ?></td>
                        </tr>
                        <tr>
                            <td>Mode of Payment</td>
                            <td><?= h($reservationSummary['payment_mode'] ?: 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td>Guests Request</td>
                            <td><?= h($reservationSummary['guest_request'] ?: 'None'); ?></td>
                        </tr>
                    </table>
                <?php else: ?>
                    <p>Your reservation will appear here</p>
                <?php endif; ?>
            </div>

            <form method="POST" id="reservationForm">
                <div class="card">
                    <h2>Room Information</h2>
                    <div class="row">
                        <div class="input-group">
                            <label>Room Type</label>
                            <select name="room_type_id" id="roomType" required>
                                <option value="">Select Room Type</option>
                                <?php foreach ($roomTypes as $type): ?>
                                    <option value="<?= h($type['id']); ?>"
                                        data-occupancy="<?= h($type['max_occupancy']); ?>">
                                        <?= h($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Room Number</label>
                            <select name="room_id" id="roomNo" required>
                                <option value="">Select Room Number</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Currency</label>
                            <select name="currency" id="currency">
                                <option value="NPR">NPR</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Price Per Night</label>
                            <input type="number" name="price_per_night" id="pricePerNight" min="0" step="0.01" required>
                        </div>
                        <div class="input-group">
                            <label>Occupancy</label>
                            <select name="occupancy" id="occupancy" required>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Check In Date</label>
                            <input type="date" name="checkin" id="checkin" required>
                        </div>
                        <div class="input-group">
                            <label>Check Out Date</label>
                            <input type="date" name="checkout" id="checkout" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Booked via <span style="color:#ef4444;">*</span></label>
                            <select name="booked_via" required>
                                <option value="">Select Channel</option>
                                <?php foreach ($bookedViaOptions as $opt): ?>
                                    <option value="<?= h($opt); ?>"><?= h($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Room Plan <span style="color:#ef4444;">*</span></label>
                            <select name="room_plan" required>
                                <option value="">Select Plan</option>
                                <?php foreach ($roomPlanOptions as $opt): ?>
                                    <option value="<?= h($opt); ?>"><?= h($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Mode of Payment <span style="color:#94a3b8; font-weight:normal; font-size:12px;"></span></label>
                            <select name="payment_mode">
                                <option value="">Select Mode</option>
                                <?php foreach ($paymentModeOptions as $opt): ?>
                                    <option value="<?= h($opt); ?>"><?= h($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Guests Request <span style="color:#94a3b8; font-weight:normal; font-size:12px;"></span></label>
                            <input type="text" name="guest_request" placeholder="Special requests or notes">
                        </div>
                    </div>
                    <div class="summary">
                        <p>Room Type: <span id="summaryRoomType">-</span></p>
                        <p>Room Number: <span id="summaryRoomNo">-</span></p>
                        <p>Occupancy: <span id="summaryOccupancy">-</span></p>
                        <p>Check-In: <span id="summaryCheckin">-</span></p>
                        <p>Check-Out: <span id="summaryCheckout">-</span></p>
                        <p>Total Nights: <span id="days">0</span></p>
                        <p>Total Price: <span id="total">0</span></p>
                    </div>
                </div>

                <div class="card">
                    <h2>Customer Detail</h2>
                    <div class="row">
                        <div class="input-group">
                            <label>First Name</label>
                            <input type="text" name="fname" required>
                        </div>
                        <div class="input-group">
                            <label>Middle Name</label>
                            <input type="text" name="mname">
                        </div>
                        <div class="input-group">
                            <label>Last Name</label>
                            <input type="text" name="lname" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact" id="contactNumber" pattern="[0-9]{10,}">
                        </div>
                        <div class="input-group">
                            <label>Email Address</label>
                            <input type="email" name="email">
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>ID Card Type</label>
                            <select name="idType" id="idType" required>
                                <option value="">Select ID Card Type</option>
                                <option>Passport</option>
                                <option>Citizenship</option>
                                <option>National ID</option>
                                <option>Driver's License</option>
                                <option>Voter ID</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>ID Card Number</label>
                            <input type="text" name="idNumber" id="idNumber" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Residential Address</label>
                            <textarea name="address"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Country</label>
                            <input type="text" name="country" required>
                        </div>
                    </div>
                    <div id="resExtraOccupantsContainer"></div>
                    <button class="btn" type="submit">Submit</button>
                </div>
            </form>
        </div>
    </div>
    <?php include 'includes/high_priority_alert.php'; ?>
    <script>
        const rate = 130;

        async function loadRooms() {
            const roomTypeId = document.getElementById('roomType').value;
            const roomNoSelect = document.getElementById('roomNo');
            roomNoSelect.innerHTML = '<option value="">Select Room Number</option>';

            if (!roomTypeId) {
                updateSummary();
                return;
            }

            const response = await fetch(`../api.php?action=rooms_by_type&room_type_id=${roomTypeId}`);
            const data = await response.json();

            if (data.success) {
                data.data.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = room.room_number;
                    option.dataset.roomNumber = room.room_number;
                    roomNoSelect.appendChild(option);
                });
            }

            updateOccupancyOptions();
            updateSummary();
        }

        function updateOccupancyOptions() {
            const roomType = document.getElementById('roomType');
            const selected = roomType.options[roomType.selectedIndex];
            const maxOccupancy = selected ? parseInt(selected.dataset.occupancy || '2', 10) : 2;
            const occupancy = document.getElementById('occupancy');

            occupancy.innerHTML = '';
            for (let i = 1; i <= maxOccupancy; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                occupancy.appendChild(option);
            }
        }

        function calculate() {
            const checkinValue = document.getElementById('checkin').value;
            const checkoutValue = document.getElementById('checkout').value;
            const price = parseFloat(document.getElementById('pricePerNight').value) || 0;
            const currency = document.getElementById('currency').value;
            const daysEl = document.getElementById('days');
            const totalEl = document.getElementById('total');

            if (!checkinValue || !checkoutValue || !price) {
                daysEl.textContent = '0';
                totalEl.textContent = '0';
                updateSummary();
                return;
            }

            const checkin = new Date(checkinValue);
            const checkout = new Date(checkoutValue);

            if (checkout > checkin) {
                const diff = (checkout - checkin) / (1000 * 60 * 60 * 24);
                let extraPrice = 0;
                document.querySelectorAll('.extra-occ-price').forEach(input => {
                    extraPrice += parseFloat(input.value) || 0;
                });
                const effectivePrice = price + extraPrice;
                daysEl.textContent = diff;
                totalEl.textContent = (diff * effectivePrice).toFixed(2) + ' ' + currency;
            }

            updateSummary();
        }

        function updateSummary() {
            const roomType = document.getElementById('roomType');
            const roomNo = document.getElementById('roomNo');
            document.getElementById('summaryRoomType').textContent = roomType.value ? roomType.options[roomType.selectedIndex].text : '-';
            document.getElementById('summaryRoomNo').textContent = roomNo.value ? roomNo.options[roomNo.selectedIndex].text : '-';
            document.getElementById('summaryOccupancy').textContent = document.getElementById('occupancy').value || '-';
            document.getElementById('summaryCheckin').textContent = document.getElementById('checkin').value || '-';
            document.getElementById('summaryCheckout').textContent = document.getElementById('checkout').value || '-';
        }

        function renderResExtraOccupants() {
            const container = document.getElementById('resExtraOccupantsContainer');
            if (!container) return;
            container.innerHTML = '';

            const occCount = parseInt(document.getElementById('occupancy').value || '1', 10);
            if (occCount <= 1) return;

            for (let i = 2; i <= occCount; i++) {
                const prefix = `occupants[${i}]`;
                const wrapper = document.createElement('div');
                wrapper.className = 'extra-occupant-card';
                wrapper.style.cssText = 'margin-top:16px; padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;';

                wrapper.innerHTML = `
                    <h3 style="margin:0 0 12px 0; font-size:14px; text-transform:uppercase; letter-spacing:0.04em; color:#2563eb; font-weight:800;">
                        <i class="fas fa-user-friends"></i> Details of Occupant ${i} (Optional)
                    </h3>
                    <input type="hidden" name="${prefix}[occupant_order]" value="${i}">
                    <div class="row">
                        <div class="input-group"><label>First Name</label><input type="text" name="${prefix}[first_name]" placeholder="First Name"></div>
                        <div class="input-group"><label>Middle Name</label><input type="text" name="${prefix}[middle_name]" placeholder="Middle Name"></div>
                        <div class="input-group"><label>Last Name</label><input type="text" name="${prefix}[last_name]" placeholder="Last Name"></div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>Contact Number</label><input type="text" name="${prefix}[contact_number]" placeholder="Contact Number"></div>
                        <div class="input-group"><label>Email Address</label><input type="email" name="${prefix}[email]" placeholder="Email Address"></div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>Country</label><input type="text" name="${prefix}[country]" placeholder="Country"></div>
                        <div class="input-group"><label>Price Per Night (Optional)</label><input type="number" step="0.01" min="0" class="extra-occ-price" name="${prefix}[price_per_night]" placeholder="0.00" oninput="calculate()"></div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>ID Card Type</label><input type="text" name="${prefix}[id_type]" placeholder="e.g. Passport, Citizenship"></div>
                        <div class="input-group"><label>ID Card Number</label><input type="text" name="${prefix}[id_number]" placeholder="ID Number"></div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>Address</label><textarea name="${prefix}[address]" placeholder="Address"></textarea></div>
                    </div>
                `;
                container.appendChild(wrapper);
            }
        }

        document.getElementById('roomType').addEventListener('change', () => { loadRooms(); calculate(); });
        document.getElementById('roomNo').addEventListener('change', calculate);
        document.getElementById('checkin').addEventListener('change', calculate);
        document.getElementById('checkout').addEventListener('change', calculate);
        document.getElementById('currency').addEventListener('change', calculate);
        document.getElementById('pricePerNight').addEventListener('input', calculate);
        document.getElementById('occupancy').addEventListener('change', () => { renderResExtraOccupants(); calculate(); });
    </script>
</body>

</html>