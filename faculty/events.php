<?php
session_start();
$active_page = 'events';
require_once('../config/db.php');
require_once('../includes/faculty_sidebar.php');
require_once('../includes/mailer.php');

$faculty_id = $_SESSION['faculty_id'];
$action     = $_GET['action'] ?? 'list';
$event_id   = (int)($_GET['id'] ?? 0);
$errors     = [];

// ── DELETE ─────────────────────────────────────────────────
if ($action === 'delete' && $event_id) {
    try {
        $c = $conn->prepare("SELECT event_id FROM events WHERE event_id=? AND created_by=?");
        $c->bind_param("ii", $event_id, $faculty_id); $c->execute();
        if ($c->get_result()->num_rows) {
            $d = $conn->prepare("DELETE FROM events WHERE event_id=?");
            $d->bind_param("i", $event_id); $d->execute();
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Event deleted successfully.'];
        }
    } catch(Exception $e) {
        $_SESSION['flash'] = ['type'=>'danger','msg'=>'Delete failed. Please try again.'];
    }
    header("Location: events.php"); exit();
}

// ── SAVE (CREATE / EDIT) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['event_name']  ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $venue   = trim($_POST['venue']       ?? '');
    $date    = $_POST['event_date']       ?? '';
    $time    = $_POST['event_time']       ?? '';
    $cap_val = (isset($_POST['capacity']) && $_POST['capacity'] !== '') ? (int)$_POST['capacity'] : null;
    $edit_id = (int)($_POST['edit_id']    ?? 0);

    if (!$name) $errors[] = 'Event name is required.';
    if (!$date) $errors[] = 'Event date is required.';

    if (empty($errors)) {
        try {
            if ($edit_id) {
                // UPDATE — status is preserved as-is
                $s = $conn->prepare("UPDATE events SET event_name=?,description=?,venue=?,event_date=?,event_time=?,capacity=? WHERE event_id=? AND created_by=?");
                $s->bind_param("ssssssii", $name,$desc,$venue,$date,$time,$cap_val,$edit_id,$faculty_id);
                $s->execute();
                $_SESSION['flash'] = ['type'=>'success','msg'=>'Event updated successfully. Changes are live in the Student portal.'];
            } else {
                // INSERT — status = Approved directly, visible to students immediately
                $s = $conn->prepare("INSERT INTO events (event_name,description,venue,event_date,event_time,capacity,status,created_by) VALUES (?,?,?,?,?,?,'Approved',?)");
                $s->bind_param("ssssssi", $name,$desc,$venue,$date,$time,$cap_val,$faculty_id);
                $s->execute();
                $_SESSION['flash'] = ['type'=>'success','msg'=>'Event created successfully! It is now live and visible to all students.'];
                // Send email notification to all students (non-blocking)
                $new_event_id = $conn->insert_id;
                notifyStudentsNewEvent($conn, $new_event_id);
            }
            header("Location: events.php"); exit();
        } catch(Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    $action = $edit_id ? 'edit' : 'create';
}

// ── LOAD FOR EDIT ──────────────────────────────────────────
$edit_ev = null;
if ($action === 'edit' && $event_id) {
    try {
        $s = $conn->prepare("SELECT * FROM events WHERE event_id=? AND created_by=?");
        $s->bind_param("ii", $event_id, $faculty_id); $s->execute();
        $edit_ev = $s->get_result()->fetch_assoc();
        if (!$edit_ev) { header("Location: events.php"); exit(); }
    } catch(Exception $e) { header("Location: events.php"); exit(); }
}

// ── LIST ───────────────────────────────────────────────────
$events = [];
$filter = $_GET['filter'] ?? 'all';
if ($action === 'list') {
    try {
        $where = "WHERE e.created_by=?";
        if ($filter === 'upcoming') $where .= " AND e.event_date >= CURDATE()";
        if ($filter === 'past')     $where .= " AND e.event_date < CURDATE()";

        $s = $conn->prepare("
            SELECT e.*, COUNT(r.registration_id) AS reg_count
            FROM events e
            LEFT JOIN registrations r ON r.event_id = e.event_id
            $where
            GROUP BY e.event_id
            ORDER BY e.event_date DESC
        ");
        $s->bind_param("i", $faculty_id); $s->execute();
        $events = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch(Exception $e) {}
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $action==='create'?'Create Event':($action==='edit'?'Edit Event':'My Events') ?> — Faculty | CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-pill { display:inline-block; padding:5px 14px; border-radius:20px; font-size:0.76rem; font-weight:700; font-family:var(--font-display); border:1.5px solid var(--border); background:#fff; color:var(--text-muted); text-decoration:none; transition:var(--transition); }
        .filter-pill:hover, .filter-pill.active { background:var(--primary); color:#fff; border-color:var(--primary); }
        .ev-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius-md); overflow:hidden; box-shadow:var(--shadow-sm); transition:var(--transition); height:100%; display:flex; flex-direction:column; }
        .ev-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-md); }
        .ev-card-top { background:linear-gradient(135deg,var(--primary),var(--accent)); color:#fff; padding:16px 18px; }
        .ev-card-top h6 { font-family:var(--font-display); font-weight:800; font-size:0.92rem; margin:0; }
        .ev-card-body { padding:13px 18px; flex:1; }
        .ev-meta { font-size:0.77rem; color:var(--text-muted); display:flex; align-items:center; gap:5px; margin-bottom:5px; }
        .ev-card-foot { display:flex; gap:6px; padding:10px 18px 14px; border-top:1px solid var(--border); margin-top:auto; }
        .form-section { font-family:var(--font-display); font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:0.6px; color:var(--text-muted); padding-bottom:8px; border-bottom:1px solid var(--border); margin-bottom:16px; }
        .live-badge { display:inline-flex; align-items:center; gap:5px; background:#e8f5e9; color:#2e7d32; font-size:0.72rem; font-weight:700; padding:3px 10px; border-radius:20px; font-family:var(--font-display); }
        .live-dot { width:6px; height:6px; border-radius:50%; background:#4caf50; animation:pulse 1.5s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.4;} }
    </style>
</head>
<body>
<main class="main-content">
<div class="content-inner">

<?php if ($flash): ?>
<div class="alert-cems alert-<?= $flash['type'] ?> mb-3">
    <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert-cems alert-danger mb-3">
    <i class="fas fa-exclamation-circle"></i>
    <div><?php foreach($errors as $er): ?><div><?= htmlspecialchars($er) ?></div><?php endforeach; ?></div>
</div>
<?php endif; ?>

<!-- ════════ CREATE / EDIT FORM ════════ -->
<?php if ($action === 'create' || $action === 'edit'):
    $ev      = $edit_ev ?? [];
    $is_edit = ($action === 'edit');
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-<?= $is_edit?'edit':'calendar-plus' ?> me-2" style="color:var(--accent);font-size:1rem;"></i>
            <?= $is_edit ? 'Edit Event' : 'Create New Event' ?></h1>
        <p><?= $is_edit ? 'Update event details. Changes are live immediately.' : 'Fill in the details. The event goes live to students instantly.' ?></p>
    </div>
    <a href="events.php" class="btn-outline-custom"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-md);padding:26px 30px;box-shadow:var(--shadow-sm);">

    <!-- Live notice -->
    <div class="alert-cems alert-success mb-4" style="font-size:0.82rem;">
        <i class="fas fa-bolt"></i>
        <?= $is_edit
            ? 'Any changes you save will be <strong>immediately visible</strong> to all students in the Student portal.'
            : 'This event will be <strong>immediately visible</strong> to all students in the Student portal once submitted.' ?>
    </div>

    <form method="POST">
        <?php if ($is_edit): ?><input type="hidden" name="edit_id" value="<?= $ev['event_id'] ?>"><?php endif; ?>

        <div class="form-section">Basic Information</div>
        <div class="row g-3 mb-4">
            <div class="col-12">
                <label class="form-label-custom">Event Name <span style="color:#ef5350;">*</span></label>
                <input type="text" name="event_name" class="form-control-custom" maxlength="150" required
                       placeholder="e.g. Annual Tech Symposium 2025"
                       value="<?= htmlspecialchars($ev['event_name'] ?? $_POST['event_name'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label-custom">Description</label>
                <textarea name="description" class="form-control-custom" rows="4" maxlength="1000"
                          id="descBox" oninput="document.getElementById('dcount').textContent=this.value.length+'/1000'"
                          placeholder="Describe the event — agenda, activities, who should attend..."><?= htmlspecialchars($ev['description'] ?? $_POST['description'] ?? '') ?></textarea>
                <div style="text-align:right;font-size:0.7rem;color:var(--text-muted);margin-top:3px;">
                    <span id="dcount"><?= strlen($ev['description'] ?? '') ?>/1000</span>
                </div>
            </div>
        </div>

        <div class="form-section">Schedule & Location</div>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label-custom">Event Date <span style="color:#ef5350;">*</span></label>
                <input type="date" name="event_date" class="form-control-custom" required
                       value="<?= htmlspecialchars($ev['event_date'] ?? $_POST['event_date'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label-custom">Start Time</label>
                <input type="time" name="event_time" class="form-control-custom"
                       value="<?= htmlspecialchars($ev['event_time'] ?? $_POST['event_time'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label-custom">Capacity <small style="text-transform:none;font-weight:400;">(blank = unlimited)</small></label>
                <input type="number" name="capacity" class="form-control-custom" min="1"
                       placeholder="e.g. 200"
                       value="<?= htmlspecialchars($ev['capacity'] ?? $_POST['capacity'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label-custom">Venue / Location</label>
                <input type="text" name="venue" class="form-control-custom" maxlength="150"
                       placeholder="e.g. Main Auditorium, Block A"
                       value="<?= htmlspecialchars($ev['venue'] ?? $_POST['venue'] ?? '') ?>">
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn-primary-custom">
                <i class="fas fa-<?= $is_edit?'save':'bolt' ?>"></i>
                <?= $is_edit ? 'Save Changes' : 'Create Event' ?>
            </button>
            <a href="events.php" class="btn-outline-custom">Cancel</a>
        </div>
    </form>
</div>

<!-- ════════ EVENT LIST ════════ -->
<?php else: ?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-calendar-alt me-2" style="color:var(--accent);font-size:1rem;"></i>My Events</h1>
        <p>All events you have created — live in the Student portal instantly.</p>
    </div>
    <a href="events.php?action=create" class="btn-primary-custom">
        <i class="fas fa-plus"></i> Create Event
    </a>
</div>

<!-- Filter pills -->
<div class="d-flex gap-2 flex-wrap mb-4">
    <?php foreach (['all'=>'All Events','upcoming'=>'Upcoming','past'=>'Past'] as $k=>$lbl): ?>
    <a href="events.php?filter=<?= $k ?>" class="filter-pill <?= $filter===$k?'active':'' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
</div>

<?php if (empty($events)): ?>
<div class="empty-state">
    <i class="fas fa-calendar-times"></i>
    <h5>No events found</h5>
    <p>No events match this filter. Create a new event to get started.</p>
</div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($events as $e): ?>
<div class="col-md-6">
  <div class="ev-card">
    <div class="ev-card-top">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <h6><?= htmlspecialchars($e['event_name']) ?></h6>
            <?php if ($e['status'] === 'Approved'): ?>
            <span class="live-badge"><span class="live-dot"></span>Live</span>
            <?php elseif ($e['status'] === 'Pending'): ?>
            <span class="badge-cems badge-pending" style="font-size:0.68rem;">Pending</span>
            <?php else: ?>
            <span class="badge-cems badge-rejected" style="font-size:0.68rem;">Rejected</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="ev-card-body">
        <?php if ($e['status'] === 'Rejected' && !empty($e['rejection_reason'])): ?>
        <div style="background:#ffebee;border-left:3px solid var(--danger);padding:8px 12px;margin-bottom:10px;border-radius:4px;font-size:0.78rem;color:var(--danger);">
            <i class="fas fa-times-circle me-1"></i><strong>Rejected:</strong> <?= htmlspecialchars($e['rejection_reason']) ?>
        </div>
        <?php elseif ($e['description']): ?>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:10px;line-height:1.5;">
            <?= htmlspecialchars(mb_strimwidth($e['description'], 0, 100, '…')) ?>
        </p>
        <?php endif; ?>
        <div class="ev-meta">
            <i class="fas fa-calendar"></i> <?= date('D, d M Y', strtotime($e['event_date'])) ?>
            <?php if ($e['event_time']): ?>&ensp;<i class="fas fa-clock"></i> <?= date('h:i A', strtotime($e['event_time'])) ?><?php endif; ?>
        </div>
        <?php if ($e['venue']): ?>
        <div class="ev-meta"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($e['venue']) ?></div>
        <?php endif; ?>
        <div class="ev-meta">
            <i class="fas fa-users"></i>
            <strong><?= $e['reg_count'] ?></strong>&nbsp;registered
            <?php if ($e['capacity']): ?> / <?= $e['capacity'] ?> capacity<?php endif; ?>
        </div>
    </div>
    <div class="ev-card-foot">
        <a href="events.php?action=edit&id=<?= $e['event_id'] ?>" class="btn-outline-custom btn-sm-custom">
            <i class="fas fa-edit"></i> Edit
        </a>
        <a href="attendance.php?event_id=<?= $e['event_id'] ?>" class="btn-outline-custom btn-sm-custom">
            <i class="fas fa-clipboard-check"></i> Attendance
            <?php if ($e['reg_count'] > 0): ?>
            <span style="background:var(--primary);color:#fff;border-radius:20px;padding:1px 7px;font-size:0.68rem;margin-left:3px;"><?= $e['reg_count'] ?></span>
            <?php endif; ?>
        </a>
        <button class="btn-danger-custom btn-sm-custom ms-auto"
                onclick="doDelete(<?= $e['event_id'] ?>,'<?= addslashes(htmlspecialchars($e['event_name'])) ?>')">
            <i class="fas fa-trash"></i>
        </button>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

</div>
</main>

<!-- Delete Modal -->
<div class="modal fade" id="delModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content" style="border:none;border-radius:var(--radius-md);overflow:hidden;">
            <div class="modal-header">
                <h5 class="modal-title" style="font-family:var(--font-display);font-weight:800;font-size:1rem;">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Event?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:20px 24px;font-size:0.88rem;">
                Permanently delete <strong id="delName"></strong>?<br>
                <span style="color:var(--danger);font-size:0.8rem;">All student registrations and attendance records will also be removed.</span>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--border);padding:12px 22px;gap:8px;">
                <button class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
                <a id="delBtn" href="#" class="btn-danger-custom" style="padding:8px 18px;">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function doDelete(id, name) {
    document.getElementById('delName').textContent = name;
    document.getElementById('delBtn').href = 'events.php?action=delete&id=' + id;
    new bootstrap.Modal(document.getElementById('delModal')).show();
}
</script>
</body>
</html>