<?php
session_start();
$active_page = 'certificates';
require_once('../config/db.php');
require_once('../includes/admin_sidebar.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = $_POST['action_type'] ?? '';

    if ($action_type === 'issue') {
        $event_id   = (int)($_POST['event_id'] ?? 0);
        $cert_url   = trim($_POST['certificate_url'] ?? '');
        $issued_all = isset($_POST['issue_all']);

        if (!$event_id) {
            $errors[] = 'Please select an event.';
        } else {
            $q = $conn->prepare("
                SELECT a.student_id FROM attendance a
                WHERE a.event_id = ? AND a.status = 'Present'
                AND a.student_id NOT IN (SELECT student_id FROM certificates WHERE event_id = ?)
            ");
            $q->bind_param("ii", $event_id, $event_id); $q->execute();
            $eligible = $q->get_result()->fetch_all(MYSQLI_ASSOC);

            if (empty($eligible)) {
                $_SESSION['flash'] = ['type' => 'info', 'msg' => 'No new certificates to issue — all present students already have certificates for this event.'];
            } else {
                $ins = $conn->prepare("INSERT IGNORE INTO certificates (event_id, student_id, certificate_url) VALUES (?,?,?)");
                $issued = 0;
                foreach ($eligible as $row) {
                    $ins->bind_param("iis", $event_id, $row['student_id'], $cert_url);
                    if ($ins->execute()) $issued++;
                }
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Issued $issued certificate(s) to present students. Visible in student Certificates page immediately."];
            }
        }
        header("Location: certificates.php"); exit();
    }

    if ($action_type === 'delete') {
        $cert_id = (int)($_POST['cert_id'] ?? 0);
        if ($cert_id) {
            $d = $conn->prepare("DELETE FROM certificates WHERE certificate_id=?");
            $d->bind_param("i", $cert_id); $d->execute();
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Certificate revoked.'];
        }
        header("Location: certificates.php"); exit();
    }
}

$event_filter = (int)($_GET['event_id'] ?? 0);
$events_with_attendance = $conn->query("
    SELECT DISTINCT e.event_id, e.event_name, e.event_date
    FROM events e
    JOIN attendance a ON a.event_id = e.event_id AND a.status = 'Present'
    ORDER BY e.event_date DESC
")->fetch_all(MYSQLI_ASSOC);

$sql = "
    SELECT c.certificate_id, c.certificate_url, c.issued_date,
           s.full_name AS student_name, s.enrollment_no,
           e.event_name, e.event_date
    FROM certificates c
    JOIN students s ON s.student_id = c.student_id
    JOIN events   e ON e.event_id   = c.event_id
";
if ($event_filter) $sql .= " WHERE c.event_id = $event_filter";
$sql .= " ORDER BY c.issued_date DESC";

$certificates = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$stats_total    = $conn->query("SELECT COUNT(*) c FROM certificates")->fetch_assoc()['c'];
$eligible_count = $conn->query("
    SELECT COUNT(*) c FROM attendance a
    WHERE a.status = 'Present'
    AND NOT EXISTS (SELECT 1 FROM certificates c WHERE c.student_id=a.student_id AND c.event_id=a.event_id)
")->fetch_assoc()['c'];

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Certificates — Admin | CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .issue-card { background:linear-gradient(135deg,#fff8e1,#fffde7); border:1px solid #ffe082; border-radius:var(--radius-md); padding:22px 26px; margin-bottom:24px; }
    </style>
</head>
<body>
<main class="main-content"><div class="content-inner">

<?php if ($flash): ?>
<div class="alert-cems alert-<?= $flash['type'] ?> mb-3">
    <i class="fas fa-<?= $flash['type']==='success'?'check-circle':($flash['type']==='info'?'info-circle':'exclamation-circle') ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert-cems alert-danger mb-3">
    <i class="fas fa-exclamation-circle"></i>
    <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-award me-2" style="color:var(--accent);font-size:1rem;"></i>Certificates</h1>
        <p>Issue, view and revoke participation certificates. Issued certificates are immediately accessible to students.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-award"></i></div>
            <div class="stat-number"><?= $stats_total ?></div>
            <div class="stat-label">Issued</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-number"><?= $eligible_count ?></div>
            <div class="stat-label">Eligible, Not Yet Issued</div>
        </div>
    </div>
</div>

<div class="issue-card">
    <h5 style="font-family:var(--font-display);font-weight:800;color:#e65100;margin-bottom:4px;">
        <i class="fas fa-stamp me-2"></i>Issue Certificates
    </h5>
    <p style="color:var(--text-muted);font-size:0.84rem;margin-bottom:16px;">
        Select an event — certificates will be issued to all students with status <strong>Present</strong> who don't already have one.
    </p>
    <form method="POST">
        <input type="hidden" name="action_type" value="issue">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label-custom">Event *</label>
                <select name="event_id" class="form-control-custom" required>
                    <option value="">— Select Event —</option>
                    <?php foreach ($events_with_attendance as $ev): ?>
                    <option value="<?= $ev['event_id'] ?>"><?= htmlspecialchars($ev['event_name']) ?> — <?= date('d M Y', strtotime($ev['event_date'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">Certificate URL <small style="text-transform:none;font-weight:400;">(optional — download link)</small></label>
                <input type="text" name="certificate_url" class="form-control-custom" placeholder="https://... or leave blank">
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn-primary-custom" style="background:#e65100;">
                <i class="fas fa-stamp"></i> Issue Certificates to All Present Students
            </button>
        </div>
    </form>
</div>

<div class="table-wrap mb-3" style="padding:14px 18px;">
    <form method="GET" class="d-flex gap-3 align-items-end">
        <div>
            <label class="form-label-custom">Filter by Event</label>
            <select name="event_id" class="form-control-custom" onchange="this.form.submit()" style="min-width:260px;">
                <option value="">— All Issued Certificates —</option>
                <?php foreach ($events_with_attendance as $ev): ?>
                <option value="<?= $ev['event_id'] ?>" <?= $event_filter == $ev['event_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ev['event_name']) ?> — <?= date('d M Y', strtotime($ev['event_date'])) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($event_filter): ?>
        <a href="certificates.php" class="btn-outline-custom"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-wrap">
    <div class="card-header-custom">
        <i class="fas fa-award"></i> Issued Certificates (<?= count($certificates) ?>)
    </div>
    <table class="cems-table">
        <thead>
            <tr><th>#</th><th>Student</th><th>Enrollment</th><th>Event</th><th>Event Date</th><th>Issued On</th><th>Certificate</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($certificates as $i => $c): ?>
        <tr>
            <td style="color:var(--text-muted);"><?= $i + 1 ?></td>
            <td><strong><?= htmlspecialchars($c['student_name']) ?></strong></td>
            <td style="color:var(--text-muted);"><?= htmlspecialchars($c['enrollment_no']) ?></td>
            <td style="color:var(--primary);"><?= htmlspecialchars($c['event_name']) ?></td>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($c['event_date'])) ?></td>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($c['issued_date'])) ?></td>
            <td>
                <?php if ($c['certificate_url']): ?>
                <a href="<?= htmlspecialchars($c['certificate_url']) ?>" target="_blank" class="btn-outline-custom btn-sm-custom">
                    <i class="fas fa-external-link-alt"></i> View
                </a>
                <?php else: ?>
                <span style="color:var(--text-muted);font-size:0.8rem;">No file</span>
                <?php endif; ?>
            </td>
            <td>
                <form method="POST" onsubmit="return confirm('Revoke this certificate?');">
                    <input type="hidden" name="action_type" value="delete">
                    <input type="hidden" name="cert_id" value="<?= $c['certificate_id'] ?>">
                    <button type="submit" class="btn-danger-custom btn-sm-custom"><i class="fas fa-trash"></i> Revoke</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($certificates)): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">No certificates issued yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</div></main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
