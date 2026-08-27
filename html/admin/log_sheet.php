<?php
session_start();
require_once '../auth_repository.php';
require_once '../log_sheet_repository.php';

requireAdminLogin();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '' || $description === '') {
            $message = 'Title and description are required.';
            $messageType = 'error';
        } elseif (createLogSheet($title, $description, getAdminName())) {
            header('Location: log_sheet.php?created=1');
            exit;
        } else {
            $message = 'Could not save log entry.';
            $messageType = 'error';
        }
    }

    if ($action === 'update') {
        $updated = updateLogSheet(
            (int) ($_POST['log_id'] ?? 0),
            trim($_POST['title'] ?? ''),
            trim($_POST['description'] ?? '')
        );
        header('Location: log_sheet.php?updated=' . ($updated ? '1' : '0'));
        exit;
    }

    if ($action === 'delete') {
        if (!isAdmin()) {
            $message = 'Unauthorized action: Staff members cannot delete log entries.';
            $messageType = 'error';
        } else {
            $deleted = deleteLogSheet((int) ($_POST['log_id'] ?? 0));
            header('Location: log_sheet.php?deleted=' . ($deleted ? '1' : '0'));
            exit;
        }
    }
}

if (isset($_GET['created'])) {
    $message = 'Log entry added successfully.';
}
if (isset($_GET['updated'])) {
    $message = $_GET['updated'] === '1' ? 'Log entry updated successfully.' : 'Log entry could not be updated.';
    $messageType = $_GET['updated'] === '1' ? 'success' : 'error';
}
if (isset($_GET['deleted'])) {
    $message = $_GET['deleted'] === '1' ? 'Log entry deleted successfully.' : 'Log entry could not be deleted.';
    $messageType = $_GET['deleted'] === '1' ? 'success' : 'error';
}

$logs = getLogSheets();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Log Sheet</title>
    <script src="https://kit.fontawesome.com/8aab9e126a.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./admin_style.css?v=20260617">
</head>

<body class="page-manage-complaints">
    <div class="topbar">HOTEL MATE</div>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="main">
            <div class="page-header">
                <h1 class="page-title">Log Sheet</h1>
                <button class="btn-add" type="button" onclick="openAddModal()">Add Entry</button>
            </div>

            <?php if ($message): ?>
                <div class="admin-alert <?= log_e($messageType); ?>"><?= log_e($message); ?></div>
            <?php endif; ?>

            <div class="table-card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Written By</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logs) === 0): ?>
                                <tr>
                                    <td colspan="5">No log entries yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr data-id="<?= log_e($log['id']); ?>" data-title="<?= log_e($log['title']); ?>"
                                        data-description="<?= log_e($log['description']); ?>"
                                        data-written-by="<?= log_e($log['written_by']); ?>"
                                        data-created-at="<?= log_e(date('M j, Y g:i A', strtotime($log['created_at']))); ?>">
                                        <td><?= log_e(date('M j, Y', strtotime($log['created_at']))); ?></td>
                                        <td><?= log_e($log['written_by']); ?></td>
                                        <td><?= log_e($log['title']); ?></td>
                                        <td><?= log_e($log['description']); ?></td>
                                        <td class="action-buttons">
                                            <button class="action-view js-view-log" type="button"><i
                                                    class="fas fa-eye"></i></button>
                                            <button class="action-edit js-edit-log" type="button"><i
                                                    class="fas fa-pencil-alt"></i></button>
                                            <?php if (isAdmin()): ?>
                                                <form method="post" style="display:inline;"
                                                    onsubmit="return confirm('Delete this log entry?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="log_id" value="<?= log_e($log['id']); ?>">
                                                    <button class="action-delete" type="submit"><i
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

    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>Add Log Entry</h3>
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" required></textarea>
                </div>
                <p><small>Written by: <?= log_e(getAdminName()); ?> (automatic)</small></p>
                <div class="modal-buttons">
                    <button class="btn-cancel" type="button" onclick="closeAddModal()">Cancel</button>
                    <button class="btn-save" type="submit">Save Entry</button>
                </div>
            </form>
        </div>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content">
            <h3>Log Entry</h3>
            <div class="ticket-detail-box" id="viewContent"></div>
            <div class="modal-buttons"><button class="btn-cancel" type="button"
                    onclick="closeViewModal()">Close</button></div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Log Entry</h3>
            <form method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="log_id" id="editLogId">
                <div class="form-group"><label>Title</label><input type="text" name="title" id="editTitle" required>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" id="editDescription"
                        required></textarea></div>
                <div class="modal-buttons">
                    <button class="btn-cancel" type="button" onclick="closeEditModal()">Cancel</button>
                    <button class="btn-save" type="submit">Save Entry</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/high_priority_alert.php'; ?>
    <script>
        function openAddModal() { document.getElementById('addModal').classList.add('show'); }
        function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }
        function closeViewModal() { document.getElementById('viewModal').classList.remove('show'); }
        function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

        document.querySelectorAll('.js-view-log').forEach(button => {
            button.addEventListener('click', () => {
                const row = button.closest('tr');
                document.getElementById('viewContent').innerHTML = `
                    <p><strong>${row.dataset.title}</strong></p>
                    <p>${row.dataset.description}</p>
                    <p><small>By ${row.dataset.writtenBy} on ${row.dataset.createdAt}</small></p>
                `;
                document.getElementById('viewModal').classList.add('show');
            });
        });

        document.querySelectorAll('.js-edit-log').forEach(button => {
            button.addEventListener('click', () => {
                const row = button.closest('tr');
                document.getElementById('editLogId').value = row.dataset.id;
                document.getElementById('editTitle').value = row.dataset.title;
                document.getElementById('editDescription').value = row.dataset.description;
                document.getElementById('editModal').classList.add('show');
            });
        });
    </script>
</body>

</html>