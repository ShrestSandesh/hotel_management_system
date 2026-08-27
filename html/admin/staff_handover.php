<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

require_once '../handover_repository.php';

$message = '';
$messageType = 'success';
$allowedPriorities = ['Low', 'Medium', 'High', 'Urgent'];
$allowedStatuses = ['Pending', 'In Progress', 'Done', 'Cancelled'];
$requestTypes = ['Late Checkout', 'Wake-up Call', 'Room Service', 'Maintenance', 'Payment Follow-up', 'Guest Message', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $guestName = trim($_POST['guest_name'] ?? '');
        $roomNumber = trim($_POST['room_number'] ?? '');
        $requestType = trim($_POST['request_type'] ?? '');
        $requestDetails = trim($_POST['request_details'] ?? '');
        $dueDate = trim($_POST['due_date'] ?? '');
        $dueTime = trim($_POST['due_time'] ?? '');
        $priority = trim($_POST['priority'] ?? 'Medium');
        $status = trim($_POST['status'] ?? 'Pending');
        $assignedTo = trim($_POST['assigned_to'] ?? '');

        if ($guestName === '' || $requestType === '' || $requestDetails === '') {
            $message = 'Please fill in guest name, request type, and request details.';
            $messageType = 'error';
        } elseif (!in_array($requestType, $requestTypes, true)) {
            $message = 'Please choose a valid request type.';
            $messageType = 'error';
        } elseif (!in_array($priority, $allowedPriorities, true) || !in_array($status, $allowedStatuses, true)) {
            $message = 'Please choose a valid priority and status.';
            $messageType = 'error';
        } else {
            $handoverData = [
                'guest_name' => $guestName,
                'room_number' => $roomNumber,
                'request_type' => $requestType,
                'request_details' => $requestDetails,
                'due_date' => $dueDate,
                'due_time' => $dueTime,
                'priority' => $priority,
                'status' => $status,
                'assigned_to' => $assignedTo
            ];

            if ($action === 'create') {
                $handoverData['created_by'] = $_SESSION['user'] ?? '';
                $created = createStaffHandover($handoverData);
                header('Location: staff_handover.php?created=' . ($created ? '1' : '0'));
                exit;
            }

            $handoverData['id'] = (int) ($_POST['handover_id'] ?? 0);
            $updated = $handoverData['id'] > 0 ? updateStaffHandover($handoverData) : false;
            header('Location: staff_handover.php?updated=' . ($updated ? '1' : '0'));
            exit;
        }
    }

    if ($action === 'delete') {
        if (!isAdmin()) {
            $message = 'Unauthorized action: Staff members cannot delete handover notes.';
            $messageType = 'error';
        } else {
            $handoverId = (int) ($_POST['handover_id'] ?? 0);
            $deleted = $handoverId > 0 ? deleteStaffHandover($handoverId) : false;
            header('Location: staff_handover.php?deleted=' . ($deleted ? '1' : '0'));
            exit;
        }
    }
}

if (isset($_GET['created'])) {
    $message = $_GET['created'] === '1' ? 'Handover note added successfully.' : 'Handover note could not be added.';
    $messageType = $_GET['created'] === '1' ? 'success' : 'error';
}

if (isset($_GET['updated'])) {
    $message = $_GET['updated'] === '1' ? 'Handover note updated successfully.' : 'Handover note could not be updated.';
    $messageType = $_GET['updated'] === '1' ? 'success' : 'error';
}

if (isset($_GET['deleted'])) {
    $message = $_GET['deleted'] === '1' ? 'Handover note deleted successfully.' : 'Handover note could not be deleted.';
    $messageType = $_GET['deleted'] === '1' ? 'success' : 'error';
}

$handovers = getStaffHandovers();

function formatHandoverDue($date, $time)
{
    if (!$date && !$time) {
        return 'Not set';
    }

    $dateText = $date ? date('M j, Y', strtotime($date)) : 'No date';
    $timeText = $time ? date('g:i A', strtotime($time)) : 'No time';

    return $dateText . ' at ' . $timeText;
}

function handoverClass($value)
{
    return strtolower(str_replace(' ', '-', $value));
}

