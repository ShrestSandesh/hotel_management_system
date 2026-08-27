<?php
session_start();
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/room_repository.php';
require_once __DIR__ . '/reservation_repository.php';

$clientUser = $_SESSION['client_user'] ?? null;
$clientUserId = $_SESSION['client_user_id'] ?? null;

if (!$clientUser) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please log in first to continue.']);
        exit;
    }

    header('Location: client_login.php?redirect=client_reservation.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_reservation') {
    header('Content-Type: application/json');

    $roomId = (int) ($_POST['room_id'] ?? 0);
    $checkIn = trim($_POST['checkin_date'] ?? '');
    $checkOut = trim($_POST['checkout_date'] ?? '');
    $pricePerNight = (float) ($_POST['price_per_night'] ?? 0);

    if ($roomId <= 0 || $checkIn === '' || $checkOut === '' || $pricePerNight <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing required booking fields.']);
        exit;
    }

    $guestData = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'middle_name' => trim($_POST['middle_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'contact_number' => trim($_POST['contact_number'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'id_type' => trim($_POST['id_type'] ?? ''),
        'id_number' => trim($_POST['id_number'] ?? '')
    ];

    if ($guestData['first_name'] === '' || $guestData['last_name'] === '' || $guestData['country'] === '') {
        echo json_encode(['success' => false, 'message' => 'Please fill in required guest details.']);
        exit;
    }

    if ($guestData['id_type'] === '' || $guestData['id_number'] === '') {
        echo json_encode(['success' => false, 'message' => 'ID Card Type and ID Card Number are required.']);
        exit;
    }

    $bookedVia = trim($_POST['booked_via'] ?? 'Booking.com');
    $roomPlan = trim($_POST['room_plan'] ?? 'EP');
    $guestRequest = trim($_POST['guest_request'] ?? '');
    $paymentMode = trim($_POST['payment_mode'] ?? 'Cash');

    if ($bookedVia === '' || $roomPlan === '') {
        echo json_encode(['success' => false, 'message' => 'Booked via and Room Plan are required.']);
        exit;
    }

    $result = createReservation([
        'room_id' => $roomId,
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'occupancy' => (int) ($_POST['occupancy'] ?? 1),
        'currency' => $_POST['currency'] ?? 'NPR',
        'price_per_night' => $pricePerNight,
        'source' => 'client',
        'booked_via' => $bookedVia,
        'room_plan' => $roomPlan,
        'guest_request' => $guestRequest,
        'payment_mode' => $paymentMode,
        'occupants' => $_POST['occupants'] ?? [],
        'guest' => $guestData,
        'user_id' => $clientUserId
    ]);

    echo json_encode($result);
    exit;
}

$roomTypes = getRoomTypes();
$bookedViaOptions = ["Booking.com", "Agoda.com", "Ctrip", "Expedia", "Airbnb", "Walk-in", "Whatsapp", "Website", "Travel Agency", "Referral"];
$roomPlanOptions = ["EP", "BB", "MAP", "AP"];
$paymentModeOptions = ["Cash", "Card", "QR"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel - Client Reservation</title>
    <link rel="stylesheet" href="./client_side.css?v=20260617">
</head>

<body class="page-client">
    <?php include 'client_sidebar.php'; ?>
    <main class="client-main">
        <section class="client-section-title">
            <h1>Book a Room</h1>
            <p>Choose your room, dates, and guest details. Your booking appears instantly in the admin panel.</p>
            <p style="margin-top:10px; font-weight:600; color:#0f766e;">Signed in as <?= h($clientUser); ?></p>
        </section>

        <form class="client-form-card" id="clientReservationForm">
            <h2>Room Information</h2>
            <div class="client-form-row">
                <div class="client-input-group">
                    <label for="roomType">Room Type</label>
                    <select id="roomType" required>
                        <option value="">Select Room Type</option>
                        <?php foreach ($roomTypes as $type): ?>
                            <option value="<?= h($type['id']); ?>" data-name="<?= h($type['name']); ?>"
                                data-occupancy="<?= h($type['max_occupancy']); ?>"
                                data-rate="<?= h($type['rate_per_night'] ?? 0); ?>">
                                <?= h($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="client-input-group">
                    <label for="roomNo">Room No</label>
                    <select id="roomNo" required>
                        <option value="">Select Room Number</option>
                    </select>
                </div>
            </div>
            <div class="client-form-row">
                <div class="client-input-group">
                    <label for="currency">Currency</label>
                    <select id="currency">
                        <option value="NPR">NPR</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
                <div class="client-input-group">
                    <label for="pricePerNight">Price Per Night</label>
                    <input type="number" id="pricePerNight" min="0" step="0.01" required readonly>
                </div>
                <div class="client-input-group">
                    <label for="occupancy">Occupancy</label>
                    <select id="occupancy" required>
                        <option value="1">1</option>
                        <option value="2">2</option>
                    </select>
                </div>
            </div>
            <div class="client-form-row">
                <div class="client-input-group"><label for="checkin">Check In Date</label><input type="date"
                        id="checkin" required></div>
                <div class="client-input-group"><label for="checkout">Check Out Date</label><input type="date"
                        id="checkout" required></div>
            </div>
            <div class="client-form-row">
                <div class="client-input-group">
                    <label for="bookedVia">Booked via <span style="color:#ef4444;">*</span></label>
                    <select id="bookedVia" required>
                        <option value="">Select Channel</option>
                        <?php foreach ($bookedViaOptions as $opt): ?>
                            <option value="<?= h($opt); ?>"><?= h($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="client-input-group">
                    <label for="roomPlan">Room Plan <span style="color:#ef4444;">*</span></label>
                    <select id="roomPlan" required>
                        <option value="">Select Plan</option>
                        <?php foreach ($roomPlanOptions as $opt): ?>
                            <option value="<?= h($opt); ?>"><?= h($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="client-input-group">
                    <label for="paymentMode">Mode of Payment <span style="color:#94a3b8; font-weight:normal; font-size:12px;">(Optional)</span></label>
                    <select id="paymentMode">
                        <option value="">Select Mode</option>
                        <?php foreach ($paymentModeOptions as $opt): ?>
                            <option value="<?= h($opt); ?>"><?= h($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="client-form-row">
                <div class="client-input-group full">
                    <label for="guestRequest">Guests Request <span style="color:#94a3b8; font-weight:normal; font-size:12px;">(Optional)</span></label>
                    <input type="text" id="guestRequest" placeholder="Special requests or notes">
                </div>
            </div>
            <div class="client-summary-box">
                <p>Total Days: <span id="days">0</span></p>
                <p>Total Amount: <span id="total">0</span></p>
            </div>

            <h2>Customer Detail</h2>
            <div class="client-form-row">
                <div class="client-input-group"><label for="fname">First Name</label><input type="text" id="fname"
                        required></div>
                <div class="client-input-group"><label for="mname">Middle Name</label><input type="text" id="mname">
                </div>
                <div class="client-input-group"><label for="lname">Last Name</label><input type="text" id="lname"
                        required></div>
            </div>
            <div class="client-form-row">
                <div class="client-input-group"><label for="contactNumber">Contact Number</label><input type="text"
                        id="contactNumber" pattern="[0-9]{10,}" required></div>
                <div class="client-input-group"><label for="email">Email Address</label><input type="email" id="email"
                        required></div>
            </div>
            <div class="client-form-row">
                <div class="client-input-group">
                    <label for="idType">ID Card Type</label>
                    <select id="idType" required>
                        <option value="">Select ID Card Type</option>
                        <option>Passport</option>
                        <option>Citizenship</option>
                        <option>National ID</option>
                        <option>Driver's License</option>
                        <option>Voter ID</option>
                    </select>
                </div>
                <div class="client-input-group"><label for="idNumber">ID Card Number</label><input type="text"
                        id="idNumber" required></div>
            </div>
            <div class="client-form-row">
                <div class="client-input-group full"><label for="address">Residential Address</label><textarea
                        id="address"></textarea></div>
            </div>
            <div class="client-form-row">
                <div class="client-input-group"><label for="country">Country</label><input type="text" id="country"
                        required></div>
            </div>
            <div id="clientExtraOccupantsContainer"></div>
            <button type="submit" class="client-primary-btn">Submit Booking</button>
        </form>
    </main>

    <div class="client-popup" id="bookingPopup">
        <button class="client-popup-close" onclick="closePopup()">×</button>
        <h3>Reservation Successful</h3>
        <div id="bookingDetails"></div>
        <a href="client_my_booking.php" class="client-primary-btn small">View My Booking</a>
    </div>

    <script>
        async function loadRooms() {
            const roomTypeId = document.getElementById('roomType').value;
            const roomNoSelect = document.getElementById('roomNo');
            roomNoSelect.innerHTML = '<option value="">Select Room Number</option>';
            document.getElementById('pricePerNight').value = '';
            if (!roomTypeId) return;

            const response = await fetch(`api.php?action=rooms_by_type&room_type_id=${roomTypeId}`);
            const data = await response.json();
            if (data.success) {
                data.data.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = room.room_number;
                    roomNoSelect.appendChild(option);
                });
            }

            const selected = document.getElementById('roomType').options[document.getElementById('roomType').selectedIndex];
            const maxOccupancy = parseInt(selected.dataset.occupancy || '2', 10);
            const occupancy = document.getElementById('occupancy');
            occupancy.innerHTML = '';
            for (let i = 1; i <= maxOccupancy; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                occupancy.appendChild(option);
            }

            const roomRate = selected.dataset.rate || '0';
            document.getElementById('pricePerNight').value = roomRate;
            calculate();
        }

        function renderClientExtraOccupants() {
            const container = document.getElementById('clientExtraOccupantsContainer');
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
                    <h4 style="margin:0 0 12px 0; font-size:14px; text-transform:uppercase; letter-spacing:0.04em; color:#2563eb; font-weight:800;">
                        <i class="fas fa-user-friends"></i> Details of Occupant ${i} (Optional)
                    </h4>
                    <div class="client-form-row">
                        <div class="client-input-group"><label>First Name</label><input type="text" id="occ_${i}_fname" placeholder="First Name"></div>
                        <div class="client-input-group"><label>Middle Name</label><input type="text" id="occ_${i}_mname" placeholder="Middle Name"></div>
                        <div class="client-input-group"><label>Last Name</label><input type="text" id="occ_${i}_lname" placeholder="Last Name"></div>
                    </div>
                    <div class="client-form-row">
                        <div class="client-input-group"><label>Contact Number</label><input type="text" id="occ_${i}_contact" placeholder="Contact Number"></div>
                        <div class="client-input-group"><label>Email Address</label><input type="email" id="occ_${i}_email" placeholder="Email Address"></div>
                    </div>
                    <div class="client-form-row">
                        <div class="client-input-group"><label>Country</label><input type="text" id="occ_${i}_country" placeholder="Country"></div>
                        <div class="client-input-group"><label>Price Per Night (Optional)</label><input type="number" step="0.01" min="0" class="client-extra-occ-price" id="occ_${i}_price" placeholder="0.00" oninput="calculate()"></div>
                    </div>
                    <div class="client-form-row">
                        <div class="client-input-group"><label>ID Card Type</label><input type="text" id="occ_${i}_idtype" placeholder="e.g. Passport, Citizenship"></div>
                        <div class="client-input-group"><label>ID Card Number</label><input type="text" id="occ_${i}_idnum" placeholder="ID Number"></div>
                    </div>
                    <div class="client-form-row">
                        <div class="client-input-group full"><label>Address</label><textarea id="occ_${i}_address" placeholder="Address"></textarea></div>
                    </div>
                `;
                container.appendChild(wrapper);
            }
        }

        function calculate() {
            const checkinValue = document.getElementById('checkin').value;
            const checkoutValue = document.getElementById('checkout').value;
            const price = parseFloat(document.getElementById('pricePerNight').value) || 0;
            const currency = document.getElementById('currency').value;
            if (!checkinValue || !checkoutValue || !price) {
                document.getElementById('days').textContent = '0';
                document.getElementById('total').textContent = '0';
                return;
            }
            const checkin = new Date(checkinValue);
            const checkout = new Date(checkoutValue);
            if (checkout > checkin) {
                const diff = (checkout - checkin) / (86400000);
                let extraPrice = 0;
                document.querySelectorAll('.client-extra-occ-price').forEach(input => {
                    extraPrice += parseFloat(input.value) || 0;
                });
                const effectivePrice = price + extraPrice;
                document.getElementById('days').textContent = diff;
                document.getElementById('total').textContent = (diff * effectivePrice).toFixed(2) + ' ' + currency;
            }
        }

        async function saveBooking(event) {
            event.preventDefault();

            const body = new URLSearchParams({
                action: 'save_reservation',
                room_id: document.getElementById('roomNo').value,
                checkin_date: document.getElementById('checkin').value,
                checkout_date: document.getElementById('checkout').value,
                occupancy: document.getElementById('occupancy').value,
                currency: document.getElementById('currency').value,
                price_per_night: document.getElementById('pricePerNight').value,
                first_name: document.getElementById('fname').value.trim(),
                middle_name: document.getElementById('mname').value.trim(),
                last_name: document.getElementById('lname').value.trim(),
                country: document.getElementById('country').value.trim(),
                contact_number: document.getElementById('contactNumber').value.trim(),
                email: document.getElementById('email').value.trim(),
                address: document.getElementById('address').value.trim(),
                id_type: document.getElementById('idType').value,
                id_number: document.getElementById('idNumber').value.trim(),
                booked_via: document.getElementById('bookedVia').value,
                room_plan: document.getElementById('roomPlan').value,
                guest_request: document.getElementById('guestRequest').value.trim(),
                payment_mode: document.getElementById('paymentMode').value
            });

            const occCount = parseInt(document.getElementById('occupancy').value || '1', 10);
            for (let i = 2; i <= occCount; i++) {
                if (document.getElementById(`occ_${i}_fname`)) {
                    body.append(`occupants[${i}][occupant_order]`, i);
                    body.append(`occupants[${i}][first_name]`, document.getElementById(`occ_${i}_fname`).value.trim());
                    body.append(`occupants[${i}][middle_name]`, document.getElementById(`occ_${i}_mname`).value.trim());
                    body.append(`occupants[${i}][last_name]`, document.getElementById(`occ_${i}_lname`).value.trim());
                    body.append(`occupants[${i}][contact_number]`, document.getElementById(`occ_${i}_contact`).value.trim());
                    body.append(`occupants[${i}][email]`, document.getElementById(`occ_${i}_email`).value.trim());
                    body.append(`occupants[${i}][country]`, document.getElementById(`occ_${i}_country`).value.trim());
                    body.append(`occupants[${i}][price_per_night]`, document.getElementById(`occ_${i}_price`).value);
                    body.append(`occupants[${i}][id_type]`, document.getElementById(`occ_${i}_idtype`).value.trim());
                    body.append(`occupants[${i}][id_number]`, document.getElementById(`occ_${i}_idnum`).value.trim());
                    body.append(`occupants[${i}][address]`, document.getElementById(`occ_${i}_address`).value.trim());
                }
            }

            const response = await fetch('client_reservation.php', { method: 'POST', body });
            const result = await response.json();

            if (!result.success) {
                alert(result.message || 'Booking could not be saved.');
                return;
            }

            document.getElementById('bookingDetails').innerHTML = `
                <table class="client-summary-table">
                    <tr><td>Reservation Number</td><td>${result.reservation_number}</td></tr>
                    <tr><td>Total Nights</td><td>${result.total_nights}</td></tr>
                    <tr><td>Total Price</td><td>${result.total_price}</td></tr>
                    <tr><td>Payment Status</td><td>${result.payment_status}</td></tr>
                </table>
            `;
            document.getElementById('bookingPopup').classList.add('show');
            event.target.reset();
            loadRooms();
            calculate();
        }

        function closePopup() {
            document.getElementById('bookingPopup').classList.remove('show');
        }

        function selectRoomTypeFromUrl() {
            const typeName = new URLSearchParams(window.location.search).get('roomType');
            if (!typeName) return;
            Array.from(document.getElementById('roomType').options).forEach(option => {
                if (option.dataset.name === typeName) {
                    document.getElementById('roomType').value = option.value;
                }
            });
            loadRooms();
        }

        const today = new Date().toISOString().split('T')[0];
        document.getElementById('checkin').min = today;
        document.getElementById('checkout').min = today;
        document.getElementById('roomType').addEventListener('change', () => { loadRooms(); calculate(); });
        document.getElementById('checkin').addEventListener('change', calculate);
        document.getElementById('checkout').addEventListener('change', calculate);
        document.getElementById('currency').addEventListener('change', calculate);
        document.getElementById('pricePerNight').addEventListener('input', calculate);
        document.getElementById('occupancy').addEventListener('change', () => { renderClientExtraOccupants(); calculate(); });
        document.getElementById('clientReservationForm').addEventListener('submit', saveBooking);
        selectRoomTypeFromUrl();
    </script>
</body>

</html>