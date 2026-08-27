<?php
session_start();
require_once '../auth_repository.php';
require_once '../room_repository.php';
require_once '../reservation_repository.php';

requireAdminLogin();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update' || $action === 'update_guest') {
        $reservationId = (int) ($_POST['reservation_id'] ?? 0);
        $reservation = getReservationById($reservationId);

        if ($reservation) {
            $checkIn = trim($_POST['check_in_date'] ?? '');
            $checkOut = trim($_POST['check_out_date'] ?? '');

            if ($checkIn === '' || $checkOut === '') {
                $message = 'Check-in and check-out dates are required.';
                $messageType = 'error';
            } elseif (strtotime($checkOut) <= strtotime($checkIn)) {
                $message = 'Check-out date must be after check-in date.';
                $messageType = 'error';
            } else {
                $extraCharges = [];
                if (isset($_POST['extra_service_name']) && is_array($_POST['extra_service_name'])) {
                    foreach ($_POST['extra_service_name'] as $idx => $sName) {
                        $sName = trim($sName);
                        $sPrice = (float) ($_POST['extra_service_price'][$idx] ?? 0);
                        if ($sName !== '') {
                            $extraCharges[] = [
                                'service_name' => $sName,
                                'price' => $sPrice
                            ];
                        }
                    }
                }

                $result = updateReservation($reservationId, [
                    'room_id' => (int) ($_POST['room_id'] ?? 0),
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                    'occupancy' => (int) ($_POST['occupancy'] ?? 1),
                    'currency' => $_POST['currency'] ?? 'NPR',
                    'price_per_night' => (float) ($_POST['price_per_night'] ?? 0),
                    'payment_status' => $_POST['payment_status'] ?? 'UNPAID',
                    'booked_via' => trim($_POST['booked_via'] ?? 'Walk-in'),
                    'room_plan' => trim($_POST['room_plan'] ?? 'EP'),
                    'guest_request' => trim($_POST['guest_request'] ?? ''),
                    'payment_mode' => trim($_POST['payment_mode'] ?? 'Cash'),
                    'extra_charges' => $extraCharges,
                    'occupants' => $_POST['occupants'] ?? [],
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

                if (!$result['success']) {
                    $message = $result['message'];
                    $messageType = 'error';
                } else {
                    header('Location: current_guests.php?updated=' . ($result['success'] ? '1' : '0'));
                    exit;
                }
            }
        }
    }

    if ($action === 'delete' || $action === 'delete_guest') {
        if (!isAdmin()) {
            $message = 'Unauthorized action: Staff members cannot delete records.';
            $messageType = 'error';
        } else {
            $deleted = deleteReservation((int) ($_POST['reservation_id'] ?? 0));
            header('Location: current_guests.php?deleted=' . ($deleted ? '1' : '0'));
            exit;
        }
    }

    if ($action === 'update_status') {
        $reservationId = (int) ($_POST['reservation_id'] ?? 0);
        $selectedStatus = trim($_POST['status'] ?? '');

        if ($selectedStatus === 'Checked out') {
            $checkInStatus = 'CHECKED IN';
            $checkOutStatus = 'CHECKED OUT';
        } elseif ($selectedStatus === 'Check in') {
            $checkInStatus = 'CHECKED IN';
            $checkOutStatus = 'NOT CHECKED OUT';
        } else {
            $checkInStatus = 'NOT CHECKED IN';
            $checkOutStatus = 'NOT CHECKED OUT';
        }

        $checkInOk = updateReservationCheckInStatus($reservationId, $checkInStatus);
        $checkOutOk = updateReservationCheckOutStatus($reservationId, $checkOutStatus);
        $ok = $checkInOk && $checkOutOk;
        header('Location: current_guests.php?statusupdated=' . ($ok ? '1' : '0'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($message)) {
    if (isset($_GET['updated'])) {
        $message = $_GET['updated'] === '1' ? 'Guest record updated successfully.' : 'Guest record could not be updated.';
        $messageType = $_GET['updated'] === '1' ? 'success' : 'error';
    }

    if (isset($_GET['deleted'])) {
        $message = $_GET['deleted'] === '1' ? 'Guest record deleted successfully.' : 'Guest record could not be deleted.';
        $messageType = $_GET['deleted'] === '1' ? 'success' : 'error';
    }

    if (isset($_GET['statusupdated'])) {
        $message = $_GET['statusupdated'] === '1' ? 'Guest status updated successfully.' : 'Could not update guest status.';
        $messageType = $_GET['statusupdated'] === '1' ? 'success' : 'error';
    }
}

// Fetch guests currently staying today (check_in_date <= CURDATE() and check_out_date >= CURDATE())
$guests = getCurrentGuestsFromReservations();
$allRooms = getAllRoomsWithTypes();
$roomTypes = getRoomTypes();
$idTypeOptions = ["Passport", "Citizenship", "National ID", "Driver's License", "Voter ID"];
$bookedViaOptions = ["Booking.com", "Agoda.com", "Ctrip", "Expedia", "Airbnb", "Walk-in", "Whatsapp", "Website", "Travel Agency", "Referral"];
$roomPlanOptions = ["EP", "BB", "MAP", "AP"];
$paymentModeOptions = ["Cash", "Card", "QR"];

$totalUSD = 0.0;
$totalNPR = 0.0;
foreach ($guests as $g) {
    $extra = 0.0;
    if (!empty($g['extra_charges'])) {
        foreach ($g['extra_charges'] as $ec) {
            $extra += (float) ($ec['price'] ?? 0);
        }
    }
    $total = (float) $g['total_price'] + $extra;
    if (($g['currency'] ?? 'NPR') === 'USD') {
        $totalUSD += $total;
    } else {
        $totalNPR += $total;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Current Guests</title>
    <script src="https://kit.fontawesome.com/8aab9e126a.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./admin_style.css?v=20260714">
    <style>
        .guest-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
            padding: 16px;
            margin-bottom: 18px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
        }

        .guest-summary .summary-item label {
            display: block;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .guest-summary .summary-item span {
            font-size: 19px;
            font-weight: 800;
            color: #1e3a8a;
        }

        tfoot td {
            font-weight: 800;
            background: #f8fafc;
            border-top: 2px solid #cbd5e1;
        }

        .no-results-row td {
            text-align: center;
            color: #64748b;
            padding: 22px 10px;
        }
    </style>
</head>

<body class="page-guest-stays">
    <div class="topbar">HOTEL MATE</div>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="main">
            <div class="page-header">
                <h1 class="page-title">Current Guests</h1>
            </div>

            <?php if ($message): ?>
                <div class="admin-alert <?= h($messageType); ?>"><?= h($message); ?></div>
            <?php endif; ?>

            <div class="guest-summary">
                <div class="summary-item">
                    <label>Current Guests (Today)</label>
                    <span id="summaryCount"><?= count($guests); ?></span>
                </div>
                <div class="summary-item">
                    <label>Total Payment (USD)</label>
                    <span id="summaryUSD"><?= number_format($totalUSD, 2); ?></span>
                </div>
                <div class="summary-item">
                    <label>Total Payment (NPR)</label>
                    <span id="summaryNPR"><?= number_format($totalNPR, 2); ?></span>
                </div>
            </div>

            <div class="table-card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Reservation Number</th>
                                <th>Guest Name</th>
                                <th>Room Number</th>
                                <th>Check-In Date</th>
                                <th>Check-Out Date</th>
                                <th>Payment (USD)</th>
                                <th>Payment (NPR)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="guestsTableBody">
                            <?php if (count($guests) === 0): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding:22px; color:#64748b; font-weight:600;">No guests currently staying today.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($guests as $guest): ?>
                                    <?php
                                    $roomTotalPrice = (float) $guest['total_price'];
                                    ?>
                                    <tr data-reservation-id="<?= h($guest['reservation_id']); ?>"
                                        data-first-name="<?= h($guest['first_name']); ?>"
                                        data-middle-name="<?= h($guest['middle_name']); ?>"
                                        data-last-name="<?= h($guest['last_name']); ?>"
                                        data-checkin="<?= h($guest['check_in_date']); ?>"
                                        data-checkout="<?= h($guest['check_out_date']); ?>"
                                        data-currency="<?= h($guest['currency']); ?>"
                                        data-total-price="<?= h($roomTotalPrice); ?>">
                                        <td><?= h($guest['reservation_number']); ?></td>
                                        <td><?= h(trim($guest['first_name'] . ' ' . ($guest['middle_name'] ?? '') . ' ' . $guest['last_name'])); ?>
                                        </td>
                                        <td><?= h($guest['room_number']); ?></td>
                                        <td><?= h(date('M j, Y', strtotime($guest['check_in_date']))); ?></td>
                                        <td><?= h(date('M j, Y', strtotime($guest['check_out_date']))); ?></td>
                                        <td><?= $guest['currency'] === 'USD' ? h(number_format((float) $roomTotalPrice, 2)) : '—'; ?>
                                        </td>
                                        <td><?= $guest['currency'] === 'NPR' ? h(number_format((float) $roomTotalPrice, 2)) : '—'; ?>
                                        </td>
                                        <td>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="reservation_id"
                                                    value="<?= h($guest['reservation_id']); ?>">
                                                <select name="status" onchange="this.form.submit()"
                                                    style="padding:6px 8px; border:1px solid #cbd5e1; border-radius:6px; min-width:120px;">
                                                    <option value="" <?= (($guest['check_in_status'] ?? 'NOT CHECKED IN') === 'NOT CHECKED IN' && (($guest['check_out_status'] ?? 'NOT CHECKED OUT') === 'NOT CHECKED OUT') ? 'selected' : '') ?>></option>
                                                    <option value="Check in" <?= (($guest['check_in_status'] ?? 'NOT CHECKED IN') === 'CHECKED IN' && (($guest['check_out_status'] ?? 'NOT CHECKED OUT') !== 'CHECKED OUT') ? 'selected' : '') ?>>Check in</option>
                                                    <option value="Checked out" <?= (($guest['check_out_status'] ?? 'NOT CHECKED OUT') === 'CHECKED OUT' ? 'selected' : '') ?>>Checked out</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="action-view" type="button" title="View" onclick="viewGuest(this)"><i
                                                    class="fas fa-eye"></i></button>
                                            <button class="action-edit" type="button" title="Edit" onclick="editGuest(this)"><i
                                                    class="fas fa-pencil-alt"></i></button>
                                            <?php if (isAdmin()): ?>
                                                <form method="post" style="display:inline;"
                                                    onsubmit="return confirm('Delete this guest record and reservation?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="reservation_id"
                                                        value="<?= h($guest['reservation_id']); ?>">
                                                    <button class="action-delete" type="submit" title="Delete"><i
                                                            class="fas fa-trash-alt"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5">Totals (Current Guests)</td>
                                <td id="footerUSD"><?= number_format($totalUSD, 2); ?></td>
                                <td id="footerNPR"><?= number_format($totalNPR, 2); ?></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="viewModal">
        <div class="modal" style="width:min(680px,100%);">
            <div class="modal-header">
                <h4>Guest Details</h4><button class="modal-close" onclick="closeModal('viewModal')">×</button>
            </div>
            <div class="modal-body" id="viewContent"></div>
        </div>
    </div>

    <div class="modal-overlay" id="editModal">
        <div class="modal" style="width:min(760px,100%);">
            <div class="modal-header">
                <h4>Edit Guest</h4><button class="modal-close" onclick="closeModal('editModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="post" id="editGuestForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="reservation_id" id="editReservationId">
                    <div class="row">
                        <div class="input-group">
                            <label>Room Type</label>
                            <select id="editRoomType" onchange="loadEditRooms()">
                                <?php foreach ($roomTypes as $type): ?>
                                    <option value="<?= h($type['id']); ?>"><?= h($type['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Room Number</label>
                            <select name="room_id" id="editRoomId" required></select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>Check In</label><input type="date" name="check_in_date"
                                id="editCheckIn" required></div>
                        <div class="input-group"><label>Check Out</label><input type="date" name="check_out_date"
                                id="editCheckOut" required></div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>Currency</label><select name="currency" id="editCurrency">
                                <option value="NPR">NPR</option>
                                <option value="USD">USD</option>
                            </select></div>
                        <div class="input-group"><label>Price Per Night</label><input type="number" step="0.01"
                                name="price_per_night" id="editPrice" required></div>
                        <div class="input-group"><label>Occupancy</label><input type="number" min="1" max="4" name="occupancy"
                                id="editOccupancy" onchange="renderExtraOccupantsForm('editExtraOccupantsContainer', parseInt(this.value, 10))" required></div>
                        <div class="input-group"><label>Payment Status</label><select name="payment_status"
                                id="editPaymentStatus">
                                <option value="UNPAID">UNPAID</option>
                                <option value="PAID">PAID</option>
                                <option value="PARTIAL">PARTIAL</option>
                            </select></div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Booked via <span style="color:#ef4444;">*</span></label>
                            <select name="booked_via" id="editBookedVia" required>
                                <option value="">Select Channel</option>
                                <?php foreach ($bookedViaOptions as $opt): ?>
                                    <option value="<?= h($opt); ?>"><?= h($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Room Plan <span style="color:#ef4444;">*</span></label>
                            <select name="room_plan" id="editRoomPlan" required>
                                <option value="">Select Plan</option>
                                <?php foreach ($roomPlanOptions as $opt): ?>
                                    <option value="<?= h($opt); ?>"><?= h($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Mode of Payment</label>
                            <select name="payment_mode" id="editPaymentMode">
                                <option value="">Select Mode</option>
                                <?php foreach ($paymentModeOptions as $opt): ?>
                                    <option value="<?= h($opt); ?>"><?= h($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Guests Request</label>
                            <input type="text" name="guest_request" id="editGuestRequest" placeholder="Special requests or notes">
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>First Name</label><input type="text" name="first_name"
                                id="editFirstName" required></div>
                        <div class="input-group"><label>Middle Name</label><input type="text" name="middle_name"
                                id="editMiddleName"></div>
                        <div class="input-group"><label>Last Name</label><input type="text" name="last_name"
                                id="editLastName" required></div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>Country</label><input type="text" name="country"
                                id="editCountry" required></div>
                        <div class="input-group"><label>Contact</label><input type="text" name="contact_number"
                                id="editContact"></div>
                        <div class="input-group"><label>Email</label><input type="email" name="email" id="editEmail">
                        </div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>Address</label><textarea name="address"
                                id="editAddress"></textarea></div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>ID Type</label><input type="text" name="id_type"
                                id="editIdType"></div>
                        <div class="input-group"><label>ID Number</label><input type="text" name="id_number"
                                id="editIdNumber"></div>
                    </div>
                    <div id="editExtraOccupantsContainer"></div>
                    <div class="extra-services-section" style="margin-top:16px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                            <h5 style="margin:0; font-size:13px; text-transform:uppercase; letter-spacing:0.04em; color:#475569; font-weight:800; display:flex; align-items:center; gap:6px;">
                                <i class="fas fa-concierge-bell"></i> Extra Services
                            </h5>
                            <span style="font-size:12px; font-weight:700; color:#64748b;" id="extraServicesCounter">(0/5)</span>
                        </div>
                        <div id="extraServicesContainer"></div>
                        <div style="margin-top:10px;">
                            <button type="button" id="btnAddExtraService" onclick="addExtraServiceRow()" style="background:none; border:none; color:#2563eb; font-weight:700; font-size:13.5px; cursor:pointer; padding:4px 0; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fas fa-plus-circle"></i> Add extra service
                            </button>
                            <span id="maxServicesMsg" style="display:none; color:#94a3b8; font-size:12.5px; font-weight:600;">Maximum of 5 extra services reached.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                        <button type="submit" class="btn-add-modal">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/high_priority_alert.php'; ?>
    <script>
        const guests = <?= json_encode(array_map(function ($g) {
            $g['guest_name'] = trim($g['first_name'] . ' ' . ($g['middle_name'] ?? '') . ' ' . $g['last_name']);
            return $g;
        }, $guests)); ?>;

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function findGuest(reservationId) {
            return guests.find(item => parseInt(item.reservation_id, 10) === parseInt(reservationId, 10));
        }

        function escapeHtml(value) {
            if (value === null || value === undefined) return '';
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        }

        function formatDateTime(dateStr) {
            if (!dateStr) return 'N/A';
            const d = new Date(String(dateStr).replace(' ', 'T'));
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        function statusBadgeClass(status) {
            return String(status || '').toLowerCase().replace(/\s+/g, '-');
        }

        function field(label, value, isMuted) {
            const display = (value === null || value === undefined || value === '' || value === 'N/A') ? 'N/A' : escapeHtml(value);
            const mutedClass = (isMuted || display === 'N/A') ? ' muted' : '';
            return `<div class="view-field"><label>${label}</label><span class="${mutedClass.trim()}">${display}</span></div>`;
        }

        function addExtraServiceRow(name = '', price = '') {
            const container = document.getElementById('extraServicesContainer');
            const rows = container.querySelectorAll('.extra-service-row');
            if (rows.length >= 5) {
                updateExtraServicesState();
                return;
            }

            const row = document.createElement('div');
            row.className = 'row extra-service-row';
            row.style.alignItems = 'end';
            row.style.marginBottom = '12px';

            row.innerHTML = `
                <div class="input-group">
                    <label>Name of the Service</label>
                    <input type="text" name="extra_service_name[]" placeholder="e.g. Laundry, Airport Shuttle" value="${escapeHtml(name)}">
                </div>
                <div class="input-group">
                    <label>Price</label>
                    <input type="number" step="0.01" min="0" name="extra_service_price[]" placeholder="0.00" value="${price !== '' && price !== null && price !== undefined ? escapeHtml(price) : ''}">
                </div>
                <div style="flex:0 0 auto;">
                    <button type="button" onclick="removeExtraServiceRow(this)" style="background:#fee2e2; color:#dc2626; border:none; width:38px; height:38px; border-radius:8px; cursor:pointer;" title="Remove service">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;

            container.appendChild(row);
            updateExtraServicesState();
        }

        function removeExtraServiceRow(button) {
            const row = button.closest('.extra-service-row');
            if (row) {
                row.remove();
                updateExtraServicesState();
            }
        }

        function updateExtraServicesState() {
            const container = document.getElementById('extraServicesContainer');
            const count = container.querySelectorAll('.extra-service-row').length;
            const btn = document.getElementById('btnAddExtraService');
            const msg = document.getElementById('maxServicesMsg');
            const counter = document.getElementById('extraServicesCounter');

            if (counter) counter.textContent = `(${count}/5)`;

            if (count >= 5) {
                if (btn) btn.style.display = 'none';
                if (msg) msg.style.display = 'inline';
            } else {
                if (btn) btn.style.display = 'inline-flex';
                if (msg) msg.style.display = 'none';
            }
        }

        function renderExtraOccupantsForm(containerId, occupancyCount, occupantsData = []) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = '';

            const needed = Math.max(0, occupancyCount - 1);
            if (needed <= 0) return;

            for (let i = 2; i <= occupancyCount; i++) {
                const occData = (occupantsData && occupantsData.find(o => parseInt(o.occupant_order, 10) === i)) || (occupantsData && occupantsData[i - 2]) || {};
                const prefix = `occupants[${i}]`;

                const wrapper = document.createElement('div');
                wrapper.className = 'extra-occupant-card';
                wrapper.style.cssText = 'margin-top:16px; padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;';

                wrapper.innerHTML = `
                    <h5 style="margin:0 0 12px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.04em; color:#2563eb; font-weight:800;">
                        <i class="fas fa-user-friends"></i> Details of Occupant ${i} (Optional)
                    </h5>
                    <input type="hidden" name="${prefix}[occupant_order]" value="${i}">
                    <div class="row">
                        <div class="input-group"><label>First Name</label><input type="text" name="${prefix}[first_name]" value="${escapeHtml(occData.first_name || '')}" placeholder="First Name"></div>
                        <div class="input-group"><label>Middle Name</label><input type="text" name="${prefix}[middle_name]" value="${escapeHtml(occData.middle_name || '')}" placeholder="Middle Name"></div>
                        <div class="input-group"><label>Last Name</label><input type="text" name="${prefix}[last_name]" value="${escapeHtml(occData.last_name || '')}" placeholder="Last Name"></div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>Contact Number</label><input type="text" name="${prefix}[contact_number]" value="${escapeHtml(occData.contact_number || '')}" placeholder="Contact Number"></div>
                        <div class="input-group"><label>Email Address</label><input type="email" name="${prefix}[email]" value="${escapeHtml(occData.email || '')}" placeholder="Email Address"></div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>Country</label><input type="text" name="${prefix}[country]" value="${escapeHtml(occData.country || '')}" placeholder="Country"></div>
                        <div class="input-group"><label>Price Per Night (Optional)</label><input type="number" step="0.01" min="0" name="${prefix}[price_per_night]" value="${occData.price_per_night !== undefined && occData.price_per_night !== null ? escapeHtml(occData.price_per_night) : ''}" placeholder="0.00"></div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>ID Card Type</label><input type="text" name="${prefix}[id_type]" value="${escapeHtml(occData.id_type || '')}" placeholder="e.g. Passport, Citizenship"></div>
                        <div class="input-group"><label>ID Card Number</label><input type="text" name="${prefix}[id_number]" value="${escapeHtml(occData.id_number || '')}" placeholder="ID Number"></div>
                    </div>
                    <div class="row">
                        <div class="input-group"><label>Address</label><textarea name="${prefix}[address]" placeholder="Address">${escapeHtml(occData.address || '')}</textarea></div>
                    </div>
                `;

                container.appendChild(wrapper);
            }
        }

        function viewGuest(buttonOrId) {
            const reservationId = typeof buttonOrId === 'number' || typeof buttonOrId === 'string'
                ? buttonOrId
                : buttonOrId.closest('tr').dataset.reservationId;
            const guest = findGuest(reservationId);
            if (!guest) return;

            const currency = guest.currency || 'NPR';
            const roomPrice = parseFloat(guest.total_price || 0);
            const extraCharges = guest.extra_charges || [];
            const extraTotal = extraCharges.reduce((sum, c) => sum + parseFloat(c.price || 0), 0);
            const grandTotal = roomPrice + extraTotal;

            const extraChargesRows = extraCharges.length
                ? extraCharges.map(c => `
                    <tr>
                        <td style="padding:7px 8px; font-weight:600;">${escapeHtml(c.service_name)}</td>
                        <td style="padding:7px 8px; text-align:right; font-weight:700;">${escapeHtml(currency)} ${parseFloat(c.price).toFixed(2)}</td>
                    </tr>
                `).join('')
                : `<tr><td colspan="2" class="muted" style="padding:8px; color:#94a3b8; font-style:italic;">No extra services added</td></tr>`;

            const occupants = guest.occupants || [];
            let extraOccupantsHtml = '';
            if (occupants.length > 0) {
                extraOccupantsHtml = occupants.map(occ => {
                    const fullName = [occ.first_name, occ.middle_name, occ.last_name].filter(Boolean).join(' ');
                    const pricePerNight = parseFloat(occ.price_per_night || 0);
                    return `
                        <div class="view-section" style="margin-top:14px; border-top:1px dashed #cbd5e1; padding-top:12px;">
                            <h5 style="color:#2563eb;"><i class="fas fa-user-friends"></i> Occupant ${occ.occupant_order} Details</h5>
                            <div class="view-grid">
                                ${field('Full Name', fullName)}
                                ${field('Country', occ.country)}
                                ${field('Contact Number', occ.contact_number)}
                                ${field('Email Address', occ.email)}
                                ${field('Price Per Night', pricePerNight > 0 ? (guest.currency || 'NPR') + ' ' + pricePerNight.toFixed(2) : 'N/A')}
                            </div>
                            <div class="view-grid single" style="margin-top:8px;">
                                ${field('Address', occ.address)}
                            </div>
                            <div class="view-grid" style="margin-top:8px;">
                                ${field('ID Card Type', occ.id_type)}
                                ${field('ID Card Number', occ.id_number)}
                            </div>
                        </div>
                    `;
                }).join('');
            }

            document.getElementById('viewContent').innerHTML = `
                <div class="view-detail">
                    <div class="view-detail-header">
                        <div>
                            <div class="res-number">${escapeHtml(guest.reservation_number || ('Reservation #' + guest.reservation_id))}</div>
                            <div class="res-created">Booked via ${escapeHtml((guest.source || 'admin').toUpperCase())} ${guest.created_at ? '&middot; ' + formatDateTime(guest.created_at) : ''}</div>
                        </div>
                        <div class="view-status-pills">
                            <span class="badge ${statusBadgeClass(guest.check_in_status)}">${escapeHtml(guest.check_in_status || 'N/A')}</span>
                            <span class="badge ${statusBadgeClass(guest.check_out_status)}">${escapeHtml(guest.check_out_status || 'N/A')}</span>
                            <span class="badge ${statusBadgeClass(guest.payment_status)}">${escapeHtml(guest.payment_status || 'N/A')}</span>
                        </div>
                    </div>

                    <div class="view-section">
                        <h5><i class="fas fa-bed"></i> Room &amp; Stay</h5>
                        <div class="view-grid">
                            ${field('Room Type', guest.room_type_name)}
                            ${field('Room Number', guest.room_number)}
                            ${field('Check-In Date', formatDate(guest.check_in_date))}
                            ${field('Check-Out Date', formatDate(guest.check_out_date))}
                            ${field('Price Per Night', (guest.currency || 'NPR') + ' ' + parseFloat(guest.price_per_night || 0).toFixed(2))}
                            ${field('Occupancy (Guests)', guest.occupancy)}
                        </div>
                    </div>

                    <div class="view-section">
                        <h5><i class="fas fa-info-circle"></i> Booking &amp; Plan Details</h5>
                        <div class="view-grid">
                            ${field('Booked Via', guest.booked_via)}
                            ${field('Room Plan', guest.room_plan)}
                            ${field('Mode of Payment', guest.payment_mode)}
                        </div>
                        <div class="view-grid single" style="margin-top:10px;">
                            ${field('Guests Request', guest.guest_request)}
                        </div>
                    </div>

                    <div class="view-section">
                        <h5><i class="fas fa-user"></i> Guest Information (Main Occupant)</h5>
                        <div class="view-grid">
                            ${field('Full Name', guest.guest_name)}
                            ${field('Country', guest.country)}
                            ${field('Contact Number', guest.contact_number)}
                            ${field('Email Address', guest.email)}
                        </div>
                        <div class="view-grid single" style="margin-top:10px;">
                            ${field('Residential Address', guest.address)}
                        </div>
                        <div class="view-grid" style="margin-top:10px;">
                            ${field('ID Card Type', guest.id_type)}
                            ${field('ID Card Number', guest.id_number)}
                        </div>
                    </div>

                    ${extraOccupantsHtml}

                    <div class="view-section">
                        <h5><i class="fas fa-concierge-bell"></i> Extra Services</h5>
                        <table class="view-charges-table" style="width:100%; margin-top:4px;">
                            <thead>
                                <tr style="border-bottom:1px solid #cbd5e1; font-size:11.5px; color:#64748b; text-transform:uppercase;">
                                    <th style="text-align:left; padding:4px 8px;">Service Name</th>
                                    <th style="text-align:right; padding:4px 8px;">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${extraChargesRows}
                            </tbody>
                        </table>
                    </div>

                    <div class="view-total-box">
                        <span>Total Payment (${escapeHtml(currency)})</span>
                        <span class="grand-total">${escapeHtml(currency)} ${roomPrice.toFixed(2)}</span>
                    </div>
                </div>
            `;
            document.getElementById('viewModal').style.display = 'flex';
        }

        async function loadEditRooms(selectedRoomId = null) {
            const roomTypeId = document.getElementById('editRoomType').value;
            const roomSelect = document.getElementById('editRoomId');
            roomSelect.innerHTML = '';

            const response = await fetch(`../api.php?action=rooms_by_type&room_type_id=${roomTypeId}`);
            const data = await response.json();

            if (data.success) {
                data.data.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = room.room_number;
                    roomSelect.appendChild(option);
                });
            }

            if (selectedRoomId) {
                roomSelect.value = selectedRoomId;
            }
        }

        function editGuest(buttonOrId) {
            const reservationId = typeof buttonOrId === 'number' || typeof buttonOrId === 'string'
                ? buttonOrId
                : buttonOrId.closest('tr').dataset.reservationId;
            const guest = findGuest(reservationId);
            if (!guest) return;

            document.getElementById('editReservationId').value = guest.reservation_id;
            document.getElementById('editCheckIn').value = guest.check_in_date || '';
            document.getElementById('editCheckOut').value = guest.check_out_date || '';
            document.getElementById('editCurrency').value = guest.currency || 'NPR';
            document.getElementById('editPrice').value = guest.price_per_night || '0';
            document.getElementById('editOccupancy').value = guest.occupancy || '1';
            document.getElementById('editPaymentStatus').value = guest.payment_status || 'UNPAID';
            document.getElementById('editBookedVia').value = guest.booked_via || '';
            document.getElementById('editRoomPlan').value = guest.room_plan || '';
            document.getElementById('editGuestRequest').value = guest.guest_request || '';
            document.getElementById('editPaymentMode').value = guest.payment_mode || '';
            document.getElementById('editFirstName').value = guest.first_name || '';
            document.getElementById('editMiddleName').value = guest.middle_name || '';
            document.getElementById('editLastName').value = guest.last_name || '';
            document.getElementById('editCountry').value = guest.country || '';
            document.getElementById('editContact').value = guest.contact_number || '';
            document.getElementById('editEmail').value = guest.email || '';
            document.getElementById('editAddress').value = guest.address || '';
            document.getElementById('editIdType').value = guest.id_type || '';
            document.getElementById('editIdNumber').value = guest.id_number || '';

            renderExtraOccupantsForm('editExtraOccupantsContainer', parseInt(guest.occupancy || '1', 10), guest.occupants || []);

            const roomTypeSelect = document.getElementById('editRoomType');
            Array.from(roomTypeSelect.options).forEach(option => {
                if (option.textContent === guest.room_type_name) {
                    roomTypeSelect.value = option.value;
                }
            });

            loadEditRooms(guest.room_id);

            const container = document.getElementById('extraServicesContainer');
            container.innerHTML = '';
            const extraCharges = guest.extra_charges || [];
            if (extraCharges.length > 0) {
                extraCharges.forEach(charge => addExtraServiceRow(charge.service_name, charge.price));
            }
            updateExtraServicesState();

            document.getElementById('editModal').style.display = 'flex';
        }
    </script>
</body>

</html>
