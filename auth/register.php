<?php
session_start();
require_once("../config/db.php");

if (isset($_SESSION['student_id'])) { header("Location: ../student/dashboard.php"); exit(); }

$errors = [];
$old    = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $old = [
        'full_name'     => trim($_POST['full_name']     ?? ''),
        'enrollment_no' => trim($_POST['enrollment_no'] ?? ''),
        'email'         => trim($_POST['email']         ?? ''),
        'phone'         => trim($_POST['phone']         ?? ''),
    ];
    $password         = $_POST['password']         ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($old['full_name']))
        $errors[] = "Full name is required.";
    if (empty($old['enrollment_no']))
        $errors[] = "Enrollment number is required.";
    elseif (!preg_match('/^\d{11}$/', $old['enrollment_no']))
        $errors[] = "Enrollment number must be exactly 11 digits (numbers only).";
    if (empty($old['email']))
        $errors[] = "Email address is required.";
    elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))
        $errors[] = "Enter a valid email address.";
    if (empty($password))
        $errors[] = "Password is required.";
    elseif (strlen($password) < 8)
        $errors[] = "Password must be at least 8 characters.";
    elseif ($password !== $confirm_password)
        $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT student_id FROM students WHERE enrollment_no = ? OR email = ?");
        $stmt->bind_param("ss", $old['enrollment_no'], $old['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Enrollment number or email is already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $ins = $conn->prepare("INSERT INTO students (full_name, enrollment_no, email, phone, password) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("sssss", $old['full_name'], $old['enrollment_no'], $old['email'], $old['phone'], $hashed);
            if ($ins->execute()) {
                $_SESSION['reg_msg'] = "Account created successfully! Please sign in.";
                header("Location: login.php"); exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register — CEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .input-icon-wrap { position: relative; }
        .input-icon-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #b0bec5; font-size: 0.9rem; }
        .input-icon-wrap .form-control-custom { padding-left: 40px; }
        .btn-register {
            width: 100%; padding: 13px;
            background: var(--primary); color: white;
            border: none; border-radius: var(--radius-sm);
            font-family: var(--font-display); font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: var(--transition);
        }
        .btn-register:hover { background: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(26,35,126,0.3); }
        .auth-container { min-height: 640px; }
        .password-strength { height: 4px; border-radius: 2px; margin-top: 6px; transition: all 0.3s; }
        .strength-weak   { width: 33%; background: #ef5350; }
        .strength-medium { width: 66%; background: #ffa726; }
        .strength-strong { width: 100%; background: #66bb6a; }
        .strength-text { font-size: 0.75rem; margin-top: 4px; font-weight: 600; }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-container">

        <!-- Brand Side -->
        <div class="auth-brand">
            <div style="position:relative;z-index:1;">
                <div style="font-size:0.75rem;font-weight:700;letter-spacing:2px;opacity:0.6;text-transform:uppercase;margin-bottom:10px;">Join</div>
                <h1>CEMS Portal</h1>
                <p>College Event Management System</p>
                <hr>
                <div style="margin-top:24px;opacity:0.85;font-size:0.88rem;line-height:1.8;">
                    <div style="margin-bottom:10px;"><i class="fas fa-check-circle me-2" style="opacity:0.7;"></i>Register for campus events</div>
                    <div style="margin-bottom:10px;"><i class="fas fa-check-circle me-2" style="opacity:0.7;"></i>Track your registrations</div>
                    <div style="margin-bottom:10px;"><i class="fas fa-check-circle me-2" style="opacity:0.7;"></i>View attendance status</div>
                    <div style="margin-bottom:10px;"><i class="fas fa-check-circle me-2" style="opacity:0.7;"></i>Download your certificates</div>
                    <div><i class="fas fa-check-circle me-2" style="opacity:0.7;"></i>Get event announcements</div>
                </div>
            </div>
        </div>

        <!-- Form Side -->
        <div class="auth-form-side">
            <h2>Create Account</h2>
            <p class="subtitle">Student registration — fill in your details below</p>

            <?php if (!empty($errors)): ?>
            <div class="alert-cems alert-danger mb-3">
                <i class="fas fa-exclamation-circle me-1"></i>
                <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label-custom">Full Name *</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-user"></i>
                            <input type="text" name="full_name" class="form-control-custom"
                                   placeholder="Enter your full name" required
                                   value="<?= htmlspecialchars($old['full_name'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Enrollment No. * <small style="text-transform:none;font-weight:400;">(11 digits)</small></label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-id-badge"></i>
                            <input type="text" name="enrollment_no" class="form-control-custom"
                                   placeholder="e.g. 92410103090" required maxlength="11"
                                   value="<?= htmlspecialchars($old['enrollment_no'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Phone Number</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-phone"></i>
                            <input type="text" name="phone" class="form-control-custom"
                                   placeholder="Optional"
                                   value="<?= htmlspecialchars($old['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label-custom">Email Address *</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" class="form-control-custom"
                                   placeholder="your@gmail.com" required
                                   value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Password *</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="passwordInput"
                                   class="form-control-custom" placeholder="Min. 8 characters" required>
                        </div>
                        <div class="password-strength" id="strengthBar"></div>
                        <div class="strength-text" id="strengthText" style="color:#b0bec5;"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Confirm Password *</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="confirm_password" id="confirmInput"
                                   class="form-control-custom" placeholder="Re-enter password" required>
                        </div>
                        <div class="strength-text" id="matchText" style="color:#b0bec5;"></div>
                    </div>
                    <div class="col-12" style="margin-top:6px;">
                        <button type="submit" class="btn-register">
                            <i class="fas fa-user-plus me-2"></i> Create My Account
                        </button>
                    </div>
                </div>
            </form>

            <div class="text-center mt-4" style="font-size:0.88rem;color:var(--text-muted);">
                Already have an account?
                <a href="login.php" style="color:var(--accent);font-weight:600;">Sign in &rarr;</a>
            </div>
        </div>
    </div>
</div>

<script>
const pw = document.getElementById('passwordInput');
const cf = document.getElementById('confirmInput');
const sb = document.getElementById('strengthBar');
const st = document.getElementById('strengthText');
const mt = document.getElementById('matchText');

pw.addEventListener('input', () => {
    const v = pw.value;
    sb.className = 'password-strength';
    if (!v.length) { st.textContent = ''; return; }
    if (v.length < 8) {
        sb.classList.add('strength-weak');
        st.textContent = 'Too short'; st.style.color = '#ef5350';
    } else if (v.length < 12 || !/[A-Z]/.test(v) || !/[0-9]/.test(v)) {
        sb.classList.add('strength-medium');
        st.textContent = 'Moderate'; st.style.color = '#ffa726';
    } else {
        sb.classList.add('strength-strong');
        st.textContent = 'Strong ✓'; st.style.color = '#66bb6a';
    }
});

cf.addEventListener('input', () => {
    if (!cf.value) { mt.textContent = ''; return; }
    if (cf.value === pw.value) {
        mt.textContent = 'Passwords match ✓'; mt.style.color = '#66bb6a';
    } else {
        mt.textContent = 'Does not match'; mt.style.color = '#ef5350';
    }
});
</script>
</body>
</html>
