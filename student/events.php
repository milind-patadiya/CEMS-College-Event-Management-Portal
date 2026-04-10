<?php
session_start();
$active_page = 'events';
require_once("../config/db.php");
require_once("../includes/student_sidebar.php");
require_once("../includes/mailer.php");

// ── Verify session student_id actually exists in DB ───────
// (guards against stale sessions after a DB reimport)
$student_id = (int)$_SESSION['student_id'];
$flash = $flash_type = '';

$chk = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
$chk->bind_param("i", $student_id); $chk->execute();
if ($chk->get_result()->num_rows === 0) {
    // Session is stale — destroy it and send back to login
    session_unset(); session_destroy();
    header("Location: ../auth/login.php?msg=session_expired"); exit();
}

// ── Handle Registration ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event_id'])) {
    $event_id = (int)$_POST['register_event_id'];

    $dup = $conn->prepare("SELECT registration_id FROM registrations WHERE student_id=? AND event_id=?");
    $dup->bind_param("ii", $student_id, $event_id); $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        $flash = "You are already registered for this event."; $flash_type = 'warning';
    } else {
        // Capacity check
        $cq = $conn->prepare("SELECT capacity FROM events WHERE event_id=?");
        $cq->bind_param("i", $event_id); $cq->execute();
        $cap = $cq->get_result()->fetch_assoc()['capacity'] ?? null;

        $rc = $conn->prepare("SELECT COUNT(*) as c FROM registrations WHERE event_id=?");
        $rc->bind_param("i", $event_id); $rc->execute();
        $reg_count = $rc->get_result()->fetch_assoc()['c'];

        if ($cap !== null && $reg_count >= $cap) {
            $flash = "Sorry, this event is at full capacity."; $flash_type = 'danger';
        } else {
            $ins = $conn->prepare("INSERT INTO registrations (student_id, event_id) VALUES (?,?)");
            $ins->bind_param("ii", $student_id, $event_id);
            if ($ins->execute()) {
                // NOTE: Do NOT auto-insert attendance here.
                // Attendance is marked by faculty only — not at registration time.
                $flash = "Successfully registered! See you at the event."; $flash_type = 'success';
                // Send confirmation email to student
                notifyStudentRegistered($conn, $student_id, $event_id);
            } else {
                $flash = "Registration failed. Please try again."; $flash_type = 'danger';
            }
        }
    }
}

// ── Fetch Events ───────────────────────────────────────────
$filter = $_GET['filter'] ?? 'upcoming'; // upcoming | all | past
$search = trim($_GET['search'] ?? '');

$where_parts = ["e.status = 'Approved'"]; // only show admin-approved events
$params = [$student_id];
$types  = "i";

if ($filter === 'upcoming') { $where_parts[] = "e.event_date >= CURDATE()"; }
elseif ($filter === 'past') { $where_parts[] = "e.event_date < CURDATE()"; }

if ($search !== '') {
    $where_parts[] = "(e.event_name LIKE ? OR e.venue LIKE ? OR e.description LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
    $types .= "sss";
}

$where = implode(' AND ', $where_parts);

