<?php
if (!isset($_SESSION['admin_id'])) { header("Location: ../auth/login.php"); exit(); }
$active_page = $active_page ?? '';
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-title">CEMS Portal</div>
        <div class="brand-role role-admin mt-2"><i class="fas fa-user-shield me-1"></i>Admin / HOD</div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="dashboard.php" class="nav-link <?= $active_page === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i> Dashboard
        </a>

        <div class="nav-section-label">Event Control</div>
        <a href="events.php" class="nav-link <?= $active_page === 'events' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> Events (CRUD)
        </a>
        <a href="approvals.php" class="nav-link <?= $active_page === 'approvals' ? 'active' : '' ?>">
            <i class="fas fa-check-double"></i> Approvals
        </a>

        <div class="nav-section-label">People</div>
        <a href="students.php" class="nav-link <?= $active_page === 'students' ? 'active' : '' ?>">
            <i class="fas fa-user-graduate"></i> Students
        </a>
        <a href="faculty.php" class="nav-link <?= $active_page === 'faculty' ? 'active' : '' ?>">
            <i class="fas fa-chalkboard-teacher"></i> Faculty
        </a>

        <div class="nav-section-label">Reports</div>
        <a href="registrations.php" class="nav-link <?= $active_page === 'registrations' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i> Registrations
        </a>
        <a href="attendance.php" class="nav-link <?= $active_page === 'attendance' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i> Attendance
        </a>
        <a href="certificates.php" class="nav-link <?= $active_page === 'certificates' ? 'active' : '' ?>">
            <i class="fas fa-award"></i> Certificates
        </a>
        <a href="announcements.php" class="nav-link <?= $active_page === 'announcements' ? 'active' : '' ?>">
            <i class="fas fa-bullhorn"></i> Announcements
        </a>

        <hr style="border-color:var(--border);margin:10px 0;">
        <a href="../auth/logout.php" class="nav-link nav-logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <div class="sidebar-user">
        <div class="user-name"><i class="fas fa-circle text-success me-1" style="font-size:0.5rem;vertical-align:middle;"></i><?= htmlspecialchars($_SESSION['admin_name']) ?></div>
        <div class="user-id mt-1">Administrator</div>
    </div>
</aside>