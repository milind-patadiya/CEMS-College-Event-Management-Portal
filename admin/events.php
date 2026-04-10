<?php
session_start();
$active_page = 'events';
require_once('../config/db.php');
require_once('../includes/admin_sidebar.php');

$action   = $_GET['action'] ?? 'list';
$event_id = (int)($_GET['id'] ?? 0);
$errors   = [];

if ($action === 'delete' && $event_id) {
    try {
        $d = $conn->prepare("DELETE FROM events WHERE event_id=?");
        $d->bind_param("i", $event_id); $d->execute();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Event deleted. All registrations and attendance removed.'];
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Could not delete event.'];
    }
    header("Location: events.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['event_name']  ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $venue   = trim($_POST['venue']       ?? '');
    $date    = $_POST['event_date']       ?? '';
    $time    = $_POST['event_time']       ?? '';
    $cap_raw = (isset($_POST['capacity']) && $_POST['capacity'] !== '') ? (int)$_POST['capacity'] : null;
    $status  = $_POST['status']           ?? 'Pending';
    $reason  = trim($_POST['rejection_reason'] ?? '');
    $edit_id = (int)($_POST['edit_id']    ?? 0);

    if (!$name) $errors[] = 'Event name is required.';
    if (!$date) $errors[] = 'Event date is required.';
    if (!in_array($status, ['Pending','Approved','Rejected'])) $status = 'Pending';

    if (empty($errors)) {
        try {
            $reason_val = $reason ?: null;
            if ($edit_id) {
                $s = $conn->prepare("UPDATE events SET event_name=?,description=?,venue=?,event_date=?,event_time=?,capacity=?,status=?,rejection_reason=? WHERE event_id=?");
                $s->bind_param("ssssssssi", $name, $desc, $venue, $date, $time, $cap_raw, $status, $reason_val, $edit_id);
                $s->execute();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Event updated. Status change is reflected immediately.'];
            } else {
                $s = $conn->prepare("INSERT INTO events (event_name,description,venue,event_date,event_time,capacity,status,rejection_reason,created_by) VALUES (?,?,?,?,?,?,?,?,NULL)");
                $s->bind_param("ssssssss", $name, $desc, $venue, $date, $time, $cap_raw, $status, $reason_val);
                $s->execute();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Event created successfully.'];
            }
            header("Location: events.php"); exit();
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    $action = $edit_id ? 'edit' : 'create';
}

$edit_event = null;
if ($action === 'edit' && $event_id) {
    $q = $conn->prepare("SELECT e.*, f.full_name AS faculty_name FROM events e LEFT JOIN faculty f ON f.faculty_id=e.created_by WHERE e.event_id=?");
    $q->bind_param("i", $event_id); $q->execute();
    $edit_event = $q->get_result()->fetch_assoc();
    if (!$edit_event) { header("Location: events.php"); exit(); }
}

$events = [];
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
if ($action === 'list') {
    try {
        $where = "1=1";
        if ($filter === 'pending')  $where = "e.status='Pending'";
        if ($filter === 'approved') $where = "e.status='Approved'";
        if ($filter === 'rejected') $where = "e.status='Rejected'";
        if ($filter === 'upcoming') $where = "e.status='Approved' AND e.event_date >= CURDATE()";
        if ($filter === 'past')     $where = "e.event_date < CURDATE()";

        $sql = "
            SELECT e.*, f.full_name AS faculty_name, f.department,
                   COUNT(r.registration_id) AS reg_count
            FROM events e
            LEFT JOIN faculty f ON f.faculty_id = e.created_by
            LEFT JOIN registrations r ON r.event_id = e.event_id
            WHERE $where
        ";
        if ($search !== '') {
            $esc = $conn->real_escape_string($search);
            $sql .= " AND (e.event_name LIKE '%$esc%' OR e.venue LIKE '%$esc%' OR f.full_name LIKE '%$esc%')";
        }
        $sql .= " GROUP BY e.event_id ORDER BY e.event_date DESC";
        $events = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {}
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Events — Admin | CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-btn { padding:5px 14px; border-radius:20px; font-size:0.78rem; font-weight:700; font-family:var(--font-display); border:1.5px solid var(--border); background:white; color:var(--text-muted); cursor:pointer; transition:var(--transition); text-decoration:none; display:inline-block; }
        .filter-btn:hover,.filter-btn.active { background:var(--primary); color:white; border-color:var(--primary); }
        .filter-btn.pending { border-color:#f9a825; color:#e65100; }
        .filter-btn.pending:hover,.filter-btn.pending.active { background:#e65100; color:white; border-color:#e65100; }
        .form-card { background:white; border:1px solid var(--border); border-radius:var(--radius-md); padding:26px 30px; box-shadow:var(--shadow-sm); }
    </style>
</head>
<body>
<main class="main-content"><div class="content-inner">

<?php if ($flash): ?>
<div class="alert-cems alert-<?= $flash['type'] ?> mb-3">
    <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert-cems alert-danger mb-3">
    <i class="fas fa-exclamation-circle"></i>
    <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
</div>
<?php endif; ?>

<?php if ($action === 'create' || ($action === 'edit' && $edit_event)):
    $ev_form = ($action === 'edit') ? $edit_event : [];
    $is_edit_ev = ($action === 'edit');
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-<?= $is_edit_ev ? 'edit' : 'plus' ?> me-2" style="color:var(--accent);font-size:1rem;"></i>
            <?= $is_edit_ev ? 'Edit Event' : 'Add New Event' ?></h1>
        <p><?= $is_edit_ev ? 'Admin can update any field including status. Changes are reflected immediately.' : 'Create a new event directly as admin.' ?></p>
    </div>
    <a href="events.php" class="btn-outline-custom"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<?php if ($is_edit_ev): ?>
<div class="alert-cems alert-info mb-3" style="font-size:0.82rem;">
    <i class="fas fa-info-circle"></i>
    Created by <strong><?= htmlspecialchars($edit_event['faculty_name'] ?? 'Admin') ?></strong>.
    Changing status to <strong>Approved</strong> will make it visible to students. <strong>Rejected</strong> hides it.
</div>
<?php endif; ?>
<div class="form-card">
    <form method="POST">
        <?php if ($is_edit_ev): ?>
        <input type="hidden" name="edit_id" value="<?= $ev_form['event_id'] ?>">
        <?php endif; ?>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label-custom">Event Name *</label>
                <input type="text" name="event_name" class="form-control-custom" required
                       value="<?= htmlspecialchars($ev_form['event_name'] ?? $_POST['event_name'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label-custom">Description</label>
                <textarea name="description" class="form-control-custom" rows="4"><?= htmlspecialchars($ev_form['description'] ?? $_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label-custom">Event Date *</label>
                <input type="date" name="event_date" class="form-control-custom" required value="<?= $ev_form['event_date'] ?? ($_POST['event_date'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label-custom">Start Time</label>
                <input type="time" name="event_time" class="form-control-custom" value="<?= $ev_form['event_time'] ?? ($_POST['event_time'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label-custom">Capacity</label>
                <input type="number" name="capacity" class="form-control-custom" min="1" value="<?= $ev_form['capacity'] ?? ($_POST['capacity'] ?? '') ?>" placeholder="Blank = unlimited">
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">Venue</label>
                <input type="text" name="venue" class="form-control-custom" value="<?= htmlspecialchars($ev_form['venue'] ?? ($_POST['venue'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">Status <small style="text-transform:none;font-weight:400;">(controls student visibility)</small></label>
                <select name="status" class="form-control-custom" id="statusSelect" onchange="toggleReason()">
                    <?php foreach (['Pending','Approved','Rejected'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($ev_form['status'] ?? $_POST['status'] ?? 'Pending') === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12" id="rejectionReasonBox" style="<?= ($ev_form['status'] ?? 'Pending') !== 'Rejected' ? 'display:none;' : '' ?>">
                <label class="form-label-custom">Rejection Reason</label>
                <textarea name="rejection_reason" class="form-control-custom" rows="2"
                          placeholder="Reason shown to faculty..."><?= htmlspecialchars($ev_form['rejection_reason'] ?? ($_POST['rejection_reason'] ?? '')) ?></textarea>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn-primary-custom"><i class="fas fa-<?= $is_edit_ev ? 'save' : 'plus' ?>"></i> <?= $is_edit_ev ? 'Save Changes' : 'Create Event' ?></button>
            <a href="events.php" class="btn-outline-custom">Cancel</a>
        </div>
    </form>
</div>

<?php elseif (false): // placeholder to close the if above correctly ?>
<?php /* old edit block removed */ ?>

<?php else: ?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-calendar-alt me-2" style="color:var(--accent);font-size:1rem;"></i>All Events</h1>
        <p>Admin has full edit and delete control over all events across all faculty.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="events.php?action=create" class="btn-primary-custom"><i class="fas fa-plus"></i> Add Event</a>
        <a href="approvals.php" class="btn-primary-custom" style="background:#e65100;"><i class="fas fa-clock"></i> Pending Approvals</a>
    </div>
</div>

<div class="d-flex gap-2 flex-wrap mb-3 align-items-center">
    <?php
    $filters = ['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','upcoming'=>'Upcoming','past'=>'Past'];
    foreach ($filters as $k => $lbl): ?>
    <a href="events.php?filter=<?= $k ?>" class="filter-btn <?= $filter===$k?'active':'' ?> <?= $k==='pending'?'pending':'' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
    <form method="GET" class="ms-auto d-flex gap-2">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <input type="text" name="search" class="form-control-custom" placeholder="Search events..."
               value="<?= htmlspecialchars($search) ?>" style="width:220px;">
        <button type="submit" class="btn-primary-custom btn-sm-custom"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="table-wrap">
    <table class="cems-table">
        <thead>
            <tr><th>#</th><th>Event Name</th><th>Faculty</th><th>Date</th><th>Venue</th><th>Reg.</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($events as $i => $ev): ?>
        <tr>
            <td style="color:var(--text-muted);"><?= $i + 1 ?></td>
            <td>
                <strong><?= htmlspecialchars($ev['event_name']) ?></strong>
                <?php if ($ev['rejection_reason']): ?>
                <div style="font-size:0.74rem;color:var(--danger);margin-top:2px;"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars(mb_strimwidth($ev['rejection_reason'], 0, 60, '…')) ?></div>
                <?php endif; ?>
            </td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= htmlspecialchars($ev['faculty_name'] ?? '—') ?></td>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($ev['event_date'])) ?></td>
            <td style="color:var(--text-muted);"><?= htmlspecialchars($ev['venue'] ?: '—') ?></td>
            <td><strong><?= $ev['reg_count'] ?></strong></td>
            <td><span class="badge-cems badge-<?= strtolower($ev['status']) ?>"><?= $ev['status'] ?></span></td>
            <td>
                <div class="d-flex gap-1">
                    <a href="events.php?action=edit&id=<?= $ev['event_id'] ?>" class="btn-outline-custom btn-sm-custom"><i class="fas fa-edit"></i></a>
                    <button class="btn-danger-custom btn-sm-custom"
                            onclick="confirmDelete(<?= $ev['event_id'] ?>, '<?= addslashes(htmlspecialchars($ev['event_name'])) ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($events)): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">No events found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</div></main>

<div class="modal fade" id="delModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:430px;">
        <div class="modal-content" style="border:none;border-radius:var(--radius-md);overflow:hidden;">
            <div class="modal-header">
                <h5 class="modal-title" style="font-family:var(--font-display);font-weight:800;font-size:1rem;">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Event?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:20px 24px;font-size:0.88rem;">
                Delete <strong id="delName"></strong>?<br>
                <span style="color:var(--danger);font-size:0.8rem;">All student registrations and attendance records will also be permanently deleted.</span>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--border);padding:12px 22px;gap:8px;">
                <button class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
                <a id="delBtn" href="#" class="btn-danger-custom" style="padding:8px 18px;"><i class="fas fa-trash"></i> Delete</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id, name) {
    document.getElementById('delName').textContent = name;
    document.getElementById('delBtn').href = 'events.php?action=delete&id=' + id;
    new bootstrap.Modal(document.getElementById('delModal')).show();
}
function toggleReason() {
    const s = document.getElementById('statusSelect').value;
    document.getElementById('rejectionReasonBox').style.display = s === 'Rejected' ? '' : 'none';
}
</script>
</body>
</html>
