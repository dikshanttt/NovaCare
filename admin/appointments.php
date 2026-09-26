<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../include/function.php';
require_once __DIR__ . '/../config/config.php';
require_login(['admin']);

$db = getDB();
$flash = get_flash();

// Handle appointment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $appId  = (int)($_POST['appointment_id'] ?? 0);

    if ($appId > 0) {
        $stmtApp = $db->prepare("
            SELECT a.*,
                   p.name AS patient_name, u_pat.email AS patient_email, p.phone AS patient_phone,
                   d.name AS doctor_name, d.specialization,
                   h.name AS hospital_name, h.email AS hospital_email
            FROM appointments a
            JOIN patients p ON p.user_id = a.patient_id
            JOIN users u_pat ON u_pat.id = a.patient_id
            LEFT JOIN doctors d ON d.user_id = a.doctor_id
            LEFT JOIN hospitals h ON h.id = a.hospital_id
            WHERE a.id = ?
        ");
        $stmtApp->execute([$appId]);
        $app = $stmtApp->fetch();

        if ($app && in_array($app['status'], ['pending', 'pending_hospital_approval'], true)) {

            if ($action === 'confirm_appointment') {

                $stmtUpd = $db->prepare("
                    UPDATE appointments
                    SET status = 'confirmed',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUpd->execute([$appId]);

                set_flash(
                    'success',
                    "Appointment #{$app['appointment_token']} confirmed successfully."
                );

            } elseif ($action === 'reject_appointment') {

                $reason = clean(
                    $_POST['rejection_reason']
                        ?? 'Appointment rejected by hospital.'
                );

                $stmtUpd = $db->prepare("
                    UPDATE appointments
                    SET status = 'rejected_by_hospital',
                        rejection_reason = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUpd->execute([$reason, $appId]);

                set_flash(
                    'success',
                    "Appointment #{$app['appointment_token']} marked as rejected."
                );
            }
        }
    }
    redirect('/admin/appointments.php');
}

// Fetch all appointments with details
$appointments = $db->query("
    SELECT a.*,
           p.name AS patient_name, p.phone AS patient_phone, p.blood_group,
           d.name AS doctor_name, d.specialization,
           h.name AS hospital_name, h.email AS hospital_email
    FROM appointments a
    JOIN patients p ON p.user_id = a.patient_id
    LEFT JOIN doctors d ON d.user_id = a.doctor_id
    LEFT JOIN hospitals h ON h.id = a.hospital_id
    ORDER BY a.appointment_date DESC, a.slot_time DESC
")->fetchAll();

$pendingApps = (int)$db->query("
    SELECT COUNT(*)
    FROM appointments
    WHERE status IN ('pending', 'pending_hospital_approval')
")->fetchColumn();

$confirmedAppointments = (int)$db->query("
    SELECT COUNT(*)
    FROM appointments
    WHERE status = 'confirmed'
      AND appointment_date >= CURRENT_DATE
      AND appointment_date <= CURRENT_DATE + INTERVAL '1 day'
")->fetchColumn();

$thisWeekAppointments = (int)$db->query("
    SELECT COUNT(*)
    FROM appointments
    WHERE appointment_date >= CURRENT_DATE
      AND appointment_date < CURRENT_DATE + INTERVAL '7 days'
")->fetchColumn();

$rejectedCancelled = (int)$db->query("
    SELECT COUNT(*)
    FROM appointments
    WHERE status IN ('rejected', 'rejected_by_hospital', 'cancelled')
      AND updated_at >= CURRENT_TIMESTAMP - INTERVAL '7 days'
")->fetchColumn();

$pendingDoctors = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'pending'")->fetchColumn();
$pendingSched   = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending_approval'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments | HAMS Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/admin_style.css">
    <link rel="stylesheet" href="../assets/css/admin/appointments.css">
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
                <a href="verify_doctor.php">
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
                <a class="active" href="appointments.php">
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
                    <strong>Appointments</strong>
                </div>

                <div class="topbar-right">
                    <div class="topbar-search">
                        <span class="search-icon">⌕</span>
                        <input type="text" id="appointmentSearchInput" placeholder="Search appointments..." aria-label="Search appointments">
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
                        <p class="eyebrow">Live Booking Queue</p>
                        <h1>Appointments</h1>
                        <p class="welcome-text">Monitor and manage patient booking requests across the network.</p>
                    </div>
                </section>

                <?php if ($flash): ?>
                    <div class="flash-message <?= htmlspecialchars($flash['type']) ?>">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <section class="metric-grid appt-metrics">
                    <article class="metric-card">
                        <div class="metric-icon teal">📋</div>
                        <div class="metric-copy">
                            <span>Pending</span>
                            <strong><?= $pendingApps ?></strong>
                            <small>Awaiting confirmation</small>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon green">✓</div>
                        <div class="metric-copy">
                            <span>Confirmed</span>
                            <strong><?= $confirmedAppointments ?></strong>
                            <small>Today &amp; tomorrow</small>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon blue">📅</div>
                        <div class="metric-copy">
                            <span>This week</span>
                            <strong><?= $thisWeekAppointments ?></strong>
                            <small>Total bookings</small>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon gold">✗</div>
                        <div class="metric-copy">
                            <span>Rejected / Cancelled</span>
                            <strong><?= $rejectedCancelled ?></strong>
                            <small>Last 7 days</small>
                        </div>
                    </article>
                </section>

                <section class="panel appointment-panel">
                    <div class="panel-head">
                        <div>
                            <span class="panel-kicker">ALL BOOKINGS</span>
                            <h2>Appointment Requests</h2>
                            <small>Latest bookings across the hospital network</small>
                        </div>
                        <div class="panel-actions">
                            <select class="filter-select" id="statusFilter" aria-label="Filter by status">
                                <option value="all">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="completed">Completed</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Patient / Token</th>
                                    <th>Doctor &amp; Hospital</th>
                                    <th>Date &amp; Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php if (empty($appointments)): ?>

                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 40px;">
                                            No appointments found.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($appointments as $appointment): ?>

                                        <?php
                                        $patientName = $appointment['patient_name'];
                                        $initials = strtoupper(
                                            substr($patientName, 0, 1) .
                                                substr(strrchr($patientName, ' ') ?: '', 1, 1)
                                        );

                                        $status = $appointment['status'];

                                        $statusClass = match ($status) {
                                            'confirmed' => 'done',
                                            'completed' => 'done',
                                            'pending', 'pending_hospital_approval' => 'waiting',
                                            'rejected', 'rejected_by_hospital', 'cancelled' => 'rejected',
                                            default => 'waiting'
                                        };

                                        $statusLabel = match ($status) {
                                            'pending' => 'Pending',
                                            'pending_hospital_approval' => 'Pending',
                                            'confirmed' => 'Confirmed',
                                            'completed' => 'Completed',
                                            'rejected' => 'Rejected',
                                            'rejected_by_hospital' => 'Rejected',
                                            'cancelled' => 'Cancelled',
                                            default => ucfirst(str_replace('_', ' ', $status))
                                        };
                                        ?>

                                        <tr class="appt-row" data-status="<?= htmlspecialchars(strtolower($statusLabel)) ?>">
                                            <!-- Patient -->
                                            <td>
                                                <div class="person-cell">
                                                    <span class="mini-avatar"><?= htmlspecialchars($initials) ?></span>
                                                    <div>
                                                        <strong><?= htmlspecialchars($patientName) ?></strong>
                                                        <small><?= htmlspecialchars($appointment['appointment_token']) ?></small>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Doctor / Hospital -->
                                            <td>
                                                <strong><?= htmlspecialchars($appointment['doctor_name'] ?? 'Unassigned') ?></strong>
                                                <small class="muted-block"><?= htmlspecialchars($appointment['hospital_name'] ?? 'N/A') ?></small>
                                            </td>

                                            <!-- Date / Time -->
                                            <td>
                                                <span class="date-cell">
                                                    <?= date('M j', strtotime($appointment['appointment_date'])) ?>
                                                </span>
                                                <small class="muted-block">
                                                    <?= htmlspecialchars(format_time_slot($appointment['slot_time'])) ?>
                                                </small>
                                            </td>

                                            <!-- Status -->
                                            <td>
                                                <span class="status-badge <?= $statusClass ?> small-badge">
                                                    <?= htmlspecialchars($statusLabel) ?>
                                                </span>
                                            </td>

                                            <!-- Actions -->
                                            <td>
                                                <div class="row-actions">
                                                    <?php if (in_array($status, ['pending', 'pending_hospital_approval'], true)): ?>

                                                        <form method="POST" style="display:inline;">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>">
                                                            <input type="hidden" name="action" value="confirm_appointment">
                                                            <button type="submit" class="action-btn approve">Confirm</button>
                                                        </form>

                                                        <form method="POST" style="display:inline;"
                                                            onsubmit="return confirm('Are you sure you want to reject this appointment?');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>">
                                                            <input type="hidden" name="action" value="reject_appointment">
                                                            <input type="hidden" name="rejection_reason" value="Appointment rejected by hospital.">
                                                            <button type="submit" class="action-btn reject">Reject</button>
                                                        </form>

                                                    <?php else: ?>

                                                        <span class="action-btn ghost" style="cursor:default; opacity:0.5;">
                                                            <?= $statusLabel ?>
                                                        </span>

                                                    <?php endif; ?>
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

        // Live status filter and search for appointments
        const searchInput = document.getElementById("appointmentSearchInput");
        const statusFilter = document.getElementById("statusFilter");

        function filterAppointments() {
            const term = (searchInput ? searchInput.value : "").toLowerCase().trim();
            const status = (statusFilter ? statusFilter.value : "all").toLowerCase();
            const rows = document.querySelectorAll(".adm-table tbody tr.appt-row");

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const rowStatus = (row.getAttribute("data-status") || "").toLowerCase();

                const matchesSearch = term === "" || text.includes(term);
                const matchesStatus = status === "all" || rowStatus === status;

                row.style.display = matchesSearch && matchesStatus ? "" : "none";
            });
        }

        if (searchInput) searchInput.addEventListener("input", filterAppointments);
        if (statusFilter) statusFilter.addEventListener("change", filterAppointments);
    </script>
</body>

</html>
