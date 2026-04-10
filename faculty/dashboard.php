<?php
session_start();
$active_page = 'dashboard';
require_once('../config/db.php');
require_once('../includes/faculty_sidebar.php');

$faculty_id   = $_SESSION['faculty_id'];
$faculty_name = $_SESSION['faculty_name'];

// ── Stats ──────────────────────────────────────────────────
$total_events = $upcoming_events = $total_regs = $pending = 0;
try {
    $s = $conn->prepare("SELECT COUNT(*) c FROM events WHERE created_by=?");
    $s->bind_param("i",$faculty_id); $s->execute();
    $total_events = $s->get_result()->fetch_assoc()['c'];

    $s = $conn->prepare("SELECT COUNT(*) c FROM events WHERE created_by=? AND event_date>=CURDATE()");
    $s->bind_param("i",$faculty_id); $s->execute();
    $upcoming_events = $s->get_result()->fetch_assoc()['c'];

    $s = $conn->prepare("SELECT COUNT(*) c FROM registrations r JOIN events e ON r.event_id=e.event_id WHERE e.created_by=?");
    $s->bind_param("i",$faculty_id); $s->execute();
    $total_regs = $s->get_result()->fetch_assoc()['c'];

    $s = $conn->prepare("SELECT COUNT(DISTINCT r.student_id) c FROM registrations r JOIN events e ON r.event_id=e.event_id WHERE e.created_by=?");
    $s->bind_param("i",$faculty_id); $s->execute();
    $pending = $s->get_result()->fetch_assoc()['c'];
} catch(Exception $e){}

// ── Recent Events ──────────────────────────────────────────
$recent = [];
try {
    $s = $conn->prepare("
        SELECT e.event_id, e.event_name, e.event_date, e.venue, e.status, e.capacity,
               COUNT(r.registration_id) AS reg_count
        FROM events e
        LEFT JOIN registrations r ON r.event_id=e.event_id
        WHERE e.created_by=?
        GROUP BY e.event_id
        ORDER BY e.created_at DESC LIMIT 6
    ");
    $s->bind_param("i",$faculty_id); $s->execute();
    $recent = $s->get_result()->fetch_all(MYSQLI_ASSOC);
} catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard — Faculty | CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<main class="main-content">
<div class="content-inner">

  <!-- Header -->
  <div class="page-header">
    <div>
      <h1><i class="fas fa-th-large me-2" style="color:var(--accent);font-size:1rem;"></i>Faculty Dashboard</h1>
      <p>Welcome back, <strong><?= htmlspecialchars($faculty_name) ?></strong>. Manage your events below.</p>
    </div>
    <a href="events.php?action=create" class="btn-primary-custom">
      <i class="fas fa-plus"></i> Create Event
    </a>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-number"><?= $total_events ?></div>
        <div class="stat-label">Total Events</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-number"><?= $upcoming_events ?></div>
        <div class="stat-label">Upcoming</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-users"></i></div>
        <div class="stat-number"><?= $total_regs ?></div>
        <div class="stat-label">Registrations</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-number"><?= $pending ?></div>
        <div class="stat-label">Students Enrolled</div>
      </div>
    </div>
  </div>

  <!-- Recent Events -->
  <div class="table-wrap">
    <div class="card-header-custom">
      <i class="fas fa-history"></i> Recently Created Events
      <a href="events.php" class="btn-outline-custom btn-sm-custom ms-auto">View All</a>
    </div>
    <?php if(empty($recent)): ?>
    <div class="empty-state">
      <i class="fas fa-calendar-plus"></i>
      <h5>No events yet</h5>
      <p>Click "Create Event" to add your first event.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="cems-table">
      <thead>
        <tr>
          <th>#</th><th>Event Name</th><th>Date</th>
          <th>Venue</th><th>Registered</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($recent as $i=>$e): ?>
      <tr>
        <td style="color:var(--text-muted)"><?= $i+1 ?></td>
        <td><strong><?= htmlspecialchars($e['event_name']) ?></strong></td>
        <td><?= date('d M Y',strtotime($e['event_date'])) ?></td>
        <td><?= htmlspecialchars($e['venue']?:'—') ?></td>
        <td>
          <strong><?= $e['reg_count'] ?></strong>
          <?php if($e['capacity']): ?><span style="color:var(--text-muted);font-size:0.76rem;"> / <?= $e['capacity'] ?></span><?php endif; ?>
        </td>
        <td><span class="badge-cems badge-<?= strtotime($e['event_date']) >= strtotime('today') ? 'open' : 'present' ?>"><?= strtotime($e['event_date']) >= strtotime('today') ? 'Upcoming' : 'Completed' ?></span></td>
        <td>
          <div class="d-flex gap-1">
            <a href="events.php?action=edit&id=<?= $e['event_id'] ?>" class="btn-outline-custom btn-sm-custom"><i class="fas fa-edit"></i> Edit</a>
            <a href="attendance.php?event_id=<?= $e['event_id'] ?>" class="btn-outline-custom btn-sm-custom"><i class="fas fa-clipboard-check"></i></a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>

</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>