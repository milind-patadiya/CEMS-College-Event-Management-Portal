<?php
session_start();
$active_page = 'dashboard';
require_once('../config/db.php');
require_once('../includes/admin_sidebar.php');

$admin_name = $_SESSION['admin_name'];

$stats = [];
try {
    $stats['students']      = $conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'];
    $stats['faculty']       = $conn->query("SELECT COUNT(*) c FROM faculty")->fetch_assoc()['c'];
    $stats['total_events']  = $conn->query("SELECT COUNT(*) c FROM events")->fetch_assoc()['c'];
    $stats['pending']       = $conn->query("SELECT COUNT(*) c FROM events WHERE status='Pending'")->fetch_assoc()['c'];
    $stats['approved']      = $conn->query("SELECT COUNT(*) c FROM events WHERE status='Approved'")->fetch_assoc()['c'];
    $stats['registrations'] = $conn->query("SELECT COUNT(*) c FROM registrations")->fetch_assoc()['c'];
    $stats['present']       = $conn->query("SELECT COUNT(*) c FROM attendance WHERE status='Present'")->fetch_assoc()['c'];
    $stats['absent']        = $conn->query("SELECT COUNT(*) c FROM attendance WHERE status='Absent'")->fetch_assoc()['c'];
    $stats['announcements'] = $conn->query("SELECT COUNT(*) c FROM announcements")->fetch_assoc()['c'];
    $stats['certificates']  = $conn->query("SELECT COUNT(*) c FROM certificates")->fetch_assoc()['c'];
} catch (Exception $e) {}

