<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/phpmailer.php';
require_once __DIR__ . '/../config/config.php';
require_login(['admin']);

$db = getDB();
$flash = get_flash();

// Handle External Hospital Status Updates (Confirmation / Rejection)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $appId  = (int)($_POST['appointment_id'] ?? 0);

    if ($appId > 0) {
        $stmtApp = $db->prepare("
            SELECT a.*,
                   p.name AS patient_name, u_pat.email AS patient_email, p.phone AS patient_phone,
                   dp.name AS doctor_name, dp.specialization,
                   h.name AS hospital_name, h.email AS hospital_email
            FROM appointments a
            JOIN patient_profiles p ON p.user_id = a.patient_id
            JOIN users u_pat ON u_pat.id = a.patient_id
            JOIN doctor_profiles dp ON dp.user_id = a.doctor_id
            JOIN hospitals h ON h.id = a.hospital_id
            WHERE a.id = ?
        ");
        $stmtApp->execute([$appId]);
        $app = $stmtApp->fetch();

        if ($app && $app['status'] === 'pending_hospital_approval') {

            if ($action === 'confirm_appointment') {

                $stmtUpd = $db->prepare("
            UPDATE appointments
            SET status = 'confirmed',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

                $stmtUpd->execute([$appId]);

                // Send email notification to patient
                send_appointment_status_to_patient($app['patient_email'], [
                    'token'            => $app['appointment_token'],
                    'patient_name'     => $app['patient_name'],
                    'doctor_name'      => $app['doctor_name'],
                    'hospital_name'    => $app['hospital_name'],
                    'appointment_date' => date(
                        'l, M j, Y',
                        strtotime($app['appointment_date'])
                    ),
                    'slot_time'        => format_time_slot($app['slot_time']),
                ], 'confirmed');

                set_flash(
                    'success',
                    "Appointment #{$app['appointment_token']} confirmed by hospital and patient notified via email."
                );
            } elseif ($action === 'reject_appointment') {

                $reason = clean(
                    $_POST['rejection_reason']
                        ?? 'Specialist unavailable due to urgent hospital surgery.'
                );

                $stmtUpd = $db->prepare("
            UPDATE appointments
            SET status = 'rejected_by_hospital',
                hospital_rejection_reason = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

                $stmtUpd->execute([$reason, $appId]);

                // Send rejection email to patient
                send_appointment_status_to_patient($app['patient_email'], [
                    'token'            => $app['appointment_token'],
                    'patient_name'     => $app['patient_name'],
                    'doctor_name'      => $app['doctor_name'],
                    'hospital_name'    => $app['hospital_name'],
                    'appointment_date' => date(
                        'l, M j, Y',
                        strtotime($app['appointment_date'])
                    ),
                    'slot_time'        => format_time_slot($app['slot_time']),
                ], 'rejected_by_hospital', $reason);

                set_flash(
                    'success',
                    "Appointment #{$app['appointment_token']} marked as rejected with reason and patient notified."
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
           dp.name AS doctor_name, dp.specialization,
           h.name AS hospital_name, h.email AS hospital_email
    FROM appointments a
    JOIN patient_profiles p ON p.user_id = a.patient_id
    JOIN doctor_profiles dp ON dp.user_id = a.doctor_id
    JOIN hospitals h ON h.id = a.hospital_id
    ORDER BY a.appointment_date DESC, a.slot_time DESC
")->fetchAll();

$pendingAppointments = (int)$db->query("
    SELECT COUNT(*)
    FROM appointments
    WHERE status = 'pending_hospital_approval'
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
    WHERE status IN ('rejected_by_hospital', 'cancelled')
      AND updated_at >= CURRENT_TIMESTAMP - INTERVAL '7 days'
")->fetchColumn();

$pendingDocs = (int)$db->query("SELECT COUNT(*) FROM doctor_profiles WHERE verification_status = 'pending'")->fetchColumn();
$pendingSched = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending_approval'")->fetchColumn();
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
                <a class="adm-brand" href="dashobard.php    ">
                    <span class="adm-brand-icon">✚</span>
                    <div class="adm-brand-text">
                        <strong>HAMS</strong>
                        <small>Admin Console</small>
                    </div>
                </a>
                <button class="sidebar-close" onclick="closeSidebar()" aria-label="Close navigation">X</button>
            </div>

            <div class="sidebar-section-title">Workspace</div>

            <nav class="adm-nav">
                <a href="dashboard.php">
                    <span class="nav-icon">⊞</span>
                    <span>Overview</span>
                </a>
                <a href="hospitals.html">
                    <span class="nav-icon">🏥</span>
                    <span>Manage Hospitals</span>
                </a>
                <a href="verify_doctor.html">
                    <span class="nav-icon">🩺</span>
                    <span>Verify &amp; Affiliations</span>
                    <?php if ($pendingDoctors > 0): ?><span class="adm-badge"><?= $pendingDoctors ?></span><?php endif; ?>
                </a>
                <a href="schedule_approval.html">
                    <span class="nav-icon">📅</span>
                    <span>Schedule Approvals</span>
                    <?php if ($pendingSched > 0): ?><span class="adm-badge"><?= $pendingSched ?></span><?php endif; ?>
                </a>
                <a class="active" href="appointments.php">
                    <span class="nav-icon">📋</span>
                    <span>Appointments</span>
                    <?php if ($pendingApps > 0): ?><span class="adm-badge" style="background:#0369a1;color:#fff"><?= $pendingApps ?>
                        </span><?php endif; ?> </a>
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
                <a class="signout-link" href="../login.html">
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
                    <strong>Appointments</strong>
                </div>

                <div class="topbar-right">
                    <div class="topbar-search">
                        <span class="search-icon">⌕</span>
                        <input type="text" placeholder="Search appointments..." aria-label="Search">
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

                <section class="metric-grid appt-metrics">
                    <article class="metric-card">
                        <div class="metric-icon teal">📋</div>
                        <div class="metric-copy">
                            <span>Pending</span>
                            <strong><?= $pendingAppointments ?></strong>
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
                            <select class="filter-select" aria-label="Filter by status">
                                <option>All status</option>
                                <option>Pending</option>
                                <option>Confirmed</option>
                                <option>Rejected</option>
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
                                        <td colspan="5" style="text-align: center;">
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
                                        ?>

                                        <tr>

                                            <!-- Patient -->
                                            <td>
                                                <div class="person-cell">

                                                    <span class="mini-avatar">
                                                        <?= htmlspecialchars($initials) ?>
                                                    </span>

                                                    <div>
                                                        <strong>
                                                            <?= htmlspecialchars($patientName) ?>
                                                        </strong>

                                                        <small>
                                                            <?= htmlspecialchars($appointment['appointment_token']) ?>
                                                        </small>
                                                    </div>

                                                </div>
                                            </td>


                                            <!-- Doctor / Hospital -->
                                            <td>

                                                <strong>
                                                    <?= htmlspecialchars($appointment['doctor_name']) ?>
                                                </strong>

                                                <small class="muted-block">
                                                    <?= htmlspecialchars($appointment['hospital_name']) ?>
                                                </small>

                                            </td>


                                            <!-- Date / Time -->
                                            <td>

                                                <span class="date-cell">
                                                    <?= date(
                                                        'M j',
                                                        strtotime($appointment['appointment_date'])
                                                    ) ?>
                                                </span>

                                                <small class="muted-block">
                                                    <?= htmlspecialchars(
                                                        format_time_slot($appointment['slot_time'])
                                                    ) ?>
                                                </small>

                                            </td>


                                            <!-- Status -->
                                            <td>

                                                <?php
                                                $statusClass = match ($status) {
                                                    'confirmed' => 'done',
                                                    'pending_hospital_approval' => 'waiting',
                                                    'rejected_by_hospital',
                                                    'cancelled' => 'rejected',
                                                    default => 'waiting'
                                                };

                                                $statusLabel = match ($status) {
                                                    'pending_hospital_approval' => 'Pending',
                                                    'confirmed' => 'Confirmed',
                                                    'rejected_by_hospital' => 'Rejected',
                                                    'cancelled' => 'Cancelled',
                                                    default => ucfirst(str_replace('_', ' ', $status))
                                                };
                                                ?>

                                                <span class="status-badge <?= $statusClass ?> small-badge">
                                                    <?= htmlspecialchars($statusLabel) ?>
                                                </span>

                                            </td>


                                            <!-- Actions -->
                                            <td>

                                                <div class="row-actions">

                                                    <?php if ($status === 'pending_hospital_approval'): ?>

                                                        <form method="POST" style="display:inline;">
                                                            <?= csrf_field() ?>

                                                            <input
                                                                type="hidden"
                                                                name="appointment_id"
                                                                value="<?= (int)$appointment['id'] ?>">

                                                            <input
                                                                type="hidden"
                                                                name="action"
                                                                value="confirm_appointment">

                                                            <button
                                                                type="submit"
                                                                class="action-btn approve">
                                                                Confirm
                                                            </button>
                                                        </form>


                                                        <form method="POST"
                                                            onsubmit="return confirm('Are you sure you want to reject this appointment?');">

                                                            <?= csrf_field() ?>

                                                            <input
                                                                type="hidden"
                                                                name="appointment_id"
                                                                value="<?= (int)$appointment['id'] ?>">

                                                            <input
                                                                type="hidden"
                                                                name="action"
                                                                value="reject_appointment">

                                                            <input
                                                                type="hidden"
                                                                name="rejection_reason"
                                                                value="Appointment rejected by hospital.">

                                                            <button type="submit" class="action-btn reject">
                                                                Reject
                                                            </button>

                                                        </form>

                                                        </form>

                                                    <?php else: ?>

                                                        <button
                                                            type="button"
                                                            class="action-btn ghost">
                                                            View
                                                        </button>

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
    </script>
</body>

</html>