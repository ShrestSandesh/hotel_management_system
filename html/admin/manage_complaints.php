<?php
session_start();
require_once '../auth_repository.php';
require_once '../room_repository.php';
require_once '../complaint_repository.php';

requireAdminLogin();

$message = '';
$messageType = 'success';
$allowedStatuses = ['Open', 'In Progress', 'Resolved'];
$allowedPriorities = ['Low', 'Medium', 'High'];
$roomNumbers = getRoomNumbersList();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $ticketTitle = trim($_POST['ticket_title'] ?? '');
        $roomNumber = trim($_POST['room_number'] ?? '');
        $description = trim($_POST['complaint_description'] ?? '');
        $priority = trim($_POST['priority'] ?? 'Medium');
        $status = trim($_POST['status'] ?? 'Open');

        if ($ticketTitle === '' || $roomNumber === '' || $description === '') {
            $message = 'Please fill in all ticket fields.';
            $messageType = 'error';
        } elseif (!in_array($priority, $allowedPriorities, true) || !in_array($status, $allowedStatuses, true)) {
            $message = 'Invalid priority or status.';
            $messageType = 'error';
        } else {
            $ticketNumber = createComplaintTicket([
                'ticket_title' => $ticketTitle,
                'room_number' => $roomNumber,
                'complaint_description' => $description,
                'priority' => $priority,
                'status' => $status
            ]);

            header('Location: manage_complaints.php?created=' . urlencode((string) $ticketNumber));
            exit;
        }
    }

    if ($action === 'update') {
        $updated = updateComplaintTicket([
            'id' => (int) ($_POST['ticket_id'] ?? 0),
            'ticket_title' => trim($_POST['ticket_title'] ?? ''),
            'room_number' => trim($_POST['room_number'] ?? ''),
            'complaint_description' => trim($_POST['complaint_description'] ?? ''),
            'priority' => trim($_POST['priority'] ?? 'Medium'),
            'status' => trim($_POST['status'] ?? 'Open')
        ]);
        header('Location: manage_complaints.php?updated=' . ($updated ? '1' : '0'));
        exit;
    }

    if ($action === 'delete') {
        if (!isAdmin()) {
            $message = 'Unauthorized action: Staff members cannot delete tickets.';
            $messageType = 'error';
        } else {
            $deleted = deleteComplaintTicket((int) ($_POST['ticket_id'] ?? 0));
            header('Location: manage_complaints.php?deleted=' . ($deleted ? '1' : '0'));
            exit;
        }
    }
}

if (isset($_GET['created'])) {
    $message = $_GET['created'] ? 'Ticket ' . e($_GET['created']) . ' created successfully.' : 'Ticket could not be created.';
    $messageType = $_GET['created'] ? 'success' : 'error';
}
if (isset($_GET['updated'])) {
    $message = $_GET['updated'] === '1' ? 'Ticket updated successfully.' : 'Ticket could not be updated.';
    $messageType = $_GET['updated'] === '1' ? 'success' : 'error';
}
if (isset($_GET['deleted'])) {
    $message = $_GET['deleted'] === '1' ? 'Ticket deleted successfully.' : 'Ticket could not be deleted.';
    $messageType = $_GET['deleted'] === '1' ? 'success' : 'error';
}

$tickets = getComplaintTickets();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Complaint Tickets</title>
    <script src="https://kit.fontawesome.com/8aab9e126a.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./admin_style.css?v=20260617">
</head>

