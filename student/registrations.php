<?php
session_start();
$active_page = 'registrations';
require_once("../config/db.php");
require_once("../includes/student_sidebar.php");

$student_id = $_SESSION['student_id'];
$flash = $flash_type = '';

// ── Handle Cancellation ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reg_id'])) {
    $reg_id  = (int)$_POST['cancel_reg_id'];
    $evt_id  = (int)$_POST['cancel_event_id'];

    $verify = $conn->prepare("SELECT registration_id FROM registrations WHERE registration_id=? AND student_id=?");
    $verify->bind_param("ii", $reg_id, $student_id); $verify->execute();

    if ($verify->get_result()->num_rows > 0) {
        // Block cancel if attendance already marked
        $att_check = $conn->prepare("SELECT status FROM attendance WHERE student_id=? AND event_id=?");
        $att_check->bind_param("ii", $student_id, $evt_id); $att_check->execute();
        $att_row = $att_check->get_result()->fetch_assoc();
        if ($att_row && in_array($att_row['status'], ['Present', 'Absent'])) {
            $flash = "Cannot cancel — your attendance has already been marked for this event.";
            $flash_type = 'danger';
        } else {
            try {
                $da = $conn->prepare("DELETE FROM attendance WHERE student_id=? AND event_id=?");
                $da->bind_param("ii", $student_id, $evt_id); $da->execute();
            } catch(Exception $e){}
            $dr = $conn->prepare("DELETE FROM registrations WHERE registration_id=?");
            $dr->bind_param("i", $reg_id);
            if ($dr->execute()) { $flash = "Registration cancelled successfully."; $flash_type = 'success'; }
        }
    } else {
        $flash = "Invalid request."; $flash_type = 'danger';
    }
}

