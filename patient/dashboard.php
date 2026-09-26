<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../include/function.php';

// Strict access control: Patient only
require_login(['patient']);

$db = getDB();
$userId = current_user_id();

// Fetch patient profile
$patientStmt = $db->prepare("
    SELECT p.*, u.email 
    FROM patients p 
    JOIN users u ON u.id = p.user_id 
    WHERE p.user_id = ?
");
$patientStmt->execute([$userId]);
$patient = $patientStmt->fetch();

if (!$patient) {
    die("Patient profile not found.");
}

$flash = get_flash();
$successMessage = ($flash && $flash['type'] === 'success') ? $flash['message'] : '';
$errorMessage = ($flash && $flash['type'] === 'error') ? $flash['message'] : '';

// Handle Appointment Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_appointment') {
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    if ($appointmentId > 0) {
        try {
            $cancelStmt = $db->prepare("
                UPDATE appointments 
                SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND patient_id = ? AND status IN ('pending', 'confirmed')
            ");
            $cancelStmt->execute([$appointmentId, $userId]);

            if ($cancelStmt->rowCount() > 0) {
                $successMessage = 'Appointment has been cancelled successfully.';
            } else {
                $errorMessage = 'Unable to cancel this appointment. It may have already been processed.';
            }
        } catch (Throwable $e) {
            error_log('Cancel appointment error: ' . $e->getMessage());
            $errorMessage = 'A database error occurred while cancelling.';
        }
    }
}

// Fetch all patient appointments from real DB
$appointmentsStmt = $db->prepare("
    SELECT a.*, 
           COALESCE(d.name, 'Assigned Specialist') AS doctor_name, 
           COALESCE(d.specialization, 'General') AS specialization,
           d.phone AS doctor_phone,
           COALESCE(h.name, 'NovaCare Central Clinic') AS hospital_name, 
           h.address AS hospital_address
    FROM appointments a
    LEFT JOIN schedules s ON s.id = a.schedule_id
    LEFT JOIN doctors d ON d.user_id = a.doctor_id OR (s.doctor_id IS NOT NULL AND d.user_id = s.doctor_id)
    LEFT JOIN hospitals h ON h.id = a.hospital_id OR (s.hospital_id IS NOT NULL AND h.id = s.hospital_id)
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.slot_time DESC
");
$appointmentsStmt->execute([$userId]);
$appointments = $appointmentsStmt->fetchAll();

// Calculate actual stats from real DB
$totalAppointments = count($appointments);
$confirmedCount = 0;
$pendingCount = 0;
$completedCount = 0;

foreach ($appointments as $app) {
    if ($app['status'] === 'confirmed') {
        $confirmedCount++;
    } elseif ($app['status'] === 'pending') {
        $pendingCount++;
    } elseif ($app['status'] === 'completed') {
        $completedCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - NovaCare</title>
    <link rel="stylesheet" href="../assets/css/login/auth.css">
    <link rel="stylesheet" href="../assets/css/patient/patient.css">
</head>
<body>

    <!-- Header Navigation -->
    <header class="auth-navbar">
        <a href="../index.php" class="auth-logo">
            <span class="auth-logo-badge">+</span>
            NovaCare
        </a>

        <div class="portal-nav-user" style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 34px; height: 34px; border-radius: 50%; background: var(--cherry); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">
                <?= strtoupper(substr($patient['name'], 0, 1)) ?>
            </div>
            <span style="font-weight: 600; font-size: 13.5px;"><?= htmlspecialchars($patient['name'], ENT_QUOTES, 'UTF-8') ?></span>
            <a href="appointment.php" class="btn-nav-book">Book Appointment</a>
            <a href="../change-password.php" style="color: var(--gray); font-size: 13px; font-weight: 500; text-decoration: none;">Change Password</a>
            <form method="POST" action="../logout.php" style="display:inline; margin:0;">
                <?= csrf_field() ?>
                <button type="submit" style="color: var(--gray); font-size: 13px; font-weight: 500; background:none; border:0; padding:0; cursor:pointer;">Sign out</button>
            </form>
        </div>

        <button class="portal-nav-toggle" onclick="togglePortalNav()" aria-label="Toggle Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </header>

    <!-- Mobile Overlay -->
    <div class="portal-nav-overlay" id="portalOverlay" onclick="closePortalNav()"></div>

    <!-- Mobile Drawer -->
    <nav class="portal-mobile-drawer" id="portalDrawer">
        <div class="portal-drawer-header">
            <a href="../index.php" class="auth-logo">
                <span class="auth-logo-badge">+</span>
                NovaCare
            </a>
            <button class="portal-drawer-close" onclick="closePortalNav()" aria-label="Close menu">&times;</button>
        </div>

        <div class="portal-drawer-user">
            <div class="avatar"><?= strtoupper(substr($patient['name'], 0, 1)) ?></div>
            <span class="name"><?= htmlspecialchars($patient['name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <a href="dashboard.php" onclick="closePortalNav()">🏠 Dashboard</a>
        <a href="appointment.php" onclick="closePortalNav()">📅 Book Appointment</a>
        <a href="../change-password.php" onclick="closePortalNav()">🔒 Change Password</a>

        <div class="drawer-signout">
            <form method="POST" action="../logout.php" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="drawer-link" style="color: var(--cherry);">🚪 Sign out</button>
            </form>
        </div>
    </nav>



    <!-- Dashboard Main -->
    <main class="dashboard-container">

        <!-- Welcome Banner -->
        <div class="dash-welcome">
            <div>
                <h1>Hello, <?= htmlspecialchars(explode(' ', $patient['name'])[0], ENT_QUOTES, 'UTF-8') ?></h1>
                <p>Manage your upcoming appointments and healthcare profile.</p>
            </div>
            <a href="appointment.php" class="btn-auth-submit" style="width: auto; padding: 12px 24px;">
                <span>+ Book New Appointment</span>
            </a>
        </div>

        <!-- Feedback Alert -->
        <?php if (!empty($successMessage)): ?>
            <div class="auth-alert success" style="margin-bottom: 25px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <span><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="auth-alert error" style="margin-bottom: 25px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <!-- Stat Cards Row (Calculated from Real DB Data) -->
        <div class="stats-cards-row">
            <div class="stat-card-box">
                <small>Total Bookings</small>
                <h3><?= $totalAppointments ?></h3>
            </div>
            <div class="stat-card-box">
                <small>Confirmed Visits</small>
                <h3 style="color: #2e7d32;"><?= $confirmedCount ?></h3>
            </div>
            <div class="stat-card-box">
                <small>Pending Approval</small>
                <h3 style="color: #f57f17;"><?= $pendingCount ?></h3>
            </div>
            <div class="stat-card-box">
                <small>Completed Visits</small>
                <h3 style="color: #1565c0;"><?= $completedCount ?></h3>
            </div>
        </div>

        <!-- 2-Column Split Content -->
        <div class="content-split">

            <!-- Left: Appointments History Table -->
            <div class="dash-panel">
                <h2>
                    <span>Your Appointments</span>
                    <span style="font-size: 13px; color: var(--gray); font-family: sans-serif; font-weight: normal;">(<?= $totalAppointments ?> Total)</span>
                </h2>

                <?php if ($totalAppointments > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="app-table">
                            <thead>
                                <tr>
                                    <th>Token</th>
                                    <th>Doctor / Specialization</th>
                                    <th>Date & Slot</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $app): ?>
                                    <tr>
                                        <td>
                                            <strong style="color: var(--cherry);"><?= htmlspecialchars($app['appointment_token'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        </td>
                                        <td>
                                            <strong>Dr. <?= htmlspecialchars($app['doctor_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                            <small style="color: var(--gray);"><?= htmlspecialchars($app['specialization'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($app['appointment_date'])) ?><br>
                                            <small style="color: var(--gray);"><?= date('h:i A', strtotime($app['slot_time'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= htmlspecialchars($app['status'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= ucfirst(str_replace('_', ' ', htmlspecialchars($app['status'], ENT_QUOTES, 'UTF-8'))) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (in_array($app['status'], ['pending', 'confirmed'])): ?>
                                                <form method="POST" action="dashboard.php" onsubmit="return confirm('Are you sure you want to cancel this appointment?');" style="margin: 0;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="cancel_appointment">
                                                    <input type="hidden" name="appointment_id" value="<?= (int)$app['id'] ?>">
                                                    <button type="submit" class="btn-cancel">Cancel</button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color: #aaa; font-size: 12px;">--</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-box">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: #bbb; margin-bottom: 12px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <p style="margin-bottom: 16px;">You haven't booked any medical consultations yet.</p>
                        <a href="appointment.php" class="btn-auth-submit" style="display: inline-flex; width: auto; padding: 10px 20px;">Book Your First Appointment</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Patient Profile Details from DB -->
            <div>
                <div class="dash-panel">
                    <h2>Patient Profile</h2>

                    <div class="profile-row">
                        <span>Full Name</span>
                        <span><?= htmlspecialchars($patient['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="profile-row">
                        <span>Email</span>
                        <span><?= htmlspecialchars($patient['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="profile-row">
                        <span>Phone</span>
                        <span><?= htmlspecialchars($patient['phone'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="profile-row">
                        <span>Date of Birth</span>
                        <span><?= htmlspecialchars($patient['date_of_birth'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="profile-row">
                        <span>Gender</span>
                        <span><?= htmlspecialchars($patient['gender'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="profile-row">
                        <span>Blood Group</span>
                        <span><?= htmlspecialchars($patient['blood_group'] ?: 'Not recorded', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="profile-row">
                        <span>Address</span>
                        <span><?= htmlspecialchars($patient['address'] ?: 'Not specified', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php if (!empty($patient['emergency_contact_name'])): ?>
                    <div class="profile-row">
                        <span>Emergency Contact</span>
                        <span><?= htmlspecialchars($patient['emergency_contact_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($patient['emergency_contact_phone'], ENT_QUOTES, 'UTF-8') ?>)</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Emergency Contact / Support Box -->
                <div class="dash-panel" style="background: var(--light-oat);">
                    <h3 style="font-family: Georgia, serif; font-size: 18px; margin-bottom: 8px;">Need Assistance?</h3>
                    <p style="color: var(--gray); font-size: 13px; line-height: 1.5; margin-bottom: 12px;">Our patient support coordination team is available to help you reschedule or find appropriate emergency care.</p>
                    <a href="tel:+18006822273" style="color: var(--cherry); font-weight: bold; font-size: 14px;">📞 +977 982-72977</a>
                </div>
            </div>

        </div>

    </main>

    <!-- Dark Footer -->
    <footer class="auth-dark-footer">
        <div class="auth-dark-footer-inner">
            <div class="footer-brand-logo">
                <span>+</span>NovaCare
            </div>
            <div class="footer-support-text">
                Secure patient portal &bull; Connected health coordination
            </div>
            <div class="footer-badges">
                <span>Privacy focused</span>
            </div>
        </div>
    </footer>

<script>
function togglePortalNav() {
    const drawer = document.getElementById('portalDrawer');
    const overlay = document.getElementById('portalOverlay');
    const isOpen = drawer.classList.toggle('open');
    if (overlay) overlay.classList.toggle('open', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}

function closePortalNav() {
    const drawer = document.getElementById('portalDrawer');
    const overlay = document.getElementById('portalOverlay');
    if (drawer) drawer.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
}

window.addEventListener('resize', function() {
    if (window.innerWidth > 640) {
        closePortalNav();
    }
});
</script>

</body>
</html>
