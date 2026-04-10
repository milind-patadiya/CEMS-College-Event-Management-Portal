<?php
session_start();
$active_page = 'registrations';
require_once('../config/db.php');
require_once('../includes/admin_sidebar.php');

// ── Admin: Remove a registration ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reg_id'])) {
    $reg_id = (int)$_POST['delete_reg_id'];
    $evt_id = (int)$_POST['delete_event_id'];
    $stu_id = (int)$_POST['delete_student_id'];
    if ($reg_id) {
        try {
            $da = $conn->prepare("DELETE FROM attendance WHERE student_id=? AND event_id=?");
            $da->bind_param("ii", $stu_id, $evt_id); $da->execute();
        } catch (Exception $e) {}
        $dr = $conn->prepare("DELETE FROM registrations WHERE registration_id=?");
        $dr->bind_param("i", $reg_id); $dr->execute();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Registration removed successfully.'];
    }
    $qs = http_build_query(['event_id' => $_POST['filt_event'] ?? '', 'search' => $_POST['filt_search'] ?? '']);
    header("Location: registrations.php" . ($qs ? "?$qs" : '')); exit();
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$event_filter = (int)($_GET['event_id'] ?? 0);
$search       = trim($_GET['search'] ?? '');

$events_list = $conn->query("SELECT event_id, event_name, event_date FROM events ORDER BY event_date DESC")->fetch_all(MYSQLI_ASSOC);

$where  = ["1=1"];
$params = [];
$types  = "";

if ($event_filter) {
    $where[] = "r.event_id = ?";
    $params[] = $event_filter; $types .= "i";
}
if ($search !== '') {
    $where[] = "(s.full_name LIKE ? OR s.enrollment_no LIKE ? OR e.event_name LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

$sql = "
    SELECT r.registration_id, r.registration_date,
           s.student_id, s.full_name AS student_name, s.enrollment_no, s.email,
           e.event_id, e.event_name, e.event_date, e.venue, e.status AS event_status,
           COALESCE(a.status, 'Pending') AS att_status
    FROM registrations r
    JOIN students s ON s.student_id = r.student_id
    JOIN events   e ON e.event_id   = r.event_id
    LEFT JOIN attendance a ON a.student_id = r.student_id AND a.event_id = r.event_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.registration_date DESC
";

$registrations = [];
try {
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params); $stmt->execute();
        $registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $registrations = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {}

$total_regs    = $conn->query("SELECT COUNT(*) c FROM registrations")->fetch_assoc()['c'];
$present_count = $conn->query("SELECT COUNT(*) c FROM attendance WHERE status='Present'")->fetch_assoc()['c'];
$absent_count  = $conn->query("SELECT COUNT(*) c FROM attendance WHERE status='Absent'")->fetch_assoc()['c'];
$pending_count = $conn->query("SELECT COUNT(*) c FROM attendance WHERE status='Pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Registrations — Admin | CEMS</title>
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
        <h1><i class="fas fa-clipboard-list me-2" style="color:var(--accent);font-size:1rem;"></i>Registrations</h1>
        <p>All event registrations across the entire system</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-number"><?= $total_regs ?></div>
            <div class="stat-label">Total Registrations</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
            <div class="stat-number"><?= $present_count ?></div>
            <div class="stat-label">Present</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-user-times"></i></div>
            <div class="stat-number"><?= $absent_count ?></div>
            <div class="stat-label">Absent</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-number"><?= $pending_count ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
</div>

<div class="table-wrap mb-3" style="padding:14px 18px;">
    <form method="GET" class="d-flex gap-3 flex-wrap align-items-end">
        <div>
            <label class="form-label-custom">Filter by Event</label>
            <select name="event_id" class="form-control-custom" onchange="this.form.submit()" style="min-width:240px;">
                <option value="">— All Events —</option>
                <?php foreach ($events_list as $ev): ?>
                <option value="<?= $ev['event_id'] ?>" <?= $event_filter == $ev['event_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ev['event_name']) ?> (<?= date('d M Y', strtotime($ev['event_date'])) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label-custom">Search</label>
            <input type="text" name="search" class="form-control-custom" style="min-width:220px;"
                   placeholder="Student name, enrollment no, event..."
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn-primary-custom"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($event_filter || $search): ?>
            <a href="registrations.php" class="btn-outline-custom"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-wrap">
    <div class="card-header-custom">
        <i class="fas fa-list"></i> Showing <?= count($registrations) ?> record<?= count($registrations) !== 1 ? 's' : '' ?>
    </div>
    <table class="cems-table">
        <thead>
            <tr><th>#</th><th>Student</th><th>Enrollment No.</th><th>Event</th><th>Event Date</th><th>Registered On</th><th>Attendance</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($registrations as $i => $r): ?>
        <?php
            $s  = $r['att_status'];
            $bc = $s === 'Present' ? 'badge-present' : ($s === 'Absent' ? 'badge-absent' : 'badge-pending');
        ?>
        <tr>
            <td style="color:var(--text-muted);"><?= $i + 1 ?></td>
            <td><strong><?= htmlspecialchars($r['student_name']) ?></strong></td>
            <td style="color:var(--text-muted);"><?= htmlspecialchars($r['enrollment_no']) ?></td>
            <td style="color:var(--primary);"><?= htmlspecialchars($r['event_name']) ?></td>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($r['event_date'])) ?></td>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($r['registration_date'])) ?></td>
            <td><span class="badge-cems <?= $bc ?>"><?= $s ?></span></td>
            <td>
                <button class="btn-danger-custom btn-sm-custom"
                    onclick="confirmDelReg(<?= $r['registration_id'] ?>, <?= $r['event_id'] ?>, <?= $r['student_id'] ?>, '<?= addslashes(htmlspecialchars($r['student_name'])) ?>', '<?= addslashes(htmlspecialchars($r['event_name'])) ?>')">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($registrations)): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">No registrations found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</div></main>

<!-- Delete Registration Modal -->
<div class="modal fade" id="delRegModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:430px;">
        <div class="modal-content" style="border:none;border-radius:var(--radius-md);overflow:hidden;">
            <div class="modal-header">
                <h5 class="modal-title" style="font-family:var(--font-display);font-weight:800;font-size:1rem;">
                    <i class="fas fa-exclamation-triangle me-2"></i>Remove Registration?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:20px 24px;font-size:0.88rem;">
                Remove <strong id="delRegStudent"></strong> from <strong id="delRegEvent"></strong>?<br>
                <span style="color:var(--danger);font-size:0.8rem;">Their attendance record for this event will also be deleted.</span>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--border);padding:12px 22px;gap:8px;">
                <button class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="delRegForm">
                    <input type="hidden" name="delete_reg_id"     id="del_reg_id">
                    <input type="hidden" name="delete_event_id"   id="del_event_id">
                    <input type="hidden" name="delete_student_id" id="del_student_id">
                    <input type="hidden" name="filt_event"  value="<?= $event_filter ?>">
                    <input type="hidden" name="filt_search" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-danger-custom" style="padding:8px 18px;">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelReg(regId, evtId, stuId, stuName, evtName) {
    document.getElementById('del_reg_id').value     = regId;
    document.getElementById('del_event_id').value   = evtId;
    document.getElementById('del_student_id').value = stuId;
    document.getElementById('delRegStudent').textContent = stuName;
    document.getElementById('delRegEvent').textContent   = evtName;
    new bootstrap.Modal(document.getElementById('delRegModal')).show();
}
setTimeout(() => {
    document.querySelectorAll('.toast').forEach(t => bootstrap.Toast.getOrCreateInstance(t).hide());
}, 4000);
</script>
</body>
</html>
