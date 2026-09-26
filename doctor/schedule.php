<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../include/function.php';

// Strict access control: Doctor only
require_login(['doctor']);

$db = getDB();
$userId = current_user_id();

// Fetch doctor details
$doctorStmt = $db->prepare("SELECT * FROM doctors WHERE user_id = ?");
$doctorStmt->execute([$userId]);
$doctor = $doctorStmt->fetch();

if (!$doctor) {
    die("Doctor profile not found.");
}

$flash = get_flash();
$successMessage = ($flash && $flash['type'] === 'success') ? $flash['message'] : '';
$errorMessage = ($flash && $flash['type'] === 'error') ? $flash['message'] : '';

// Handle Schedule Actions: Delete or Add Slot
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'delete_schedule') {
        $schedId = (int) ($_POST['schedule_id'] ?? 0);
        if ($schedId > 0) {
            try {
                $delStmt = $db->prepare("DELETE FROM schedules WHERE id = ? AND doctor_id = ?");
                $delStmt->execute([$schedId, $userId]);
                $successMessage = 'Consultation slot has been deleted.';
            } catch (Throwable $e) {
                error_log('Delete schedule error: ' . $e->getMessage());
                $errorMessage = 'Cannot remove slot because appointments are already linked to it.';
            }
        }
    } elseif ($action === 'add_schedule') {
        $hospitalId = (int) ($_POST['hospital_id'] ?? 0);
        $dayOfWeek = trim($_POST['day_of_week'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $slotMinutes = (int) ($_POST['slot_duration_minutes'] ?? 20);
        $maxPatients = (int) ($_POST['max_patients_per_slot'] ?? 1);

        $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        if (empty($hospitalId) || !in_array($dayOfWeek, $validDays, true) || empty($startTime) || empty($endTime)) {
            $errorMessage = 'Please select a hospital, day of the week, and valid consultation time range.';
        } elseif (strtotime($endTime) <= strtotime($startTime)) {
            $errorMessage = 'Consultation end time must be after the start time.';
        } else {
            try {
                $db->beginTransaction();

                // 1. Ensure doctor_hospital relationship exists
                $linkStmt = $db->prepare("
                    SELECT id FROM doctor_hospital WHERE doctor_id = ? AND hospital_id = ? LIMIT 1
                ");
                $linkStmt->execute([$userId, $hospitalId]);
                $docHosp = $linkStmt->fetch();

                if ($docHosp) {
                    $docHospId = (int)$docHosp['id'];
                } else {
                    $insLink = $db->prepare("
                        INSERT INTO doctor_hospital (doctor_id, hospital_id, join_date, status)
                        VALUES (?, ?, CURRENT_DATE, 'active')
                        RETURNING id
                    ");
                    $insLink->execute([$userId, $hospitalId]);
                    $docHospId = (int)$insLink->fetchColumn();
                }

                // 2. Insert schedule slot
                $insSched = $db->prepare("
                    INSERT INTO schedules (
                        doctor_hospital_id, doctor_id, hospital_id, day_of_week, 
                        start_time, end_time, slot_duration_minutes, max_patients_per_slot, 
                        status, requested_at, approved_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $insSched->execute([
                    $docHospId,
                    $userId,
                    $hospitalId,
                    $dayOfWeek,
                    $startTime,
                    $endTime,
                    $slotMinutes,
                    $maxPatients
                ]);

                $db->commit();
                $successMessage = 'Consultation slot added successfully!';

            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Add schedule error: ' . $e->getMessage());
                $errorMessage = 'A database error occurred while creating the schedule.';
            }
        }
    }
}

// Fetch active partner hospitals from real DB
$hospitalsStmt = $db->query("SELECT id, name, address FROM hospitals WHERE is_active = TRUE ORDER BY name ASC");
$hospitals = $hospitalsStmt->fetchAll();

// Fetch doctor's existing schedules from real DB
$schedulesStmt = $db->prepare("
    SELECT s.*, 
           COALESCE(h.name, 'Affiliated Clinic') AS hospital_name, 
           h.address AS hospital_address
    FROM schedules s
    LEFT JOIN hospitals h ON h.id = s.hospital_id
    WHERE s.doctor_id = ?
    ORDER BY CASE s.day_of_week
        WHEN 'Monday' THEN 1
        WHEN 'Tuesday' THEN 2
        WHEN 'Wednesday' THEN 3
        WHEN 'Thursday' THEN 4
        WHEN 'Friday' THEN 5
        WHEN 'Saturday' THEN 6
        WHEN 'Sunday' THEN 7
        ELSE 8 END, s.start_time ASC
");
$schedulesStmt->execute([$userId]);
$schedules = $schedulesStmt->fetchAll();
$totalSlots = count($schedules);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Consultation Schedule - NovaCare</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 5% 60px;
            width: 100%;
        }
        .dash-welcome {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        .dash-welcome h1 {
            font-family: Georgia, serif;
            font-size: 34px;
            color: var(--black);
        }
        .content-split {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 30px;
        }
        .dash-panel {
            background: #ffffff;
            border-radius: 24px;
            padding: 32px;
            border: 1px solid rgba(220, 214, 200, 0.7);
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            margin-bottom: 25px;
        }
        .dash-panel h2 {
            font-family: Georgia, serif;
            font-size: 22px;
            color: var(--black);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .sched-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        .sched-table th {
            text-align: left;
            padding: 12px 14px;
            background: var(--light-oat);
            color: var(--gray);
            font-weight: 700;
            border-bottom: 1.5px solid var(--border-soft);
        }
        .sched-table td {
            padding: 14px;
            border-bottom: 1px solid var(--border-soft);
            color: var(--black);
            vertical-align: middle;
        }
        .day-tag {
            display: inline-block;
            background: var(--light-oat);
            border: 1px solid var(--border-soft);
            padding: 3px 10px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 12px;
            color: var(--cherry);
        }
        .btn-del {
            background: transparent;
            border: 1px solid #ef9a9a;
            color: #d32f2f;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 11.5px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-del:hover {
            background: #d32f2f;
            color: #ffffff;
        }
        .empty-box {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        @media (max-width: 900px) {
            .content-split { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .auth-navbar {
                height: auto;
                padding: 12px 4%;
                flex-wrap: wrap;
                gap: 12px;
            }
            .portal-nav-user {
                display: none !important;
            }
            .portal-nav-toggle { display: flex; }
            .dashboard-container {
                padding: 20px 4% 40px;
            }
            .dash-welcome h1 {
                font-size: 26px;
            }
            .dash-panel {
                padding: 22px 16px;
                border-radius: 18px;
            }
        }

        /* Hamburger Toggle Button */
        .portal-nav-toggle {
            display: none;
            flex-direction: column;
            justify-content: center;
            gap: 5px;
            width: 38px;
            height: 38px;
            background: transparent;
            border: 1px solid #ddd8ca;
            border-radius: 10px;
            cursor: pointer;
            padding: 8px;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .portal-nav-toggle:hover { background: var(--oat); }
        .portal-nav-toggle span {
            display: block;
            width: 100%;
            height: 2px;
            background: var(--black);
            border-radius: 2px;
        }

        .portal-nav-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0,0,0,0.45);
            backdrop-filter: blur(3px);
        }
        .portal-nav-overlay.open { display: block; }

        .portal-mobile-drawer {
            position: fixed;
            top: 0; right: 0; bottom: 0;
            width: min(300px, 85vw);
            background: var(--light-oat, #faf8f5);
            z-index: 1001;
            display: flex;
            flex-direction: column;
            padding: 24px 20px 32px;
            box-shadow: -8px 0 30px rgba(0,0,0,0.15);
            transform: translateX(105%);
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }
        .portal-mobile-drawer.open { transform: translateX(0); }

        .portal-drawer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 18px;
            margin-bottom: 12px;
            border-bottom: 1px solid #ddd8ca;
        }
        .portal-drawer-header .auth-logo { font-size: 16px; }
        .portal-drawer-close {
            background: none; border: none;
            font-size: 28px; line-height: 1;
            cursor: pointer; color: var(--gray);
            padding: 4px 8px; border-radius: 8px;
        }
        .portal-drawer-close:hover { color: var(--cherry); background: var(--oat); }

        .portal-drawer-user {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 0; margin-bottom: 8px;
            border-bottom: 1px solid rgba(221,216,202,0.5);
        }
        .portal-drawer-user .avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: var(--maroon-card, #6b2737); color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; font-size: 14px; flex-shrink: 0;
        }
        .portal-drawer-user .name { font-weight: 600; font-size: 14px; color: var(--black); }

        .portal-mobile-drawer a,
        .portal-mobile-drawer button.drawer-link {
            display: block; padding: 13px 12px;
            font-size: 15px; font-weight: 600;
            color: var(--black); text-decoration: none;
            border-radius: 10px;
            border-bottom: 1px solid rgba(221,216,202,0.5);
            background: none; border-left: none; border-right: none; border-top: none;
            width: 100%; text-align: left; cursor: pointer; font-family: inherit;
        }
        .portal-mobile-drawer a:hover,
        .portal-mobile-drawer button.drawer-link:hover { background: var(--oat); }

        .portal-mobile-drawer .drawer-signout {
            margin-top: auto; padding-top: 20px;
            border-top: 1px solid #ddd8ca;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header class="auth-navbar">
        <a href="../index.php" class="auth-logo">
            <span class="auth-logo-badge">+</span>
            NovaCare
        </a>

        <div class="portal-nav-user" style="display: flex; align-items: center; gap: 14px;">
            <span style="font-weight: 600; font-size: 13.5px;">Dr. <?= htmlspecialchars($doctor['name'], ENT_QUOTES, 'UTF-8') ?></span>
            <a href="dashboard.php" style="color: var(--cherry); font-weight: 600;">Dashboard</a>
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
            <div class="avatar">Dr</div>
            <span class="name">Dr. <?= htmlspecialchars($doctor['name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <a href="dashboard.php" onclick="closePortalNav()">🏠 Dashboard</a>
        <a href="schedule.php" onclick="closePortalNav()">📅 Manage Schedule</a>
        <a href="../change-password.php" onclick="closePortalNav()">🔒 Change Password</a>

        <div class="drawer-signout">
            <form method="POST" action="../logout.php" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="drawer-link" style="color: var(--cherry);">🚪 Sign out</button>
            </form>
        </div>
    </nav>



    <!-- Main Content -->
    <main class="dashboard-container">

        <!-- Welcome Banner -->
        <div class="dash-welcome">
            <div>
                <h1>Consultation Schedule</h1>
                <p>Configure the days, hospitals, and hours you are available to see patients.</p>
            </div>
            <a href="dashboard.php" class="role-btn" style="background: var(--light-oat); color: var(--black); padding: 10px 20px;">
                &larr; Back to Appointments
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

        <div class="content-split">

            <!-- Left: Current Active Schedules List -->
            <div class="dash-panel">
                <h2>
                    <span>Your Active Hours</span>
                    <span style="font-size: 13px; color: var(--gray); font-family: sans-serif; font-weight: normal;">(<?= $totalSlots ?> Slots)</span>
                </h2>

                <?php if ($totalSlots > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="sched-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Time Range</th>
                                    <th>Hospital</th>
                                    <th>Slot Length</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $s): ?>
                                    <tr>
                                        <td>
                                            <span class="day-tag"><?= htmlspecialchars($s['day_of_week'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td>
                                            <strong><?= date('h:i A', strtotime($s['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($s['end_time'])) ?></strong>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($s['hospital_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td>
                                            <?= (int)$s['slot_duration_minutes'] ?> mins
                                        </td>
                                        <td>
                                            <form method="POST" action="schedule.php" onsubmit="return confirm('Remove this consultation slot?');" style="margin: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_schedule">
                                                <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
                                                <button type="submit" class="btn-del">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-box">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: #bbb; margin-bottom: 12px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <p>No active consultation hours set yet.</p>
                        <small style="color: var(--gray);">Use the form on the right to configure your weekly slots.</small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Add New Schedule Slot Form -->
            <div class="dash-panel">
                <h2>Add Consultation Hours</h2>

                <form method="POST" action="schedule.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_schedule">

                    <!-- Hospital -->
                    <div class="form-group">
                        <label for="hospital_id" class="form-label">Affiliated Hospital *</label>
                        <div class="input-wrap">
                            <select id="hospital_id" name="hospital_id" class="form-select no-icon" required>
                                <option value="">-- Choose Hospital --</option>
                                <?php foreach ($hospitals as $h): ?>
                                    <option value="<?= (int)$h['id'] ?>">
                                        <?= htmlspecialchars($h['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Day of Week -->
                    <div class="form-group">
                        <label for="day_of_week" class="form-label">Day of Week *</label>
                        <div class="input-wrap">
                            <select id="day_of_week" name="day_of_week" class="form-select no-icon" required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                    </div>

                    <!-- Start & End Time Row -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="start_time" class="form-label">Start Time *</label>
                            <div class="input-wrap">
                                <input type="time" id="start_time" name="start_time" class="form-input no-icon" value="09:00" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="end_time" class="form-label">End Time *</label>
                            <div class="input-wrap">
                                <input type="time" id="end_time" name="end_time" class="form-input no-icon" value="13:00" required>
                            </div>
                        </div>
                    </div>

                    <!-- Slot Duration & Max Patients Row -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="slot_duration_minutes" class="form-label">Slot Duration</label>
                            <div class="input-wrap">
                                <select id="slot_duration_minutes" name="slot_duration_minutes" class="form-select no-icon">
                                    <option value="15">15 minutes</option>
                                    <option value="20" selected>20 minutes</option>
                                    <option value="30">30 minutes</option>
                                    <option value="45">45 minutes</option>
                                    <option value="60">60 minutes</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="max_patients_per_slot" class="form-label">Max Patients/Slot</label>
                            <div class="input-wrap">
                                <input type="number" id="max_patients_per_slot" name="max_patients_per_slot" class="form-input no-icon" value="1" min="1" max="5" required>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-auth-submit" style="margin-top: 10px;">
                        <span>+ Add Consultation Hours</span>
                    </button>
                </form>
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
                Doctor clinical coordination &bull; Real-time schedule sync
            </div>
            <div class="footer-badges">
                <span>HIPAA-ready</span>
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
