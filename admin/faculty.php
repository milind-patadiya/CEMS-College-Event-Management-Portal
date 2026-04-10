<?php
session_start();
$active_page = 'faculty';
require_once('../config/db.php');
require_once('../includes/admin_sidebar.php');

$action     = $_GET['action'] ?? 'list';
$faculty_id = (int)($_GET['id'] ?? 0);
$errors     = [];

if ($action === 'delete' && $faculty_id) {
    try {
        $d = $conn->prepare("DELETE FROM faculty WHERE faculty_id = ?");
        $d->bind_param("i", $faculty_id); $d->execute();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Faculty member deleted. Their events have also been removed.'];
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Cannot delete this faculty member.'];
    }
    header("Location: faculty.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $department = trim($_POST['department'] ?? '');
    $username   = trim($_POST['username']   ?? '');
    $password   = $_POST['password']        ?? '';
    $edit_id    = (int)($_POST['edit_id']   ?? 0);

    if (!$full_name)                                           $errors[] = 'Full name is required.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (!$username)                                            $errors[] = 'Username is required.';
    elseif (strlen(trim($username)) < 3)                       $errors[] = 'Username must be at least 3 characters.';
    if (!$edit_id && strlen($password) < 8)                    $errors[] = 'Password must be at least 8 characters.';

    if (empty($errors)) {
        try {
            if ($edit_id) {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $s = $conn->prepare("UPDATE faculty SET full_name=?,email=?,department=?,username=?,password=? WHERE faculty_id=?");
                    $s->bind_param("sssssi", $full_name, $email, $department, $username, $hash, $edit_id);
                } else {
                    $s = $conn->prepare("UPDATE faculty SET full_name=?,email=?,department=?,username=? WHERE faculty_id=?");
                    $s->bind_param("ssssi", $full_name, $email, $department, $username, $edit_id);
                }
                $s->execute();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Faculty updated. Changes visible immediately.'];
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $s = $conn->prepare("INSERT INTO faculty (full_name,email,department,username,password) VALUES (?,?,?,?,?)");
                $s->bind_param("sssss", $full_name, $email, $department, $username, $hash);
                $s->execute();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Faculty member added. They can now log in and create events.'];
            }
            header("Location: faculty.php"); exit();
        } catch (Exception $e) {
            $errors[] = strpos($e->getMessage(), 'Duplicate') !== false
                ? 'Username or email already exists.'
                : 'Database error: ' . $e->getMessage();
        }
    }
    $action = $edit_id ? 'edit' : 'create';
}

$edit_fac = null;
if ($action === 'edit' && $faculty_id) {
    $q = $conn->prepare("SELECT * FROM faculty WHERE faculty_id=?");
    $q->bind_param("i", $faculty_id); $q->execute();
    $edit_fac = $q->get_result()->fetch_assoc();
    if (!$edit_fac) { header("Location: faculty.php"); exit(); }
}

