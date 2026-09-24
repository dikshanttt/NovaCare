<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/config.php';
require_login(['admin']);

$db = getDB();
$flash = get_flash();

/* ---------- APPROVE / REJECT DOCTOR ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action   = $_POST['action'] ?? '';
    $doctorId = (int)($_POST['doctor_id'] ?? 0);

    if ($doctorId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $newStatus = $action === 'approve' ? 'verified' : 'rejected';

        $stmt = $db->prepare("
            UPDATE doctor_profiles
            SET verification_status = ?, verified_at = CURRENT_TIMESTAMP
            WHERE user_id = ? AND verification_status = 'pending'
        ");
        $stmt->execute([$newStatus, $doctorId]);

        if ($action === 'approve') {
            $db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$doctorId]);
        }

        set_flash(
            'success',
            $action === 'approve'
                ? 'Doctor verified successfully.'
                : 'Doctor application rejected.'
        );
    }

    redirect('/admin/verify_doctors.php');
}

/* ---------- STATS ---------- */
$pendingDoctors = (int)$db->query("SELECT COUNT(*) FROM doctor_profiles WHERE verification_status = 'pending'")->fetchColumn();
$pendingSched   = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending_approval'")->fetchColumn();
$pendingApps    = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending_hospital_approval'")->fetchColumn();
$affiliationCount = (int)$db->query("
    SELECT COUNT(*) FROM doctor_hospital WHERE status = 'pending'
")->fetchColumn();

/* ---------- PENDING DOCTORS ---------- */
$pendingList = $db->query("
    SELECT
        dp.user_id,
        dp.name,
        dp.specialty,
        dp.verification_status,
        dp.created_at,
        u.email,
        h.name AS hospital_name,
        h.address AS hospital_address
    FROM doctor_profiles dp
    JOIN users u ON u.id = dp.user_id
    LEFT JOIN doctor_hospital dh ON dh.doctor_id = dp.user_id AND dh.status IN ('pending', 'active')
    LEFT JOIN hospitals h ON h.id = dh.hospital_id
    WHERE dp.verification_status = 'pending'
    ORDER BY dp.created_at ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify &amp; Affiliations | HAMS Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/admin_style.css">
    <link rel="stylesheet" href="../assets/css/admin/verify_doctor.css">
</head>

<body class="admin-page">

    <div class="adm-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="admin-shell">
        <aside class="adm-sidebar" id="adminSidebar">
            <div class="sidebar-top">
                <a class="adm-brand" href="dashboard.php">
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
                <a href="dashboard.php">
                    <span class="nav-icon">⊞</span>
                    <span>Overview</span>
                </a>
                <a href="hospitals.php">
                    <span class="nav-icon">🏥</span>
                    <span>Manage Hospitals</span>
                </a>
                <a class="active" href="verify_doctors.php">
                    <span class="nav-icon">🩺</span>
                    <span>Verify &amp; Affiliations</span>
                    <?php if ($pendingDoctors > 0): ?>
                        <span class="adm-badge"><?= $pendingDoctors ?></span>
                    <?php endif; ?>
                </a>
                <a href="schedule_approval.php">
                    <span class="nav-icon">📅</span>
                    <span>Schedule Approvals</span>
                    <?php if ($pendingSched > 0): ?>
                        <span class="adm-badge"><?= $pendingSched ?></span>
                    <?php endif; ?>
                </a>
                <a href="appointments.php">
                    <span class="nav-icon">📋</span>
                    <span>Appointments</span>
                    <?php if ($pendingApps > 0): ?>
                        <span class="adm-badge appointment-badge"><?= $pendingApps ?></span>
                    <?php endif; ?>
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
                <a class="signout-link" href="../logout.php">
                    <span class="nav-icon">↩</span>
                    Sign Out
                </a>
            </div>
        </aside>

        <main class="adm-content">
            <header class="adm-topbar">
                <button class="adm-mob-toggle" id="sidebarToggle" onclick="openSidebar()" aria-label="Open navigation">☰</button>

                <div class="breadcrumb">
                    <span>HAMS</span>
                    <b>/</b>
                    <strong>Verify Doctors</strong>
                </div>

                <div class="topbar-right">
                    <div class="topbar-search">
                        <span class="search-icon">⌕</span>
                        <input type="text" placeholder="Search doctors..." aria-label="Search">
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
                    </div>
                </div>
            </header>

            <div class="adm-body">
                <section class="welcome-row">
                    <div>
                        <p class="eyebrow">Credentials &amp; Affiliations</p>
                        <h1>Verify Doctors</h1>
                        <p class="welcome-text">Review medical licenses and hospital affiliations pending approval.</p>
                    </div>
                </section>

                <?php if ($flash): ?>
                    <div class="flash-message <?= htmlspecialchars($flash['type']) ?>">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <section class="priority-strip verify-priority">
                    <div class="priority-title">
                        <span class="priority-mark">!</span>
                        <div>
                            <strong><?= $pendingDoctors ?> application<?= $pendingDoctors !== 1 ? 's' : '' ?></strong>
                            <small>Awaiting license verification</small>
                        </div>
                    </div>
                    <div class="priority-item static">
                        <span class="priority-number blue"><?= str_pad((string)$affiliationCount, 2, '0', STR_PAD_LEFT) ?></span>
                        <div>
                            <strong>New affiliations</strong>
                            <small>Hospital link requests</small>
                        </div>
                    </div>
                    <div class="priority-item static">
                        <span class="priority-number gold"><?= str_pad((string)$pendingDoctors, 2, '0', STR_PAD_LEFT) ?></span>
                        <div>
                            <strong>Pending review</strong>
                            <small>Doctor applications in queue</small>
                        </div>
                    </div>
                </section>

                <section class="panel verify-panel">
                    <div class="panel-head">
                        <div>
                            <span class="panel-kicker">QUEUE</span>
                            <h2>Pending Verification</h2>
                            <small>Doctor applications requiring review</small>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Doctor</th>
                                    <th>Specialty</th>
                                    <th>Hospital</th>
                                    <th>Submitted</th>
                                    <th>Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingList)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center;padding:40px;">
                                            No pending doctor applications.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pendingList as $doc): ?>
                                        <?php
                                        $initials = strtoupper(substr($doc['name'] ?? 'DR', 0, 2));
                                        $submitted = !empty($doc['created_at'])
                                            ? date('M j', strtotime($doc['created_at']))
                                            : '—';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="person-cell">
                                                    <span class="mini-avatar doctor-av"><?= htmlspecialchars($initials) ?></span>
                                                    <div>
                                                        <strong><?= htmlspecialchars($doc['name']) ?></strong>
                                                        <small><?= htmlspecialchars($doc['email']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($doc['specialty'] ?? '—') ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($doc['hospital_name'] ?? 'Not linked') ?></strong>
                                                <?php if (!empty($doc['hospital_address'])): ?>
                                                    <small class="muted-block"><?= htmlspecialchars($doc['hospital_address']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="date-cell"><?= htmlspecialchars($submitted) ?></span></td>
                                            <td><span class="type-tag new">New registration</span></td>
                                            <td>
                                                <div class="row-actions">
                                                    <form method="POST" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="doctor_id" value="<?= (int)$doc['user_id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="action-btn approve">Approve</button>
                                                    </form>
                                                    <form method="POST" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="doctor_id" value="<?= (int)$doc['user_id'] ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="action-btn reject">Reject</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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