<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../include/function.php';
require_once __DIR__ . '/../config/config.php';
require_login(['admin']);

$db = getDB();
$flash = get_flash();

/* ---------- APPROVE / REJECT DOCTOR ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action   = $_POST['action'] ?? '';
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $adminId  = (int)($_SESSION['user_id'] ?? 0);

    if ($doctorId > 0 && in_array($action, ['approve', 'reject'], true)) {

        $db->beginTransaction();
        try {
            if ($action === 'approve') {
                $stmt = $db->prepare("
                    UPDATE doctors
                    SET verification_status = 'verified',
                        verified_at = CURRENT_TIMESTAMP,
                        verified_by_admin_id = ?
                    WHERE user_id = ? AND verification_status = 'pending'
                ");
                $stmt->execute([$adminId, $doctorId]);

                $db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$doctorId]);

                set_flash('success', 'Doctor verified successfully.');
            } else {
                $reason = clean($_POST['rejection_reason'] ?? 'Application does not meet requirements.');

                $stmt = $db->prepare("
                    UPDATE doctors
                    SET verification_status = 'rejected',
                        rejection_reason = ?,
                        verified_by_admin_id = ?
                    WHERE user_id = ? AND verification_status = 'pending'
                ");
                $stmt->execute([$reason, $adminId, $doctorId]);

                $db->prepare("UPDATE users SET status = 'rejected' WHERE id = ?")->execute([$doctorId]);

                set_flash('success', 'Doctor application rejected.');
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /* Handle affiliation approve/reject */
    $affAction = $_POST['affiliation_action'] ?? '';
    $affId     = (int)($_POST['affiliation_id'] ?? 0);

    if ($affId > 0 && in_array($affAction, ['approve_affiliation', 'reject_affiliation'], true)) {
        $newStatus = $affAction === 'approve_affiliation' ? 'active' : 'inactive';
        $stmt = $db->prepare("UPDATE doctor_hospital SET status = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$newStatus, $affId]);
        set_flash('success', $affAction === 'approve_affiliation'
            ? 'Affiliation approved.'
            : 'Affiliation request rejected.');
    }

    redirect('/admin/verify_doctor.php');
}

/* ---------- STATS ---------- */
$pendingDoctors = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'pending'")->fetchColumn();
$pendingSched   = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending_approval'")->fetchColumn();
$pendingApps    = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status IN ('pending','pending_hospital_approval')")->fetchColumn();
$affiliationCount = (int)$db->query("
    SELECT COUNT(*) FROM doctor_hospital WHERE status = 'pending'
")->fetchColumn();

/* ---------- PENDING DOCTORS ---------- */
$pendingList = $db->query("
    SELECT
        d.user_id,
        d.name,
        d.specialization,
        d.qualification,
        d.license_no,
        d.experience_years,
        d.phone,
        d.verification_status,
        u.email,
        u.created_at,
        h.name AS hospital_name,
        h.address AS hospital_address
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    LEFT JOIN doctor_hospital dh ON dh.doctor_id = d.user_id AND dh.status IN ('pending', 'active')
    LEFT JOIN hospitals h ON h.id = dh.hospital_id
    WHERE d.verification_status = 'pending'
    ORDER BY u.created_at ASC
")->fetchAll();

/* ---------- PENDING AFFILIATIONS ---------- */
$pendingAffiliations = $db->query("
    SELECT
        dh.id AS affiliation_id,
        d.name AS doctor_name,
        d.specialization,
        u.email AS doctor_email,
        h.name AS hospital_name,
        h.address AS hospital_address,
        dh.created_at
    FROM doctor_hospital dh
    JOIN doctors d ON d.user_id = dh.doctor_id
    JOIN users u ON u.id = dh.doctor_id
    JOIN hospitals h ON h.id = dh.hospital_id
    WHERE dh.status = 'pending'
    ORDER BY dh.created_at ASC
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
                <a class="active" href="verify_doctor.php">
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

                <!-- Pending Doctor Verification -->
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
                                    <th>Specialization</th>
                                    <th>License / Qualification</th>
                                    <th>Hospital</th>
                                    <th>Submitted</th>
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
                                            <td><?= htmlspecialchars($doc['specialization'] ?? '—') ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($doc['license_no'] ?? '—') ?></strong>
                                                <small class="muted-block"><?= htmlspecialchars($doc['qualification'] ?? '') ?> · <?= (int)$doc['experience_years'] ?> yrs</small>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($doc['hospital_name'] ?? 'Not linked') ?></strong>
                                                <?php if (!empty($doc['hospital_address'])): ?>
                                                    <small class="muted-block"><?= htmlspecialchars($doc['hospital_address']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="date-cell"><?= htmlspecialchars($submitted) ?></span></td>
                                            <td>
                                                <div class="row-actions">
                                                    <form method="POST" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="doctor_id" value="<?= (int)$doc['user_id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="action-btn approve">Approve</button>
                                                    </form>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to reject this doctor?');">
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

                <!-- Pending Affiliations -->
                <section class="panel verify-panel">
                    <div class="panel-head">
                        <div>
                            <span class="panel-kicker">AFFILIATIONS</span>
                            <h2>Pending Hospital Links</h2>
                            <small>Doctors requesting to join a hospital</small>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Doctor</th>
                                    <th>Hospital</th>
                                    <th>Requested</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingAffiliations)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center;padding:40px;">
                                            No pending affiliation requests.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pendingAffiliations as $aff): ?>
                                        <?php
                                        $affInitials = strtoupper(substr($aff['doctor_name'] ?? 'DR', 0, 2));
                                        $affDate = !empty($aff['created_at'])
                                            ? date('M j', strtotime($aff['created_at']))
                                            : '—';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="person-cell">
                                                    <span class="mini-avatar doctor-av"><?= htmlspecialchars($affInitials) ?></span>
                                                    <div>
                                                        <strong><?= htmlspecialchars($aff['doctor_name']) ?></strong>
                                                        <small><?= htmlspecialchars($aff['doctor_email']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($aff['hospital_name']) ?></strong>
                                                <small class="muted-block"><?= htmlspecialchars($aff['hospital_address']) ?></small>
                                            </td>
                                            <td><span class="date-cell"><?= htmlspecialchars($affDate) ?></span></td>
                                            <td>
                                                <div class="row-actions">
                                                    <form method="POST" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="affiliation_id" value="<?= (int)$aff['affiliation_id'] ?>">
                                                        <input type="hidden" name="affiliation_action" value="approve_affiliation">
                                                        <button type="submit" class="action-btn approve">Approve</button>
                                                    </form>
                                                    <form method="POST" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="affiliation_id" value="<?= (int)$aff['affiliation_id'] ?>">
                                                        <input type="hidden" name="affiliation_action" value="reject_affiliation">
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