<body class="page-manage-complaints">
    <div class="topbar">HOTEL MATE</div>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="main">
            <div class="page-header">
                <h1 class="page-title">Complaint Tickets</h1>
                <button class="btn-add" type="button" onclick="openAddTicketModal()">Add Ticket</button>
            </div>

            <?php if ($message): ?>
                <div class="admin-alert <?= e($messageType); ?>"><?= $message; ?></div>
            <?php endif; ?>

            <div class="table-card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Title</th>
                                <th>Room</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($tickets) === 0): ?>
                                <tr>
                                    <td colspan="7">No complaint tickets found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr data-id="<?= e($ticket['id']); ?>" data-number="<?= e($ticket['ticket_number']); ?>"
                                        data-title="<?= e($ticket['ticket_title']); ?>"
                                        data-room="<?= e($ticket['room_number']); ?>"
                                        data-description="<?= e($ticket['complaint_description']); ?>"
                                        data-priority="<?= e($ticket['priority']); ?>"
                                        data-status="<?= e($ticket['status']); ?>">
                                        <td><?= e($ticket['ticket_number']); ?></td>
                                        <td><?= e($ticket['ticket_title']); ?></td>
                                        <td><?= e($ticket['room_number']); ?></td>
                                        <td><span
                                                class="ticket-priority <?= e(strtolower($ticket['priority'])); ?>"><?= e($ticket['priority']); ?></span>
                                        </td>
                                        <td><span
                                                class="ticket-status <?= e(strtolower(str_replace(' ', '-', $ticket['status']))); ?>"><?= e($ticket['status']); ?></span>
                                        </td>
                                        <td><?= e(date('M j, Y', strtotime($ticket['created_at']))); ?></td>
                                        <td>
                                            <button class="btn-edit" type="button" onclick="editComplaint(this)"><i
                                                    class="fas fa-edit"></i></button>
                                            <?php if (isAdmin()): ?>
                                                <form method="post" style="display:inline;"
                                                    onsubmit="return confirm('Delete this ticket?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="ticket_id" value="<?= e($ticket['id']); ?>">
                                                    <button type="submit"
                                                        style="background:none;border:none;font-size:18px;cursor:pointer;">🗑️</button>
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

    <div id="addTicketModal" class="modal">
        <div class="modal-content">
            <h3>Create Complaint Ticket</h3>
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="form-group"><label>Ticket Title</label><input type="text" name="ticket_title" required>
                </div>
                <div class="form-group">
                    <label>Room Number</label>
                    <select name="room_number" required>
                        <option value="">Select Room</option>
                        <?php foreach ($roomNumbers as $roomNumber): ?>
                            <option value="<?= e($roomNumber); ?>"><?= e($roomNumber); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Complaint Description</label><textarea name="complaint_description"
                        required></textarea></div>
                <div class="row">
                    <div class="input-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option>Low</option>
                            <option selected>Medium</option>
                            <option>High</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Status</label>
                        <select name="status">
                            <option>Open</option>
                            <option>In Progress</option>
                            <option>Resolved</option>
                        </select>
                    </div>
                </div>
                <div class="modal-buttons">
                    <button class="btn-cancel" type="button" onclick="closeAddTicketModal()">Cancel</button>
                    <button class="btn-save" type="submit">Create Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Ticket</h3>
            <form method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="ticket_id" id="editTicketId">
                <div class="form-group"><label>Ticket Title</label><input type="text" name="ticket_title" id="editTitle"
                        required></div>
                <div class="form-group">
                    <label>Room Number</label>
                    <select name="room_number" id="editRoom" required>
                        <?php foreach ($roomNumbers as $roomNumber): ?>
                            <option value="<?= e($roomNumber); ?>"><?= e($roomNumber); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Complaint Description</label><textarea name="complaint_description"
                        id="editDescription" required></textarea></div>
                <div class="row">
                    <div class="input-group"><label>Priority</label><select name="priority" id="editPriority">
                            <option>Low</option>
                            <option>Medium</option>
                            <option>High</option>
                        </select></div>
                    <div class="input-group"><label>Status</label><select name="status" id="editStatus">
                            <option>Open</option>
                            <option>In Progress</option>
                            <option>Resolved</option>
                        </select></div>
                </div>
                <div class="modal-buttons">
                    <button class="btn-cancel" type="button" onclick="closeEditModal()">Cancel</button>
                    <button class="btn-save" type="submit">Save Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/high_priority_alert.php'; ?>
    <script>
        function openAddTicketModal() { document.getElementById('addTicketModal').classList.add('show'); }
        function closeAddTicketModal() { document.getElementById('addTicketModal').classList.remove('show'); }
        function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

        function editComplaint(button) {
            const row = button.closest('tr');
            document.getElementById('editTicketId').value = row.dataset.id;
            document.getElementById('editTitle').value = row.dataset.title;
            document.getElementById('editRoom').value = row.dataset.room;
            document.getElementById('editDescription').value = row.dataset.description;
            document.getElementById('editPriority').value = row.dataset.priority;
            document.getElementById('editStatus').value = row.dataset.status;
            document.getElementById('editModal').classList.add('show');
        }
    </script>
</body>

</html>