function shortenHandoverText($value, $length = 70)
{
    return strlen($value) > $length ? substr($value, 0, $length) . '...' : $value;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Staff Handover</title>
    <script src="https://kit.fontawesome.com/8aab9e126a.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./admin_style.css?v=20260616">
</head>

<body class="page-staff-handover">
    <div class="topbar">HOTEL MANAGEMENT SYSTEM</div>
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <div class="main">
            <div class="page-header">
                <h1 class="page-title">Staff Handover</h1>
                <button class="btn-add" type="button" onclick="openAddHandoverModal()">
                    <i class="fas fa-plus"></i> Add Note
                </button>
            </div>

            <?php if ($message): ?>
                <div class="admin-alert <?= handover_e($messageType); ?>"><?= handover_e($message); ?></div>
            <?php endif; ?>

            <div class="table-card">
                <div class="table-controls">
                    <div class="show-entries">
                        <label for="entries">Show</label>
                        <input id="entries" type="number" min="1" value="10" oninput="filterHandovers()">
                        <span>entries</span>
                    </div>
                    <div class="search-box">
                        <label for="search">Search:</label>
                        <input id="search" type="text" placeholder="Search handover notes..." oninput="filterHandovers()">
                    </div>
                </div>

                <div class="table-wrapper">
                    <table id="handoverTable">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Room</th>
                                <th>Request</th>
                                <th>Due</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($handovers) === 0): ?>
                                <tr class="empty-row">
                                    <td colspan="8">No handover notes found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($handovers as $handover): ?>
                                    <tr
                                        data-id="<?= handover_e($handover['id']); ?>"
                                        data-guest="<?= handover_e($handover['guest_name']); ?>"
                                        data-room="<?= handover_e($handover['room_number']); ?>"
                                        data-type="<?= handover_e($handover['request_type']); ?>"
                                        data-details="<?= handover_e($handover['request_details']); ?>"
                                        data-due-date="<?= handover_e($handover['due_date']); ?>"
                                        data-due-time="<?= handover_e($handover['due_time']); ?>"
                                        data-priority="<?= handover_e($handover['priority']); ?>"
                                        data-status="<?= handover_e($handover['status']); ?>"
                                        data-assigned="<?= handover_e($handover['assigned_to']); ?>"
                                        data-created-by="<?= handover_e($handover['created_by']); ?>"
                                        data-created-at="<?= handover_e(date('M j, Y g:i A', strtotime($handover['created_at']))); ?>"
                                    >
                                        <td><?= handover_e($handover['guest_name']); ?></td>
                                        <td><?= $handover['room_number'] ? handover_e($handover['room_number']) : 'Not set'; ?></td>
                                        <td>
                                            <strong><?= handover_e($handover['request_type']); ?></strong><br>
                                            <small><?= handover_e(shortenHandoverText($handover['request_details'])); ?></small>
                                        </td>
                                        <td><?= handover_e(formatHandoverDue($handover['due_date'], $handover['due_time'])); ?></td>
                                        <td><span class="ticket-priority <?= handover_e(handoverClass($handover['priority'])); ?>"><?= handover_e($handover['priority']); ?></span></td>
                                        <td><span class="ticket-status <?= handover_e(handoverClass($handover['status'])); ?>"><?= handover_e($handover['status']); ?></span></td>
                                        <td><?= $handover['assigned_to'] ? handover_e($handover['assigned_to']) : 'Next shift'; ?></td>
                                        <td class="action-buttons">
                                            <button class="action-view js-view-handover" type="button" title="View"><i class="fas fa-eye"></i></button>
                                            <button class="action-edit js-edit-handover" type="button" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                                            <?php if (isAdmin()): ?>
                                                <form method="post" action="staff_handover.php" style="display:inline; margin:0;" onsubmit="return confirm('Delete this handover note?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="handover_id" value="<?= handover_e($handover['id']); ?>">
                                                    <button class="action-delete" type="submit" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <span id="handoverPaginationText">Showing <?= min(10, count($handovers)); ?> of <?= count($handovers); ?> notes</span>
                    <div class="pagination-buttons">
                        <button type="button">Previous</button>
                        <button type="button" class="active">1</button>
                        <button type="button">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>Add Handover Note</h3>
            <form method="post" action="staff_handover.php">
                <input type="hidden" name="action" value="create">
                <?php include 'staff_handover_form.php'; ?>
                <div class="modal-buttons">
                    <button class="btn-cancel" type="button" onclick="closeAddHandoverModal()">Cancel</button>
                    <button class="btn-save" type="submit">Save Note</button>
                </div>
            </form>
        </div>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content">
            <h3>Handover Details</h3>
            <div class="ticket-detail-box">
                <p><strong id="viewGuest"></strong></p>
                <p id="viewRoom"></p>
                <p id="viewRequest"></p>
                <p id="viewDue"></p>
                <p id="viewPriority"></p>
                <p id="viewStatus"></p>
                <p id="viewAssigned"></p>
                <p id="viewCreated"></p>
                <p id="viewDetails"></p>
            </div>
            <div class="modal-buttons">
                <button class="btn-cancel" type="button" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Handover Note</h3>
            <form method="post" action="staff_handover.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="handover_id" id="editHandoverId">

                <div class="form-group">
                    <label for="editGuestName">Guest Name</label>
                    <input type="text" id="editGuestName" name="guest_name" required>
                </div>
                <div class="row">
                    <div class="input-group">
                        <label for="editRoomNumber">Room Number</label>
                        <input type="text" id="editRoomNumber" name="room_number">
                    </div>
                    <div class="input-group">
                        <label for="editRequestType">Request Type</label>
                        <select id="editRequestType" name="request_type" required>
                            <?php foreach ($requestTypes as $type): ?>
                                <option value="<?= handover_e($type); ?>"><?= handover_e($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="editRequestDetails">Request Details</label>
                    <textarea id="editRequestDetails" name="request_details" required></textarea>
                </div>
                <div class="row">
                    <div class="input-group">
                        <label for="editDueDate">Due Date</label>
                        <input type="date" id="editDueDate" name="due_date">
                    </div>
                    <div class="input-group">
                        <label for="editDueTime">Due Time</label>
                        <input type="time" id="editDueTime" name="due_time">
                    </div>
                </div>
                <div class="row">
                    <div class="input-group">
                        <label for="editPriority">Priority</label>
                        <select id="editPriority" name="priority" required>
                            <?php foreach ($allowedPriorities as $priority): ?>
                                <option value="<?= handover_e($priority); ?>"><?= handover_e($priority); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="editStatus">Status</label>
                        <select id="editStatus" name="status" required>
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option value="<?= handover_e($status); ?>"><?= handover_e($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="editAssignedTo">Assigned To</label>
                    <input type="text" id="editAssignedTo" name="assigned_to" placeholder="Next shift, reception, housekeeping">
                </div>

                <div class="modal-buttons">
                    <button class="btn-cancel" type="button" onclick="closeEditModal()">Cancel</button>
                    <button class="btn-save" type="submit">Save Note</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddHandoverModal() {
            document.getElementById('addModal').classList.add('show');
        }

        function closeAddHandoverModal() {
            document.getElementById('addModal').classList.remove('show');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function getHandoverRow(button) {
            return button.closest('tr');
        }

        function formatDue(row) {
            const date = row.dataset.dueDate || 'No date';
            const time = row.dataset.dueTime || 'No time';
            return date === 'No date' && time === 'No time' ? 'Not set' : `${date} at ${time}`;
        }

        function viewHandover(button) {
            const row = getHandoverRow(button);
            if (!row) {
                return;
            }

            document.getElementById('viewGuest').textContent = row.dataset.guest;
            document.getElementById('viewRoom').textContent = `Room: ${row.dataset.room || 'Not set'}`;
            document.getElementById('viewRequest').textContent = `Request: ${row.dataset.type}`;
            document.getElementById('viewDue').textContent = `Due: ${formatDue(row)}`;
            document.getElementById('viewPriority').textContent = `Priority: ${row.dataset.priority}`;
            document.getElementById('viewStatus').textContent = `Status: ${row.dataset.status}`;
            document.getElementById('viewAssigned').textContent = `Assigned to: ${row.dataset.assigned || 'Next shift'}`;
            document.getElementById('viewCreated').textContent = `Created by: ${row.dataset.createdBy || 'Not set'} on ${row.dataset.createdAt}`;
            document.getElementById('viewDetails').textContent = `Details: ${row.dataset.details}`;
            document.getElementById('viewModal').classList.add('show');
        }

        function editHandover(button) {
            const row = getHandoverRow(button);
            if (!row) {
                return;
            }

            document.getElementById('editHandoverId').value = row.dataset.id;
            document.getElementById('editGuestName').value = row.dataset.guest;
            document.getElementById('editRoomNumber').value = row.dataset.room;
            document.getElementById('editRequestType').value = row.dataset.type;
            document.getElementById('editRequestDetails').value = row.dataset.details;
            document.getElementById('editDueDate').value = row.dataset.dueDate;
            document.getElementById('editDueTime').value = row.dataset.dueTime;
            document.getElementById('editPriority').value = row.dataset.priority;
            document.getElementById('editStatus').value = row.dataset.status;
            document.getElementById('editAssignedTo').value = row.dataset.assigned;
            document.getElementById('editModal').classList.add('show');
        }

        document.querySelectorAll('.js-view-handover').forEach(button => {
            button.addEventListener('click', () => viewHandover(button));
        });

        document.querySelectorAll('.js-edit-handover').forEach(button => {
            button.addEventListener('click', () => editHandover(button));
        });

        ['addModal', 'viewModal', 'editModal'].forEach(modalId => {
            document.getElementById(modalId).addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

        function filterHandovers() {
            const query = document.getElementById('search').value.toLowerCase();
            const maxEntries = parseInt(document.getElementById('entries').value, 10) || 10;
            const rows = document.querySelectorAll('#handoverTable tbody tr:not(.empty-row)');
            let visibleCount = 0;
            let matchCount = 0;

            rows.forEach(row => {
                const matchesSearch = row.textContent.toLowerCase().includes(query);
                if (matchesSearch) {
                    matchCount++;
                }
                if (matchesSearch && visibleCount < maxEntries) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('handoverPaginationText').textContent = `Showing ${visibleCount} of ${matchCount} notes`;
        }

        filterHandovers();
    </script>
</body>

</html>
