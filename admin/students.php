<?php
session_start();
$active_page = 'students';
require_once('../config/db.php');
require_once('../includes/admin_sidebar.php');

$action     = $_GET['action'] ?? 'list';
$student_id = (int)($_GET['id'] ?? 0);
$errors     = [];

if ($action === 'delete' && $student_id) {
    try {
        $d = $conn->prepare("DELETE FROM students WHERE student_id = ?");
        $d->bind_param("i", $student_id); $d->execute();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Student deleted successfully.'];
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Cannot delete — student may have existing records.'];
    }
    header("Location: students.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name     = trim($_POST['full_name']     ?? '');
    $enrollment_no = trim($_POST['enrollment_no'] ?? '');
    $email         = trim($_POST['email']         ?? '');
    $phone         = trim($_POST['phone']         ?? '');
    $password      = $_POST['password']           ?? '';
    $edit_id       = (int)($_POST['edit_id']      ?? 0);

    if (!$full_name)                                              $errors[] = 'Full name is required.';
    if (!$enrollment_no)                                          $errors[] = 'Enrollment number is required.';
    elseif (!preg_match('/^\d{11}$/', $enrollment_no))            $errors[] = 'Enrollment number must be exactly 11 digits.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'A valid email address is required.';
    if (!$edit_id && strlen($password) < 8)                       $errors[] = 'Password must be at least 8 characters.';

    if (empty($errors)) {
        try {
            if ($edit_id) {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $s = $conn->prepare("UPDATE students SET full_name=?,enrollment_no=?,email=?,phone=?,password=? WHERE student_id=?");
                    $s->bind_param("sssssi", $full_name, $enrollment_no, $email, $phone, $hash, $edit_id);
                } else {
                    $s = $conn->prepare("UPDATE students SET full_name=?,enrollment_no=?,email=?,phone=? WHERE student_id=?");
                    $s->bind_param("ssssi", $full_name, $enrollment_no, $email, $phone, $edit_id);
                }
                $s->execute();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Student updated. Changes immediately visible across all modules.'];
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $s = $conn->prepare("INSERT INTO students (full_name,enrollment_no,email,phone,password) VALUES (?,?,?,?,?)");
                $s->bind_param("sssss", $full_name, $enrollment_no, $email, $phone, $hash);
                $s->execute();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Student added successfully. They can now log in.'];
            }
            header("Location: students.php"); exit();
        } catch (Exception $e) {
            $errors[] = strpos($e->getMessage(), 'Duplicate') !== false
                ? 'Enrollment number or email already exists.'
                : 'Database error: ' . $e->getMessage();
        }
    }
    $action = $edit_id ? 'edit' : 'create';
}

$edit_student = null;
if ($action === 'edit' && $student_id) {
    $q = $conn->prepare("SELECT * FROM students WHERE student_id=?");
    $q->bind_param("i", $student_id); $q->execute();
    $edit_student = $q->get_result()->fetch_assoc();
    if (!$edit_student) { header("Location: students.php"); exit(); }
}