$pending_events = [];
try {
    $q = $conn->query("
        SELECT e.event_id, e.event_name, e.event_date, e.venue,
               f.full_name AS faculty_name, f.department, e.created_at
        FROM events e
        LEFT JOIN faculty f ON f.faculty_id = e.created_by
        WHERE e.status = 'Pending'
        ORDER BY e.created_at ASC LIMIT 5
    ");
    $pending_events = $q->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}

$recent_events = [];
try {
    $q = $conn->query("
        SELECT e.event_id, e.event_name, e.event_date, e.status,
               f.full_name AS faculty_name,
               COUNT(r.registration_id) AS reg_count
        FROM events e
        LEFT JOIN faculty f ON f.faculty_id = e.created_by
        LEFT JOIN registrations r ON r.event_id = e.event_id
        GROUP BY e.event_id
        ORDER BY e.created_at DESC LIMIT 6
    ");
    $recent_events = $q->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}

$recent_students = [];
try {
    $q = $conn->query("SELECT student_id, full_name, enrollment_no, email, created_at FROM students ORDER BY created_at DESC LIMIT 5");
    $recent_students = $q->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Dashboard — CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, #6a0080 0%, #880e4f 50%, #c62828 100%);
            border-radius: var(--radius-md); padding: 28px 32px; color: white;
            margin-bottom: 24px; position: relative; overflow: hidden;
        }
        .welcome-banner::before {
            content: ''; position: absolute; top: -40px; right: -40px;
            width: 180px; height: 180px; border-radius: 50%; background: rgba(255,255,255,0.06);
        }
        .welcome-banner::after {
            content: ''; position: absolute; bottom: -30px; right: 140px;
            width: 100px; height: 100px; border-radius: 50%; background: rgba(255,255,255,0.04);
        }
        .welcome-banner h2 { font-family: var(--font-display); font-size: 1.55rem; font-weight: 800; margin-bottom: 5px; }
        .welcome-banner p  { opacity: 0.82; font-size: 0.9rem; margin: 0; }
        .stat-icon.pink   { background: #fce4ec; color: #880e4f; }
        .stat-icon.orange { background: #fff3e0; color: #e65100; }
        .pending-alert { background: #fff8e1; border: 1px solid #ffe082; border-left: 4px solid #f9a825; border-radius: var(--radius-md); padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .quick-action-card { background: white; border: 2px dashed var(--border); border-radius: var(--radius-md); padding: 20px; text-align: center; text-decoration: none; transition: var(--transition); display: block; }
        .quick-action-card:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .quick-action-card .qa-icon { width: 44px; height: 44px; border-radius: 10px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .quick-action-card .qa-label { font-family: var(--font-display); font-weight: 700; font-size: 0.84rem; color: var(--primary); }
    </style>
</head>
<body>
<main class="main-content">
<div class="content-inner">

    <div class="welcome-banner">
        <div style="position:relative;z-index:1;">
            <h2><i class="fas fa-user-shield me-2" style="opacity:0.85;"></i>Admin / HOD Dashboard</h2>
            <p>Welcome back, <strong><?= htmlspecialchars($admin_name) ?></strong> — full system control panel below.</p>
        </div>
    </div>

    <?php if (!empty($pending_events)): ?>
    <div class="pending-alert">
        <i class="fas fa-exclamation-triangle" style="color:#f9a825;font-size:1.2rem;flex-shrink:0;"></i>
        <div style="flex:1;">
            <strong style="color:#e65100;"><?= count($pending_events) ?> event<?= count($pending_events) > 1 ? 's' : '' ?> awaiting your approval</strong>
            <span style="color:var(--text-muted);font-size:0.82rem;"> — faculty have submitted events for review</span>
        </div>
        <a href="approvals.php" class="btn-primary-custom btn-sm-custom" style="background:#e65100;flex-shrink:0;">
            <i class="fas fa-check-double"></i> Review Now
        </a>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-number"><?= $stats['students'] ?></div>
                <div class="stat-label">Total Students</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-number"><?= $stats['faculty'] ?></div>
                <div class="stat-label">Faculty Members</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-number"><?= $stats['total_events'] ?></div>
                <div class="stat-label">Total Events</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-number"><?= $stats['registrations'] ?></div>
                <div class="stat-label">Registrations</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                <div class="stat-number"><?= $stats['present'] ?></div>
                <div class="stat-label">Present Records</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon pink"><i class="fas fa-bullhorn"></i></div>
                <div class="stat-number"><?= $stats['announcements'] ?></div>
                <div class="stat-label">Announcements</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon gold"><i class="fas fa-award"></i></div>
                <div class="stat-number"><?= $stats['certificates'] ?></div>
                <div class="stat-label">Certificates Issued</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="students.php?action=create" class="quick-action-card">
                <div class="qa-icon" style="background:var(--accent-light);"><i class="fas fa-user-plus" style="color:var(--primary);"></i></div>
                <div class="qa-label">Add Student</div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="faculty.php?action=create" class="quick-action-card">
                <div class="qa-icon" style="background:#e8f5e9;"><i class="fas fa-chalkboard-teacher" style="color:#2e7d32;"></i></div>
                <div class="qa-label">Add Faculty</div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="approvals.php" class="quick-action-card">
                <div class="qa-icon" style="background:#fff3e0;"><i class="fas fa-check-double" style="color:#e65100;"></i></div>
                <div class="qa-label">Approve Events</div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="announcements.php?action=create" class="quick-action-card">
                <div class="qa-icon" style="background:#fce4ec;"><i class="fas fa-bullhorn" style="color:#880e4f;"></i></div>
                <div class="qa-label">Post Announcement</div>
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <?php if (!empty($pending_events)): ?>
        <div class="col-12">
            <div class="table-wrap">
                <div class="card-header-custom" style="background:#fff8e1;">
                    <i class="fas fa-clock" style="color:#e65100;"></i>
                    <span style="color:#e65100;font-weight:800;">Pending Approvals (<?= count($pending_events) ?>)</span>
                    <a href="approvals.php" class="btn-primary-custom btn-sm-custom ms-auto" style="background:#e65100;">View All</a>
                </div>
                <table class="cems-table">
                    <thead><tr><th>Event Name</th><th>Faculty</th><th>Department</th><th>Event Date</th><th>Submitted</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($pending_events as $ev): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ev['event_name']) ?></strong></td>
                        <td style="color:var(--text-muted);"><?= htmlspecialchars($ev['faculty_name'] ?? '—') ?></td>
                        <td style="color:var(--text-muted);font-size:0.78rem;"><?= htmlspecialchars($ev['department'] ?? '—') ?></td>
                        <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($ev['event_date'])) ?></td>
                        <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($ev['created_at'])) ?></td>
                        <td>
                            <a href="approvals.php?event_id=<?= $ev['event_id'] ?>" class="btn-primary-custom btn-sm-custom" style="background:#e65100;">
                                <i class="fas fa-eye"></i> Review
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-lg-8">
            <div class="table-wrap">
                <div class="card-header-custom">
                    <i class="fas fa-calendar-alt"></i> Recent Events
                    <a href="events.php" class="btn-outline-custom btn-sm-custom ms-auto">View All</a>
                </div>
                <table class="cems-table">
                    <thead><tr><th>Event</th><th>Faculty</th><th>Date</th><th>Reg.</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_events as $ev): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ev['event_name']) ?></strong></td>
                        <td style="color:var(--text-muted);font-size:0.78rem;"><?= htmlspecialchars($ev['faculty_name'] ?? '—') ?></td>
                        <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($ev['event_date'])) ?></td>
                        <td><strong><?= $ev['reg_count'] ?></strong></td>
                        <td><span class="badge-cems badge-<?= strtolower($ev['status']) ?>"><?= $ev['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent_events)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted);">No events yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-wrap">
                <div class="card-header-custom">
                    <i class="fas fa-user-graduate"></i> New Students
                    <a href="students.php" class="btn-outline-custom btn-sm-custom ms-auto">All</a>
                </div>
                <table class="cems-table">
                    <thead><tr><th>Name</th><th>Enrollment</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_students as $s): ?>
                    <tr>
                        <td><strong style="font-size:0.82rem;"><?= htmlspecialchars($s['full_name']) ?></strong></td>
                        <td style="color:var(--text-muted);font-size:0.78rem;"><?= htmlspecialchars($s['enrollment_no']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent_students)): ?>
                    <tr><td colspan="2" style="text-align:center;padding:24px;color:var(--text-muted);">No students yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