// ── Fetch Registrations ────────────────────────────────────
$stmt = $conn->prepare("
    SELECT r.registration_id, r.registration_date, r.event_id,
           e.event_name, e.event_date, e.event_time, e.venue,
           COALESCE(a.status,'Pending') as att_status
    FROM registrations r
    JOIN events e ON r.event_id = e.event_id
    LEFT JOIN attendance a ON a.student_id = r.student_id AND a.event_id = r.event_id
    WHERE r.student_id = ?
    ORDER BY e.event_date DESC
");
$stmt->bind_param("i", $student_id); $stmt->execute();
$all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$upcoming = array_values(array_filter($all, fn($r) => strtotime($r['event_date']) >= strtotime('today')));
$past     = array_values(array_filter($all, fn($r) => strtotime($r['event_date']) <  strtotime('today')));

$present_count = count(array_filter($all, fn($r) => $r['att_status'] === 'Present'));
$absent_count  = count(array_filter($all, fn($r) => $r['att_status'] === 'Absent'));
$pending_count = count(array_filter($all, fn($r) => $r['att_status'] === 'Pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Registrations — CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .toast-wrap { position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; }
        .modal-header { background: var(--primary); color: white; border-radius: 12px 12px 0 0; }
        .modal-header .btn-close { filter: invert(1); opacity: 0.8; }
    </style>
</head>
<body>
<?php include '../includes/student_sidebar.php'; ?>

<?php if ($flash): ?>
<div class="toast-wrap">
    <div class="toast show align-items-center border-0 text-white bg-<?= $flash_type ?>" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold">
                <i class="fas fa-<?= $flash_type === 'success' ? 'check-circle' : 'times-circle' ?> me-2"></i>
                <?= htmlspecialchars($flash) ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<main class="main-content">
<div class="content-inner">
    <div class="page-header">
        <div>
            <h1>My Registrations</h1>
            <p>Track all event registrations and attendance status</p>
        </div>
        <a href="events.php" class="btn-primary-custom">
            <i class="fas fa-plus"></i> Find Events
        </a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-list-check"></i></div>
                <div class="stat-number"><?= count($all) ?></div>
                <div class="stat-label">Total Registered</div>
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

    <!-- Upcoming -->
    <div class="table-wrap mb-4">
        <div class="card-header-custom">
            <i class="fas fa-rocket"></i> Upcoming Registrations
            <span class="badge-cems badge-open ms-auto"><?= count($upcoming) ?></span>
        </div>
        <?php if (empty($upcoming)): ?>
            <div class="empty-state" style="border:none;">
                <i class="fas fa-calendar-check"></i>
                <h5>No upcoming registrations</h5>
                <p><a href="events.php">Browse events to register →</a></p>
            </div>
        <?php else: ?>
        <table class="cems-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Event Name</th>
                    <th>Date & Time</th>
                    <th>Venue</th>
                    <th>Registered On</th>
                    <th>Attendance</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcoming as $i => $r):
                    $s = $r['att_status'];
                    $bc = $s === 'Present' ? 'badge-present' : ($s === 'Absent' ? 'badge-absent' : 'badge-pending');
                    $ic = $s === 'Present' ? 'check' : ($s === 'Absent' ? 'times' : 'clock');
                ?>
                <tr>
                    <td style="color:var(--text-muted);font-weight:600;"><?= $i+1 ?></td>
                    <td style="font-weight:600;color:var(--primary);"><?= htmlspecialchars($r['event_name']) ?></td>
                    <td>
                        <?= date('d M Y', strtotime($r['event_date'])) ?>
                        <?php if ($r['event_time']): ?><br><small class="text-muted"><?= htmlspecialchars($r['event_time']) ?></small><?php endif; ?>
                    </td>
                    <td style="color:var(--text-muted);"><?= htmlspecialchars($r['venue'] ?: '—') ?></td>
                    <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($r['registration_date'])) ?></td>
                    <td><span class="badge-cems <?= $bc ?>"><i class="fas fa-<?= $ic ?> me-1"></i><?= $s ?></span></td>
                    <td>
                        <?php if ($s === 'Pending'): ?>
                        <button class="btn-danger-custom" data-bs-toggle="modal" data-bs-target="#cancelModal"
                            data-reg-id="<?= $r['registration_id'] ?>"
                            data-event-id="<?= $r['event_id'] ?>"
                            data-event-name="<?= htmlspecialchars($r['event_name']) ?>">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <?php else: ?>
                        <span style="font-size:0.78rem;color:var(--text-muted);font-style:italic;">
                            <i class="fas fa-lock me-1"></i>Attendance marked
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Past -->
    <div class="table-wrap">
        <div class="card-header-custom">
            <i class="fas fa-history"></i> Past Registrations
            <span class="badge-cems badge-pending ms-auto"><?= count($past) ?></span>
        </div>
        <?php if (empty($past)): ?>
            <div class="empty-state" style="border:none;">
                <i class="fas fa-inbox"></i>
                <h5>No past registrations yet</h5>
            </div>
        <?php else: ?>
        <table class="cems-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Event Name</th>
                    <th>Date</th>
                    <th>Venue</th>
                    <th>Registered On</th>
                    <th>Attendance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($past as $i => $r):
                    $s = $r['att_status'];
                    $bc = $s === 'Present' ? 'badge-present' : ($s === 'Absent' ? 'badge-absent' : 'badge-pending');
                    $ic = $s === 'Present' ? 'check' : ($s === 'Absent' ? 'times' : 'clock');
                ?>
                <tr>
                    <td style="color:var(--text-muted);font-weight:600;"><?= $i+1 ?></td>
                    <td style="font-weight:600;color:var(--primary);"><?= htmlspecialchars($r['event_name']) ?></td>
                    <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($r['event_date'])) ?></td>
                    <td style="color:var(--text-muted);"><?= htmlspecialchars($r['venue'] ?: '—') ?></td>
                    <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($r['registration_date'])) ?></td>
                    <td><span class="badge-cems <?= $bc ?>"><i class="fas fa-<?= $ic ?> me-1"></i><?= $s ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</div><!-- /content-inner -->
</main>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;overflow:hidden;">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Cancel Registration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-1" style="color:var(--text-muted);">You are about to cancel registration for:</p>
                <p class="fw-bold fs-5" id="modal-event-name" style="color:var(--primary);"></p>
                <div class="alert-cems alert-warning" style="margin:0;">
                    <i class="fas fa-info-circle"></i>
                    This cannot be undone. Your attendance record will also be removed.
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--border);">
                <button type="button" class="btn-outline-custom" data-bs-dismiss="modal">Keep It</button>
                <form method="POST">
                    <input type="hidden" name="cancel_reg_id"    id="modal-reg-id">
                    <input type="hidden" name="cancel_event_id"  id="modal-event-id">
                    <button type="submit" class="btn-danger-custom" style="padding:10px 22px;">
                        <i class="fas fa-times"></i> Yes, Cancel
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('cancelModal').addEventListener('show.bs.modal', e => {
        const b = e.relatedTarget;
        document.getElementById('modal-event-name').textContent = b.dataset.eventName;
        document.getElementById('modal-reg-id').value    = b.dataset.regId;
        document.getElementById('modal-event-id').value  = b.dataset.eventId;
    });
    setTimeout(() => {
        document.querySelectorAll('.toast').forEach(t => bootstrap.Toast.getOrCreateInstance(t).hide());
    }, 4000);
</script>
</body>
</html>