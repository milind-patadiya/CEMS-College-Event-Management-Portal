<?php
session_start();
$active_page = 'announcements';
require_once("../config/db.php");
if (!isset($_SESSION['faculty_id'])) { header("Location: ../auth/login.php"); exit(); }
require_once('../includes/mailer.php');

$faculty_id = $_SESSION['faculty_id'];
$action     = $_GET['action'] ?? 'list';
$ann_id     = (int)($_GET['id'] ?? 0);
$errors     = [];
$flash      = '';

// ── DELETE ─────────────────────────────────────────────────
if ($action === 'delete' && $ann_id) {
    try {
        $d = $conn->prepare("DELETE FROM announcements WHERE announcement_id=? AND posted_by=? AND posted_by_role='faculty'");
        $d->bind_param("ii", $ann_id, $faculty_id); $d->execute();
        $_SESSION['flash'] = "Announcement deleted.";
    } catch (Exception $e) {}
    header("Location: announcements.php"); exit();
}

// ── SAVE ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title']    ?? '');
    $content  = trim($_POST['content']  ?? '');
    $event_id = (isset($_POST['event_id']) && $_POST['event_id'] !== '') ? (int)$_POST['event_id'] : null;
    $edit_id  = (int)($_POST['edit_id'] ?? 0);

    if (!$title)   $errors[] = "Title is required.";
    if (!$content) $errors[] = "Content is required.";

    if (empty($errors)) {
        try {
            if ($edit_id) {
                $stmt = $conn->prepare("UPDATE announcements SET title=?, content=?, event_id=? WHERE announcement_id=? AND posted_by=? AND posted_by_role='faculty'");
                $stmt->bind_param("ssiii", $title, $content, $event_id, $edit_id, $faculty_id);
                $stmt->execute();
                $_SESSION['flash'] = "Announcement updated.";
            } else {
                $stmt = $conn->prepare("INSERT INTO announcements (title, content, event_id, posted_by, posted_by_role) VALUES (?,?,?,?,'faculty')");
                $stmt->bind_param("ssii", $title, $content, $event_id, $faculty_id);
                $stmt->execute();
                $_SESSION['flash'] = "Announcement posted. Students can now see it.";
                // Email all students
                $fac_name = $_SESSION['faculty_name'] ?? 'Faculty';
                $ev_name_mail = null;
                if ($event_id) {
                    $en = $conn->prepare("SELECT event_name FROM events WHERE event_id=?");
                    $en->bind_param("i", $event_id); $en->execute();
                    $ev_name_mail = $en->get_result()->fetch_assoc()['event_name'] ?? null;
                }
                notifyStudentsAnnouncement($conn, $title, $content, $fac_name, 'faculty', $ev_name_mail);
            }
            header("Location: announcements.php"); exit();
        } catch (Exception $e) { $errors[] = "Error: " . $e->getMessage(); }
    }
    $action = $edit_id ? 'edit' : 'create';
}

// ── LOAD FOR EDIT ──────────────────────────────────────────
$edit_ann = null;
if ($action === 'edit' && $ann_id) {
    try {
        $q = $conn->prepare("SELECT * FROM announcements WHERE announcement_id=? AND posted_by=? AND posted_by_role='faculty'");
        $q->bind_param("ii", $ann_id, $faculty_id); $q->execute();
        $edit_ann = $q->get_result()->fetch_assoc();
        if (!$edit_ann) { header("Location: announcements.php"); exit(); }
    } catch (Exception $e) { header("Location: announcements.php"); exit(); }
}

