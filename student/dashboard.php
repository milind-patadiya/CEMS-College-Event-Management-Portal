<?php
session_start();
$active_page = 'dashboard';
require_once("../config/db.php");
require_once("../includes/student_sidebar.php");

$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// ── Stats ─────────────────────────────────────────────────
$active_events = $my_registrations = $my_certificates = $announcements_count = 0;

try {
    $r1 = $conn->query("SELECT COUNT(*) as c FROM events WHERE event_date >= CURDATE() AND status='Approved'");
    $active_events = $r1->fetch_assoc()['c'];

    $r2 = $conn->prepare("SELECT COUNT(*) as c FROM registrations WHERE student_id = ?");
    $r2->bind_param("i", $student_id); $r2->execute();
    $my_registrations = $r2->get_result()->fetch_assoc()['c'];

    $r3 = $conn->prepare("
        SELECT COUNT(*) as c FROM certificates c
        JOIN attendance a ON a.student_id = c.student_id AND a.event_id = c.event_id
        WHERE c.student_id = ? AND a.status = 'Present'
    ");
    $r3->bind_param("i", $student_id); $r3->execute();
    $my_certificates = $r3->get_result()->fetch_assoc()['c'];

    $r4 = $conn->query("SELECT COUNT(*) as c FROM announcements");
    $announcements_count = $r4->fetch_assoc()['c'];
} catch (Exception $e) {}

// ── Upcoming registered events ────────────────────────────
$upcoming = [];
try {
    $q = $conn->prepare("
        SELECT e.event_name, e.event_date, e.event_time, e.venue,
               COALESCE(a.status,'Pending') as att_status
        FROM registrations r
        JOIN events e ON r.event_id = e.event_id
        LEFT JOIN attendance a ON a.student_id = r.student_id AND a.event_id = r.event_id
        WHERE r.student_id = ? AND e.event_date >= CURDATE() AND e.status = 'Approved'
        ORDER BY e.event_date ASC LIMIT 5
    ");
    $q->bind_param("i", $student_id); $q->execute();
    $upcoming = $q->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}

// ── Latest announcements ──────────────────────────────────
$announcements = [];
try {
    $qa = $conn->query("
        SELECT title, created_at FROM announcements
        ORDER BY created_at DESC LIMIT 4
    ");
    $announcements = $qa->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — CEMS Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .welcome-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: var(--radius-md);
            padding: 30px 34px;
            color: white;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }
        .welcome-card::before {
            content: '';
            position: absolute; top: -50px; right: -50px;
            width: 180px; height: 180px; border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }
        .welcome-card::after {
            content: '';
            position: absolute; bottom: -30px; right: 100px;
            width: 100px; height: 100px; border-radius: 50%;
            background: rgba(255,255,255,0.04);
        }
        .welcome-card h2 {
            font-family: var(--font-display);
            font-size: 1.65rem; font-weight: 800;
            margin-bottom: 6px;
        }
        .welcome-card p { opacity: 0.8; font-size: 0.9rem; margin: 0; }

        .event-row {
            display: flex; align-items: center; gap: 16px;
            padding: 14px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: var(--transition);
        }
        .event-row:last-child { border-bottom: none; }
        .event-row:hover { background: #fafbff; }

        .event-date-box {
            min-width: 48px; height: 52px;
            background: var(--accent-light);
            border-radius: 10px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
        }
        .event-date-box .day {
            font-family: var(--font-display);
            font-size: 1.2rem; font-weight: 800;
            color: var(--primary); line-height: 1;
        }
        .event-date-box .mon {
            font-size: 0.65rem; font-weight: 700;
            text-transform: uppercase; color: var(--accent);
        }

        .ann-item {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 13px 20px; border-bottom: 1px solid #f0f0f0;
        }
        .ann-item:last-child { border-bottom: none; }
        .ann-dot {
            width: 8px; height: 8px; border-radius: 50%;
            margin-top: 6px; flex-shrink: 0;
            background: var(--accent);
        }
    </style>
</head>
<body>
<?php include '../includes/student_sidebar.php'; ?>

<main class="main-content">
<div class="content-inner">

    <!-- Welcome Banner -->
    <div class="welcome-card">
        <div style="position:relative;z-index:1;">
            <h2>Welcome back, <?= htmlspecialchars(explode(' ', $student_name)[0]) ?>! 👋</h2>
            <p>Here's what's happening on campus today — <?= date('l, d F Y') ?></p>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-number"><?= $active_events ?></div>
                <div class="stat-label">Active Events</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-number"><?= $my_registrations ?></div>
                <div class="stat-label">My Registrations</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon gold"><i class="fas fa-award"></i></div>
                <div class="stat-number"><?= $my_certificates ?></div>
                <div class="stat-label">Certificates</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-bullhorn"></i></div>
                <div class="stat-number"><?= $announcements_count ?></div>
                <div class="stat-label">Announcements</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Upcoming Events -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header-custom">
                    <i class="fas fa-calendar-check"></i> My Upcoming Events
                    <a href="events.php" class="ms-auto btn-primary-custom btn-sm-custom">Browse All</a>
                </div>
                <?php if (empty($upcoming)): ?>
                    <div class="empty-state" style="border:none;">
                        <i class="fas fa-calendar-times"></i>
                        <h5>No upcoming events</h5>
                        <p>Register for events to see them here</p>
                        <a href="events.php" class="btn-primary-custom btn-sm-custom mt-2">Browse Events</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming as $ev): ?>
                    <div class="event-row">
                        <div class="event-date-box">
                            <div class="day"><?= date('d', strtotime($ev['event_date'])) ?></div>
                            <div class="mon"><?= date('M', strtotime($ev['event_date'])) ?></div>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;color:var(--text-dark);font-size:0.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= htmlspecialchars($ev['event_name']) ?>
                            </div>
                            <div style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;">
                                <?php if ($ev['venue']): ?>
                                    <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($ev['venue']) ?>
                                <?php endif; ?>
                                <?php if ($ev['event_time']): ?>
                                    &nbsp;·&nbsp;<i class="fas fa-clock me-1"></i><?= htmlspecialchars($ev['event_time']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                        $s = $ev['att_status'];
                        $cls = $s === 'Present' ? 'badge-present' : ($s === 'Absent' ? 'badge-absent' : 'badge-pending');
                        ?>
                        <span class="badge-cems <?= $cls ?>"><?= $s ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Announcements -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header-custom">
                    <i class="fas fa-bullhorn"></i> Latest Announcements
                    <a href="announcements.php" class="ms-auto btn-outline-custom btn-sm-custom">View All</a>
                </div>
                <?php if (empty($announcements)): ?>
                    <div class="empty-state" style="border:none;">
                        <i class="fas fa-bell-slash"></i>
                        <h5>No announcements</h5>
                        <p>Check back later for updates</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                    <div class="ann-item">
                        <div class="ann-dot"></div>
                        <div>
                            <div style="font-weight:600;font-size:0.88rem;color:var(--text-dark);">
                                <?= htmlspecialchars($ann['title']) ?>
                            </div>
                            <div style="font-size:0.76rem;color:var(--text-muted);margin-top:2px;">
                                <?= date('d M Y', strtotime($ann['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
</div><!-- /content-inner -->
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
