<?php
session_start();
$active_page = 'attendance';
require_once("../config/db.php");
if (!isset($_SESSION['faculty_id'])) { header("Location: ../auth/login.php"); exit(); }

$faculty_id = $_SESSION['faculty_id'];
$event_id   = (int)($_GET['event_id'] ?? 0);
$success    = '';
$error      = '';

// ── MARK ATTENDANCE SAVE ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    $post_event_id = (int)($_POST['event_id'] ?? 0);
    // Verify faculty owns this event
    $chk = $conn->prepare("SELECT event_id FROM events WHERE event_id=? AND created_by=?");
    $chk->bind_param("ii", $post_event_id, $faculty_id); $chk->execute();
    if ($chk->get_result()->num_rows) {
        $conn->begin_transaction();
        try {
            foreach ($_POST['attendance'] as $student_id => $status) {
                $student_id = (int)$student_id;
                $status     = in_array($status, ['Present','Absent','Pending']) ? $status : 'Pending';
                $upsert = $conn->prepare("
                    INSERT INTO attendance (student_id, event_id, status)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), marked_at = NOW()
                ");
                $upsert->bind_param("iis", $student_id, $post_event_id, $status);
                $upsert->execute();
            }
            $conn->commit();
            $success = "Attendance saved successfully.";
            $event_id = $post_event_id;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to save attendance. Please try again.";
        }
    } else {
        $error = "You do not have permission to mark attendance for this event.";
    }
}

