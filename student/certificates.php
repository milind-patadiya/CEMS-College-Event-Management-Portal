<?php
session_start();
$active_page = 'certificates';
require_once("../config/db.php");
require_once("../includes/student_sidebar.php");

$student_id = $_SESSION['student_id'];

// ── Available certs (Present attendance) ──────────────────
$q1 = $conn->prepare("
    SELECT c.certificate_id, c.certificate_url, c.issued_date,
           e.event_name, e.event_date, e.venue, a.status as att_status
    FROM certificates c
    JOIN events e  ON c.event_id = e.event_id
    JOIN attendance a ON a.student_id = c.student_id AND a.event_id = c.event_id
    WHERE c.student_id = ? AND a.status = 'Present'
    ORDER BY c.issued_date DESC
");
$q1->bind_param("i", $student_id); $q1->execute();
$available = $q1->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Attended but cert not issued yet ──────────────────────
$q2 = $conn->prepare("
    SELECT e.event_name, e.event_date
    FROM attendance a
    JOIN events e ON a.event_id = e.event_id
    WHERE a.student_id = ? AND a.status = 'Present'
    AND e.event_id NOT IN (SELECT event_id FROM certificates WHERE student_id = ?)
    ORDER BY e.event_date DESC
");
$q2->bind_param("ii", $student_id, $student_id); $q2->execute();
$pending = $q2->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Certs issued but student was absent (locked) ──────────
$q3 = $conn->prepare("
    SELECT c.certificate_id, e.event_name, e.event_date, a.status as att_status
    FROM certificates c
    JOIN events e ON c.event_id = e.event_id
    LEFT JOIN attendance a ON a.student_id = c.student_id AND a.event_id = c.event_id
    WHERE c.student_id = ? AND (a.status != 'Present' OR a.status IS NULL)
    ORDER BY e.event_date DESC
");
$q3->bind_param("i", $student_id); $q3->execute();
$locked = $q3->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificates — CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .cert-card {
            background: white; border: 1px solid var(--border);
            border-radius: var(--radius-lg); overflow: hidden;
            transition: var(--transition); height: 100%;
            display: flex; flex-direction: column;
        }
        .cert-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }

        .cert-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            padding: 28px 24px; text-align: center; position: relative; overflow: hidden;
        }
        .cert-header::before {
            content: '';
            position: absolute; top: -30px; right: -30px;
            width: 100px; height: 100px; border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }

        .cert-medal {
            width: 60px; height: 60px; border-radius: 50%;
            background: var(--gold); margin: 0 auto 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: white;
            box-shadow: 0 4px 16px rgba(249,168,37,0.45);
            position: relative; z-index: 1;
        }

        .cert-title {
            font-family: var(--font-display); font-size: 0.95rem;
            font-weight: 700; color: white; position: relative; z-index: 1;
        }

        .cert-body { padding: 20px 22px; flex: 1; display: flex; flex-direction: column; }

        .cert-info { font-size: 0.83rem; color: var(--text-muted); margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
        .cert-info i { color: var(--accent); width: 14px; }

        .dl-btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: auto; padding-top: 16px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white; border: none; border-radius: 9px;
            padding: 11px 20px; font-weight: 700; font-size: 0.9rem;
            cursor: pointer; transition: var(--transition); text-decoration: none;
            width: 100%;
        }
        .dl-btn:hover { opacity: 0.9; color: white; transform: translateY(-1px); }
        .dl-btn.disabled { background: #e0e0e0; color: #aaa; cursor: not-allowed; pointer-events: none; }

        /* Pending card */
        .pending-card {
            background: white; border: 2px dashed #ffe082;
            border-radius: var(--radius-md); padding: 22px;
        }
        .pending-icon {
            width: 44px; height: 44px; background: var(--gold-light);
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: #f57c00; margin-bottom: 12px;
        }

        /* Locked card */
        .locked-card {
            background: #fafafa; border: 1px solid #eee;
            border-radius: var(--radius-md); overflow: hidden; opacity: 0.65;
        }
        .locked-header {
            background: #b0bec5; padding: 22px; text-align: center;
        }
    </style>
</head>
<body>
<?php include '../includes/student_sidebar.php'; ?>

<main class="main-content">
<div class="content-inner">
    <div class="page-header">
        <div>
            <h1>My Certificates</h1>
            <p>Download your participation certificates — available after attending events</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon gold"><i class="fas fa-award"></i></div>
                <div class="stat-number"><?= count($available) ?></div>
                <div class="stat-label">Available</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-number"><?= count($pending) ?></div>
                <div class="stat-label">Being Prepared</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-lock"></i></div>
                <div class="stat-number"><?= count($locked) ?></div>
                <div class="stat-label">Locked</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?= count($available) + count($pending) ?></div>
                <div class="stat-label">Events Attended</div>
            </div>
        </div>
    </div>

    <!-- Available -->
    <div class="section-label"><i class="fas fa-award"></i> Available to Download</div>

    <?php if (empty($available)): ?>
        <div class="empty-state mb-4">
            <i class="fas fa-graduation-cap"></i>
            <h5>No certificates available yet</h5>
            <p>Attend events and get marked Present to unlock certificates</p>
            <a href="events.php" class="btn-primary-custom btn-sm-custom mt-2">
                <i class="fas fa-calendar-alt"></i> Browse Events
            </a>
        </div>
    <?php else: ?>
    <div class="row g-4 mb-5">
        <?php foreach ($available as $c): ?>
        <div class="col-md-6 col-xl-4">
            <div class="cert-card">
                <div class="cert-header">
                    <div class="cert-medal"><i class="fas fa-award"></i></div>
                    <div class="cert-title"><?= htmlspecialchars($c['event_name']) ?></div>
                </div>
                <div class="cert-body">
                    <div class="cert-info">
                        <i class="fas fa-calendar"></i>
                        <?= date('d M Y', strtotime($c['event_date'])) ?>
                    </div>
                    <?php if ($c['venue']): ?>
                    <div class="cert-info">
                        <i class="fas fa-map-marker-alt"></i>
                        <?= htmlspecialchars($c['venue']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="cert-info">
                        <i class="fas fa-stamp"></i>
                        Issued: <?= date('d M Y', strtotime($c['issued_date'])) ?>
                    </div>
                    <div class="cert-info" style="color:var(--success);font-weight:600;">
                        <i class="fas fa-check-circle" style="color:var(--success);"></i>
                        Attendance: Present
                    </div>

                    <!-- Always show View Certificate button (generates PDF in browser) -->
                    <a href="generate_certificate.php?cert_id=<?= $c['certificate_id'] ?>"
                       target="_blank" class="dl-btn mt-3">
                        <i class="fas fa-award"></i> View &amp; Download Certificate
                    </a>
                    <?php if (!empty($c['certificate_url'])): ?>
                    <a href="<?= htmlspecialchars($c['certificate_url']) ?>"
                       target="_blank" class="dl-btn mt-3" style="background:linear-gradient(135deg,#2e7d32,#388e3c);margin-top:8px !important;">
                        <i class="fas fa-external-link-alt"></i> External Link
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Pending (attended, cert not issued) -->
    <?php if (!empty($pending)): ?>
    <div class="section-label"><i class="fas fa-hourglass-half"></i> Being Prepared by Admin</div>
    <div class="row g-3 mb-5">
        <?php foreach ($pending as $p): ?>
        <div class="col-md-6 col-xl-4">
            <div class="pending-card">
                <div class="pending-icon"><i class="fas fa-hourglass-half"></i></div>
                <div style="font-weight:700;font-size:0.92rem;color:var(--text-dark);margin-bottom:5px;">
                    <?= htmlspecialchars($p['event_name']) ?>
                </div>
                <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:10px;">
                    <i class="fas fa-calendar me-1"></i><?= date('d M Y', strtotime($p['event_date'])) ?>
                </div>
                <span class="badge-cems badge-present"><i class="fas fa-check me-1"></i>You attended</span>
                &nbsp;
                <span class="badge-cems badge-pending">Certificate pending</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Locked -->
    <?php if (!empty($locked)): ?>
    <div class="section-label"><i class="fas fa-lock"></i> Locked Certificates</div>
    <div class="row g-3 mb-3">
        <?php foreach ($locked as $l): ?>
        <div class="col-md-6 col-xl-4">
            <div class="locked-card">
                <div class="locked-header">
                    <i class="fas fa-lock fa-2x" style="color:rgba(255,255,255,0.5);"></i>
                </div>
                <div style="padding:16px 20px;">
                    <div style="font-weight:600;color:var(--text-muted);font-size:0.9rem;">
                        <?= htmlspecialchars($l['event_name']) ?>
                    </div>
                    <div style="font-size:0.78rem;color:#b0bec5;margin:4px 0 8px;">
                        <i class="fas fa-calendar me-1"></i><?= date('d M Y', strtotime($l['event_date'])) ?>
                    </div>
                    <span class="badge-cems badge-absent">
                        <i class="fas fa-times me-1"></i>Attendance: <?= $l['att_status'] ?? 'Not Marked' ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <p style="font-size:0.8rem;color:var(--text-muted);">
        <i class="fas fa-info-circle me-1"></i>
        Certificates are only available when you are marked <strong>Present</strong> by the faculty.
    </p>
    <?php endif; ?>

</div>
</div><!-- /content-inner -->
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>