// ── LIST ───────────────────────────────────────────────────
$announcements = [];
if ($action === 'list') {
    try {
        $q = $conn->prepare("
            SELECT a.*, e.event_name
            FROM announcements a
            LEFT JOIN events e ON e.event_id = a.event_id
            WHERE a.posted_by = ? AND a.posted_by_role = 'faculty'
            ORDER BY a.created_at DESC
        ");
        $q->bind_param("i", $faculty_id); $q->execute();
        $announcements = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {}
}

// Faculty's approved events for linking
$my_events = [];
try {
    $eq = $conn->prepare("SELECT event_id, event_name FROM events WHERE created_by=? AND status='Approved' ORDER BY event_date DESC");
    $eq->bind_param("i", $faculty_id); $eq->execute();
    $my_events = $eq->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
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
        .ann-card { background:white; border:1px solid var(--border); border-radius:var(--radius-md); overflow:hidden; transition:var(--transition); box-shadow:var(--shadow-sm); margin-bottom:14px; }
        .ann-card:hover { box-shadow:var(--shadow-md); }
        .ann-body   { padding:16px 18px; flex:1; }
        .ann-title  { font-family:var(--font-display); font-weight:800; font-size:0.9rem; color:var(--text-dark); margin-bottom:5px; }
        .ann-content{ font-size:0.82rem; color:var(--text-muted); line-height:1.55; margin-bottom:10px; }
        .ann-meta   { font-size:0.74rem; color:#aab0be; display:flex; gap:12px; flex-wrap:wrap; }
        .ann-actions{ display:flex; gap:6px; align-items:center; padding:10px 18px; border-top:1px solid var(--border); }
        .form-card  { background:white; border:1px solid var(--border); border-radius:var(--radius-md); padding:26px 30px; box-shadow:var(--shadow-sm); }
        .event-tag  { display:inline-flex; align-items:center; gap:5px; background:var(--accent-light); color:var(--primary); padding:3px 10px; border-radius:20px; font-size:0.74rem; font-weight:600; }
    </style>
</head>
<body>
<?php require_once("../includes/faculty_sidebar.php"); ?>
<main class="main-content">
<div class="content-inner">

<?php if ($flash): ?>
<div class="alert-cems alert-success mb-3"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert-cems alert-danger mb-3">
    <div><i class="fas fa-exclamation-circle me-1"></i>
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'create' || $action === 'edit'):
    $an = $edit_ann ?? [];
    $is_edit = $action === 'edit';
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-bullhorn me-2" style="color:var(--accent);font-size:1rem;"></i>
            <?= $is_edit ? 'Edit Announcement' : 'New Announcement' ?></h1>
        <p><?= $is_edit ? 'Update the announcement details.' : 'Post a new announcement visible to all students.' ?></p>
    </div>
    <a href="announcements.php" class="btn-outline-custom"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="form-card">
    <form method="POST">
        <?php if ($is_edit): ?><input type="hidden" name="edit_id" value="<?= $an['announcement_id'] ?>"><?php endif; ?>

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label-custom">Title <span style="color:#ef5350;">*</span></label>
                <input type="text" name="title" class="form-control-custom" maxlength="150"
                       placeholder="e.g. Registration Deadline Reminder" required
                       value="<?= htmlspecialchars($an['title'] ?? $_POST['title'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label-custom">Content <span style="color:#ef5350;">*</span></label>
                <textarea name="content" class="form-control-custom" rows="5"
                          placeholder="Write the full announcement message here..."><?= htmlspecialchars($an['content'] ?? $_POST['content'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label-custom">Link to Event <small style="font-weight:400;text-transform:none;">(optional)</small></label>
                <select name="event_id" class="form-control-custom">
                    <option value="">— General Announcement —</option>
                    <?php foreach ($my_events as $me): ?>
                    <option value="<?= $me['event_id'] ?>" <?= (($an['event_id'] ?? $_POST['event_id'] ?? '') == $me['event_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($me['event_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn-primary-custom">
                <i class="fas fa-paper-plane"></i> <?= $is_edit ? 'Update Announcement' : 'Post Announcement' ?>
            </button>
            <a href="announcements.php" class="btn-outline-custom">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-bullhorn me-2" style="color:var(--accent);font-size:1rem;"></i>My Announcements</h1>
        <p>Announcements you have posted are visible to all students.</p>
    </div>
    <a href="announcements.php?action=create" class="btn-primary-custom">
        <i class="fas fa-plus"></i> New Announcement
    </a>
</div>

<?php if (empty($announcements)): ?>
<div class="empty-state">
    <i class="fas fa-bullhorn"></i>
    <h5>No announcements yet</h5>
    <p>Post your first announcement for students to see.</p>
</div>
<?php else: ?>
<?php foreach ($announcements as $ann): ?>
<div class="ann-card">
    <div class="ann-body">
        <div class="ann-title"><?= htmlspecialchars($ann['title']) ?></div>
        <div class="ann-content"><?= nl2br(htmlspecialchars(mb_strimwidth($ann['content'], 0, 200, '…'))) ?></div>
        <div class="ann-meta">
            <span><i class="fas fa-clock me-1"></i><?= date('d M Y, h:i A', strtotime($ann['created_at'])) ?></span>
            <?php if ($ann['event_name']): ?>
            <span class="event-tag"><i class="fas fa-link"></i><?= htmlspecialchars($ann['event_name']) ?></span>
            <?php else: ?>
            <span><i class="fas fa-globe me-1"></i>General</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="ann-actions">
        <a href="announcements.php?action=edit&id=<?= $ann['announcement_id'] ?>" class="btn-outline-custom btn-sm-custom">
            <i class="fas fa-edit"></i> Edit
        </a>
        <button class="btn-danger-custom btn-sm-custom ms-auto"
                onclick="if(confirm('Delete this announcement?')) location.href='announcements.php?action=delete&id=<?= $ann['announcement_id'] ?>'">
            <i class="fas fa-trash"></i> Delete
        </button>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

</div><!-- /content-inner -->
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
