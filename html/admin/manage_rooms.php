<?php
session_start();
require_once '../auth_repository.php';
require_once '../room_repository.php';
require_once '../reservation_repository.php';

requireAdminLogin();

$message = '';
$messageType = 'success';
$roomTypes = getRoomTypes();
$extraServices = ['Laundry', 'Tour', 'Airport Shuttle', 'Sound Healing', 'Massage'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'check_in') {
        $ok = updateReservationCheckInStatus((int) $_POST['reservation_id'], 'CHECKED IN');
        header('Location: manage_rooms.php?updated=' . ($ok ? '1' : '0'));
        exit;
    }

    if ($action === 'delete') {
        if (!isAdmin()) {
            $message = 'Unauthorized action: Staff members cannot delete reservations.';
            $messageType = 'error';
        } else {
            $ok = deleteReservation((int) $_POST['reservation_id']);
            header('Location: manage_rooms.php?deleted=' . ($ok ? '1' : '0'));
            exit;
        }
    }

    if ($action === 'update') {
        $reservationId = (int) ($_POST['reservation_id'] ?? 0);
        $extraCharges = [];

        if (!empty($_POST['extra_service']) && is_array($_POST['extra_service'])) {
            foreach ($_POST['extra_service'] as $index => $service) {
                $extraCharges[] = [
                    'service_name' => $service,
                    'price' => $_POST['extra_price'][$index] ?? 0
                ];
            }
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

        if ($guestData['id_type'] === '' || $guestData['id_number'] === '') {
            $message = 'ID Card Type and ID Card Number are required.';
            $messageType = 'error';
        } else {
            $result = updateReservation($reservationId, [
                'room_id' => (int) ($_POST['room_id'] ?? 0),
                'check_in_date' => trim($_POST['check_in_date'] ?? ''),
                'check_out_date' => trim($_POST['check_out_date'] ?? ''),
                'occupancy' => (int) ($_POST['occupancy'] ?? 1),
                'currency' => $_POST['currency'] ?? 'NPR',
                'price_per_night' => (float) ($_POST['price_per_night'] ?? 0),
                'payment_status' => $_POST['payment_status'] ?? 'UNPAID',
                'extra_charges' => $extraCharges,
                'guest' => $guestData
            ]);

            if (!$result['success']) {
                $message = $result['message'];
                $messageType = 'error';
            } else {
                header('Location: manage_rooms.php?updated=1');
                exit;
            }
        }
    }
}

if (isset($_GET['updated'])) {
    $message = $_GET['updated'] === '1' ? 'Reservation updated successfully.' : 'Update failed.';
    $messageType = $_GET['updated'] === '1' ? 'success' : 'error';
}

if (isset($_GET['deleted'])) {
    $message = $_GET['deleted'] === '1' ? 'Reservation deleted successfully.' : 'Delete failed.';
    $messageType = $_GET['deleted'] === '1' ? 'success' : 'error';
}

// Manage Rooms only ever shows reservations that have not checked in yet.
// The moment a reservation is checked in, it drops out of this query and
// starts showing up on the All Guests page instead.
$reservations = getPendingCheckInReservations();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Rooms</title>
    <script src="https://kit.fontawesome.com/8aab9e126a.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./admin_style.css?v=20260714">
    <style>
        .filter-card {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 14px;
            padding: 16px;
            margin-bottom: 18px;
        }

        .filter-card .input-group {
            flex: 1;
            min-width: 170px;
            margin-bottom: 0;
        }

        .filter-card .filter-actions {
            display: flex;
            gap: 10px;
            flex: 0 0 auto;
        }

        .filter-card .filter-actions button {
            white-space: nowrap;
        }

        .no-results-row td {
            text-align: center;
            color: #64748b;
            padding: 22px 10px;
        }
    </style>
</head>