$stmt = $conn->prepare("
    SELECT e.*,
           f.full_name AS faculty_name, f.department,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) as reg_count,
           (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.event_id AND r2.student_id = ?) as is_registered
    FROM events e
    LEFT JOIN faculty f ON f.faculty_id = e.created_by
    WHERE $where
    ORDER BY e.event_date ASC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>All Events — CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-bar {
            background: white; border: 1px solid var(--border);
            border-radius: var(--radius-md); padding: 16px 20px;
            margin-bottom: 24px;
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .filter-tabs { display: flex; gap: 6px; }
        .filter-tab {
            padding: 7px 18px; border-radius: 20px; font-size: 0.84rem;
            font-weight: 600; cursor: pointer; text-decoration: none;
            border: 1.5px solid var(--border); color: var(--text-muted);
            transition: var(--transition);
        }
        .filter-tab.active, .filter-tab:hover {
            background: var(--primary); color: white; border-color: var(--primary);
        }
        .search-wrap { flex: 1; min-width: 200px; position: relative; }
        .search-wrap i {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%); color: #b0bec5;
        }
        .search-wrap input {
            width: 100%; padding: 9px 14px 9px 38px;
            border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 0.88rem; outline: none; font-family: var(--font-body);
        }
        .search-wrap input:focus { border-color: var(--accent); }

        .event-card {
            background: white; border: 1px solid var(--border);
            border-radius: var(--radius-lg); overflow: hidden;
            transition: var(--transition); height: 100%;
            display: flex; flex-direction: column;
        }
        .event-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }

        .event-card-header {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            padding: 22px 24px; position: relative; overflow: hidden;
        }
        .event-card-header.past { background: linear-gradient(135deg, #78909c, #90a4ae); }
        .event-card-header::after {
            content: '';
            position: absolute; top: -20px; right: -20px;
            width: 80px; height: 80px; border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .event-card-header h5 {
            font-family: var(--font-display); font-size: 1rem;
            font-weight: 700; color: white; margin: 0;
            position: relative; z-index: 1;
        }
        .event-card-header .event-date-line {
            font-size: 0.8rem; color: rgba(255,255,255,0.75);
            margin-top: 6px; position: relative; z-index: 1;
        }
        .card-badge {
            position: absolute; top: 14px; right: 14px;
            z-index: 2;
        }

        .event-card-body { padding: 20px 22px; flex: 1; display: flex; flex-direction: column; }
        .event-desc {
            font-size: 0.85rem; color: var(--text-muted);
            line-height: 1.6; margin-bottom: 14px; flex: 1;
        }
        .event-info-row {
            display: flex; align-items: center; gap: 7px;
            font-size: 0.83rem; color: #666; margin-bottom: 7px;
        }
        .event-info-row i { color: var(--accent); width: 15px; }

        .register-btn {
            width: 100%; padding: 10px; margin-top: 16px;
            border-radius: 8px; font-weight: 700; font-size: 0.88rem;
            border: none; cursor: pointer; transition: var(--transition);
            display: flex; align-items: center; justify-content: center; gap: 7px;
        }
        .register-btn.open { background: var(--primary); color: white; }
        .register-btn.open:hover { background: var(--accent); }
        .register-btn.registered { background: var(--success-light); color: var(--success); cursor: default; }
        .register-btn.full { background: #f5f5f5; color: #aaa; cursor: not-allowed; }
        .register-btn.past-btn { background: #f5f5f5; color: #aaa; cursor: default; }

        .toast-wrap { position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; }
    </style>
</head>
<body>
<?php include '../includes/student_sidebar.php'; ?>

<?php if ($flash): ?>
<div class="toast-wrap">
    <div class="toast show align-items-center border-0 text-white bg-<?= $flash_type === 'success' ? 'success' : ($flash_type === 'warning' ? 'warning text-dark' : 'danger') ?>" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold">
                <i class="fas fa-<?= $flash_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
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
            <h1>All Events</h1>
            <p>Browse and register for campus events</p>
        </div>
        <span class="badge-cems badge-open" style="font-size:0.9rem;padding:8px 16px;">
            <?= count($events) ?> Events
        </span>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-tabs">
            <a href="?filter=upcoming" class="filter-tab <?= $filter === 'upcoming' ? 'active' : '' ?>">Upcoming</a>
            <a href="?filter=all"      class="filter-tab <?= $filter === 'all'      ? 'active' : '' ?>">All</a>
            <a href="?filter=past"     class="filter-tab <?= $filter === 'past'     ? 'active' : '' ?>">Past</a>
        </div>
        <form method="GET" class="search-wrap">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search events, venues..."
                   value="<?= htmlspecialchars($search) ?>">
        </form>
    </div>

    <?php if (empty($events)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h5>No events found</h5>
            <p><?= $search ? "Try a different search term" : "Check back later for new events" ?></p>
        </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($events as $ev):
            $cap      = $ev['capacity'];
            $reg_cnt  = $ev['reg_count'];
            $is_reg   = $ev['is_registered'] > 0;
            $is_full  = $cap && $reg_cnt >= $cap;
            $is_past  = strtotime($ev['event_date']) < strtotime('today');
            $pct      = $cap ? min(100, round($reg_cnt / $cap * 100)) : 0;
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="event-card">
                <div class="event-card-header <?= $is_past ? 'past' : '' ?>">
                    <div class="card-badge">
                        <?php if ($is_past): ?>
                            <span class="badge-cems" style="background:rgba(255,255,255,0.2);color:white;">Completed</span>
                        <?php elseif ($is_reg): ?>
                            <span class="badge-cems" style="background:#c8e6c9;color:#1b5e20;">✓ Registered</span>
                        <?php elseif ($is_full): ?>
                            <span class="badge-cems badge-full">Full</span>
                        <?php else: ?>
                            <span class="badge-cems badge-open">Open</span>
                        <?php endif; ?>
                    </div>
                    <h5><?= htmlspecialchars($ev['event_name']) ?></h5>
                    <div class="event-date-line">
                        <i class="fas fa-calendar me-1"></i><?= date('D, d M Y', strtotime($ev['event_date'])) ?>
                        <?php if ($ev['event_time']): ?>
                            &nbsp;·&nbsp;<i class="fas fa-clock me-1"></i><?= htmlspecialchars($ev['event_time']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="event-card-body">
                    <?php if ($ev['venue']): ?>
                    <div class="event-info-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?= htmlspecialchars($ev['venue']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($ev['faculty_name']): ?>
                    <div class="event-info-row">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span><?= htmlspecialchars($ev['faculty_name']) ?><?= $ev['department'] ? ' · ' . htmlspecialchars($ev['department']) : '' ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($ev['description']): ?>
                    <p class="event-desc"><?= htmlspecialchars(mb_strimwidth($ev['description'], 0, 110, '…')) ?></p>
                    <?php endif; ?>

                    <?php if ($cap): ?>
                    <div style="margin-top:auto;">
                        <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--text-muted);margin-bottom:5px;">
                            <span><i class="fas fa-users me-1"></i><?= $reg_cnt ?>/<?= $cap ?> registered</span>
                            <span><?= $pct ?>%</span>
                        </div>
                        <div class="cap-bar-track">
                            <div class="cap-bar-fill <?= $is_full ? 'full' : '' ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($is_past): ?>
                        <button class="register-btn past-btn" disabled>
                            <i class="fas fa-flag-checkered"></i> Event Completed
                        </button>
                    <?php elseif ($is_reg): ?>
                        <button class="register-btn registered" disabled>
                            <i class="fas fa-check-circle"></i> Already Registered
                        </button>
                    <?php elseif ($is_full): ?>
                        <button class="register-btn full" disabled>
                            <i class="fas fa-lock"></i> Event Full
                        </button>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="register_event_id" value="<?= $ev['event_id'] ?>">
                            <button type="submit" class="register-btn open">
                                <i class="fas fa-plus-circle"></i> Register Now
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div><!-- /content-inner -->
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    setTimeout(() => {
        document.querySelectorAll('.toast').forEach(t => bootstrap.Toast.getOrCreateInstance(t).hide());
    }, 4000);
</script>
</body>
</html>