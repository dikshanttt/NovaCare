<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../include/function.php';
require_once __DIR__ . '/../config/config.php';
require_login(['admin']);

$db = getDB();

// Aggregated Stats
$totalHospitals = (int)$db->query("SELECT COUNT(*) FROM hospitals WHERE is_active = TRUE")->fetchColumn();
$activeDoctors  = (int)$db->query("SELECT COUNT(DISTINCT d.user_id) FROM doctor_profiles d JOIN users u ON d.user_id = u.id WHERE d.verification_status = 'verified' AND u.status = 'active'")->fetchColumn();
$pendingDoctors = (int)$db->query("SELECT COUNT(*) FROM doctor_profiles WHERE verification_status = 'pending'")->fetchColumn();
$pendingSched   = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending_approval'")->fetchColumn();
$pendingApps    = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status IN ('pending', 'pending_hospital_approval')")->fetchColumn();
$confirmedApps  = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status = 'confirmed'")->fetchColumn();
$totalPatients  = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();

// 5 most recent sign-ups
$recentUsers = $db->query("
    SELECT u.id, u.email, u.role, u.status, u.created_at,
           COALESCE(p.name, dp.name, ap.name, 'N/A') AS display_name
    FROM users u
    LEFT JOIN patient_profiles p  ON p.user_id  = u.id AND u.role = 'patient'
    LEFT JOIN doctor_profiles  dp ON dp.user_id = u.id AND u.role = 'doctor'
    LEFT JOIN admin_profiles   ap ON ap.user_id = u.id AND u.role = 'admin'
    ORDER BY u.created_at DESC
    LIMIT 5
")->fetchAll();

// Pending schedule requests preview
$pendingSchedPreview = $db->query("
    SELECT s.*, dp.name AS doctor_name, h.name AS hospital_name
    FROM schedules s
    JOIN doctor_profiles dp ON dp.user_id = s.doctor_id
    JOIN hospitals h ON h.id = s.hospital_id
    WHERE s.status = 'pending_approval'
    ORDER BY s.requested_at ASC
    LIMIT 3
")->fetchAll();

// Recent appointments preview
$recentAppsPreview = $db->query("
    SELECT a.*, p.name AS patient_name, dp.name AS doctor_name, h.name AS hospital_name
    FROM appointments a
    JOIN patient_profiles p ON p.user_id = a.patient_id
    LEFT JOIN doctor_profiles dp ON dp.user_id = a.doctor_id
    LEFT JOIN hospitals h ON h.id = a.hospital_id
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetchAll();

$flash = get_flash();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Operations | HAMS Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/admin_style.css">
</head>

<body class="admin-page">

    <div class="adm-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="admin-shell">
        <aside class="adm-sidebar" id="adminSidebar">
            <div class="sidebar-top">
                <a class="adm-brand" href="dashobard.php">
                    <span class="adm-brand-icon">✚</span>
                    <div class="adm-brand-text">
                        <strong>HAMS</strong>
                        <small>Admin Console</small>
                    </div>
                </a>

                <button class="sidebar-close" onclick="closeSidebar()" aria-label="Close navigation">×</button>
            </div>

            <div class="sidebar-section-title">Workspace</div>

            <nav class="adm-nav">
                <a class="active" href="dashboard.php">
                    <span class="nav-icon">⊞</span>
                    <span>Overview</span>
                </a>
                <a href="hospitals.php">
                    <span class="nav-icon">🏥</span>
                    <span>Manage Hospitals</span>
                </a>
                <a href="verify_doctor.php">
                    <span class="nav-icon">🩺</span>
                    <span>Verify &amp; Affiliations</span>
                    <?php if ($pendingDoctors > 0): ?><span class="adm-badge"><?= $pendingDoctors ?></span><?php endif; ?>
                </a>
                <a href="schedule_approval.php">
                    <span class="nav-icon">📅</span>
                    <span>Schedule Approvals</span>
                    <?php if ($pendingSched > 0): ?><span class="adm-badge"><?= $pendingSched ?></span><?php endif; ?>
                </a>
                <a href="appointments.php">
                    <span class="nav-icon">📋</span>
                    <span>Appointments</span>
                    <?php if ($pendingApps > 0): ?><span class="adm-badge" style="background:#0369a1;color:#fff"><?= $pendingApps ?></span><?php endif; ?>
                </a>
            </nav>

            <div class="sidebar-bottom">
                <div class="network-status">
                    <span class="status-pulse"></span>
                    <div>
                        <strong>Network Online</strong>
                        <small>All systems operational</small>
                    </div>
                </div>
                <form method="POST" action="../logout.php" style="margin:0;">
                    <?= csrf_field() ?>
                    <button type="submit" class="signout-link" style="width:100%; border:0; background:transparent; cursor:pointer; text-align:left;">
                        <span class="nav-icon">↩</span>
                        Sign Out
                    </button>
                </form>
            </div>
        </aside>

        <main class="adm-content">
            <header class="adm-topbar">
                <button class="adm-mob-toggle" id="sidebarToggle" onclick="openSidebar()" aria-label="Open navigation">☰</button>

                <div class="breadcrumb">
                    <span>HAMS</span>
                    <b>/</b>
                    <strong>Dashboard</strong>
                </div>

                <div class="topbar-right">
                    <div class="topbar-search">
                        <span class="search-icon">⌕</span>
                        <input type="text" placeholder="Search records..." aria-label="Search">
                    </div>

                    <button class="topbar-icon-btn" aria-label="Notifications">
                        ♧
                        <span class="notif-dot"></span>
                    </button>

                    <div class="admin-profile-pill">
                        <div class="avatar-circle">SA</div>
                        <div class="profile-copy">
                            <strong>Super Admin</strong>
                            <small>Administrator</small>
                        </div>
                        <!-- <span class="profile-chevron">⌄</span> -->
                    </div>
                </div>
            </header>

            <div class="adm-body">
                <section class="welcome-row">
                    <div>
                        <p class="eyebrow"><?= date('l, F j, Y') ?></p>
                        <h1>Good morning, Admin.</h1>
                        <p class="welcome-text">Here is the current activity across the hospital network.</p>
                    </div>
                    <a href="appointments.php" class="overview-button">View appointments <span>→</span></a>
                </section>

                <section class="metric-grid">
                    <article class="metric-card">
                        <div class="metric-icon green">🏥</div>
                        <div class="metric-copy">
                            <span>Partner Hospitals</span>
                            <strong><?= $totalHospitals ?></strong>
                            <small>Accredited facilities</small>
                        </div>
                        <span class="metric-arrow">↗</span>
                    </article>

                    <article class="metric-card">
                        <div class="metric-icon blue">🩺</div>
                        <div class="metric-copy">
                            <span>Verified Doctors</span>
                            <strong><?= $activeDoctors ?></strong>
                            <small><b><?= $pendingDoctors ?></b> awaiting review</small>
                        </div>
                        <span class="metric-arrow">↗</span>
                    </article>

                    <article class="metric-card attention">
                        <div class="metric-icon gold">⏳</div>
                        <div class="metric-copy">
                            <span>Schedule Requests</span>
                            <strong><?= $pendingSched ?></strong>
                            <small>Need approval</small>
                        </div>
                        <span class="metric-arrow">↗</span>
                    </article>

                    <article class="metric-card">
                        <div class="metric-icon teal">📋</div>
                        <div class="metric-copy">
                            <span>Pending Bookings</span>
                            <strong><?= $pendingApps ?></strong>
                            <small>Awaiting confirmation</small>
                        </div>
                        <span class="metric-arrow">↗</span>
                    </article>
                </section>

                <section class="priority-strip">
                    <div class="priority-title">
                        <span class="priority-mark">!</span>
                        <div>
                            <strong>Action required</strong>
                            <small>Items waiting for your review</small>
                        </div>
                    </div>

                    <a href="verify_doctors.php" class="priority-item">
                        <span class="priority-number blue">
                            <?= str_pad($pendingDoctors, 2, '0', STR_PAD_LEFT) ?>
                        </span>
                        <div>
                            <strong>Doctor applications</strong>
                            <small>Medical license verification</small>
                        </div>
                        <span>→</span>
                    </a>

                    <a href="schedule_approvals.php" class="priority-item">
                        <span class="priority-number gold">
                            <?= str_pad($pendingSched, 2, '0', STR_PAD_LEFT) ?>
                        </span>
                        <div>
                            <strong>Schedule changes</strong>
                            <small>New consultation slots</small>
                        </div>
                        <span>→</span>
                    </a>
                </section>

                <section class="dashboard-layout">
                    <div class="panel appointment-panel">
                        <div class="panel-head">
                            <div>
                                <span class="panel-kicker">LIVE QUEUE</span>
                                <h2>Recent Appointment Requests</h2>
                                <small>Latest bookings across the hospital network</small>
                            </div>
                            <a href="appointments.php" class="panel-link">View all <span>→</span></a>
                        </div>

                        <div class="table-wrap">
                            <table class="adm-table">
                                <thead>
                                    <tr>
                                        <th>Patient / Token</th>
                                        <th>Doctor &amp; Hospital</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php if (empty($recentAppsPreview)): ?>

                                        <tr>
                                            <td colspan="4" style="text-align:center;">
                                                No appointments found.
                                            </td>
                                        </tr>

                                    <?php else: ?>

                                        <?php foreach ($recentAppsPreview as $appointment): ?>

                                            <tr>
                                                <td>
                                                    <div class="person-cell">
                                                        <span class="mini-avatar">
                                                            <?= strtoupper(substr($appointment['patient_name'], 0, 2)) ?>
                                                        </span>

                                                        <div>
                                                            <strong>
                                                                <?= htmlspecialchars($appointment['patient_name']) ?>
                                                            </strong>

                                                            <small>
                                                                <?= htmlspecialchars($appointment['token'] ?? 'N/A') ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td>
                                                    <strong>
                                                        <?= htmlspecialchars($appointment['doctor_name']) ?>
                                                    </strong>

                                                    <small class="muted-block">
                                                        <?= htmlspecialchars($appointment['hospital_name']) ?>
                                                    </small>
                                                </td>

                                                <td>
                                                    <span class="date-cell">
                                                        <?= htmlspecialchars($appointment['appointment_date'] ?? 'N/A') ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <span class="status-badge waiting small-badge">
                                                        <?= htmlspecialchars($appointment['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </tbody>
                            </table>
                        </div>
                    </div>

                    <aside class="panel activity-panel">
                        <div class="panel-head">
                            <div>
                                <span class="panel-kicker">DIRECTORY</span>
                                <h2>Latest Users</h2>
                                <small>Most recent registrations</small>
                            </div>
                        </div>

                        <div class="user-list">

                            <?php if (empty($recentUsers)): ?>

                                <div class="user-row">
                                    <div class="user-info">
                                        <strong>No users found</strong>
                                    </div>
                                </div>

                            <?php else: ?>

                                <?php foreach ($recentUsers as $user): ?>

                                    <?php
                                    $name = $user['display_name'];
                                    $initials = strtoupper(substr($name, 0, 2));
                                    ?>

                                    <div class="user-row">

                                        <span class="user-avatar <?= htmlspecialchars($user['role']) ?>">
                                            <?= htmlspecialchars($initials) ?>
                                        </span>

                                        <div class="user-info">
                                            <strong>
                                                <?= htmlspecialchars($name) ?>
                                            </strong>

                                            <small>
                                                <?= htmlspecialchars($user['email']) ?>
                                            </small>
                                        </div>

                                        <div class="user-meta">

                                            <span class="badge-tag">
                                                <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                            </span>

                                            <span class="status-badge 
                                                <?= $user['status'] === 'active' ? 'done' : 'waiting' ?>">
                                                <?= ucfirst(htmlspecialchars($user['status'])) ?>
                                            </span>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                        <div class="directory-footer">
                            <span>3 recent registrations</span>
                            <span>•</span>
                            <span>Network-wide</span>
                        </div>
                    </aside>
                </section>
            </div>
        </main>
    </div>

    <script>
        function openSidebar() {
            document.querySelector(".adm-sidebar").classList.add("open");
            document.getElementById("sidebarOverlay").classList.add("open");
        }

        function closeSidebar() {
            document.querySelector(".adm-sidebar").classList.remove("open");
            document.getElementById("sidebarOverlay").classList.remove("open");
        }
    </script>
</body>

</html>
