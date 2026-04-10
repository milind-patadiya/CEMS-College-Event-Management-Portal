<?php
session_start();
$active_page = 'approvals';
require_once('../config/db.php');
require_once('../includes/admin_sidebar.php');

$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decision'])) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $reason   = trim($_POST['rejection_reason'] ?? '');

    if ($event_id && in_array($decision, ['Approved', 'Rejected'])) {
        try {
            if ($decision === 'Approved') {
                $s = $conn->prepare("UPDATE events SET status='Approved', rejection_reason=NULL WHERE event_id=?");
                $s->bind_param("i", $event_id); $s->execute();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Event approved — it is now visible to all students.'];
            } else {
                if (!$reason) $reason = 'Rejected by Admin/HOD.';
                $s = $conn->prepare("UPDATE events SET status='Rejected', rejection_reason=? WHERE event_id=?");
                $s->bind_param("si", $reason, $event_id); $s->execute();
                $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Event rejected. Faculty has been notified with the reason.'];
            }
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error updating event status.'];
        }
        header("Location: approvals.php"); exit();
    }
}

$event_id = (int)($_GET['event_id'] ?? 0);
$event_detail = null;
if ($event_id) {
    try {
        $q = $conn->prepare("
            SELECT e.*, f.full_name AS faculty_name, f.department, f.email AS faculty_email,
                   COUNT(r.registration_id) AS reg_count
            FROM events e
            LEFT JOIN faculty f ON f.faculty_id = e.created_by
            LEFT JOIN registrations r ON r.event_id = e.event_id
            WHERE e.event_id = ?
            GROUP BY e.event_id
        ");
        $q->bind_param("i", $event_id); $q->execute();
        $event_detail = $q->get_result()->fetch_assoc();
    } catch (Exception $e) {}
}

$pending_events = [];
try {
    $q = $conn->query("
        SELECT e.event_id, e.event_name, e.event_date, e.event_time, e.venue, e.capacity, e.description, e.created_at,
               f.full_name AS faculty_name, f.department
        FROM events e
        LEFT JOIN faculty f ON f.faculty_id = e.created_by
        WHERE e.status = 'Pending'
        ORDER BY e.created_at ASC
    ");
    $pending_events = $q->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}

$recent_decisions = [];
try {
    $q = $conn->query("
        SELECT e.event_id, e.event_name, e.event_date, e.status, e.rejection_reason,
               f.full_name AS faculty_name
        FROM events e
        LEFT JOIN faculty f ON f.faculty_id = e.created_by
        WHERE e.status != 'Pending'
        ORDER BY e.created_at DESC LIMIT 10
    ");
    $recent_decisions = $q->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Event Approvals — Admin | CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .detail-card { background:white; border:1px solid var(--border); border-radius:var(--radius-md); padding:24px 28px; box-shadow:var(--shadow-sm); margin-bottom:20px; }
        .ev-detail-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; margin-bottom:20px; }
        .ev-detail-item { background:#f8f9ff; border-radius:var(--radius-sm); padding:12px 14px; }
        .ev-detail-item .label { font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:var(--text-muted); margin-bottom:4px; }
        .ev-detail-item .value { font-weight:600; font-size:0.88rem; color:var(--text-dark); }
        .decision-btns { display:flex; gap:10px; flex-wrap:wrap; }
        .btn-approve { background:var(--success); color:white; border:none; padding:10px 24px; border-radius:var(--radius-sm); font-family:var(--font-display); font-weight:700; font-size:0.9rem; cursor:pointer; transition:var(--transition); display:flex; align-items:center; gap:7px; }
        .btn-approve:hover { background:#1b5e20; transform:translateY(-1px); }
        .btn-reject  { background:var(--danger); color:white; border:none; padding:10px 24px; border-radius:var(--radius-sm); font-family:var(--font-display); font-weight:700; font-size:0.9rem; cursor:pointer; transition:var(--transition); display:flex; align-items:center; gap:7px; }
        .btn-reject:hover { background:#b71c1c; transform:translateY(-1px); }
        .pending-ev-row { cursor:pointer; }
        .pending-ev-row:hover { background:#fff8e1 !important; }
        .pending-ev-row.selected { background:#fff3cd !important; border-left:3px solid #f9a825; }
        .reason-box { display:none; margin-top:12px; }
    </style>
</head>
<body>
<main class="main-content"><div class="content-inner">

<?php if ($flash): ?>
<div class="alert-cems alert-<?= $flash['type'] ?> mb-3">
    <i class="fas fa-<?= $flash['type']==='success'?'check-circle':($flash['type']==='warning'?'exclamation-triangle':'exclamation-circle') ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-check-double me-2" style="color:var(--accent);font-size:1rem;"></i>Event Approvals</h1>
        <p>Review and approve or reject events submitted by faculty. Approved events are immediately visible to students.</p>
    </div>
    <span class="badge-cems badge-pending" style="font-size:0.9rem;padding:8px 16px;">
        <?= count($pending_events) ?> Pending
    </span>
</div>

<?php if ($event_detail): ?>
<div class="detail-card">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 style="font-family:var(--font-display);font-weight:800;color:var(--primary);margin-bottom:4px;">
                <?= htmlspecialchars($event_detail['event_name']) ?>
            </h4>
            <p style="color:var(--text-muted);font-size:0.84rem;margin:0;">
                Submitted by <strong><?= htmlspecialchars($event_detail['faculty_name'] ?? '—') ?></strong>
                <?= $event_detail['department'] ? ' · ' . htmlspecialchars($event_detail['department']) : '' ?>
            </p>
        </div>
        <span class="badge-cems badge-pending">Pending Review</span>
    </div>

    <div class="ev-detail-grid">
        <div class="ev-detail-item">
            <div class="label"><i class="fas fa-calendar me-1"></i>Date</div>
            <div class="value"><?= date('D, d M Y', strtotime($event_detail['event_date'])) ?></div>
        </div>
        <div class="ev-detail-item">
            <div class="label"><i class="fas fa-clock me-1"></i>Time</div>
            <div class="value"><?= $event_detail['event_time'] ? date('h:i A', strtotime($event_detail['event_time'])) : '—' ?></div>
        </div>
        <div class="ev-detail-item">
            <div class="label"><i class="fas fa-map-marker-alt me-1"></i>Venue</div>
            <div class="value"><?= htmlspecialchars($event_detail['venue'] ?: '—') ?></div>
        </div>
        <div class="ev-detail-item">
            <div class="label"><i class="fas fa-users me-1"></i>Capacity</div>
            <div class="value"><?= $event_detail['capacity'] ? $event_detail['capacity'] . ' students' : 'Unlimited' ?></div>
        </div>
        <div class="ev-detail-item">
            <div class="label"><i class="fas fa-clipboard-list me-1"></i>Registrations</div>
            <div class="value"><?= $event_detail['reg_count'] ?> so far</div>
        </div>
        <div class="ev-detail-item">
            <div class="label"><i class="fas fa-paper-plane me-1"></i>Submitted</div>
            <div class="value"><?= date('d M Y', strtotime($event_detail['created_at'])) ?></div>
        </div>
    </div>

    <?php if ($event_detail['description']): ?>
    <div style="background:#f8f9ff;border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:20px;">
        <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;">Description</div>
        <p style="font-size:0.88rem;color:var(--text-dark);margin:0;line-height:1.65;"><?= nl2br(htmlspecialchars($event_detail['description'])) ?></p>
    </div>
    <?php endif; ?>

    <form method="POST" id="decisionForm">
        <input type="hidden" name="event_id" value="<?= $event_detail['event_id'] ?>">
        <input type="hidden" name="decision" id="decisionInput">

        <div class="reason-box" id="reasonBox">
            <label class="form-label-custom">Rejection Reason <span style="color:#ef5350;">*</span> <small style="text-transform:none;font-weight:400;">(shown to faculty)</small></label>
            <textarea name="rejection_reason" class="form-control-custom" rows="3" id="reasonText"
                      placeholder="Explain clearly why this event is being rejected so the faculty can revise and resubmit..."></textarea>
        </div>

        <div class="decision-btns mt-3">
            <button type="button" class="btn-approve" onclick="submitDecision('Approved')">
                <i class="fas fa-check"></i> Approve Event
            </button>
            <button type="button" class="btn-reject" onclick="showRejectBox()">
                <i class="fas fa-times"></i> Reject Event
            </button>
            <button type="button" id="confirmRejectBtn" class="btn-reject" style="display:none;" onclick="submitDecision('Rejected')">
                <i class="fas fa-times-circle"></i> Confirm Rejection
            </button>
            <a href="approvals.php" class="btn-outline-custom">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (!empty($pending_events)): ?>
<div class="table-wrap mb-4">
    <div class="card-header-custom" style="background:#fff8e1;">
        <i class="fas fa-clock" style="color:#e65100;"></i>
        <span style="color:#e65100;font-weight:800;">Awaiting Approval (<?= count($pending_events) ?>)</span>
    </div>
    <table class="cems-table">
        <thead><tr><th>Event Name</th><th>Faculty</th><th>Department</th><th>Event Date</th><th>Capacity</th><th>Submitted</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($pending_events as $ev): ?>
        <tr class="pending-ev-row <?= $event_id == $ev['event_id'] ? 'selected' : '' ?>">
            <td><strong><?= htmlspecialchars($ev['event_name']) ?></strong></td>
            <td style="color:var(--text-muted);"><?= htmlspecialchars($ev['faculty_name'] ?? '—') ?></td>
            <td style="color:var(--text-muted);font-size:0.78rem;"><?= htmlspecialchars($ev['department'] ?? '—') ?></td>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($ev['event_date'])) ?></td>
            <td style="color:var(--text-muted);"><?= $ev['capacity'] ?: 'Unlimited' ?></td>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($ev['created_at'])) ?></td>
            <td>
                <div class="d-flex gap-1">
                    <a href="approvals.php?event_id=<?= $ev['event_id'] ?>" class="btn-primary-custom btn-sm-custom" style="background:#e65100;">
                        <i class="fas fa-eye"></i> Review
                    </a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="event_id" value="<?= $ev['event_id'] ?>">
                        <input type="hidden" name="decision" value="Approved">
                        <button type="submit" class="btn-outline-custom btn-sm-custom" style="color:var(--success);border-color:var(--success);" title="Quick Approve">
                            <i class="fas fa-check"></i>
                        </button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty-state mb-4">
    <i class="fas fa-check-circle" style="color:var(--success);font-size:2.5rem;"></i>
    <h5>All clear!</h5>
    <p>No events are pending approval right now.</p>
</div>
<?php endif; ?>

<div class="table-wrap">
    <div class="card-header-custom"><i class="fas fa-history"></i> Recent Decisions</div>
    <table class="cems-table">
        <thead><tr><th>Event Name</th><th>Faculty</th><th>Event Date</th><th>Decision</th><th>Reason</th></tr></thead>
        <tbody>
        <?php foreach ($recent_decisions as $ev): ?>
        <tr>
            <td><strong><?= htmlspecialchars($ev['event_name']) ?></strong></td>
            <td style="color:var(--text-muted);font-size:0.78rem;"><?= htmlspecialchars($ev['faculty_name'] ?? '—') ?></td>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($ev['event_date'])) ?></td>
            <td><span class="badge-cems badge-<?= strtolower($ev['status']) ?>"><?= $ev['status'] ?></span></td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= htmlspecialchars($ev['rejection_reason'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recent_decisions)): ?>
        <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted);">No decisions yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</div></main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function submitDecision(decision) {
    document.getElementById('decisionInput').value = decision;
    document.getElementById('decisionForm').submit();
}
function showRejectBox() {
    document.getElementById('reasonBox').style.display = 'block';
    document.getElementById('confirmRejectBtn').style.display = 'flex';
    document.querySelector('.btn-reject:not(#confirmRejectBtn)').style.display = 'none';
    document.getElementById('reasonText').focus();
}
</script>
</body>
</html>
