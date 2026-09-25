<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../include/function.php';

// Strict authorization check: Patient must be logged in
if (!is_logged_in() || current_role() !== 'patient') {
    set_flash('error', 'You must be logged in as a patient to book an appointment. Please sign in or create an account.');
    redirect('login.php?redirect=patient/appointment.php&role=patient');
}

$db = getDB();
$userId = current_user_id();

// Fetch patient profile
$patientStmt = $db->prepare("SELECT * FROM patients WHERE user_id = ?");
$patientStmt->execute([$userId]);
$patient = $patientStmt->fetch();

$successMessage = '';
$errorMessage = '';
$bookedToken = '';

// Handle Appointment Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctorId = (int) ($_POST['doctor_id'] ?? 0);
    $appointmentDate = trim($_POST['appointment_date'] ?? '');
    $slotTime = trim($_POST['slot_time'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($doctorId) || empty($appointmentDate) || empty($slotTime)) {
        $errorMessage = 'Please select a doctor, consultation date, and time slot.';
    } elseif (strtotime($appointmentDate) < strtotime(date('Y-m-d'))) {
        $errorMessage = 'Appointment date cannot be in the past.';
    } else {
        try {
            // Find an active schedule or doctor_hospital link for this doctor
            $schedStmt = $db->prepare("
                SELECT s.id as schedule_id
                FROM schedules s
                JOIN doctor_hospital dh ON dh.id = s.doctor_hospital_id
                WHERE dh.doctor_id = ? AND s.status = 'active'
                LIMIT 1
            ");
            $schedStmt->execute([$doctorId]);
            $schedule = $schedStmt->fetch();

            $scheduleId = $schedule ? (int)$schedule['schedule_id'] : null;

            // If no active schedule row exists yet, find or create doctor_hospital link
            if (!$scheduleId) {
                // Find any active schedule or hospital link
                $anySchedStmt = $db->query("SELECT id FROM schedules WHERE status = 'active' LIMIT 1");
                $anySched = $anySchedStmt->fetch();
                $scheduleId = $anySched ? (int)$anySched['id'] : null;
            }

            if (!$scheduleId) {
                $errorMessage = 'No active schedule is available for this provider right now. Please choose another doctor.';
            } else {
                // Generate unique token (e.g. TK-20260925-4821)
                $token = generate_appointment_token($db);

                $insertStmt = $db->prepare("
                    INSERT INTO appointments (
                        appointment_token, patient_id, schedule_id, appointment_date, 
                        slot_time, reason, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $insertStmt->execute([
                    $token,
                    $userId,
                    $scheduleId,
                    $appointmentDate,
                    $slotTime,
                    $reason ?: 'General Medical Consultation'
                ]);

                $successMessage = 'Your appointment request has been submitted successfully!';
                $bookedToken = $token;
            }

        } catch (Throwable $e) {
            error_log('Appointment booking error: ' . $e->getMessage());
            $errorMessage = 'A database error occurred while booking. Please try again.';
        }
    }
}

// Fetch verified doctors list for booking
$doctorsStmt = $db->query("
    SELECT d.user_id, d.name, d.specialization, d.experience_years, d.image_path
    FROM doctors d
    WHERE d.verification_status = 'verified'
    ORDER BY d.name ASC
");
$verifiedDoctors = $doctorsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment - NovaCare</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        .patient-nav-user {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 13.5px;
        }
        .user-avatar-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--cherry);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
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

        <div class="patient-nav-user">
            <div class="user-avatar-circle">
                <?= strtoupper(substr($patient['name'] ?? 'P', 0, 1)) ?>
            </div>
            <span><?= htmlspecialchars($patient['name'] ?? 'Patient', ENT_QUOTES, 'UTF-8') ?></span>
            <span style="color: #bbb;">|</span>
            <a href="dashboard.php" style="color: var(--cherry); font-weight: 600;">Dashboard</a>
            <span style="color: #bbb;">|</span>
            <a href="../logout.php" style="color: var(--gray);">Sign out</a>
        </div>
    </header>

    <!-- Subnav Breadcrumb -->
    <div class="auth-subnav">
        <a href="../index.php" class="auth-back-link">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back to Home
        </a>
        <span class="auth-breadcrumb-current">/ Book an Appointment</span>
    </div>

    <!-- Centered Booking Form -->
    <main class="auth-main-container">
        <div class="auth-centered-container">
            <div class="auth-card">

                <?php if (!empty($bookedToken)): ?>
                    <!-- Success Confirmation -->
                    <div style="text-align: center; padding: 20px 0;">
                        <div style="width: 70px; height: 70px; background: #eaf5e7; color: #2e7d32; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <span class="auth-card-tag">Booking Confirmed</span>
                        <h1 class="auth-card-title">Appointment Requested!</h1>
                        <p style="color: var(--gray); font-size: 14.5px; max-width: 480px; margin: 0 auto 24px;">
                            Your appointment request has been sent to the hospital. You will receive an update once confirmed.
                        </p>

                        <div style="background: var(--light-oat); border: 1.5px dashed var(--border-soft); border-radius: 18px; padding: 20px; max-width: 360px; margin: 0 auto 30px; text-align: center;">
                            <span style="font-size: 11px; font-weight: 700; color: var(--gray); text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 4px;">Appointment Token</span>
                            <span style="font-family: Georgia, serif; font-size: 26px; font-weight: 700; color: var(--cherry); letter-spacing: 1px;"><?= htmlspecialchars($bookedToken, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
                            <a href="dashboard.php" class="btn-auth-submit" style="width: auto; padding: 13px 28px;">
                                <span>Go to Patient Dashboard</span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </a>
                            <a href="appointment.php" class="role-btn" style="background: var(--light-oat); color: var(--black);">Book Another</a>
                        </div>
                    </div>

                <?php else: ?>

                    <span class="auth-card-tag">Patient Booking</span>
                    <h1 class="auth-card-title">Schedule a Consultation</h1>
                    <p class="auth-card-subtitle">Select your verified specialist and preferred time slot for in-person or digital care.</p>

                    <!-- Feedback Alert -->
                    <?php if (!empty($errorMessage)): ?>
                        <div class="auth-alert error">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="appointment.php">
                        <!-- Select Doctor -->
                        <div class="form-group">
                            <label for="doctor_id" class="form-label">Choose Specialist / Doctor *</label>
                            <div class="input-wrap">
                                <select id="doctor_id" name="doctor_id" class="form-select no-icon" required>
                                    <option value="">-- Select a verified doctor --</option>
                                    <?php if (!empty($verifiedDoctors)): ?>
                                        <?php foreach ($verifiedDoctors as $doc): ?>
                                            <option value="<?= (int)$doc['user_id'] ?>">
                                                Dr. <?= htmlspecialchars($doc['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($doc['specialization'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= (int)$doc['experience_years'] ?> yrs exp)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No verified doctors currently available</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Date & Time Row -->
                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="appointment_date" class="form-label">Consultation Date *</label>
                                <div class="input-wrap">
                                    <input type="date" id="appointment_date" name="appointment_date" class="form-input no-icon" 
                                           min="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="slot_time" class="form-label">Preferred Time Slot *</label>
                                <div class="input-wrap">
                                    <select id="slot_time" name="slot_time" class="form-select no-icon" required>
                                        <option value="">Select slot</option>
                                        <option value="09:00:00">09:00 AM</option>
                                        <option value="09:30:00">09:30 AM</option>
                                        <option value="10:00:00">10:00 AM</option>
                                        <option value="10:30:00">10:30 AM</option>
                                        <option value="11:00:00">11:00 AM</option>
                                        <option value="11:30:00">11:30 AM</option>
                                        <option value="14:00:00">02:00 PM</option>
                                        <option value="14:30:00">02:30 PM</option>
                                        <option value="15:00:00">03:00 PM</option>
                                        <option value="15:30:00">03:30 PM</option>
                                        <option value="16:00:00">04:00 PM</option>
                                        <option value="16:30:00">04:30 PM</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Reason / Symptoms -->
                        <div class="form-group">
                            <label for="reason" class="form-label">Reason for Visit / Symptoms</label>
                            <div class="input-wrap">
                                <input type="text" id="reason" name="reason" class="form-input no-icon" 
                                       placeholder="e.g. Regular health checkup, persistent cough, joint ache">
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn-auth-submit" style="margin-top: 10px;">
                            <span>Confirm & Request Appointment</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </button>
                    </form>

                <?php endif; ?>

            </div>
        </div>
    </main>

    <!-- Dark Footer Strip -->
    <footer class="auth-dark-footer">
        <div class="auth-dark-footer-inner">
            <div class="footer-brand-logo">
                <span>+</span>NovaCare
            </div>
            <div class="footer-support-text">
                Need help with booking? +1 800 682 2273 &bull; Mon&ndash;Sat, 9am&ndash;8pm
            </div>
            <div class="footer-badges">
                <span>Secure care coordination</span>
                <span>&bull;</span>
                <span>HIPAA-ready</span>
            </div>
        </div>
    </footer>

</body>
</html>