$faculty_list = [];
$search = trim($_GET['search'] ?? '');
if ($action === 'list') {
    try {
        if ($search !== '') {
            $like = "%$search%";
            $q = $conn->prepare("
                SELECT f.*, COUNT(e.event_id) AS event_count
                FROM faculty f LEFT JOIN events e ON e.created_by=f.faculty_id
                WHERE f.full_name LIKE ? OR f.username LIKE ? OR f.email LIKE ? OR f.department LIKE ?
                GROUP BY f.faculty_id ORDER BY f.created_at DESC
            ");
            $q->bind_param("ssss", $like, $like, $like, $like); $q->execute();
        } else {
            $q = $conn->query("
                SELECT f.*, COUNT(e.event_id) AS event_count
                FROM faculty f LEFT JOIN events e ON e.created_by=f.faculty_id
                GROUP BY f.faculty_id ORDER BY f.created_at DESC
            ");
        }
        $faculty_list = $q->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {}
}

$departments = ['Computer Science','Information Technology','Electronics & Communication','Mechanical Engineering','Civil Engineering','Management','BBA','MBA','Commerce','Sciences','Humanities','Other'];
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Faculty — Admin | CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-card { background:white; border:1px solid var(--border); border-radius:var(--radius-md); padding:26px 30px; box-shadow:var(--shadow-sm); }
        .search-wrap { position:relative; }
        .search-wrap i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#b0bec5; pointer-events:none; }
        .search-wrap input { padding-left:36px; }
    </style>
</head>
<body>
<main class="main-content"><div class="content-inner">

<?php if ($flash): ?>
<div class="alert-cems alert-<?= $flash['type'] ?> mb-3">
    <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert-cems alert-danger mb-3">
    <i class="fas fa-exclamation-circle"></i>
    <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
</div>
<?php endif; ?>

<?php if ($action === 'create' || $action === 'edit'):
    $f = $edit_fac ?? [];
    $is_edit = ($action === 'edit');
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-chalkboard-teacher me-2" style="color:var(--accent);font-size:1rem;"></i>
            <?= $is_edit ? 'Edit Faculty' : 'Add Faculty Member' ?></h1>
        <p><?= $is_edit ? 'Update faculty details. Changes apply immediately.' : 'Add a new faculty account. They can log in and create events immediately.' ?></p>
    </div>
    <a href="faculty.php" class="btn-outline-custom"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="form-card">
    <form method="POST">
        <?php if ($is_edit): ?>
        <input type="hidden" name="edit_id" value="<?= $f['faculty_id'] ?>">
        <?php endif; ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label-custom">Full Name *</label>
                <input type="text" name="full_name" class="form-control-custom" required
                       value="<?= htmlspecialchars($f['full_name'] ?? $_POST['full_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">Username * <small style="text-transform:none;font-weight:400;">(used to log in)</small></label>
                <input type="text" name="username" class="form-control-custom" required
                       placeholder="e.g. prof.gopal or gopal_krishna"
                       value="<?= htmlspecialchars($f['username'] ?? $_POST['username'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">Email Address *</label>
                <input type="email" name="email" class="form-control-custom" required
                       value="<?= htmlspecialchars($f['email'] ?? $_POST['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">Department</label>
                <select name="department" class="form-control-custom">
                    <option value="">— Select Department —</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept ?>" <?= ($f['department'] ?? $_POST['department'] ?? '') === $dept ? 'selected' : '' ?>><?= $dept ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">
                    Password <?= $is_edit ? '<small style="text-transform:none;font-weight:400;">(leave blank to keep current)</small>' : '*' ?>
                </label>
                <input type="password" name="password" class="form-control-custom"
                       placeholder="<?= $is_edit ? 'Leave blank to keep unchanged' : 'Min. 8 characters' ?>"
                       <?= $is_edit ? '' : 'required' ?>>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn-primary-custom">
                <i class="fas fa-<?= $is_edit ? 'save' : 'plus' ?>"></i> <?= $is_edit ? 'Save Changes' : 'Add Faculty' ?>
            </button>
            <a href="faculty.php" class="btn-outline-custom">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-chalkboard-teacher me-2" style="color:var(--accent);font-size:1rem;"></i>Faculty</h1>
        <p><?= count($faculty_list) ?> faculty member<?= count($faculty_list) !== 1 ? 's' : '' ?></p>
    </div>
    <a href="faculty.php?action=create" class="btn-primary-custom"><i class="fas fa-plus"></i> Add Faculty</a>
</div>

<div class="table-wrap mb-3" style="padding:14px 18px;">
    <form method="GET" class="search-wrap" style="max-width:400px;">
        <i class="fas fa-search"></i>
        <input type="text" name="search" class="form-control-custom"
               placeholder="Search name, username, department..."
               value="<?= htmlspecialchars($search) ?>">
    </form>
</div>

<div class="table-wrap">
    <table class="cems-table">
        <thead>
            <tr><th>#</th><th>Name</th><th>Username</th><th>Email</th><th>Department</th><th>Events</th><th>Joined</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($faculty_list as $i => $f): ?>
        <tr>
            <td style="color:var(--text-muted);"><?= $i + 1 ?></td>
            <td><strong><?= htmlspecialchars($f['full_name']) ?></strong></td>
            <td style="color:var(--text-muted);">@<?= htmlspecialchars($f['username']) ?></td>
            <td style="color:var(--text-muted);"><?= htmlspecialchars($f['email']) ?></td>
            <td style="color:var(--text-muted);"><?= htmlspecialchars($f['department'] ?: '—') ?></td>
            <td><strong><?= $f['event_count'] ?></strong></td>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($f['created_at'])) ?></td>
            <td>
                <div class="d-flex gap-1">
                    <a href="faculty.php?action=edit&id=<?= $f['faculty_id'] ?>" class="btn-outline-custom btn-sm-custom"><i class="fas fa-edit"></i></a>
                    <button class="btn-danger-custom btn-sm-custom"
                            onclick="confirmDelete(<?= $f['faculty_id'] ?>, '<?= addslashes(htmlspecialchars($f['full_name'])) ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($faculty_list)): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">No faculty found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</div></main>

<div class="modal fade" id="delModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:430px;">
        <div class="modal-content" style="border:none;border-radius:var(--radius-md);overflow:hidden;">
            <div class="modal-header">
                <h5 class="modal-title" style="font-family:var(--font-display);font-weight:800;font-size:1rem;">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Faculty Member?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:20px 24px;font-size:0.88rem;">
                Delete <strong id="delName"></strong>?<br>
                <span style="color:var(--danger);font-size:0.8rem;">All events created by this faculty and all related student registrations and attendance will also be deleted.</span>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--border);padding:12px 22px;gap:8px;">
                <button class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
                <a id="delBtn" href="#" class="btn-danger-custom" style="padding:8px 18px;">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id, name) {
    document.getElementById('delName').textContent = name;
    document.getElementById('delBtn').href = 'faculty.php?action=delete&id=' + id;
    new bootstrap.Modal(document.getElementById('delModal')).show();
}
</script>
</body>
</html>
