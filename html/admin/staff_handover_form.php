<div class="form-group">
    <label for="guestName">Guest Name</label>
    <input type="text" id="guestName" name="guest_name" required>
</div>
<div class="row">
    <div class="input-group">
        <label for="roomNumber">Room Number</label>
        <input type="text" id="roomNumber" name="room_number">
    </div>
    <div class="input-group">
        <label for="requestType">Request Type</label>
        <select id="requestType" name="request_type" required>
            <?php foreach ($requestTypes as $type): ?>
                <option value="<?= handover_e($type); ?>"><?= handover_e($type); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="form-group">
    <label for="requestDetails">Request Details</label>
    <textarea id="requestDetails" name="request_details" placeholder="Example: Guest requested checkout at 12:30 PM. Please confirm room key and minibar before checkout." required></textarea>
</div>
<div class="row">
    <div class="input-group">
        <label for="dueDate">Due Date</label>
        <input type="date" id="dueDate" name="due_date">
    </div>
    <div class="input-group">
        <label for="dueTime">Due Time</label>
        <input type="time" id="dueTime" name="due_time">
    </div>
</div>
<div class="row">
    <div class="input-group">
        <label for="priority">Priority</label>
        <select id="priority" name="priority" required>
            <?php foreach ($allowedPriorities as $priority): ?>
                <option value="<?= handover_e($priority); ?>" <?= $priority === 'Medium' ? 'selected' : ''; ?>><?= handover_e($priority); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="input-group">
        <label for="status">Status</label>
        <select id="status" name="status" required>
            <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?= handover_e($status); ?>" <?= $status === 'Pending' ? 'selected' : ''; ?>><?= handover_e($status); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="form-group">
    <label for="assignedTo">Assigned To</label>
    <input type="text" id="assignedTo" name="assigned_to" placeholder="Next shift, reception, housekeeping">
</div>
