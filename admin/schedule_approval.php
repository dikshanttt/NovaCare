<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/config.php';
require_login(['admin']);

$db = getDB();
$flash = get_flash();

/* ---------- APPROVE / REJECT SCHEDULE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $scheduleId = (int)($_POST['schedule_id'] ?? 0);

    if ($scheduleId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        $stmt = $db->prepare("
            UPDATE schedules
            SET status = ?, reviewed_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status = 'pending_approval'
        ");
        $stmt->execute([$newStatus, $scheduleId]);

        set_flash(
            'success',
            $action === 'approve'
                ? 'Schedule request approved and slots published.'
                : 'Schedule request rejected.'
        );
    }

    redirect('/admin/schedule_approval.php');
}

/* ---------- STATS ---------- */
$pendingSched   = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending_approval'")->fetchColumn();
$approvedToday  = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'approved' AND DATE(reviewed_at) = CURRENT_DATE")->fetchColumn();
$weekTotal      = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE requested_at >= CURRENT_DATE - INTERVAL '7 days'")->fetchColumn();
$rejectedWeek   = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'rejected' AND reviewed_at >= CURRENT_DATE - INTERVAL '7 days'")->fetchColumn();
$pendingDoctors = (int)$db->query("SELECT COUNT(*) FROM doctor_profiles WHERE verification_status = 'pending'")->fetchColumn();
$pendingApps    = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending_hospital_approval'")->fetchColumn();

/* ---------- PENDING SCHEDULES ---------- */
$pendingList = $db->query("
    SELECT
        s.*,
        dp.name AS doctor_name,
        h.name AS hospital_name
    FROM schedules s
    JOIN doctor_profiles dp ON dp.user_id = s.doctor_id
    JOIN hospitals h ON h.id = s.hospital_id
    WHERE s.status = 'pending_approval'
    ORDER BY s.requested_at ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Approvals | HAMS Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/admin_style.css">
    <link rel="stylesheet" href="../assets/css/admin/schedule_approval.css">
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
                <a href="verify_doctors.php">
                    <span class="nav-icon">🩺</span>
                    <span>Verify &amp; Affiliations</span>
                    <?php if ($pendingDoctors > 0): ?>
                        <span class="adm-badge"><?= $pendingDoctors ?></span>
                    <?php endif; ?>
                </a>
                <a class="active" href="schedule_approval.php">
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
                    <strong>Schedule Approvals</strong>
                </div>

                <div class="topbar-right">
                    <div class="topbar-search">
                        <span class="search-icon">⌕</span>
                        <input type="text" placeholder="Search schedules..." aria-label="Search">
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
                        <p class="eyebrow">Consultation Slots</p>
                        <h1>Schedule Approvals</h1>
                        <p class="welcome-text">Review and approve doctor schedule change requests.</p>
                    </div>
                </section>

                <?php if ($flash): ?>
                    <div class="flash-message <?= htmlspecialchars($flash['type']) ?>">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <section class="metric-grid schedule-metrics">
                    <article class="metric-card attention">
                        <div class="metric-icon gold">⏳</div>
                        <div class="metric-copy">
                            <span>Pending</span>
                            <strong><?= $pendingSched ?></strong>
                            <small>Need your approval</small>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon green">✓</div>
                        <div class="metric-copy">
                            <span>Approved today</span>
                            <strong><?= $approvedToday ?></strong>
                            <small>Slots published</small>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon blue">📅</div>
                        <div class="metric-copy">
                            <span>This week</span>
                            <strong><?= $weekTotal ?></strong>
                            <small>Total requests</small>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon teal">✗</div>
                        <div class="metric-copy">
                            <span>Rejected</span>
                            <strong><?= $rejectedWeek ?></strong>
                            <small>This week</small>
                        </div>
                    </article>
                </section>

                <section class="panel schedule-panel">
                    <div class="panel-head">
                        <div>
                            <span class="panel-kicker">PENDING</span>
                            <h2>Schedule Change Requests</h2>
                            <small>New consultation slots submitted by doctors</small>
                        </div>
                    </div>

                    <div class="schedule-cards">
                        <?php if (empty($pendingList)): ?>
                            <div style="padding: 32px 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                                No pending schedule requests.
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingList as $s): ?>
                                <?php
                                $initials = strtoupper(substr($s['doctor_name'] ?? 'DR', 0, 2));
                                $dayLabel = !empty($s['day_of_week'])
                                    ? ucfirst($s['day_of_week'])
                                    : (!empty($s['schedule_date']) ? date('l', strtotime($s['schedule_date'])) : '—');
                                $timeLabel = '';
                                if (!empty($s['start_time']) && !empty($s['end_time'])) {
                                    $timeLabel = substr($s['start_time'], 0, 5) . ' – ' . substr($s['end_time'], 0, 5);
                                } elseif (!empty($s['time_slot'])) {
                                    $timeLabel = $s['time_slot'];
                                } else {
                                    $timeLabel = '—';
                                }
                                $slotCount = (int)($s['max_slots'] ?? $s['slot_count'] ?? 0);
                                $submitted = !empty($s['requested_at'])
                                    ? date('M j', strtotime($s['requested_at']))
                                    : '—';
                                $note = $s['notes'] ?? $s['description'] ?? '';
                                ?>
                                <article class="schedule-card">
                                    <div class="schedule-card-top">
                                        <div class="person-cell">
                                            <span class="mini-avatar doctor-av"><?= htmlspecialchars($initials) ?></span>
                                            <div>
                                                <strong><?= htmlspecialchars($s['doctor_name']) ?></strong>
                                                <small><?= htmlspecialchars($s['hospital_name']) ?></small>
                                            </div>
                                        </div>
                                        <span class="status-badge waiting small-badge">Pending</span>
                                    </div>
                                    <div class="schedule-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Day</span>
                                            <strong><?= htmlspecialchars($dayLabel) ?></strong>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Time</span>
                                            <strong><?= htmlspecialchars($timeLabel) ?></strong>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Slots</span>
                                            <strong><?= $slotCount > 0 ? $slotCount . ' slots' : '—' ?></strong>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Submitted</span>
                                            <strong><?= htmlspecialchars($submitted) ?></strong>
                                        </div>
                                    </div>
                                    <?php if ($note !== ''): ?>
                                        <p class="schedule-note"><?= htmlspecialchars($note) ?></p>
                                    <?php endif; ?>
                                    <div class="row-actions">
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="action-btn approve">Approve</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="action-btn reject">Reject</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
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