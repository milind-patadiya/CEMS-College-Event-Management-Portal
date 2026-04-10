<?php
// Shared Student Sidebar — include at top of every student page
// Requires: $_SESSION['student_name'], $_SESSION['enrollment_no']
// Requires: $active_page variable set before including (e.g. 'dashboard', 'events', etc.)
if (!isset($_SESSION['student_id'])) { header("Location: ../auth/login.php"); exit(); }
$active_page = $active_page ?? '';
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-title">CEMS Portal</div>
        <div class="brand-role role-student mt-2"><i class="fas fa-user-graduate me-1"></i>Student</div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main Menu</div>
        <a href="dashboard.php" class="nav-link <?= $active_page === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
        <a href="events.php" class="nav-link <?= $active_page === 'events' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> All Events
        </a>
        <a href="registrations.php" class="nav-link <?= $active_page === 'registrations' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i> My Registrations
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
        <div class="user-name"><i class="fas fa-circle text-success me-1" style="font-size:0.5rem;vertical-align:middle;"></i><?= htmlspecialchars($_SESSION['student_name']) ?></div>
        <div class="user-id mt-1"><?= htmlspecialchars($_SESSION['enrollment_no']) ?></div>
    </div>
</aside>