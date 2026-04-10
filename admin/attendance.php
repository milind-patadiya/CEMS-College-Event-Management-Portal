<?php
session_start();
$active_page = 'attendance';
require_once('../config/db.php');
require_once('../includes/admin_sidebar.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_att'])) {
    $att_id    = (int)($_POST['att_id']  ?? 0);
    $new_status = in_array($_POST['att_status'] ?? '', ['Present','Absent','Pending'])
                  ? $_POST['att_status'] : 'Pending';
    try {
        $u = $conn->prepare("UPDATE attendance SET status=?, marked_at=NOW() WHERE attendance_id=?");
        $u->bind_param("si", $new_status, $att_id); $u->execute();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Attendance record updated.'];
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Update failed.'];
    }
    $back = $_SERVER['HTTP_REFERER'] ?? 'attendance.php';
    header("Location: $back"); exit();
}

$event_filter = (int)($_GET['event_id'] ?? 0);
$events_list  = $conn->query("SELECT event_id, event_name, event_date FROM events ORDER BY event_date DESC")->fetch_all(MYSQLI_ASSOC);

$attendance = [];
if ($event_filter) {
    $q = $conn->prepare("
        SELECT a.attendance_id, a.status AS att_status, a.marked_at,
               s.full_name AS student_name, s.enrollment_no,
               e.event_name, e.event_date
        FROM attendance a
        JOIN students s ON s.student_id = a.student_id
        JOIN events   e ON e.event_id   = a.event_id
        WHERE a.event_id = ?
        ORDER BY s.full_name ASC
    ");
    $q->bind_param("i", $event_filter); $q->execute();
    $attendance = $q->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $attendance = $conn->query("
        SELECT a.attendance_id, a.status AS att_status, a.marked_at,
               s.full_name AS student_name, s.enrollment_no,
               e.event_name, e.event_date
        FROM attendance a
        JOIN students s ON s.student_id = a.student_id
        JOIN events   e ON e.event_id   = a.event_id
        ORDER BY a.marked_at DESC LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);
}

$present = count(array_filter($attendance, fn($r) => $r['att_status'] === 'Present'));
$absent  = count(array_filter($attendance, fn($r) => $r['att_status'] === 'Absent'));
$pending = count(array_filter($attendance, fn($r) => $r['att_status'] === 'Pending'));

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Attendance — Admin | CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<main class="main-content"><div class="content-inner">

<?php if ($flash): ?>
<div class="alert-cems alert-<?= $flash['type'] ?> mb-3">
    <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-chart-bar me-2" style="color:var(--accent);font-size:1rem;"></i>Attendance</h1>
        <p>View all attendance records. Override any record — changes are immediately visible to students.</p>
    </div>
</div>

<div class="table-wrap mb-4" style="padding:14px 18px;">
    <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
        <div>
            <label class="form-label-custom">Filter by Event</label>
            <select name="event_id" class="form-control-custom" onchange="this.form.submit()" style="min-width:260px;">
                <option value="">— All Events (last 100) —</option>
                <?php foreach ($events_list as $ev): ?>
                <option value="<?= $ev['event_id'] ?>" <?= $event_filter == $ev['event_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ev['event_name']) ?> — <?= date('d M Y', strtotime($ev['event_date'])) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($event_filter): ?>
        <a href="attendance.php" class="btn-outline-custom"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($event_filter): ?>
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
<?php endif; ?>

<div class="table-wrap">
    <div class="card-header-custom">
        <i class="fas fa-clipboard-check"></i>
        <?= $event_filter ? 'Attendance Sheet' : 'Recent Attendance (last 100 records)' ?>
        <span style="margin-left:6px;font-size:0.75rem;font-weight:400;color:var(--text-muted);"><?= count($attendance) ?> records</span>
    </div>
    <table class="cems-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Enrollment</th>
                <?php if (!$event_filter): ?><th>Event</th><th>Event Date</th><?php else: ?><th>Event Date</th><?php endif; ?>
                <th>Status</th>
                <th>Marked At</th>
                <th>Override</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($attendance as $i => $a): ?>
        <?php $bc = $a['att_status']==='Present' ? 'badge-present' : ($a['att_status']==='Absent' ? 'badge-absent' : 'badge-pending'); ?>
        <tr>
            <td style="color:var(--text-muted);"><?= $i + 1 ?></td>
            <td><strong><?= htmlspecialchars($a['student_name']) ?></strong></td>
            <td style="color:var(--text-muted);"><?= htmlspecialchars($a['enrollment_no']) ?></td>
            <?php if (!$event_filter): ?>
            <td style="color:var(--primary);font-size:0.82rem;"><?= htmlspecialchars($a['event_name']) ?></td>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($a['event_date'])) ?></td>
            <?php else: ?>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($a['event_date'])) ?></td>
            <?php endif; ?>
            <td><span class="badge-cems <?= $bc ?>"><?= $a['att_status'] ?></span></td>
            <td style="color:var(--text-muted);font-size:0.78rem;"><?= $a['marked_at'] ? date('d M Y, h:i A', strtotime($a['marked_at'])) : '—' ?></td>
            <td>
                <form method="POST" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="att_id" value="<?= $a['attendance_id'] ?>">
                    <input type="hidden" name="update_att" value="1">
                    <select name="att_status" class="form-control-custom" style="padding:4px 8px;font-size:0.78rem;width:105px;">
                        <?php foreach (['Present','Absent','Pending'] as $st): ?>
                        <option value="<?= $st ?>" <?= $a['att_status']===$st?'selected':'' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-primary-custom btn-sm-custom" title="Update"><i class="fas fa-check"></i></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($attendance)): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">No attendance records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</div></main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
