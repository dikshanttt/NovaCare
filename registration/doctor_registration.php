<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../include/function.php';

// Redirect if already logged in
if (is_logged_in()) {
    $role = current_role();
    if ($role === 'doctor') {
        redirect('doctor/dashboard.php');
    } elseif ($role === 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect('patient/dashboard.php');
    }
}

$errorMessage = '';
$registrationSuccess = false;
$assignedDoctorId = '';

$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'specialization' => '',
    'qualification' => '',
    'license_no' => '',
    'experience_years' => ''
];

// Handle Doctor Registration Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['phone'] = trim($_POST['phone'] ?? '');
    $formData['specialization'] = trim($_POST['specialization'] ?? '');
    $formData['qualification'] = trim($_POST['qualification'] ?? '');
    $formData['license_no'] = trim($_POST['license_no'] ?? '');
    $formData['experience_years'] = (int) ($_POST['experience_years'] ?? 0);

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($formData['name']) || empty($formData['email']) || empty($formData['phone']) || empty($formData['specialization']) || empty($formData['qualification']) || empty($formData['license_no'])) {
        $errorMessage = 'Please complete all required professional information fields.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif ($formData['experience_years'] < 0) {
        $errorMessage = 'Experience years cannot be negative.';
    } elseif (strlen($password) < 6) {
        $errorMessage = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Passwords do not match.';
    } else {
        try {
            $db = getDB();

            // Check if email already exists
            $checkStmt = $db->prepare("SELECT 1 FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $checkStmt->execute([$formData['email']]);
            if ($checkStmt->fetch()) {
                $errorMessage = 'An account with this email address already exists. Please sign in or use a different email.';
            } else {
                // Check if license number already exists
                $licStmt = $db->prepare("SELECT 1 FROM doctors WHERE license_no = ? LIMIT 1");
                $licStmt->execute([$formData['license_no']]);
                if ($licStmt->fetch()) {
                    $errorMessage = 'A doctor profile with this medical license number is already registered.';
                } else {
                    // Handle image upload if provided
                    $imagePath = null;
                    if (!empty($_FILES['image']['name'])) {
                        try {
                            $imagePath = handle_doctor_image_upload($_FILES['image']);
                        } catch (Exception $imgEx) {
                            $errorMessage = $imgEx->getMessage();
                        }
                    }

                    if (empty($errorMessage)) {
                        $db->beginTransaction();

                        // 1. Create user account with status 'pending'
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $userStmt = $db->prepare("
                            INSERT INTO users (email, password_hash, role, status, created_at)
                            VALUES (?, ?, 'doctor', 'pending', CURRENT_TIMESTAMP)
                            RETURNING id
                        ");
                        $userStmt->execute([$formData['email'], $passwordHash]);
                        $userId = (int) $userStmt->fetchColumn();

                        // 2. Generate unique Doctor Login ID (e.g. DOC-1234)
                        $assignedDoctorId = generate_doctor_login_id($db);

                        // 3. Create doctor profile
                        $docStmt = $db->prepare("
                            INSERT INTO doctors (
                                user_id, doctor_login_id, name, phone, specialization, 
                                qualification, license_no, experience_years, image_path, 
                                verification_status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                        ");
                        $docStmt->execute([
                            $userId,
                            $assignedDoctorId,
                            $formData['name'],
                            $formData['phone'],
                            $formData['specialization'],
                            $formData['qualification'],
                            $formData['license_no'],
                            $formData['experience_years'],
                            $imagePath
                        ]);

                        $db->commit();
                        $registrationSuccess = true;

                        // Send confirmation email
                        require_once __DIR__ . '/../include/phpmailer.php';
                        $emailSubject = 'NovaCare - Doctor Registration Received';
                        $emailBody = "Hello Dr. {$formData['name']},\n\n"
                            . "Thank you for registering with NovaCare.\n\n"
                            . "Your assigned Doctor Login ID is: {$assignedDoctorId}\n"
                            . "Status: Pending Administrative Verification\n\n"
                            . "Our administrative team will review your medical credentials and license shortly. Once approved, you will be notified via email and will be able to log in to your provider dashboard.\n\n"
                            . "Regards,\nNovaCare Healthcare Team";
                        send_email($formData['email'], $emailSubject, $emailBody);
                    }
                }
            }

        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Doctor registration error: ' . $e->getMessage());
            if (str_contains($e->getMessage(), 'users_email_key') || str_contains($e->getMessage(), 'unique constraint')) {
                $errorMessage = 'An account with this email address already exists. Please sign in instead.';
            } elseif (str_contains($e->getMessage(), 'doctors_license_no_key')) {
                $errorMessage = 'A doctor profile with this medical license number is already registered.';
            } elseif (str_contains($e->getMessage(), 'value too long for type character varying')) {
                $errorMessage = 'One or more of the submitted fields exceeds the character limit.';
            } else {
                $errorMessage = 'Registration error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Registration - NovaCare</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>

    <!-- Subnav / Back link -->
    <div class="auth-subnav">
        <a href="account_selection.php" class="auth-back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back to account selection
        </a>
    </div>

    <!-- Centered Form Container -->
    <main class="auth-main-container">
        <div class="auth-centered-container">
            <div class="auth-card">

                <?php if ($registrationSuccess): ?>
                    <!-- Success State Display -->
                    <div style="text-align: center; padding: 20px 0;">
                        <div style="width: 72px; height: 72px; background: #eaf5e7; color: #2e7d32; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>

                        <span class="auth-card-tag">Application Submitted</span>
                        <h1 class="auth-card-title">Thank you, Dr. <?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8') ?>!</h1>
                        
                        <p style="color: var(--gray); font-size: 15px; max-width: 480px; margin: 0 auto 24px; line-height: 1.6;">
                            Your application has been received and is currently under administrative verification. Once our medical board reviews your license, your account will be activated.
                        </p>

                        <div style="background: var(--light-oat); border: 1.5px dashed var(--border-soft); border-radius: 18px; padding: 20px; max-width: 380px; margin: 0 auto 30px; text-align: center;">
                            <span style="font-size: 12px; font-weight: 700; color: var(--gray); text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 6px;">Your Assigned Doctor ID</span>
                            <span style="font-family: Georgia, serif; font-size: 28px; font-weight: 700; color: var(--cherry); letter-spacing: 1px;"><?= htmlspecialchars($assignedDoctorId, ENT_QUOTES, 'UTF-8') ?></span>
                            <small style="display: block; color: var(--gray); font-size: 11.5px; margin-top: 6px;">Please save this ID for logging into your doctor dashboard once verified.</small>
                        </div>

                        <a href="../login.php" class="btn-auth-submit" style="display: inline-flex; width: auto; padding: 14px 32px; margin: 0 auto;">
                            <span>Return to Sign In</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Registration Form -->
                    <span class="auth-card-tag">Doctor Registration</span>
                    <h1 class="auth-card-title">Bring your practice to NovaCare.</h1>
                    <p class="auth-card-subtitle">Submit your medical qualifications to join our verified provider network.</p>

                    <!-- Feedback Alert -->
                    <?php if (!empty($errorMessage)): ?>
                        <div class="auth-alert error">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="doctor_registration.php" enctype="multipart/form-data" autocomplete="on">
                        <?= csrf_field() ?>
                        
                        <!-- Full Name -->
                        <div class="form-group">
                            <label for="name" class="form-label">Doctor's Full Name *</label>
                            <div class="input-wrap">
                                <span class="input-icon-left">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                </span>
                                <input type="text" id="name" name="name" class="form-input" 
                                       placeholder="e.g. Dr. Jennifer Adams"
                                       value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                        </div>

                        <!-- Email & Phone Row -->
                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address *</label>
                                <div class="input-wrap">
                                    <span class="input-icon-left">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                            <polyline points="22,6 12,13 2,6"/>
                                        </svg>
                                    </span>
                                    <input type="email" id="email" name="email" class="form-input" 
                                           placeholder="doctor@example.com"
                                           value="<?= htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <div class="input-wrap">
                                    <span class="input-icon-left">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    </svg>
                                    </span>
                                    <input type="tel" id="phone" name="phone" class="form-input" 
                                           placeholder="+1 (555) 123-4567"
                                           value="<?= htmlspecialchars($formData['phone'], ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Specialization & Qualification Row -->
                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="specialization" class="form-label">Medical Specialization *</label>
                                <div class="input-wrap">
                                    <select id="specialization" name="specialization" class="form-select no-icon" required>
                                        <option value="">Select Specialization</option>
                                        <option value="Cardiology" <?= $formData['specialization'] === 'Cardiology' ? 'selected' : '' ?>>Cardiology (Heart Care)</option>
                                        <option value="Pediatrics" <?= $formData['specialization'] === 'Pediatrics' ? 'selected' : '' ?>>Pediatrics (Children)</option>
                                        <option value="Orthopedics" <?= $formData['specialization'] === 'Orthopedics' ? 'selected' : '' ?>>Orthopedics (Bones & Joints)</option>
                                        <option value="Primary Care" <?= $formData['specialization'] === 'Primary Care' ? 'selected' : '' ?>>Primary Care & General Medicine</option>
                                        <option value="Dermatology" <?= $formData['specialization'] === 'Dermatology' ? 'selected' : '' ?>>Dermatology (Skin)</option>
                                        <option value="Neurology" <?= $formData['specialization'] === 'Neurology' ? 'selected' : '' ?>>Neurology (Brain & Nerves)</option>
                                        <option value="Gynecology" <?= $formData['specialization'] === 'Gynecology' ? 'selected' : '' ?>>Obstetrics & Gynecology</option>
                                        <option value="Psychiatry" <?= $formData['specialization'] === 'Psychiatry' ? 'selected' : '' ?>>Psychiatry & Mental Health</option>
                                        <option value="Ophthalmology" <?= $formData['specialization'] === 'Ophthalmology' ? 'selected' : '' ?>>Ophthalmology (Eye Care)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="qualification" class="form-label">Highest Qualification *</label>
                                <div class="input-wrap">
                                    <input type="text" id="qualification" name="qualification" class="form-input no-icon" 
                                           placeholder="e.g. MBBS, MD (Cardiology)"
                                           value="<?= htmlspecialchars($formData['qualification'], ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- License No & Experience Row -->
                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="license_no" class="form-label">Medical License Number *</label>
                                <div class="input-wrap">
                                    <input type="text" id="license_no" name="license_no" class="form-input no-icon" 
                                           placeholder="e.g. MED-847291"
                                           value="<?= htmlspecialchars($formData['license_no'], ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="experience_years" class="form-label">Years of Experience *</label>
                                <div class="input-wrap">
                                    <input type="number" id="experience_years" name="experience_years" class="form-input no-icon" 
                                           placeholder="e.g. 8" min="0" max="60"
                                           value="<?= htmlspecialchars((string)$formData['experience_years'], ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Photo Upload -->
                        <div class="form-group">
                            <label for="image" class="form-label">Profile Photo (Optional)</label>
                            <div class="input-wrap">
                                <input type="file" id="image" name="image" class="form-input no-icon" accept="image/jpeg,image/png,image/webp">
                            </div>
                            <small style="color: var(--gray); font-size: 11.5px; margin-top: 4px; display: block;">Accepted formats: JPG, PNG, WEBP (Max 2MB)</small>
                        </div>

                        <!-- Password & Confirm Password Row -->
                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="password" class="form-label">Create Password *</label>
                                <div class="input-wrap">
                                    <span class="input-icon-left">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                    </span>
                                    <input type="password" id="password" name="password" class="form-input" 
                                           placeholder="At least 6 characters" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <div class="input-wrap">
                                    <span class="input-icon-left">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                    </span>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                                           placeholder="Re-enter password" required>
                                </div>
                            </div>
                        </div>

                        <!-- Trust / Verification Note -->
                        <div style="background: var(--light-oat); border-radius: 14px; padding: 14px 18px; margin: 10px 0 20px; font-size: 12.5px; color: #4f5348; display: flex; gap: 10px; align-items: center;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--cherry); flex-shrink: 0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span>All doctor profiles are verified by our clinical administration team before they are published to patients.</span>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn-auth-submit">
                            <span>Submit Doctor Application</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </button>
                    </form>

                    <p class="auth-card-foot">
                        Already verified? <a href="../login.php">Sign in to your account</a>
                    </p>
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
                Need help? +1 800 682 2273 &bull; Mon&ndash;Sat, 9am&ndash;8pm
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