<body class="page-manage-rooms">
    <div class="topbar">HOTEL MANAGEMENT SYSTEM</div>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="main">
            <div class="page-header">
                <h3>Manage Rooms (Awaiting Check-In)</h3>
            </div>

            <?php if ($message): ?>
                <div class="admin-alert <?= h($messageType); ?>"><?= h($message); ?></div>
            <?php endif; ?>

            <div class="table-card">
                <div class="filter-card">
                    <div class="input-group">
                        <label>Search by Name</label>
                        <input type="text" id="filterName" placeholder="e.g. Sita Rai">
                    </div>
                    <div class="input-group">
                        <label>Check-In From</label>
                        <input type="date" id="filterFrom">
                    </div>
                    <div class="input-group">
                        <label>Check-In To</label>
                        <input type="date" id="filterTo">
                    </div>
                    <div class="filter-actions">
                        <button class="btn" type="button" onclick="applyFilters()"><i class="fas fa-filter"></i> Apply
                            Filter</button>
                        <button class="btn-cancel" type="button" onclick="resetFilters()">Reset</button>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Reservation #</th>
                                <th>Guest</th>
                                <th>Room No</th>
                                <th>Room Type</th>
                                <th>Check-In Date</th>
                                <th>Check-Out Date</th>
                                <th>Extra Charges</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="roomsTableBody">
                            <?php if (count($reservations) === 0): ?>
                                <tr>
                                    <td colspan="8">No reservations awaiting check-in.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reservations as $reservation): ?>
                                    <?php $extraTotal = getExtraChargesTotal((int) $reservation['id']); ?>
                                    <tr data-reservation-id="<?= h($reservation['id']); ?>"
                                        data-first-name="<?= h($reservation['first_name']); ?>"
                                        data-middle-name="<?= h($reservation['middle_name']); ?>"
                                        data-last-name="<?= h($reservation['last_name']); ?>"
                                        data-checkin="<?= h($reservation['check_in_date']); ?>">
                                        <td><?= h($reservation['reservation_number']); ?></td>
                                        <td><?= h(guestFullName($reservation)); ?></td>
                                        <td><?= h($reservation['room_number']); ?></td>
                                        <td><?= h($reservation['room_type_name']); ?></td>
                                        <td><?= h(date('M j, Y', strtotime($reservation['check_in_date']))); ?></td>
                                        <td><?= h(date('M j, Y', strtotime($reservation['check_out_date']))); ?></td>
                                        <td><?= $extraTotal > 0 ? 'NPR ' . h(number_format($extraTotal, 2)) : '-'; ?></td>
                                        <td class="action-buttons">
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="check_in">
                                                <input type="hidden" name="reservation_id"
                                                    value="<?= h($reservation['id']); ?>">
                                                <button class="btn-add-modal" type="submit">Check In</button>
                                            </form>
                                             <button class="action-view" type="button" title="View"
                                                onclick="viewReservation(<?= (int) $reservation['id']; ?>)"><i
                                                    class="fas fa-eye"></i></button>
                                            <button class="action-edit" type="button" title="Edit"
                                                onclick="editReservation(<?= (int) $reservation['id']; ?>)"><i
                                                    class="fas fa-pencil-alt"></i></button>
                                            <?php if (isAdmin()): ?>
                                                <form method="post" style="display:inline;"
                                                    onsubmit="return confirm('Delete this reservation permanently?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="reservation_id"
                                                        value="<?= h($reservation['id']); ?>">
                                                    <button class="action-delete" type="submit" title="Delete"><i
                                                            class="fas fa-trash-alt"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="viewModal">
        <div class="modal" style="width:min(680px,100%);">
            <div class="modal-header">
                <h4>Reservation Details</h4>
                <button class="modal-close" onclick="closeModal('viewModal')">×</button>
            </div>
            <div class="modal-body" id="viewContent"></div>
        </div>
    </div>

    <div class="modal-overlay" id="editModal">
        <div class="modal" style="width:min(760px,100%);">
            <div class="modal-header">
                <h4>Edit Reservation</h4>
                <button class="modal-close" onclick="closeModal('editModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="post" id="editForm">
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
                        <div class="input-group"><label>Occupancy</label><input type="number" min="1" name="occupancy"
                                id="editOccupancy" required></div>
                        <div class="input-group"><label>Payment Status</label><select name="payment_status"
                                id="editPaymentStatus">
                                <option value="UNPAID">UNPAID</option>
                                <option value="PAID">PAID</option>
                                <option value="PARTIAL">PARTIAL</option>
                            </select></div>
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
                        <div class="input-group"><label>ID Type</label><input type="text" name="id_type" id="editIdType"
                                required></div>
                        <div class="input-group"><label>ID Number</label><input type="text" name="id_number"
                                id="editIdNumber" required></div>
                    </div>

                    <h4 style="margin:18px 0 10px;">Extra Charges</h4>
                    <div id="extraChargesContainer"></div>
                    <button type="button" class="btn-cancel" onclick="addExtraChargeRow()">Add More</button>

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
        const reservations = <?= json_encode(array_map(function ($r) {
            $r['guest_name'] = guestFullName($r);
            $r['extra_charges'] = getExtraCharges((int) $r['id']);
            return $r;
        }, $reservations)); ?>;
        const extraServices = <?= json_encode($extraServices); ?>;

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function findReservation(id) {
            return reservations.find(item => parseInt(item.id, 10) === parseInt(id, 10));
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
            const d = new Date(dateStr.replace(' ', 'T'));
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        function statusBadgeClass(status) {
            return String(status || '').toLowerCase().replace(/\s+/g, '-');
        }

        function field(label, value, isMuted) {
            const display = (value === null || value === undefined || value === '') ? 'N/A' : escapeHtml(value);
            const mutedClass = (isMuted || display === 'N/A') ? ' muted' : '';
            return `<div class="view-field"><label>${label}</label><span class="${mutedClass.trim()}">${display}</span></div>`;
        }

        function viewReservation(id) {
            const reservation = findReservation(id);
            if (!reservation) return;

            const charges = reservation.extra_charges || [];
            const chargesTotal = charges.reduce((sum, c) => sum + parseFloat(c.price || 0), 0);
            const roomTotal = parseFloat(reservation.total_price || 0);
            const grandTotal = roomTotal + chargesTotal;
            const currency = reservation.currency || 'NPR';

            const chargesRows = charges.length
                ? charges.map(c => `
                    <tr>
                        <td>${escapeHtml(c.service_name)}</td>
                        <td>${currency} ${parseFloat(c.price).toFixed(2)}</td>
                    </tr>
                `).join('')
                : `<tr><td colspan="2" class="muted">No extra charges added</td></tr>`;

            document.getElementById('viewContent').innerHTML = `
                <div class="view-detail">
                    <div class="view-detail-header">
                        <div>
                            <div class="res-number">${escapeHtml(reservation.reservation_number || ('Reservation #' + reservation.id))}</div>
                            <div class="res-created">Booked via ${escapeHtml((reservation.source || 'admin').toUpperCase())} ${reservation.created_at ? '&middot; ' + formatDateTime(reservation.created_at) : ''}</div>
                        </div>
                        <div class="view-status-pills">
                            <span class="badge ${statusBadgeClass(reservation.check_in_status)}">${escapeHtml(reservation.check_in_status || 'N/A')}</span>
                            <span class="badge ${statusBadgeClass(reservation.check_out_status)}">${escapeHtml(reservation.check_out_status || 'N/A')}</span>
                            <span class="badge ${statusBadgeClass(reservation.payment_status)}">${escapeHtml(reservation.payment_status || 'N/A')}</span>
                        </div>
                    </div>

                    <div class="view-section">
                        <h5><i class="fas fa-bed"></i> Room &amp; Stay</h5>
                        <div class="view-grid">
                            ${field('Room Type', reservation.room_type_name)}
                            ${field('Room Number', reservation.room_number)}
                            ${field('Check-In Date', formatDate(reservation.check_in_date))}
                            ${field('Check-Out Date', formatDate(reservation.check_out_date))}
                            ${field('Total Nights', reservation.total_nights)}
                            ${field('Occupancy (Guests)', reservation.occupancy)}
                        </div>
                    </div>

                    <div class="view-section">
                        <h5><i class="fas fa-user"></i> Guest Information</h5>
                        <div class="view-grid">
                            ${field('Full Name', reservation.guest_name)}
                            ${field('Country', reservation.country)}
                            ${field('Contact Number', reservation.contact_number)}
                            ${field('Email Address', reservation.email)}
                        </div>
                        <div class="view-grid single" style="margin-top:10px;">
                            ${field('Residential Address', reservation.address)}
                        </div>
                        <div class="view-grid" style="margin-top:10px;">
                            ${field('ID Card Type', reservation.id_type)}
                            ${field('ID Card Number', reservation.id_number)}
                        </div>
                    </div>

                    <div class="view-section">
                        <h5><i class="fas fa-receipt"></i> Billing &amp; Charges</h5>
                        <div class="view-grid">
                            ${field('Price per Night', currency + ' ' + parseFloat(reservation.price_per_night || 0).toFixed(2))}
                            ${field('Room Subtotal', currency + ' ' + roomTotal.toFixed(2))}
                        </div>
                        <table class="view-charges-table" style="margin-top:12px;">
                            <thead><tr><th>Extra Service</th><th>Price</th></tr></thead>
                            <tbody>${chargesRows}</tbody>
                            ${charges.length ? `<tfoot><tr><td>Extra Charges Subtotal</td><td>${currency} ${chargesTotal.toFixed(2)}</td></tr></tfoot>` : ''}
                        </table>
                    </div>

                    <div class="view-total-box">
                        <span>Total Payment (${currency})</span>
                        <span class="grand-total">${currency} ${roomTotal.toFixed(2)}</span>
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

        function addExtraChargeRow(service = '', price = '') {
            const container = document.getElementById('extraChargesContainer');
            const row = document.createElement('div');
            row.className = 'row extra-charge-row';
            row.innerHTML = `
                <div class="input-group">
                    <select name="extra_service[]">
                        ${extraServices.map(item => `<option ${item === service ? 'selected' : ''}>${item}</option>`).join('')}
                    </select>
                </div>
                <div class="input-group">
                    <input type="number" step="0.01" name="extra_price[]" value="${price}" placeholder="Price">
                </div>
            `;
            container.appendChild(row);
        }

        function editReservation(id) {
            const reservation = findReservation(id);
            if (!reservation) return;

            document.getElementById('editReservationId').value = reservation.id;
            document.getElementById('editCheckIn').value = reservation.check_in_date;
            document.getElementById('editCheckOut').value = reservation.check_out_date;
            document.getElementById('editCurrency').value = reservation.currency;
            document.getElementById('editPrice').value = reservation.price_per_night;
            document.getElementById('editOccupancy').value = reservation.occupancy;
            document.getElementById('editPaymentStatus').value = reservation.payment_status;
            document.getElementById('editFirstName').value = reservation.first_name;
            document.getElementById('editMiddleName').value = reservation.middle_name || '';
            document.getElementById('editLastName').value = reservation.last_name;
            document.getElementById('editCountry').value = reservation.country;
            document.getElementById('editContact').value = reservation.contact_number || '';
            document.getElementById('editEmail').value = reservation.email || '';
            document.getElementById('editAddress').value = reservation.address || '';
            document.getElementById('editIdType').value = reservation.id_type || '';
            document.getElementById('editIdNumber').value = reservation.id_number || '';

            const roomTypeSelect = document.getElementById('editRoomType');
            Array.from(roomTypeSelect.options).forEach(option => {
                if (option.textContent === reservation.room_type_name) {
                    roomTypeSelect.value = option.value;
                }
            });

            loadEditRooms(reservation.room_id);

            const container = document.getElementById('extraChargesContainer');
            container.innerHTML = '';
            if (reservation.extra_charges && reservation.extra_charges.length) {
                reservation.extra_charges.forEach(charge => addExtraChargeRow(charge.service_name, charge.price));
            } else {
                addExtraChargeRow();
            }

            document.getElementById('editModal').style.display = 'flex';
        }

        function applyFilters() {
            const nameQuery = document.getElementById('filterName').value.trim().toLowerCase();
            const fromValue = document.getElementById('filterFrom').value;
            const toValue = document.getElementById('filterTo').value;

            let visibleCount = 0;
            const rows = document.querySelectorAll('#roomsTableBody tr[data-reservation-id]');

            rows.forEach(row => {
                const fullName = `${row.dataset.firstName || ''} ${row.dataset.middleName || ''} ${row.dataset.lastName || ''}`.toLowerCase();
                const checkin = row.dataset.checkin || '';

                let visible = true;
                if (nameQuery && !fullName.includes(nameQuery)) visible = false;
                if (fromValue && checkin < fromValue) visible = false;
                if (toValue && checkin > toValue) visible = false;

                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount++;
            });

            const existingNoResults = document.getElementById('noResultsRow');
            if (rows.length > 0 && visibleCount === 0) {
                if (!existingNoResults) {
                    const tr = document.createElement('tr');
                    tr.id = 'noResultsRow';
                    tr.className = 'no-results-row';
                    tr.innerHTML = '<td colspan="8">No reservations match the current filters.</td>';
                    document.getElementById('roomsTableBody').appendChild(tr);
                }
            } else if (existingNoResults) {
                existingNoResults.remove();
            }
        }

        function resetFilters() {
            document.getElementById('filterName').value = '';
            document.getElementById('filterFrom').value = '';
            document.getElementById('filterTo').value = '';
            applyFilters();
        }

        document.getElementById('filterName').addEventListener('input', applyFilters);
        document.getElementById('filterFrom').addEventListener('change', applyFilters);
        document.getElementById('filterTo').addEventListener('change', applyFilters);
        document.addEventListener('DOMContentLoaded', applyFilters);
    </script>
</body>

</html>