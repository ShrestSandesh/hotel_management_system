<div id="highPriorityAlert" class="high-priority-overlay" style="display:none;">
    <div class="high-priority-popup">
        <button type="button" class="close-btn" onclick="closeHighPriorityAlert()">×</button>
        <h3>⚠ HIGH PRIORITY TICKET</h3>
        <div id="highPriorityContent"></div>
        <button type="button" class="btn" onclick="closeHighPriorityAlert()">Acknowledge</button>
    </div>
</div>
<style>
.high-priority-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.65);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 20px;
}
.high-priority-popup {
    background: #fff;
    border: 3px solid #dc2626;
    border-radius: 8px;
    padding: 24px;
    width: min(520px, 100%);
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
}
.high-priority-popup h3 {
    color: #991b1b;
    margin-bottom: 14px;
}
.high-priority-item {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
}
</style>
<script>
(function () {
    const THIRTY_MINUTES = 30 * 60 * 1000;
    let dismissedUntil = 0;

    window.closeHighPriorityAlert = function () {
        document.getElementById('highPriorityAlert').style.display = 'none';
        dismissedUntil = Date.now() + THIRTY_MINUTES;
    };

    function showHighPriorityAlert(tickets) {
        if (!tickets.length || Date.now() < dismissedUntil) {
            return;
        }

        const content = document.getElementById('highPriorityContent');
        content.innerHTML = tickets.map(ticket => `
            <div class="high-priority-item">
                <strong>Room ${escapeHtml(ticket.room_number)}</strong><br>
                ${escapeHtml(ticket.ticket_title)}<br>
                <small>Status: ${escapeHtml(ticket.status)}</small>
            </div>
        `).join('');

        document.getElementById('highPriorityAlert').style.display = 'flex';
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function checkHighPriorityTickets() {
        fetch('../api.php?action=high_priority_tickets')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length) {
                    showHighPriorityAlert(data.data);
                }
            })
            .catch(() => {});
    }

    checkHighPriorityTickets();
    setInterval(checkHighPriorityTickets, THIRTY_MINUTES);
})();
</script>
