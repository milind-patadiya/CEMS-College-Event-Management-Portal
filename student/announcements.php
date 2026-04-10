<?php
session_start();
$active_page = 'announcements';
require_once("../config/db.php");
require_once("../includes/student_sidebar.php");

$student_id = (int)$_SESSION['student_id'];

$_guard = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
$_guard->bind_param("i", $student_id); $_guard->execute();
if ($_guard->get_result()->num_rows === 0) {
    session_unset(); session_destroy();
    header("Location: ../auth/login.php?msg=session_expired"); exit();
}
unset($_guard);

$type_filter = $_GET['type'] ?? 'all';

$where = [];
if ($type_filter === 'general') { $where[] = "a.event_id IS NULL"; }
if ($type_filter === 'event')   { $where[] = "a.event_id IS NOT NULL"; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT a.*, e.event_name,
           CASE a.posted_by_role
               WHEN 'faculty' THEN (SELECT full_name FROM faculty WHERE faculty_id = a.posted_by)
               WHEN 'admin'   THEN (SELECT full_name FROM admins  WHERE admin_id   = a.posted_by)
           END AS poster_name
    FROM announcements a
    LEFT JOIN events e ON a.event_id = e.event_id
    $where_sql
    ORDER BY a.created_at DESC
";

$announcements = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$total         = $conn->query("SELECT COUNT(*) AS c FROM announcements")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Announcements — CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-bar { background: white; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .filter-chip { padding: 6px 18px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; text-decoration: none; border: 1.5px solid var(--border); color: var(--text-muted); transition: var(--transition); }
        .filter-chip.active { background: var(--primary); color: white; border-color: var(--primary); }
        .filter-chip:hover:not(.active) { border-color: var(--accent); color: var(--accent); }
        .ann-card { background: white; border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden; margin-bottom: 16px; transition: var(--transition); }
        .ann-card:hover { box-shadow: var(--shadow-sm); transform: translateY(-2px); }
        .ann-card-inner { padding: 20px 24px; }
        .ann-title { font-family: var(--font-display); font-size: 1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
        .ann-content { font-size: 0.88rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 14px; }
        .ann-footer { display: flex; align-items: center; gap: 14px; font-size: 0.78rem; color: #b0bec5; flex-wrap: wrap; }
        .ann-footer span { display: flex; align-items: center; gap: 5px; }
        .event-link-tag { display: inline-flex; align-items: center; gap: 6px; background: var(--accent-light); color: var(--primary); padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
        .ann-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; background: var(--accent-light); color: var(--primary); }
    </style>
</head>
<body>
<main class="main-content">
<div class="content-inner">

    <div class="page-header">
        <div>
            <h1>Announcements</h1>
            <p>Stay updated with notices from faculty and administration</p>
        </div>
        <span class="badge-cems badge-open" style="font-size:0.9rem;padding:8px 16px;">
            <?= $total ?> Total
        </span>
    </div>

    <div class="filter-bar">
        <span style="font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Filter:</span>
        <a href="?type=all"     class="filter-chip <?= $type_filter === 'all'     ? 'active' : '' ?>">All</a>
        <a href="?type=general" class="filter-chip <?= $type_filter === 'general' ? 'active' : '' ?>">General</a>
        <a href="?type=event"   class="filter-chip <?= $type_filter === 'event'   ? 'active' : '' ?>">Event-Specific</a>
    </div>

    <?php if (empty($announcements)): ?>
    <div class="empty-state">
        <i class="fas fa-bell-slash"></i>
        <h5>No announcements found</h5>
        <p>Try a different filter or check back later</p>
    </div>
    <?php else: ?>
    <?php foreach ($announcements as $ann): ?>
    <div class="ann-card">
        <div class="ann-card-inner">
            <div class="d-flex gap-3 align-items-start">
                <div class="ann-icon"><i class="fas fa-bullhorn"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="ann-title"><?= htmlspecialchars($ann['title']) ?></div>
                    <?php if ($ann['event_name']): ?>
                    <div class="mb-2">
                        <span class="event-link-tag">
                            <i class="fas fa-link"></i><?= htmlspecialchars($ann['event_name']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="ann-content"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
                    <div class="ann-footer">
                        <span><i class="fas fa-user-circle"></i><?= htmlspecialchars($ann['poster_name'] ?? 'Faculty') ?></span>
                        <span><i class="fas fa-tag"></i><?= ucfirst($ann['posted_by_role']) ?></span>
                        <span><i class="fas fa-clock"></i><?= date('d M Y, h:i A', strtotime($ann['created_at'])) ?></span>
                        <?php if (!$ann['event_name']): ?>
                        <span><i class="fas fa-globe"></i>General</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