$students = [];
$search   = trim($_GET['search'] ?? '');
if ($action === 'list') {
    try {
        if ($search !== '') {
            $like = "%$search%";
            $q = $conn->prepare("
                SELECT s.*, COUNT(r.registration_id) AS reg_count
                FROM students s LEFT JOIN registrations r ON r.student_id=s.student_id
                WHERE s.full_name LIKE ? OR s.enrollment_no LIKE ? OR s.email LIKE ?
                GROUP BY s.student_id ORDER BY s.created_at DESC
            ");
            $q->bind_param("sss", $like, $like, $like); $q->execute();
        } else {
            $q = $conn->query("
                SELECT s.*, COUNT(r.registration_id) AS reg_count
                FROM students s LEFT JOIN registrations r ON r.student_id=s.student_id
                GROUP BY s.student_id ORDER BY s.created_at DESC
            ");
        }
        $students = $q->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {}
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Students — Admin | CEMS</title>
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
    $st = $edit_student ?? [];
    $is_edit = ($action === 'edit');
?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-user-graduate me-2" style="color:var(--accent);font-size:1rem;"></i>
            <?= $is_edit ? 'Edit Student' : 'Add New Student' ?></h1>
        <p><?= $is_edit ? 'Update student details. Changes are reflected immediately.' : 'Add a new student account. They can log in immediately after.' ?></p>
    </div>
    <a href="students.php" class="btn-outline-custom"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="form-card">
    <form method="POST">
        <?php if ($is_edit): ?>
        <input type="hidden" name="edit_id" value="<?= $st['student_id'] ?>">
        <?php endif; ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label-custom">Full Name *</label>
                <input type="text" name="full_name" class="form-control-custom" required
                       value="<?= htmlspecialchars($st['full_name'] ?? $_POST['full_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">Enrollment Number * <small style="text-transform:none;font-weight:400;">(11 digits)</small></label>
                <input type="text" name="enrollment_no" class="form-control-custom" required maxlength="11"
                       placeholder="e.g. 22CS0010001"
                       value="<?= htmlspecialchars($st['enrollment_no'] ?? $_POST['enrollment_no'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">Email Address *</label>
                <input type="email" name="email" class="form-control-custom" required
                       value="<?= htmlspecialchars($st['email'] ?? $_POST['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">Phone Number</label>
                <input type="text" name="phone" class="form-control-custom" maxlength="15"
                       value="<?= htmlspecialchars($st['phone'] ?? $_POST['phone'] ?? '') ?>">
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
                <i class="fas fa-<?= $is_edit ? 'save' : 'plus' ?>"></i> <?= $is_edit ? 'Save Changes' : 'Add Student' ?>
            </button>
            <a href="students.php" class="btn-outline-custom">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="page-header">
    <div>
        <h1><i class="fas fa-user-graduate me-2" style="color:var(--accent);font-size:1rem;"></i>Students</h1>
        <p><?= count($students) ?> student<?= count($students) !== 1 ? 's' : '' ?> in the system</p>
    </div>
    <a href="students.php?action=create" class="btn-primary-custom"><i class="fas fa-plus"></i> Add Student</a>
</div>

<div class="table-wrap mb-3" style="padding:14px 18px;">
    <form method="GET" class="search-wrap" style="max-width:400px;">
        <i class="fas fa-search"></i>
        <input type="text" name="search" class="form-control-custom"
               placeholder="Search name, enrollment no, email..."
               value="<?= htmlspecialchars($search) ?>">
    </form>
</div>

<div class="table-wrap">
    <table class="cems-table">
        <thead>
            <tr><th>#</th><th>Name</th><th>Enrollment No.</th><th>Email</th><th>Phone</th><th>Reg.</th><th>Joined</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($students as $i => $s): ?>
        <tr>
            <td style="color:var(--text-muted);"><?= $i + 1 ?></td>
            <td><strong><?= htmlspecialchars($s['full_name']) ?></strong></td>
            <td style="color:var(--text-muted);"><?= htmlspecialchars($s['enrollment_no']) ?></td>
            <td style="color:var(--text-muted);"><?= htmlspecialchars($s['email']) ?></td>
            <td style="color:var(--text-muted);"><?= htmlspecialchars($s['phone'] ?: '—') ?></td>
            <td><strong><?= $s['reg_count'] ?></strong></td>
            <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
            <td>
                <div class="d-flex gap-1">
                    <a href="students.php?action=edit&id=<?= $s['student_id'] ?>" class="btn-outline-custom btn-sm-custom"><i class="fas fa-edit"></i></a>
                    <button class="btn-danger-custom btn-sm-custom"
                            onclick="confirmDelete(<?= $s['student_id'] ?>, '<?= addslashes(htmlspecialchars($s['full_name'])) ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">
            No students found<?= $search ? ' for "'.htmlspecialchars($search).'"' : '' ?>.</td></tr>
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
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Student?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:20px 24px;font-size:0.88rem;">
                Delete <strong id="delName"></strong>?<br>
                <span style="color:var(--danger);font-size:0.8rem;">All registrations, attendance records, and certificates for this student will also be deleted.</span>
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
    document.getElementById('delBtn').href = 'students.php?action=delete&id=' + id;
    new bootstrap.Modal(document.getElementById('delModal')).show();
}
</script>
</body>
</html>
