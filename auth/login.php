<?php
session_start();
require_once("../config/db.php");

// Already logged in? Redirect
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
if (isset($_SESSION['student_id'])) {
    $go = ($redirect === 'events') ? '../student/events.php' : '../student/dashboard.php';
    header("Location: $go"); exit();
}
if (isset($_SESSION['faculty_id']))  { header("Location: ../faculty/dashboard.php");  exit(); }
if (isset($_SESSION['admin_id']))    { header("Location: ../admin/dashboard.php");    exit(); }

$error = "";
$active_role = $_POST['role'] ?? 'student';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $role     = $_POST['role'] ?? 'student';
    $password = $_POST['password'] ?? '';

    if ($role === 'student') {
        $enrollment = trim($_POST['identifier'] ?? '');
        $stmt = $conn->prepare("SELECT * FROM students WHERE enrollment_no = ?");
        $stmt->bind_param("s", $enrollment);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['student_id']    = $user['student_id'];
            $_SESSION['student_name']  = $user['full_name'];
            $_SESSION['enrollment_no'] = $user['enrollment_no'];
            $_SESSION['role']          = 'student';
            $go = ($redirect === 'events') ? '../student/events.php' : '../student/dashboard.php';
            header("Location: $go"); exit();
        }
        $error = "Invalid Enrollment Number or Password.";

    } elseif ($role === 'faculty') {
        $username = trim($_POST['identifier'] ?? '');
        $stmt = $conn->prepare("SELECT * FROM faculty WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['faculty_id']   = $user['faculty_id'];
            $_SESSION['faculty_name'] = $user['full_name'];
            $_SESSION['faculty_user'] = $user['username'];
            $_SESSION['role']         = 'faculty';
            header("Location: ../faculty/dashboard.php"); exit();
        }
        $error = "Invalid Username or Password.";

    } elseif ($role === 'admin') {
        $username = trim($_POST['identifier'] ?? '');
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_id']   = $user['admin_id'];
            $_SESSION['admin_name'] = $user['full_name'];
            $_SESSION['role']       = 'admin';
            header("Location: ../admin/dashboard.php"); exit();
        }
        $error = "Invalid Username or Password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .input-icon-wrap { position: relative; }
        .input-icon-wrap i {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: #b0bec5; font-size: 0.9rem;
        }
        .input-icon-wrap .form-control-custom { padding-left: 40px; }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-family: var(--font-display);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            letter-spacing: 0.3px;
        }
        .btn-login:hover { background: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(26,35,126,0.3); }

        .divider { display: flex; align-items: center; gap: 12px; margin: 20px 0; color: #ccc; font-size: 0.8rem; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .auth-brand .feature-item {
            display: flex; align-items: flex-start; gap: 12px;
            margin-bottom: 16px; opacity: 0.85;
        }
        .auth-brand .feature-icon {
            width: 32px; height: 32px; background: rgba(255,255,255,0.15);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; flex-shrink: 0; font-size: 0.9rem;
        }
        .auth-brand .feature-text { font-size: 0.85rem; line-height: 1.5; }
        .auth-brand .feature-text strong { display: block; font-size: 0.9rem; margin-bottom: 1px; opacity: 1; }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-container">

        <!-- Brand Side -->
        <div class="auth-brand">
            <div style="position:relative;z-index:1;">
                <div style="font-size:0.75rem;font-weight:700;letter-spacing:2px;opacity:0.6;text-transform:uppercase;margin-bottom:10px;">Welcome to</div>
                <h1>CEMS Portal</h1>
                <p style="margin-bottom:30px;">College Event Management System</p>
                <hr>
                <div style="margin-top:24px;">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="feature-text">
                            <strong>Event Management</strong>
                            Browse, register & track campus events
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-clipboard-check"></i></div>
                        <div class="feature-text">
                            <strong>Attendance Tracking</strong>
                            Real-time present / absent records
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-award"></i></div>
                        <div class="feature-text">
                            <strong>Certificates</strong>
                            Download participation certificates instantly
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-bullhorn"></i></div>
                        <div class="feature-text">
                            <strong>Announcements</strong>
                            Stay updated with college notices
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Side -->
        <div class="auth-form-side">
            <h2>Sign In</h2>
            <p class="subtitle">Select your role to continue</p>

            <!-- Role Tabs -->
            <div class="role-tabs" id="roleTabs">
                <button type="button" class="role-tab <?= $active_role === 'student' ? 'active' : '' ?>" data-role="student">
                    <i class="fas fa-user-graduate me-1"></i> Student
                </button>
                <button type="button" class="role-tab <?= $active_role === 'faculty' ? 'active' : '' ?>" data-role="faculty">
                    <i class="fas fa-chalkboard-teacher me-1"></i> Faculty
                </button>
                <button type="button" class="role-tab <?= $active_role === 'admin' ? 'active' : '' ?>" data-role="admin">
                    <i class="fas fa-user-shield me-1"></i> Admin
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert-cems alert-danger mb-3">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['reg_msg'])): ?>
                <div class="alert-cems alert-success mb-3">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['reg_msg']); unset($_SESSION['reg_msg']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($active_role) ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <div class="form-group">
                    <label class="form-label-custom" id="identifierLabel">
                        <?= $active_role === 'student' ? 'Enrollment Number' : 'Username' ?>
                    </label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-id-card" id="identifierIcon"></i>
                        <input type="text" name="identifier" id="identifierInput"
                               class="form-control-custom"
                               placeholder="<?= $active_role === 'student' ? 'Enter your enrollment number' : 'Enter your username' ?>"
                               value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                               required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label-custom">Password</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control-custom"
                               placeholder="••••••••" required autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> Sign In
                </button>
            </form>

            <div id="registerLink" style="<?= $active_role !== 'student' ? 'display:none;' : '' ?>">
                <div class="divider">or</div>
                <div class="text-center" style="font-size:0.88rem; color: var(--text-muted);">
                    New student?
                    <a href="register.php" style="color:var(--accent);font-weight:600;">Create an account →</a>
                </div>
            </div>

            <div class="text-center mt-4" style="font-size:0.78rem; color:#c5cad4;">
                CEMS — College Event Management System
            </div>
        </div>
    </div>
</div>

<script>
    const tabs        = document.querySelectorAll('.role-tab');
    const roleInput   = document.getElementById('roleInput');
    const idLabel     = document.getElementById('identifierLabel');
    const idInput     = document.getElementById('identifierInput');
    const regLink     = document.getElementById('registerLink');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const role = tab.dataset.role;
            roleInput.value = role;
            idInput.value   = '';

            if (role === 'student') {
                idLabel.textContent     = 'Enrollment Number';
                idInput.placeholder     = 'Enter your enrollment number';
                regLink.style.display   = '';
            } else {
                idLabel.textContent     = 'Username';
                idInput.placeholder     = 'Enter your username';
                regLink.style.display   = 'none';
            }
        });
    });
</script>
</body>
</html>