// ── LOAD FACULTY'S EVENTS FOR DROPDOWN ─────────────────────
$my_events = [];
try {
    $q = $conn->prepare("
        SELECT event_id, event_name, event_date, status
        FROM events
        WHERE created_by = ? AND event_date <= CURDATE() AND status = 'Approved'
        ORDER BY event_date DESC
    ");
    $q->bind_param("i", $faculty_id); $q->execute();
    $my_events = $q->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}

// ── LOAD REGISTRATIONS FOR SELECTED EVENT ─────────────────
$event_info   = null;
$registrations = [];
if ($event_id) {
    try {
        $eq = $conn->prepare("SELECT * FROM events WHERE event_id=? AND created_by=?");
        $eq->bind_param("ii", $event_id, $faculty_id); $eq->execute();
        $event_info = $eq->get_result()->fetch_assoc();

        if ($event_info) {
            $rq = $conn->prepare("
                SELECT s.student_id, s.full_name, s.enrollment_no,
                       COALESCE(a.status, 'Pending') as att_status
                FROM registrations r
                JOIN students s ON s.student_id = r.student_id
                LEFT JOIN attendance a ON a.student_id = r.student_id AND a.event_id = r.event_id
                WHERE r.event_id = ?
                ORDER BY s.full_name ASC
            ");
            $rq->bind_param("i", $event_id); $rq->execute();
            $registrations = $rq->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {}
}

// Counts
$present = count(array_filter($registrations, fn($r) => $r['att_status']==='Present'));
$absent  = count(array_filter($registrations, fn($r) => $r['att_status']==='Absent'));
$pending = count(array_filter($registrations, fn($r) => $r['att_status']==='Pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance — CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .att-row { display:flex; align-items:center; gap:14px; padding:11px 18px; border-bottom:1px solid #f0f0f0; transition:background 0.15s; }
        .att-row:last-child { border-bottom:none; }
        .att-row:hover { background:#fafbff; }
        .att-avatar { width:34px; height:34px; border-radius:50%; background:var(--accent-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.82rem; flex-shrink:0; font-family:var(--font-display); }
        .att-name  { font-weight:600; font-size:0.86rem; }
        .att-enroll{ font-size:0.74rem; color:var(--text-muted); }
        .att-radio-group { display:flex; gap:6px; margin-left:auto; }
        .att-radio-label { padding:5px 12px; border-radius:20px; font-size:0.74rem; font-weight:700; font-family:var(--font-display); border:1.5px solid var(--border); cursor:pointer; transition:var(--transition); color:var(--text-muted); user-select:none; }
        .att-radio-input { display:none; }
        .att-radio-input[value="Present"]:checked + .att-radio-label { background:var(--success-light); color:var(--success); border-color:var(--success); }
        .att-radio-input[value="Absent"]:checked  + .att-radio-label { background:var(--danger-light);  color:var(--danger);  border-color:var(--danger);  }
        .att-radio-input[value="Pending"]:checked + .att-radio-label { background:var(--warning-light); color:var(--warning); border-color:#fb8c00; }
        .quick-btn { padding:5px 12px; border-radius:6px; font-size:0.76rem; font-weight:700; font-family:var(--font-display); border:1.5px solid var(--border); background:white; cursor:pointer; transition:var(--transition); }
        .quick-btn:hover { border-color:var(--primary); color:var(--primary); }
    </style>
</head>
<body>
<?php require_once("../includes/faculty_sidebar.php"); ?>
<main class="main-content">
<div class="content-inner">

    <div class="page-header">
        <div>
            <h1><i class="fas fa-clipboard-check me-2" style="color:var(--accent);font-size:1rem;"></i>Attendance</h1>
            <p>Mark and manage student attendance for your events.</p>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert-cems alert-success mb-3"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert-cems alert-danger mb-3"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Event Selector -->
    <div class="card mb-4">
        <div class="card-header-custom"><i class="fas fa-calendar-alt"></i> Select Event</div>
        <div style="padding:18px 22px;">
            <?php if (empty($my_events)): ?>
            <div class="alert-cems alert-info" style="margin:0;">
                <i class="fas fa-info-circle"></i>
                No approved past events found. Attendance can only be marked for events that have already taken place.
            </div>
            <?php else: ?>
            <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
                <div style="flex:1;min-width:240px;">
                    <label class="form-label-custom">Choose an event</label>
                    <select name="event_id" class="form-control-custom" onchange="this.form.submit()">
                        <option value="">— Select Event —</option>
                        <?php foreach ($my_events as $me): ?>
                        <option value="<?= $me['event_id'] ?>" <?= $event_id==$me['event_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($me['event_name']) ?> (<?= date('d M Y', strtotime($me['event_date'])) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript><button type="submit" class="btn-primary-custom">Load</button></noscript>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Attendance Sheet -->
    <?php if ($event_info && !empty($registrations)): ?>
    <!-- Summary -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="stat-card text-center" style="padding:14px;">
                <div class="stat-number" style="color:var(--success);"><?= $present ?></div>
                <div class="stat-label">Present</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card text-center" style="padding:14px;">
                <div class="stat-number" style="color:var(--danger);"><?= $absent ?></div>
                <div class="stat-label">Absent</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card text-center" style="padding:14px;">
                <div class="stat-number" style="color:var(--warning);"><?= $pending ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="event_id" value="<?= $event_id ?>">
        <div class="table-wrap mb-3">
            <div class="card-header-custom" style="justify-content:space-between;">
                <span><i class="fas fa-users"></i> <?= htmlspecialchars($event_info['event_name']) ?> — <?= count($registrations) ?> students</span>
                <div class="d-flex gap-2">
                    <button type="button" class="quick-btn" onclick="markAll('Present')">All Present</button>
                    <button type="button" class="quick-btn" onclick="markAll('Absent')">All Absent</button>
                </div>
            </div>
            <?php foreach ($registrations as $reg): ?>
            <div class="att-row">
                <div class="att-avatar"><?= strtoupper(substr($reg['full_name'],0,1)) ?></div>
                <div>
                    <div class="att-name"><?= htmlspecialchars($reg['full_name']) ?></div>
                    <div class="att-enroll"><?= htmlspecialchars($reg['enrollment_no']) ?></div>
                </div>
                <div class="att-radio-group">
                    <?php foreach (['Present','Absent','Pending'] as $status): ?>
                    <input type="radio" class="att-radio-input"
                           name="attendance[<?= $reg['student_id'] ?>]"
                           id="att_<?= $reg['student_id'] ?>_<?= $status ?>"
                           value="<?= $status ?>"
                           <?= $reg['att_status']===$status ? 'checked' : '' ?>>
                    <label class="att-radio-label" for="att_<?= $reg['student_id'] ?>_<?= $status ?>"><?= $status ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn-primary-custom">
            <i class="fas fa-save"></i> Save Attendance
        </button>
    </form>

    <?php elseif ($event_info && empty($registrations)): ?>
    <div class="empty-state">
        <i class="fas fa-user-slash"></i>
        <h5>No students registered</h5>
        <p>No students have registered for this event yet.</p>
    </div>
    <?php endif; ?>

</div><!-- /content-inner -->
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function markAll(status) {
    document.querySelectorAll('.att-radio-input[value="' + status + '"]').forEach(r => r.checked = true);
}
</script>
</body>
</html>